<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\IsrcCode;
use App\Models\UpcCode;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/*
 * W116 MIXXTUNE_CODE_LISTS
 * The UPC / ISRC codes allotted to Mixx Tune: read from an Excel / CSV / txt file, pasted text or a
 * first-last range, checked (UPC check digit, ISRC shape), doubles and codes already on a release / track
 * left out, and kept as "available". Generate takes the oldest free one and records who got it.
 */
class IdentifierPool
{
    public const MAX_PER_UPLOAD = 100000;

    public function __construct(private readonly UpcService $upc, private readonly IsrcService $isrc)
    {
    }

    /** @return array<int, string> raw strings found in the file (every cell / line / comma part) */
    public function readFile(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
            try {
                $book = IOFactory::load($file->getRealPath());
            } catch (Throwable $e) {
                throw ValidationException::withMessages(['file' => 'The Excel file could not be read: '.$e->getMessage()]);
            }
            $out = [];
            foreach ($book->getAllSheets() as $sheet) {
                foreach ($sheet->toArray(null, false, false, false) as $row) {
                    foreach ($row as $cell) {
                        if ($cell !== null && $cell !== '') {
                            /* long numbers come back as floats - write them out in full */
                            $out[] = is_float($cell) ? sprintf('%.0f', $cell) : (string) $cell;
                        }
                    }
                }
            }

            return $out;
        }

