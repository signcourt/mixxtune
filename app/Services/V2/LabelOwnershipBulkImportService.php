<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * MIXXTUNE_LABEL_OWNER_BULK_IMPORT_V1
 *
 * Assign owners to many labels from one CSV file.
 *  - Labels are matched ONLY by label_id (never by name). If label_name is
 *    filled it must match the label's real name, otherwise the row is an error.
 *  - Rows with an empty owner_email are skipped (label left untouched).
 *  - Rows are grouped by owner_email. An existing Label account is used as is;
 *    an unknown e-mail creates a new Label user through the Users module
 *    (same rules, pending + invitation), via LabelOwnershipService::apply().
 *  - Preview never writes anything. Applying needs the e-mailed OTP and then
 *    runs in small batches so a long run cannot time out half-way.
 */
class LabelOwnershipBulkImportService
{
    public const MAX_ROWS = 500;
    public const BATCH_SIZE = 5;
    public const PLAN_MINUTES = 60;

    public const COLUMNS = [
        'label_id', 'label_name', 'current_owner_email', 'owner_email', 'owner_name', 'username', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state_code', 'postal_code',
        'revenue_share_percentage', 'account_holder_name', 'bank_account_number', 'bank_name',
        'ifsc_code', 'branch_name', 'pan_number', 'gst_number',
    ];

    /* Columns that describe a NEW owner account (first filled row of an e-mail wins). */
    private const DETAIL_COLUMNS = [
        'owner_name', 'username', 'phone', 'address_line_1', 'address_line_2', 'city', 'state_code',
        'postal_code', 'revenue_share_percentage', 'account_holder_name', 'bank_account_number',
        'bank_name', 'ifsc_code', 'branch_name', 'pan_number', 'gst_number',
    ];

    public const INDIA_STATES = [
        'AN' => 'Andaman and Nicobar Islands', 'AP' => 'Andhra Pradesh', 'AR' => 'Arunachal Pradesh',
        'AS' => 'Assam', 'BR' => 'Bihar', 'CH' => 'Chandigarh', 'CG' => 'Chhattisgarh',
        'DN' => 'Dadra and Nagar Haveli and Daman and Diu', 'DL' => 'Delhi', 'GA' => 'Goa',
        'GJ' => 'Gujarat', 'HR' => 'Haryana', 'HP' => 'Himachal Pradesh', 'JK' => 'Jammu and Kashmir',
        'JH' => 'Jharkhand', 'KA' => 'Karnataka', 'KL' => 'Kerala', 'LA' => 'Ladakh', 'LD' => 'Lakshadweep',
        'MP' => 'Madhya Pradesh', 'MH' => 'Maharashtra', 'MN' => 'Manipur', 'ML' => 'Meghalaya',
        'MZ' => 'Mizoram', 'NL' => 'Nagaland', 'OD' => 'Odisha', 'PY' => 'Puducherry', 'PB' => 'Punjab',
        'RJ' => 'Rajasthan', 'SK' => 'Sikkim', 'TN' => 'Tamil Nadu', 'TS' => 'Telangana', 'TR' => 'Tripura',
        'UP' => 'Uttar Pradesh', 'UK' => 'Uttarakhand', 'WB' => 'West Bengal',
    ];

    public function __construct(
        private LabelOwnershipService $ownership,
        private UserFieldRequirementService $requirements
    ) {
    }

    /* ---------------------------------------------------------------- template */

