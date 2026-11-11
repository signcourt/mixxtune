<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * W150: the owner Publishing dashboard shows only the published rights/
 * performing royalty lines (source_type = rights_royalty_allocation) that were
 * matched to THIS owner's catalogue. Another owner's lines and non-rights
 * (digital) lines must not appear.
 */
class RightsRoyaltyPublishingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function artistUser(string $name): array
    {
        $user = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $artistId = DB::table('artists')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'stage_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $artistId];
    }

    private function ledger(int $artistId, float $amount, string $sourceType): void
    {
        DB::table('royalty_ledgers')->insert([
            'public_id' => (string) Str::ulid(),
            'artist_id' => $artistId,
            'statement_month' => '2026-08',
            'store_name' => $sourceType === 'rights_royalty_allocation' ? 'RIGHTS ROYALTY' : 'Spotify',
            'source_type' => $sourceType,
            'currency' => 'INR',
            'gross_amount' => $amount,
            'payable_amount' => $amount,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_owner_sees_only_their_published_rights_lines(): void
    {
        [$me, $myArtist] = $this->artistUser('My Act');
        [, $otherArtist] = $this->artistUser('Other Act');

        // mine: two rights lines
        $this->ledger($myArtist, 100, 'rights_royalty_allocation');
        $this->ledger($myArtist, 50, 'rights_royalty_allocation');
        // mine: a digital (non-rights) line - must be excluded here
        $this->ledger($myArtist, 777, 'revenue_import');
        // someone else's rights line - must be excluded
        $this->ledger($otherArtist, 999, 'rights_royalty_allocation');

        $this->actingAs($me)
            ->get('/publishing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Royalties/Publishing')
                ->where('isOwner', true)
                ->where('summary.lines', 2)
                ->where('summary.total_payable', 150)
                ->has('rows.data', 2));
    }

    public function test_owner_with_no_rights_royalties_sees_empty(): void
    {
        [$me] = $this->artistUser('Quiet Act');

        $this->actingAs($me)
            ->get('/publishing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Royalties/Publishing')
                ->where('isOwner', true)
                ->where('summary.lines', 0)
                ->has('rows.data', 0));
    }
}
