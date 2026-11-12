<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/**
 * W163: one "Held revenue" screen listing BOTH hold sources
 * (digital report imports + IPRS publishing) per owner.
 */
class HeldRevenueUnifiedTest extends TestCase
{
    use ConfirmsActionOtp;
    use RefreshDatabase;

    private int $importId = 0;
    private int $publicationId = 0;
    private int $seq = 0;

    /** Schema-tolerant insert: fills any required column the test didn't set. */
    private function ins(string $table, array $data): int
    {
        $cols = Schema::getColumns($table);
        $data = array_intersect_key($data, array_flip(array_column($cols, 'name')));
        foreach ($cols as $c) {
            $n = $c['name'];
            if ($n === 'id' || array_key_exists($n, $data)) {
                continue;
            }
            if (in_array($n, ['created_at', 'updated_at'], true)) {
                $data[$n] = now();
                continue;
            }
            if ($c['nullable'] || $c['default'] !== null || ! empty($c['auto_increment'])) {
                continue;
            }
            $full = strtolower((string) ($c['type'] ?? ''));
            $t = strtolower((string) ($c['type_name'] ?? $c['type'] ?? ''));
            if (preg_match('/^enum\((.+)\)$/', $full, $em)) {
                $data[$n] = trim(explode(',', $em[1])[0], "'\" ");
                continue;
            }
            $this->seq++;
            $data[$n] = match (true) {
                str_contains($t, 'int') => 1,
                str_contains($t, 'dec') || str_contains($t, 'num') || str_contains($t, 'float')
                    || str_contains($t, 'double') || str_contains($t, 'real') => 0,
                str_contains($t, 'bool') => 0,
                str_contains($t, 'date') || str_contains($t, 'time') => now(),
                str_contains($t, 'json') => '{}',
                default => 'x'.$this->seq,
            };
        }

        return (int) DB::table($table)->insertGetId($data);
    }

    private function seedImportParents(): void
    {
        if ($this->importId) {
            return;
        }
        $this->importId = $this->ins('report_imports', [
            'reporting_month' => '2026-08',
            'financial_status' => 'published',
            'original_filename' => 'aug.csv',
        ]);
        $this->publicationId = $this->ins('report_import_publications', [
            'report_import_id' => $this->importId,
            'status' => 'completed',
        ]);
    }

    private function makeUser(string $role, string $status = 'active'): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'role' => $role,
            'account_status' => $status,
            'kyc_status' => 'verified',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function makeLabel(?int $userId): int
    {
        $row = ['name' => 'Label '.Str::random(5), 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()];
        foreach (['slug' => Str::slug('l-'.Str::random(6)), 'public_id' => (string) Str::uuid(), 'status' => 'active', 'revenue_share_percentage' => 80] as $c => $v) {
            if (Schema::hasColumn('labels', $c)) {
                $row[$c] = $v;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    private function seedDigitalHold(int $labelId, float $net): void
    {
        // Matching held posting (the releaser checks owner/currency/earnings against it).
        $postingId = $this->ins('report_import_postings', [
            'publication_id' => $this->publicationId,
            'report_import_id' => $this->importId,
            'owner_type' => 'label',
            'owner_id' => $labelId,
            'currency' => 'INR',
            'posting_key' => (string) Str::ulid(),
            'status' => 'held_unclaimed',
            'row_count' => 1,
            'collected_revenue' => $net,
            'earnings' => $net,
        ]);

        $this->ins('report_unclaimed_revenues', [
            'hold_key' => (string) Str::ulid(),
            'report_import_id' => $this->importId,
            'report_import_posting_id' => $postingId,
            'owner_type' => 'label',
            'owner_id' => $labelId,
            'currency' => 'INR',
            'gross_amount' => $net,
            'net_amount' => $net,
            'status' => 'held',
        ]);
    }

    private function seedIprsHold(int $labelId, ?int $userId, float $net): void
    {
        $this->ins('iprs_held_revenues', [
            'public_id' => (string) Str::ulid(),
            'iprs_upload_id' => 1,
            'iprs_line_id' => ++$this->seq + 1000,
            'work_int_no' => 'W'.(++$this->seq),
            'owner_type' => 'label',
            'owner_id' => $labelId,
            'user_id' => $userId,
            'gross_amount' => $net,
            'share_percentage' => 80,
            'net_amount' => $net,
            'royalty_type' => 'publishing',
            'reason' => 'owner_user_missing',
            'status' => 'held',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_screen_lists_both_sources_per_owner(): void
    {
        $this->seedImportParents();
        $admin = $this->makeUser('super_admin');

        // Owner A: has both a digital hold and an IPRS hold, no active user.
        $labelA = $this->makeLabel(null);
        $this->seedDigitalHold($labelA, 500);   // one digital hold -> 500
        $this->seedIprsHold($labelA, null, 150);

        // Owner B: only IPRS hold.
        $labelB = $this->makeLabel(null);
        $this->seedIprsHold($labelB, null, 90);

        $res = $this->actingAs($admin)->get('/admin/finance/held');
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('V2/Admin/Finance/Held')
            ->where('summary.digital_total', fn ($v) => (float) $v === 500.0)
            ->where('summary.iprs_total', fn ($v) => (float) $v === 240.0)
            ->where('summary.total', fn ($v) => (float) $v === 740.0)
            ->where('summary.owner_count', 2)
        );
    }

    public function test_digital_owner_release_is_otp_gated_and_credits(): void
    {
        $this->seedImportParents();
        $owner = $this->makeUser('artist');          // active user to receive the wallet credit
        $admin = $this->makeUser('super_admin');
        $labelA = $this->makeLabel($owner->id);      // label linked to an active user
        $this->seedDigitalHold($labelA, 500);

        $send = fn () => $this->actingAs($admin)->post('/admin/finance/held/release-owner', [
            'owner_type' => 'label',
            'owner_id' => $labelA,
        ]);

        // First attempt stopped with action_otp; after the code, holds release.
        $this->viaActionOtp($admin, $send)->assertSessionHas('success');

        $this->assertSame(0, DB::table('report_unclaimed_revenues')->where('owner_id', $labelA)->where('status', 'held')->count());
    }
}
