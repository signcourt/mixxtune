<?php

namespace App\Console\Commands;

use App\Services\V2\UsernameService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WordPressLegacyUsersImportCommand extends Command
{
    protected $signature =
        'mixxtune:wordpress-legacy-users
        {directory}
        {--commit : Actually write users to the database}';

    protected $description =
        'Dry-run or import WordPress legacy user accounts';

    public function handle(UsernameService $usernameService): int
    {
        $directory = rtrim(
            (string) $this->argument('directory'),
            '/'
        );

        $usersFile = $directory . '/users.jsonl';
        $metaFile = $directory . '/usermeta.jsonl';

        if (
            ! is_file($usersFile)
            || ! is_file($metaFile)
        ) {
            $this->error('Required snapshot files are missing.');

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');

        $this->info(
            $commit
                ? 'MODE=COMMIT'
                : 'MODE=DRY_RUN'
        );


        $users = [];

        $handle = fopen($usersFile, 'rb');

        if ($handle === false) {
            $this->error('Unable to open users.jsonl.');

            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode(
                $line,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $users[(string) $row['ID']] = $row;
        }

        fclose($handle);

        $meta = [];

        $handle = fopen($metaFile, 'rb');

        if ($handle === false) {
            $this->error('Unable to open usermeta.jsonl.');

            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode(
                $line,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $userId = (string) $row['user_id'];
            $key = (string) $row['meta_key'];

            $meta[$userId][$key] = $row['meta_value'];
        }

        fclose($handle);

        $existingEmails = DB::table('users')
            ->pluck('id', 'email')
            ->mapWithKeys(
                fn ($id, $email) => [
                    strtolower((string) $email) => $id,
                ]
            )
            ->all();

        $existingUsernames = DB::table('users')
            ->whereNotNull('username')
            ->pluck('id', 'username')
            ->mapWithKeys(
                fn ($id, $username) => [
                    strtolower((string) $username) => $id,
                ]
            )
            ->all();

        $summary = [
            'source_users' => count($users),
            'new_accounts' => 0,
            'existing_account_maps' => 0,
            'pending_orphan_accounts' => 0,
            'email_conflicts' => 0,
            'username_conflicts' => 0,
            'missing_email' => 0,
            'wp176_target_user_id' => null,
            'orphan_117_present' => isset($meta['117']),
        ];

        $plannedUsernames = [];

        foreach ($users as $wpId => $row) {
            $email = strtolower(
                trim((string) ($row['user_email'] ?? ''))
            );

            if ($email === '') {
                $summary['missing_email']++;
                continue;
            }

            /*
             * Authoritative existing-account exception:
             * WP 176 is the surviving Mixx Tune account and matches
             * Laravel super_admin ID 1 by exact email.
             */
            if (
                (string) $wpId === '176'
                && isset($existingEmails[$email])
            ) {
                $summary['existing_account_maps']++;
                $summary['wp176_target_user_id'] =
                    $existingEmails[$email];

                continue;
            }

            $username = $usernameService->normalize(
                (string) ($row['user_login'] ?? '')
            );

            if ($usernameService->isReserved($username)) {
                $username = substr(
                    $username . 'account',
                    0,
                    40
                );
            }

            if (isset($existingEmails[$email])) {
                $summary['email_conflicts']++;
            }

            if (
                isset($existingUsernames[strtolower($username)])
                || isset($plannedUsernames[strtolower($username)])
            ) {
                $summary['username_conflicts']++;
            }

            $plannedUsernames[strtolower($username)] = $wpId;
            $summary['new_accounts']++;
        }

        /*
         * WP 117 has no users row but owns 72 releases and one
         * transaction. Preserve it as an explicit pending exception;
         * never discard or auto-merge it.
         */
        if (isset($meta['117'])) {
            $summary['pending_orphan_accounts'] = 1;
        }

        $this->line(
            json_encode(
                $summary,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            )
        );

        if (! $commit) {
            $this->info('WORDPRESS_USERS_DRY_RUN_COMPLETE');

            return self::SUCCESS;
        }

        if (
            $summary['email_conflicts'] !== 0
            || $summary['username_conflicts'] !== 0
            || $summary['missing_email'] !== 0
            || $summary['existing_account_maps'] !== 1
            || $summary['wp176_target_user_id'] !== 1
        ) {
            $this->error('COMMIT_ABORTED: dry-run safety checks failed.');

            return self::FAILURE;
        }

        $sourceKey = 'backstagemixxtune.com';
        $created = 0;
        $mappedExisting = 0;
        $alreadyMapped = 0;

        DB::transaction(function () use (
            $users,
            $meta,
            $usernameService,
            $sourceKey,
            &$created,
            &$mappedExisting,
            &$alreadyMapped
        ) {
            foreach ($users as $wpId => $row) {
                $wpId = (string) $wpId;

                $existingMap = DB::table(
                    'wordpress_legacy_id_maps'
                )
                    ->where('source_key', $sourceKey)
                    ->where('source_entity_type', 'user')
                    ->where('source_entity_id', $wpId)
                    ->first();

                if ($existingMap) {
                    $alreadyMapped++;
                    continue;
                }

                $email = strtolower(
                    trim((string) ($row['user_email'] ?? ''))
                );

                if ($wpId === '176') {
                    DB::table('wordpress_legacy_id_maps')->insert([
                        'source_key' => $sourceKey,
                        'source_entity_type' => 'user',
                        'source_entity_id' => $wpId,
                        'target_entity_type' => 'user',
                        'target_entity_id' => 1,
                        'migration_status' => 'mapped',
                        'source_snapshot' => json_encode([
                            'user_login' => $row['user_login'] ?? null,
                            'user_email' => $row['user_email'] ?? null,
                            'display_name' => $row['display_name'] ?? null,
                        ]),
                        'metadata' => json_encode([
                            'mapping_reason' =>
                                'existing_mixxtune_super_admin',
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $mappedExisting++;
                    continue;
                }

                $username = $usernameService->generate(
                    (string) ($row['user_login'] ?? ''),
                    $email
                );

                $userMeta = $meta[$wpId] ?? [];

                $firstName = trim(
                    (string) ($userMeta['first_name'] ?? '')
                );

                $lastName = trim(
                    (string) ($userMeta['last_name'] ?? '')
                );

                $metaName = trim(
                    $firstName . ' ' . $lastName
                );

                $name = $metaName !== ''
                    ? $metaName
                    : trim(
                        (string) (
                            $row['display_name']
                            ?? $row['user_login']
                            ?? $email
                        )
                    );

                $phone = trim(
                    (string) ($userMeta['billing_phone'] ?? '')
                );

                $country = trim(
                    (string) ($userMeta['billing_country'] ?? '')
                );

                $state = strtoupper(
                    trim(
                        (string) ($userMeta['billing_state'] ?? '')
                    )
                );

                if (strlen($state) !== 2) {
                    $state = null;
                }

                /*
                 * Opening balance is intentionally NOT posted here.
                 * Finance migration will handle user_earning separately.
                 */
                $targetId = DB::table('users')->insertGetId([
                    'name' => $name !== '' ? $name : $username,
                    'username' => $username,
                    'email' => $email,
                    'role' => 'label',
                    'phone' => $phone !== '' ? $phone : null,
                    'label_name' => null,
                    'country' => $country !== '' ? $country : null,
                    'state_code' => $state,
                    'client_id' => null,
                    'account_status' => 'active',
                    'kyc_status' => 'pending',
                    'wallet_balance' => 0,
                    'email_verified_at' => null,
                    'password' => Hash::make(Str::random(64)),
                    'remember_token' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'last_login_at' => null,
                    'invitation_status' => 'not_invited',
                    'invitation_token' => null,
                    'invitation_sent_at' => null,
                    'invitation_expires_at' => null,
                    'invitation_accepted_at' => null,
                    'invitation_count' => 0,
                    'invitation_error' => null,
                    'password_set_at' => null,
                ]);

                DB::table('wordpress_legacy_id_maps')->insert([
                    'source_key' => $sourceKey,
                    'source_entity_type' => 'user',
                    'source_entity_id' => $wpId,
                    'target_entity_type' => 'user',
                    'target_entity_id' => $targetId,
                    'migration_status' => 'mapped',
                    'source_snapshot' => json_encode([
                        'user_login' => $row['user_login'] ?? null,
                        'user_email' => $row['user_email'] ?? null,
                        'display_name' => $row['display_name'] ?? null,
                    ]),
                    'metadata' => json_encode([
                        'role' => 'label',
                        'invitation_status' => 'not_invited',
                        'opening_wallet_migrated' => false,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            }

            /*
             * WP117 remains deliberately unresolved.
             * Record the exception without creating/merging an account.
             */
            if (isset($meta['117'])) {
                DB::table('wordpress_legacy_id_maps')
                    ->updateOrInsert(
                        [
                            'source_key' => $sourceKey,
                            'source_entity_type' => 'user',
                            'source_entity_id' => '117',
                        ],
                        [
                            'target_entity_type' => 'user',
                            'target_entity_id' => null,
                            'migration_status' => 'pending',
                            'source_snapshot' => json_encode([
                                'nickname' =>
                                    $meta['117']['nickname'] ?? null,
                            ]),
                            'metadata' => json_encode([
                                'reason' =>
                                    'orphan_user_owns_72_releases_and_1_transaction',
                                'requires_manual_resolution' => true,
                            ]),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
            }
        });

        $this->line(json_encode([
            'created_users' => $created,
            'mapped_existing_users' => $mappedExisting,
            'already_mapped_users' => $alreadyMapped,
            'orphan_117_status' => 'pending',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('WORDPRESS_USERS_COMMIT_COMPLETE');

        return self::SUCCESS;
    }
}
