<?php

namespace App\Services\Iprs;

use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Models\Iprs\IprsWork;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/*
 * Imports one IPRS statement file: parse -> validate against the file's own
 * TOTAL ROYALTIES -> reject if the same content was already imported -> persist
 * the upload, its works and its royalty lines.
 *
 * Returns a per-file result so the controller can upload many files at once and
 * report each one (imported / needs_review / duplicate / failed) without one bad
 * file aborting the rest.
 */
class IprsImportService
{
    public function __construct(private IprsStatementParser $parser)
    {
    }

    /**
     * @return array{status:string,message:string,file_name:string,upload:?IprsUpload}
     */
    public function import(UploadedFile $file, int $userId, ?string $periodLabel = null): array
    {
        $fileName = $file->getClientOriginalName();

        try {
            $parsed = $this->parser->parse($file->getRealPath());
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'message' => 'Could not read "'.$fileName.'": '.$e->getMessage(),
                'file_name' => $fileName,
                'upload' => null,
            ];
        }

        if (count($parsed['lines']) === 0) {
            return [
                'status' => 'failed',
                'message' => 'No publisher royalty lines were found in "'.$fileName.'".',
                'file_name' => $fileName,
                'upload' => null,
            ];
        }

        $hash = $this->contentHash($parsed['lines']);

        if (IprsUpload::where('file_hash', $hash)->exists()) {
            return [
                'status' => 'duplicate',
                'message' => '"'.$fileName.'" was skipped - an identical statement has already been imported.',
                'file_name' => $fileName,
                'upload' => null,
            ];
        }

        // Validate the parsed sum against the file's own grand total.
        $fileTotal = $parsed['file_total'];
        $parsedTotal = $parsed['parsed_total'];
        $diff = $fileTotal !== null ? round($parsedTotal - $fileTotal, 4) : null;
        $status = ($fileTotal !== null && abs($diff) <= 0.01) ? 'imported' : 'needs_review';

        $storedPath = $file->store('iprs-statements', 'local');

        $overlap = $this->overlapWarnings($parsed['lines']);

        $upload = DB::transaction(function () use ($parsed, $hash, $userId, $periodLabel, $fileName, $storedPath, $fileTotal, $parsedTotal, $diff, $status, $overlap) {
            $upload = IprsUpload::create([
                'public_id' => (string) Str::ulid(),
                'file_name' => $fileName,
                'file_hash' => $hash,
                'stored_path' => $storedPath,
                'uploaded_by' => $userId,
                'member_internal_no' => $parsed['member']['internal_no'] ?? null,
                'member_name' => $parsed['member']['name'] ?? null,
                'ipi_name_no' => $parsed['member']['ipi_name_no'] ?? null,
                'ipi_base_no' => $parsed['member']['ipi_base_no'] ?? null,
                'file_total' => $fileTotal ?? 0,
                'parsed_total' => $parsedTotal,
                'total_lines' => count($parsed['lines']),
                'total_works' => count($parsed['works']),
                'status' => $status,
                'period_label' => $periodLabel,
                'metadata' => [
                    'validation' => [
                        'file_total' => $fileTotal,
                        'parsed_total' => $parsedTotal,
                        'difference' => $diff,
                        'within_tolerance' => $status === 'imported',
                    ],
                    'source_totals' => $parsed['source_totals'],
                    'summary_source_totals' => $parsed['summary_source_totals'],
                    'duplicate_line_warnings' => $overlap,
                ],
            ]);

            foreach ($parsed['works'] as $work) {
                IprsWork::updateOrCreate(
                    ['work_int_no' => $work['work_int_no']],
                    [
                        'title' => $work['title'],
                        'language' => $work['language'],
                        'credits' => $work['credits'],
                    ]
                );
            }

            foreach ($parsed['lines'] as $line) {
                IprsRoyaltyLine::create([
                    'public_id' => (string) Str::ulid(),
                    'iprs_upload_id' => $upload->id,
                    'work_int_no' => $line['work_int_no'],
                    'title' => $line['title'],
                    'pool' => $line['pool'],
                    'source' => $line['source'],
                    'source_month' => $line['source_month'],
                    'own_pct' => $line['own_pct'],
                    'coll_pct' => $line['coll_pct'],
                    'amount' => $line['amount'],
                    'mapping_status' => 'unmatched',
                    'raw' => $line,
                ]);
            }

            return $upload;
        });

        // Auto-map lines whose work number already sits on a track from an earlier mapping.
        app(IprsMappingService::class)->autoMapUpload($upload);

        $message = $status === 'imported'
            ? '"'.$fileName.'" imported - '.$upload->total_lines.' line(s), '.$upload->total_works.' work(s).'
            : '"'.$fileName.'" imported but needs review - parsed total '.$parsedTotal.' vs file total '.($fileTotal ?? 'n/a').'.';

        return [
            'status' => $status,
            'message' => $message,
            'file_name' => $fileName,
            'upload' => $upload,
        ];
    }

    /** sha256 of the sorted "workno|SOURCE|amount" lines - identifies the same content regardless of row order. */
    private function contentHash(array $lines): string
    {
        $keys = [];
        foreach ($lines as $l) {
            $keys[] = $l['work_int_no'].'|'.strtoupper((string) ($l['source'] ?? '')).'|'.number_format((float) $l['amount'], 4, '.', '');
        }
        sort($keys);

        return hash('sha256', implode("\n", $keys));
    }

    /** Count individual (work no + source + amount) lines that already exist from an earlier upload. */
    private function overlapWarnings(array $lines): int
    {
        $workNos = array_values(array_unique(array_map(fn ($l) => $l['work_int_no'], $lines)));
        if ($workNos === []) {
            return 0;
        }

        $existing = IprsRoyaltyLine::whereIn('work_int_no', $workNos)
            ->get(['work_int_no', 'source', 'amount']);

        if ($existing->isEmpty()) {
            return 0;
        }

        $seen = [];
        foreach ($existing as $e) {
            $seen[$e->work_int_no.'|'.strtoupper((string) $e->source).'|'.number_format((float) $e->amount, 4, '.', '')] = true;
        }

        $hits = 0;
        foreach ($lines as $l) {
            $k = $l['work_int_no'].'|'.strtoupper((string) ($l['source'] ?? '')).'|'.number_format((float) $l['amount'], 4, '.', '');
            if (isset($seen[$k])) {
                $hits++;
            }
        }

        return $hits;
    }
}