    public function template(bool $includeOwned): string
    {
        $rows = DB::table('labels as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->whereNull('l.deleted_at')
            ->when(!$includeOwned, fn ($q) => $q->whereNull('l.user_id'))
            ->orderBy('l.name')
            ->get(['l.id', 'l.name', 'l.revenue_share_percentage', 'u.email as owner_email']);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::COLUMNS);

        foreach ($rows as $row) {
            $line = array_fill_keys(self::COLUMNS, '');
            $line['label_id'] = (int) $row->id;
            $line['label_name'] = $row->name;
            $line['current_owner_email'] = (string) ($row->owner_email ?? '');
            $line['revenue_share_percentage'] = $row->revenue_share_percentage !== null
                ? rtrim(rtrim(number_format((float) $row->revenue_share_percentage, 2, '.', ''), '0'), '.')
                : '';
            fputcsv($out, array_values($line));
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return "\xEF\xBB\xBF".$csv;
    }

    /* ----------------------------------------------------------------- parsing */

    private function readCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (!mb_check_encoding((string) $content, 'UTF-8')) {
            $content = mb_convert_encoding((string) $content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) $content);
        $delimiter = ',';
        $header = null;
        $rows = [];
        $lineNo = 0;

        foreach ($lines as $line) {
            $lineNo++;

            if (trim($line) === '') {
                continue;
            }

            if ($header === null && substr_count($line, ';') > substr_count($line, ',')) {
                $delimiter = ';';
            }

            $cells = str_getcsv($line, $delimiter, '"', '');

            if ($header === null) {
                $header = array_map(fn ($h) => Str::snake(strtolower(trim((string) $h))), $cells);

                if (!in_array('label_id', $header, true) || !in_array('owner_email', $header, true)) {
                    throw ValidationException::withMessages([
                        'csv' => 'The file must have the template header row (label_id, owner_email, ...). Download the template and fill it.',
                    ]);
                }

                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $value = $cells[$index] ?? '';
                $row[$column] = is_string($value) ? trim($value) : '';
            }

            $rows[] = ['line' => $lineNo, 'data' => $row];
        }

        if ($header === null) {
            throw ValidationException::withMessages(['csv' => 'The file is empty.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['csv' => 'Maximum '.self::MAX_ROWS.' rows per file.']);
        }

        return $rows;
    }

    private function newOwnerRules(): array
    {
        $req = fn (string $field) => $this->requirements->isRequired('label', $field) ? 'required' : 'nullable';

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'username' => [$req('username'), 'nullable', 'string', 'max:40'],
            'phone' => [$req('phone'), 'nullable', 'string', 'max:30'],
            'address_line_1' => [$req('address_line_1'), 'nullable', 'string', 'max:255'],
            'address_line_2' => [$req('address_line_2'), 'nullable', 'string', 'max:255'],
            'city' => [$req('city'), 'nullable', 'string', 'max:120'],
            'state_code' => [$req('state'), 'nullable', 'string', 'in:'.implode(',', array_keys(self::INDIA_STATES))],
            'postal_code' => [$req('postal_code'), 'nullable', 'string', 'max:30'],
            'revenue_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'account_holder_name' => [$req('account_holder_name'), 'nullable', 'string', 'max:190'],
            'bank_account_number' => [$req('bank_account_number'), 'nullable', 'string', 'max:60'],
            'bank_name' => [$req('bank_name'), 'nullable', 'string', 'max:190'],
            'ifsc_code' => [$req('ifsc_code'), 'nullable', 'string', 'max:30'],
            'branch_name' => [$req('branch_name'), 'nullable', 'string', 'max:190'],
            'pan_number' => [$req('pan_number'), 'nullable', 'string', 'max:30'],
            'gst_number' => [$req('gst_number'), 'nullable', 'string', 'max:40'],
        ];
    }

    private function toNewUser(string $email, array $details): array
    {
        $code = strtoupper((string) ($details['state_code'] ?? ''));

        return $this->ownership->normalizeNewUser([
            'name' => $details['owner_name'] ?? null,
            'email' => $email,
            'username' => $details['username'] ?? null,
            'phone' => $details['phone'] ?? null,
            'address_line_1' => $details['address_line_1'] ?? null,
            'address_line_2' => $details['address_line_2'] ?? null,
            'city' => $details['city'] ?? null,
            'state_code' => $code !== '' ? $code : null,
            'state' => self::INDIA_STATES[$code] ?? null,
            'postal_code' => $details['postal_code'] ?? null,
            'country' => 'India',
            'country_code' => 'IN',
            'revenue_share_percentage' => $details['revenue_share_percentage'] ?? null,
            'account_holder_name' => $details['account_holder_name'] ?? null,
            'bank_account_number' => $details['bank_account_number'] ?? null,
            'bank_name' => $details['bank_name'] ?? null,
            'ifsc_code' => $details['ifsc_code'] ?? null,
            'branch_name' => $details['branch_name'] ?? null,
            'pan_number' => $details['pan_number'] ?? null,
            'gst_number' => $details['gst_number'] ?? null,
        ]);
    }

    /*
     * Build the plan. Nothing is written. Returns groups, errors and a summary.
     */
    public function analyse(string $content): array
    {
        $rows = $this->readCsv($content);
        $errors = [];
        $skipped = 0;
        $seenLabels = [];
        $groups = [];

        $labelIds = collect($rows)->pluck('data.label_id')
            ->filter(fn ($v) => ctype_digit((string) $v))->map(fn ($v) => (int) $v)->unique()->values()->all();

        $labels = DB::table('labels as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->whereIn('l.id', $labelIds ?: [0])
            ->whereNull('l.deleted_at')
            ->get(['l.id', 'l.name', 'l.user_id', 'u.email as owner_email'])
            ->keyBy('id');

        foreach ($rows as $row) {
            $line = $row['line'];
            $data = $row['data'];
            $email = strtolower((string) ($data['owner_email'] ?? ''));
            $rawId = (string) ($data['label_id'] ?? '');

            if ($email === '') {
                $skipped++;

                continue;
            }

            if (!ctype_digit($rawId)) {
                $errors[] = ['line' => $line, 'message' => 'label_id is missing or not a number.'];

                continue;
            }

            $labelId = (int) $rawId;
            $label = $labels[$labelId] ?? null;

            if (!$label) {
                $errors[] = ['line' => $line, 'message' => "Label #{$labelId} does not exist."];

                continue;
            }

            $givenName = (string) ($data['label_name'] ?? '');

            if ($givenName !== '' && mb_strtolower(trim($givenName)) !== mb_strtolower(trim((string) $label->name))) {
                $errors[] = ['line' => $line, 'message' => "Label #{$labelId} is \"{$label->name}\", but the file says \"{$givenName}\". Fix the id or the name."];

                continue;
            }

            if (isset($seenLabels[$labelId])) {
                $errors[] = ['line' => $line, 'message' => "Label #{$labelId} is listed twice (also on line {$seenLabels[$labelId]})."];

                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $line, 'message' => "owner_email \"{$email}\" is not a valid e-mail."];

                continue;
            }

            $seenLabels[$labelId] = $line;

            if (!isset($groups[$email])) {
                $groups[$email] = ['email' => $email, 'lines' => [], 'labels' => [], 'details' => [], 'details_from' => [], 'details_line' => null];
            }

            $groups[$email]['lines'][] = $line;
            $groups[$email]['labels'][] = [
                'id' => $labelId,
                'name' => $label->name,
                'current_owner_id' => $label->user_id ? (int) $label->user_id : null,
                'current_owner_email' => $label->owner_email,
            ];

            $details = array_intersect_key($data, array_flip(self::DETAIL_COLUMNS));
            $filled = array_filter($details, fn ($v) => $v !== '' && $v !== null);

            /*
             * Owner details may be spread over the owner's rows: the first
             * non-empty value of each column is used. revenue % is per label in
             * the template, so the first one is used as the owner's default.
             */
                        foreach ($filled as $key => $value) {
                $first = (string) ($groups[$email]['details'][$key] ?? '');

                if ($first === '') {
                    $groups[$email]['details'][$key] = $value;
                    $groups[$email]['details_from'][$key] = $line;

                    if ($key !== 'revenue_share_percentage') {
                        $groups[$email]['details_line'] ??= $line;
                    }
                } elseif ($key !== 'revenue_share_percentage' && strcasecmp($first, (string) $value) !== 0) {
                    $errors[] = ['line' => $line, 'message' => "{$key} for {$email} differs from line {$groups[$email]['details_from'][$key]}. Use the same details (or leave them blank) on every row of one owner."];
                }
            }
        }

        $existingUsers = User::query()
            ->whereIn('email', array_keys($groups) ?: ['-'])
            ->get(['id', 'name', 'email', 'role', 'account_status'])
            ->keyBy(fn ($u) => strtolower($u->email));

        $plan = [];

        foreach ($groups as $email => $group) {
            $existing = $existingUsers[$email] ?? null;
            $firstLine = $group['lines'][0];
            $entry = [
                'email' => $email,
                'lines' => $group['lines'],
                'label_ids' => array_column($group['labels'], 'id'),
                'labels' => $group['labels'],
                'mode' => $existing ? 'existing' : 'new',
                'target_user_id' => null,
                'owner_name' => null,
                'new_user' => null,
                'assign' => 0,
                'transfer' => 0,
                'unchanged' => 0,
            ];

            if ($existing) {
                if ($existing->role !== 'label') {
                    $errors[] = ['line' => $firstLine, 'message' => "{$email} already belongs to a {$existing->role} account, not a Label account."];

                    continue;
                }

                $entry['target_user_id'] = (int) $existing->id;
                $entry['owner_name'] = $existing->name;
                $entry['owner_status'] = $existing->account_status;
            } else {
                $newUser = $this->toNewUser($email, (array) ($group['details'] ?? []));

                /* Excel turns long numbers into 1.23E+11 or drops leading zeros. */
                foreach (['bank_account_number' => 'bank account number', 'phone' => 'phone'] as $key => $label) {
                    if ($newUser[$key] !== null && preg_match('/e\+|\./i', (string) $newUser[$key])) {
                        $errors[] = ['line' => $group['details_from'][$key] ?? $firstLine, 'message' => "New owner {$email}: {$label} \"{$newUser[$key]}\" was changed by Excel. Format that column as Text and type it again."];

                        continue 2;
                    }
                }
                $validator = Validator::make(
                    array_merge($newUser, ['state_code' => $newUser['state_code']]),
                    $this->newOwnerRules(),
                    [],
                    ['name' => 'owner_name', 'state_code' => 'state_code']
                );

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = ['line' => $group['details_line'] ?? $firstLine, 'message' => "New owner {$email}: {$message}"];
                    }

                    continue;
                }

                $entry['new_user'] = $newUser;
                $entry['owner_name'] = $newUser['name'];
                $entry['owner_status'] = 'new';
            }

            foreach ($group['labels'] as $label) {
                if ($label['current_owner_id'] === null) {
                    $entry['assign']++;
                } elseif ($existing && $label['current_owner_id'] === (int) $existing->id) {
                    $entry['unchanged']++;
                } else {
                    $entry['transfer']++;
                }
            }

            $plan[] = $entry;
        }

