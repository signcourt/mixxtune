<?php

namespace Tests\Feature\Royalties;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W72 MIXXTUNE_ROYALTIES_V3 */
class OwnerRoyaltiesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function artistUser(): array
    {
        $user = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);
        $artistId = DB::table('artists')->insertGetId([
            'public_id' => 'ART-'.strtoupper(substr((string) Str::ulid(), -12)),
            'user_id' => $user->id,
            'stage_name' => 'Royalty Page Artist',
            'legal_name' => 'Royalty Page Artist',
            'slug' => 'royalty-page-artist-'.strtolower(substr((string) Str::ulid(), -6)),
            'email' => 'rpa-'.strtolower(substr((string) Str::ulid(), -6)).'@example.test',
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'account_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $artistId];
    }

    private function statement(int $artistId, string $month, float $gross, float $tax, string $status): int
    {
        return (int) DB::table('royalty_statements')->insertGetId([
            'public_id' => 'RS-'.$month.'-'.Str::lower(Str::random(6)), 'artist_id' => $artistId, 'statement_month' => $month, 'currency' => 'INR',
            'gross_earnings' => $gross, 'commission_amount' => 0, 'tax_amount' => $tax, 'other_deductions' => 0,
            'net_payable' => $gross - $tax, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_totals_skip_cancelled_and_chart_counts_and_filters_cover_all_statements(): void
    {
        [$user, $artistId] = $this->artistUser();

        $this->statement($artistId, '2026-06', 100, 10, 'pending');
        $this->statement($artistId, '2026-07', 200, 20, 'available');
        $this->statement($artistId, '2026-07-01', 50, 0, 'paid');
        $this->statement($artistId, '2026-08', 999, 0, 'cancelled');
        foreach (range(1, 26) as $i) {
            $this->statement($artistId, '2025-01', 1, 0, 'paid');
        }

        $this->actingAs($user)->get('/v2/royalties')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Royalties/Index')
            ->where('summary.gross', fn ($v) => abs((float) $v - 376) < 0.001)
            ->where('summary.deductions', fn ($v) => abs((float) $v - 30) < 0.001)
            ->where('summary.net', fn ($v) => abs((float) $v - 346) < 0.001)
            ->where('counts.all', 30)
            ->where('counts.paid', 27)
            ->where('counts.cancelled', 1)
            ->where('monthly', fn ($rows) => collect($rows)->pluck('month')->all() === ['2025-01', '2026-06', '2026-07']
                && abs(collect($rows)->firstWhere('month', '2026-07')['net'] - 230) < 0.001)
            ->where('statements.total', 30));

        $this->actingAs($user)->get('/v2/royalties?status=paid')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('filters.status', 'paid')
            ->where('statements.total', 27)
            ->where('statements.data.0.statement_month', '2026-07-01'));

        $this->actingAs($user)->get('/v2/royalties?search=2026-06')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('statements.total', 1)
            ->where('statements.data.0.status', 'pending'));

        $this->actingAs($user)->get('/v2/royalties?status=nonsense')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('filters.status', '')
            ->where('statements.total', 30));
    }

    public function test_owner_never_sees_other_artists_statements(): void
    {
        [$user, $artistId] = $this->artistUser();
        [, $otherArtist] = $this->artistUser();
        $this->statement($artistId, '2026-07', 10, 0, 'paid');
        $this->statement($otherArtist, '2026-07', 500, 0, 'paid');

        $this->actingAs($user)->get('/v2/royalties')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('counts.all', 1)
            ->where('summary.gross', fn ($v) => abs((float) $v - 10) < 0.001));
    }
}
