<?php

namespace App\Services\V2;

use App\Models\Finance\PayoutProfile;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 * MIXXTUNE_KYC_REVIEW_V1
 *
 * Automatic checks shown next to every payout / KYC profile so the
 * reviewer sees problems before pressing Verify:
 *  - PAN / IFSC / account number / GST format, GST contains the same PAN
 *  - account holder name looks like the owner (or one of their labels)
 *  - address complete
 *  - the same bank account (or PAN) used by another owner
 *  - bank details changed in the last 48 h (payout hold)
 * "fail" = must be fixed (Verify needs an explicit override + reason),
 * "warn" = check by hand, "ok" = fine.
 */
class KycChecks
{
    private ?array $index = null;

    public function __construct(private readonly BankChangeGuard $bankGuard)
    {
    }

    /** account+ifsc and PAN -> user ids, built once per request from the encrypted columns. */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $bank = [];
        $pan = [];

        PayoutProfile::query()->select(['id', 'user_id', 'bank_account_number', 'ifsc_code', 'pan_number'])
            ->chunk(500, function ($rows) use (&$bank, &$pan) {
                foreach ($rows as $row) {
                    try {
                        $acc = preg_replace('/\s+/', '', (string) $row->bank_account_number);
                        $ifsc = strtoupper(trim((string) $row->ifsc_code));
                        $p = strtoupper(trim((string) $row->pan_number));
                    } catch (Throwable $e) {
                        continue;
                    }

                    if ($acc !== '') {
                        $bank[$acc.'|'.$ifsc][] = (int) $row->user_id;
                    }

                    if ($p !== '') {
                        $pan[$p][] = (int) $row->user_id;
                    }
                }
            });