        usort($errors, fn ($a, $b) => $a['line'] <=> $b['line']);

        return [
            'groups' => $plan,
            'errors' => $errors,
            'summary' => [
                'rows' => count($rows),
                'skipped_rows' => $skipped,
                'owners' => count($plan),
                'new_owners' => count(array_filter($plan, fn ($g) => $g['mode'] === 'new')),
                'existing_owners' => count(array_filter($plan, fn ($g) => $g['mode'] === 'existing')),
                'labels' => array_sum(array_map(fn ($g) => count($g['label_ids']), $plan)),
                'assign' => array_sum(array_column($plan, 'assign')),
                'transfer' => array_sum(array_column($plan, 'transfer')),
                'unchanged' => array_sum(array_column($plan, 'unchanged')),
            ],
        ];
    }

    /* -------------------------------------------------------- plan + OTP + run */

    private function planKey(User $admin, string $token): string
    {
        return 'mixxtune:label-owner-bulk:'.$admin->id.':'.$token;
    }

    public function fingerprint(array $groups): string
    {
        $canonical = array_map(function ($g) {
            $ids = $g['label_ids'];
            sort($ids);

            return [$g['email'], $g['mode'], $g['target_user_id'], $ids, $g['new_user']];
        }, $groups);

        usort($canonical, fn ($a, $b) => strcmp($a[0], $b[0]));

        return hash('sha256', 'bulk|'.json_encode($canonical));
    }

    public function storePlan(User $admin, array $analysis): string
    {
        $token = Str::random(40);

        Cache::put($this->planKey($admin, $token), [
            'groups' => $analysis['groups'],
            'summary' => $analysis['summary'],
            'fingerprint' => $this->fingerprint($analysis['groups']),
            'confirmed' => false,
            'next' => 0,
            'results' => [],
        ], now()->addMinutes(self::PLAN_MINUTES));

        return $token;
    }

    public function plan(User $admin, string $token): array
    {
        $plan = Cache::get($this->planKey($admin, $token));

        if (!is_array($plan)) {
            throw ValidationException::withMessages([
                'token' => 'This preview has expired. Upload the file again.',
            ]);
        }

        return $plan;
    }

    private function savePlan(User $admin, string $token, array $plan): void
    {
        Cache::put($this->planKey($admin, $token), $plan, now()->addMinutes(self::PLAN_MINUTES));
    }

    public function sendOtp(User $admin, string $token): array
    {
        $plan = $this->plan($admin, $token);

        if ($plan['confirmed']) {
            throw ValidationException::withMessages(['token' => 'This import is already confirmed.']);
        }

        $s = $plan['summary'];
        $this->ownership->sendOtp(
            $admin,
            $plan['fingerprint'],
            (int) $s['labels'],
            "BULK CSV: {$s['owners']} owner(s) ({$s['new_owners']} new), {$s['transfer']} transfer(s)"
        );

        return $plan;
    }

    public function confirm(User $admin, string $token, string $otp): void
    {
        $plan = $this->plan($admin, $token);

        if ($plan['confirmed']) {
            return;
        }

        if (!$this->ownership->verifyOtp($admin, $plan['fingerprint'], $otp)) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired confirmation code.']);
        }

        $plan['confirmed'] = true;
        $this->savePlan($admin, $token, $plan);
    }

    /*
     * Process the next small batch of owner groups. Each group is independent:
     * a failure is recorded and the run continues with the next owner.
     */
    public function runBatch(User $admin, string $token, ?Request $request = null): array
    {
        $plan = $this->plan($admin, $token);

        if (!$plan['confirmed']) {
            throw ValidationException::withMessages(['otp' => 'Confirm the import with the e-mailed code first.']);
        }

        @set_time_limit(300);
        $total = count($plan['groups']);
        $end = min($total, $plan['next'] + self::BATCH_SIZE);

        for ($i = $plan['next']; $i < $end; $i++) {
            $group = $plan['groups'][$i];

            /* Mark as started before working, so an interrupted run is visible. */
            $plan['results'][$i] = ['email' => $group['email'], 'status' => 'interrupted', 'message' => 'Stopped while processing. Check this owner on the Labels page.'];
            $plan['next'] = $i + 1;
            $this->savePlan($admin, $token, $plan);

            try {
                $targetId = $group['mode'] === 'existing' ? (int) $group['target_user_id'] : null;
                $newUser = $group['mode'] === 'new' ? $group['new_user'] : null;

                if ($newUser && User::query()->where('email', $group['email'])->exists()) {
                    throw ValidationException::withMessages(['email' => 'An account with this e-mail was created meanwhile. Upload the file again to use it.']);
                }

                $result = $this->ownership->apply($admin, $group['label_ids'], $targetId, $newUser, $request);
                $counts = array_count_values(array_column($result['changes'], 'status'));

                $plan['results'][$i] = [
                    'email' => $group['email'],
                    'status' => 'done',
                    'created' => (bool) $result['created'],
                    'owner_id' => $result['owner']['id'] ?? null,
                    'assigned' => $counts['assigned'] ?? 0,
                    'transferred' => $counts['transferred'] ?? 0,
                    'unchanged' => $counts['unchanged'] ?? 0,
                    'message' => trim(implode(' ', array_filter([
                        $result['invitation_error'] ? 'Invitation e-mail failed: '.$result['invitation_error'] : null,
                        $result['recalculation_error'] ? 'Earnings recalculation failed: '.$result['recalculation_error'] : null,
                    ]))),
                ];
            } catch (ValidationException $exception) {
                $plan['results'][$i] = ['email' => $group['email'], 'status' => 'failed', 'message' => implode(' ', $exception->validator->errors()->all())];
            } catch (\Throwable $exception) {
                report($exception);
                $plan['results'][$i] = ['email' => $group['email'], 'status' => 'failed', 'message' => $exception->getMessage()];
            }

            $this->savePlan($admin, $token, $plan);
        }

        $results = array_values($plan['results']);

        return [
            'done' => $plan['next'] >= $total,
            'processed' => $plan['next'],
            'total' => $total,
            'results' => $results,
            'succeeded' => count(array_filter($results, fn ($r) => $r['status'] === 'done')),
            'failed' => count(array_filter($results, fn ($r) => $r['status'] !== 'done')),
        ];
    }
}