        return $this->splitText((string) file_get_contents($file->getRealPath()));
    }

    public function splitText(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;\t]+/', $text) ?: []), fn ($s) => $s !== ''));
    }

    /** every code from the first to the last (same prefix), check digits worked out */
    public function range(string $type, string $first, string $last): array
    {
        if ($type === 'upc') {
            $a = $this->upc->normalise($first);
            $b = $this->upc->normalise($last);
            if (strlen($a) !== strlen($b) || !in_array(strlen($a), [12, 13], true)) {
                throw ValidationException::withMessages(['range_first' => 'First and last UPC must both have 12 (or both 13) digits.']);
            }
            $bodyA = substr($a, 0, -1);
            $bodyB = substr($b, 0, -1);
            if (strcmp($bodyA, $bodyB) > 0) {
                throw ValidationException::withMessages(['range_last' => 'The last UPC is smaller than the first.']);
            }
            $count = (int) $bodyB - (int) $bodyA + 1;
            if ($count > self::MAX_PER_UPLOAD) {
                throw ValidationException::withMessages(['range_last' => 'At most '.number_format(self::MAX_PER_UPLOAD).' codes at once.']);
            }
            $len = strlen($bodyA);
            $out = [];
            for ($n = (int) $bodyA; $n <= (int) $bodyB; $n++) {
                $body = str_pad((string) $n, $len, '0', STR_PAD_LEFT);
                $out[] = $body.self::gs1CheckDigit($body);
            }

            return $out;
        }
        $a = $this->isrc->normalise($first);
        $b = $this->isrc->normalise($last);
        $re = '/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/';
        if (!preg_match($re, $a) || !preg_match($re, $b)) {
            throw ValidationException::withMessages(['range_first' => 'First and last ISRC must look like IN-ABC-26-00001.']);
        }
        if (substr($a, 0, 7) !== substr($b, 0, 7)) {
            throw ValidationException::withMessages(['range_last' => 'First and last ISRC must have the same country, registrant and year (only the last 5 digits change).']);
        }
        $from = (int) substr($a, 7);
        $to = (int) substr($b, 7);
        if ($to < $from) {
            throw ValidationException::withMessages(['range_last' => 'The last ISRC is smaller than the first.']);
        }
        $out = [];
        for ($n = $from; $n <= $to; $n++) {
            $out[] = substr($a, 0, 7).sprintf('%05d', $n);
        }

        return $out;
    }

    /** GS1 check digit for a body (any length): from the right, weights 3, 1, 3, 1 ... */
    public static function gs1CheckDigit(string $body): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($body)) as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * @param array<int, string> $raw
     * @return array{added:int, skipped_double:int, skipped_used:int, invalid:int, invalid_examples:array<int,string>, batch_id:int|null}
     */
    public function import(string $type, array $raw, User $user, string $source, ?string $note = null): array
    {
        if (!in_array($type, ['upc', 'isrc'], true)) {
            throw ValidationException::withMessages(['type' => 'Pick UPC or ISRC.']);
        }
        if (count($raw) > self::MAX_PER_UPLOAD) {
            throw ValidationException::withMessages(['file' => 'At most '.number_format(self::MAX_PER_UPLOAD).' codes at once.']);
        }
        $valid = [];
        $invalid = [];
        foreach ($raw as $r) {
            $r = trim($r);
            /* a header cell like "UPC" or "ISRC" is not a code */
            if ($r === '' || preg_match('/^(upc|ean|isrc|codes?|s\.?\s*no\.?|sr\.?\s*no\.?)$/i', $r)) {
                continue;
            }
            try {
                $code = $type === 'upc' ? $this->upc->validate($r) : $this->isrc->validate($r);
                $valid[$type === 'upc' ? $code : $this->isrc->normalise($code)] = $code;
            } catch (ValidationException) {
                $invalid[] = mb_substr($r, 0, 40);
            }
        }
        if ($valid === [] && $invalid === []) {
            throw ValidationException::withMessages(['file' => 'No codes found.']);
        }
        $rawCount = count(array_filter($raw, fn ($r) => trim($r) !== '' && !preg_match('/^(upc|ean|isrc|codes?|s\.?\s*no\.?|sr\.?\s*no\.?)$/i', trim($r))));
        $doubleInFile = $rawCount - count($invalid) - count($valid);

        return DB::transaction(function () use ($type, $valid, $invalid, $user, $source, $note, $doubleInFile) {
            $added = 0;
            $double = $doubleInFile;
            $used = 0;
            $batchId = DB::table('identifier_batches')->insertGetId(['type' => $type, 'source' => mb_substr($source, 0, 190), 'note' => $note, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_chunk($valid, 500, true) as $chunk) {
                if ($type === 'upc') {
                    $inPool = UpcCode::query()->whereIn('code', array_values($chunk))->pluck('code')->all();
                    $onRelease = Release::withTrashed()->whereIn('upc', array_values($chunk))->pluck('upc')->all();
                } else {
                    $inPool = IsrcCode::query()->whereIn('code', array_values($chunk))->pluck('code')->all();
                    $onRelease = DB::table('tracks')->whereIn(DB::raw("REPLACE(UPPER(isrc), '-', '')"), array_keys($chunk))->pluck('isrc')->map(fn ($c) => $this->isrc->format((string) $c))->all();
                }
                $inPool = array_flip($inPool);
                $onRelease = array_flip($onRelease);
                $rows = [];
                foreach ($chunk as $norm => $code) {
                    if (isset($onRelease[$code])) {
                        $used++;
                        continue;
                    }
                    if (isset($inPool[$code])) {
                        $double++;
                        continue;
                    }
                    $base = ['public_id' => (string) Str::ulid(), 'code' => $code, 'status' => 'available', 'batch_id' => $batchId, 'notes' => null, 'created_at' => now(), 'updated_at' => now()];
                    if ($type === 'upc') {
                        $rows[] = $base + ['prefix' => substr($code, 0, 6)];
                    } else {
                        $n = $this->isrc->normalise($code);
                        $rows[] = $base + ['country_code' => substr($n, 0, 2), 'registrant_code' => substr($n, 2, 3), 'reference_year' => (int) ('20'.substr($n, 5, 2)), 'designation_code' => (int) substr($n, 7)];
                    }
                }
                if ($rows !== []) {
                    DB::table($type === 'upc' ? 'upc_codes' : 'isrc_codes')->insert($rows);
                    $added += count($rows);
                }
            }
            DB::table('identifier_batches')->where('id', $batchId)->update(['added' => $added, 'skipped' => $double + $used + count($invalid)]);
            FinanceAudit::log('identifiers.list_uploaded', strtoupper($type).' list "'.$source.'": '.$added.' added, '.$double.' already in the list, '.$used.' already on a '.($type === 'upc' ? 'release' : 'track').', '.count($invalid).' not valid.', ['batch_id' => $batchId]);

            return ['added' => $added, 'skipped_double' => $double, 'skipped_used' => $used, 'invalid' => count($invalid), 'invalid_examples' => array_slice($invalid, 0, 5), 'batch_id' => $batchId];
        });
    }

    public function stats(): array
    {
        $out = [];
        foreach (['upc' => 'upc_codes', 'isrc' => 'isrc_codes'] as $type => $table) {
            $out[$type] = [
                'available' => DB::table($table)->where('status', 'available')->count(),
                'used_from_list' => DB::table($table)->whereNotNull('batch_id')->where('status', 'assigned')->count(),
                'next' => DB::table($table)->where('status', 'available')->orderBy('id')->value('code'),
            ];
        }
        $out['batches'] = DB::table('identifier_batches as b')->leftJoin('users as u', 'u.id', '=', 'b.created_by')
            ->orderByDesc('b.id')->limit(10)->get(['b.id', 'b.type', 'b.source', 'b.added', 'b.skipped', 'b.created_at', 'u.name as by'])
            ->map(fn ($b) => ['id' => $b->id, 'type' => $b->type, 'source' => $b->source, 'added' => (int) $b->added, 'skipped' => (int) $b->skipped, 'by' => $b->by,
                'left' => DB::table($b->type === 'upc' ? 'upc_codes' : 'isrc_codes')->where('batch_id', $b->id)->where('status', 'available')->count(),
                'at' => \Illuminate\Support\Carbon::parse($b->created_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A')])->all();

        return $out;
    }

    /** the next free UPC from the list (row locked; caller is inside a transaction) - null when the list is empty */
    public function takeUpc(): ?UpcCode
    {
        while ($row = UpcCode::query()->where('status', 'available')->orderBy('id')->lockForUpdate()->first()) {
            if (!Release::withTrashed()->where('upc', $row->code)->exists()) {
                return $row;
            }
            /* typed in by hand on a release meanwhile - never give it twice */
            $row->forceFill(['status' => 'used_elsewhere', 'notes' => 'Already on a release when it was to be given out.'])->save();
        }

        return null;
    }

    public function takeIsrc(): ?IsrcCode
    {
        while ($row = IsrcCode::query()->where('status', 'available')->orderBy('id')->lockForUpdate()->first()) {
            if (!$this->isrc->usedElsewhere((string) $row->code, 0)) {
                return $row;
            }
            $row->forceFill(['status' => 'used_elsewhere', 'notes' => 'Already on a track when it was to be given out.'])->save();
        }

        return null;
    }

    public function hasUpc(): bool
    {
        return UpcCode::query()->where('status', 'available')->exists();
    }

    public function hasIsrc(): bool
    {
        return IsrcCode::query()->where('status', 'available')->exists();
    }
}