        return $this->index = ['bank' => $bank, 'pan' => $pan];
    }

    private static function norm(string $s): array
    {
        $s = strtolower(preg_replace('/[^a-z0-9 ]/i', ' ', $s));
        $stop = ['mr', 'mrs', 'ms', 'shri', 'smt', 'the', 'and', 'pvt', 'ltd', 'private', 'limited', 'llp', 'music', 'records', 'entertainment', 'films', 'production', 'productions', 'studio', 'studios'];

        return array_values(array_filter(preg_split('/\s+/', $s), fn ($w) => strlen($w) > 1 && !in_array($w, $stop, true)));
    }

    /** @return array<int, array{key:string,label:string,state:string,detail:string}> */
    public function run(PayoutProfile $profile): array
    {
        $c = [];
        $add = function (string $key, string $label, string $state, string $detail = '') use (&$c) {
            $c[] = compact('key', 'label', 'state', 'detail');
        };

        try {
            $pan = strtoupper(trim((string) $profile->pan_number));
            $acc = preg_replace('/\s+/', '', (string) $profile->bank_account_number);
        } catch (Throwable $e) {
            $pan = '';
            $acc = '';
            $add('decrypt', 'Stored PAN / account readable', 'fail', 'Could not read the saved values — re-enter them on the user page.');
        }

        $ifsc = strtoupper(trim((string) $profile->ifsc_code));
        $gst = strtoupper(trim((string) $profile->gst_number));
        $holder = trim((string) $profile->account_holder_name);

        // PAN
        if ($pan === '') {
            $add('pan', 'PAN', 'fail', 'Missing.');
        } elseif (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            $add('pan', 'PAN format (ABCDE1234F)', 'fail', 'Does not look like a PAN.');
        } else {
            $types = ['P' => 'person', 'C' => 'company', 'F' => 'firm / LLP', 'H' => 'HUF', 'T' => 'trust', 'A' => 'AOP', 'B' => 'BOI', 'G' => 'government', 'J' => 'artificial person', 'L' => 'local authority'];
            $add('pan', 'PAN format', 'ok', 'PAN of a '.($types[$pan[3]] ?? 'unknown type').'.');
        }

        // Bank
        if ($acc === '' || $ifsc === '') {
            $add('bank', 'Bank account + IFSC', 'fail', 'Missing.');
        } else {
            $add('account', 'Account number (9–18 digits)', preg_match('/^\d{9,18}$/', $acc) ? 'ok' : 'fail', preg_match('/^\d{9,18}$/', $acc) ? strlen($acc).' digits.' : 'Only digits, 9 to 18 long.');
            $add('ifsc', 'IFSC format (ABCD0123456)', preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc) ? 'ok' : 'fail', preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc) ? 'Bank code '.substr($ifsc, 0, 4).'.' : '4 letters, 0, then 6 letters / digits.');
        }

        // Name match
        if ($holder === '') {
            $add('holder', 'Account holder name', 'fail', 'Missing.');
        } else {
            $names = [(string) $profile->user?->name];
            $names = array_merge($names, DB::table('labels')->where('user_id', $profile->user_id)->whereNull('deleted_at')->pluck('name')->all());
            $names = array_merge($names, DB::table('labels')->where('user_id', $profile->user_id)->whereNull('deleted_at')->whereNotNull('legal_name')->pluck('legal_name')->all());

            $h = self::norm($holder);
            $hit = collect($names)->filter()->first(fn ($n) => count(array_intersect($h, self::norm((string) $n))) > 0);

            $add('holder', 'Holder name looks like the owner', $hit ? 'ok' : 'warn', $hit ? 'Matches "'.$hit.'".' : 'No word in "'.$holder.'" matches the user / label name — check the bank proof.');
        }

        // GST (optional)
        if ($gst !== '') {
            $fmt = (bool) preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gst);
            if (!$fmt) {
                $add('gst', 'GST format', 'fail', 'Does not look like a GSTIN (15 characters).');
            } elseif ($pan !== '' && substr($gst, 2, 10) !== $pan) {
                $add('gst', 'GST belongs to this PAN', 'warn', 'GSTIN has PAN '.substr($gst, 2, 10).', profile has a different PAN.');
            } else {
                $add('gst', 'GST format + same PAN', 'ok', 'State code '.substr($gst, 0, 2).'.');
            }
        }

        // Address
        $missing = collect(['address_line_1' => 'address', 'city' => 'city', 'state' => 'state', 'postal_code' => 'PIN'])
            ->filter(fn ($l, $f) => blank($profile->{$f}))->values()->all();
        $pinOk = blank($profile->postal_code) || preg_match('/^\d{6}$/', (string) $profile->postal_code) || strtoupper((string) $profile->country_code) !== 'IN';
        $add('address', 'Address complete', $missing ? 'fail' : ($pinOk ? 'ok' : 'warn'), $missing ? 'Missing: '.implode(', ', $missing).'.' : ($pinOk ? '' : 'PIN code should be 6 digits.'));

        // Duplicates across owners
        $index = $this->index();
        if ($acc !== '') {
            $others = array_values(array_diff(array_unique($index['bank'][$acc.'|'.$ifsc] ?? []), [(int) $profile->user_id]));
            $add('dup_bank', 'Bank account used only by this owner', $others ? 'fail' : 'ok',
                $others ? 'Same account + IFSC also on: '.DB::table('users')->whereIn('id', $others)->pluck('name')->implode(', ').'.' : '');
        }
        if ($pan !== '') {
            $others = array_values(array_diff(array_unique($index['pan'][$pan] ?? []), [(int) $profile->user_id]));
            if ($others) {
                $add('dup_pan', 'PAN used by another owner', 'warn', 'Same PAN on: '.DB::table('users')->whereIn('id', $others)->pluck('name')->implode(', ').' (fine if it is the same person).');
            }
        }

        // 48 h hold after a bank change
        try {
            $until = $this->bankGuard->holdUntil($profile);
            if ($until) {
                $add('hold', 'Bank details changed recently', 'warn', 'Payout hold until '.$until->copy()->timezone('Asia/Kolkata')->format('d M, h:i A').'.');
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $c;
    }

    public static function summary(array $checks): array
    {
        $c = collect($checks);

        return ['fail' => $c->where('state', 'fail')->count(), 'warn' => $c->where('state', 'warn')->count()];
    }
}
