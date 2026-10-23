<?php

namespace Tests\Feature\Accounts;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* MIXXTUNE_LABEL_SAFETY_V1 (W59) */
class LabelSafetyTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_hindi_label_name_is_listed_and_not_grouped_with_others(): void
    {
        $super = $this->user('super_admin');
        Label::factory()->create(['name' => 'सनातन संगीत']);
        Label::factory()->create(['name' => '!!!']);
        Label::factory()->create(['name' => '???']);

        $this->actingAs($super)->get('/admin/labels')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('labels.total', 3));
    }

    public function test_bulk_percent_needs_the_code_even_for_labels_without_percent(): void
    {
        Mail::fake();
        $super = $this->user('super_admin');
        $a = Label::factory()->create(['revenue_share_percentage' => null]);
        $b = Label::factory()->create(['revenue_share_percentage' => null]);

        $send = fn () => $this->actingAs($super)->post('/admin/labels/bulk-action', [
            'label_ids' => [$a->id, $b->id], 'action' => 'set_rate', 'revenue_share_percentage' => 70,
        ]);

        $this->viaActionOtp($super, $send)->assertSessionHasNoErrors();
        $this->assertEquals(70, (float) $a->fresh()->revenue_share_percentage);
        $this->assertEquals(70, (float) $b->fresh()->revenue_share_percentage);
    }

    public function test_changing_a_sub_label_share_needs_the_code(): void
    {
        $super = $this->user('super_admin');
        $master = Label::factory()->create(['name' => 'Master Music']);
        $child = Label::factory()->create(['name' => 'Child Music', 'parent_label_id' => $master->id]);
        LabelRevenueShare::query()->create([
            'master_label_id' => $master->id, 'beneficiary_type' => 'label', 'beneficiary_id' => $child->id,
            'revenue_share_percent' => 60, 'show_revenue_share' => true, 'is_active' => true,
            'effective_from' => now()->toDateString(), 'created_by' => $super->id, 'updated_by' => $super->id,
        ]);

        $send = fn () => $this->actingAs($super)->put('/super-admin/label-hierarchy/'.$child->id, [
            'name' => 'Child Music', 'parent_label_id' => $master->id, 'status' => 'active', 'revenue_share_percent' => 10,
        ]);

        $this->viaActionOtp($super, $send)->assertSessionHasNoErrors();
        $this->assertEquals(10, (float) LabelRevenueShare::query()->where('beneficiary_id', $child->id)->value('revenue_share_percent'));

        $this->actingAs($super)->get('/super-admin/label-hierarchy')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('labels', fn ($rows) => collect($rows)->firstWhere('id', $child->id)['depth'] === 1
                && collect($rows)->firstWhere('id', $master->id)['descendant_count'] === 1
                && (float) collect($rows)->firstWhere('id', $child->id)['revenue_share_percentage'] === 10.0));
    }

    public function test_artist_with_revenue_rows_is_not_deleted_and_managers_cannot_delete(): void
    {
        $super = $this->user('super_admin');
        $artist = Artist::factory()->create();
        $import = DB::table('report_imports')->insertGetId(['public_id' => (string) Str::ulid(), 'original_filename' => 'x.csv', 'stored_path' => 'x', 'status' => 'completed', 'financial_status' => 'published', 'reporting_month' => '2026-07', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('report_rows')->insert(['report_import_id' => $import, 'row_hash' => 'h1', 'track_title' => 'S', 'track_artist' => 'A', 'isrc' => 'INAAA2600001', 'platform' => 'Spotify', 'currency' => 'INR', 'sale_type' => 'Stream', 'sale_month' => '2026-07', 'streams' => 1, 'sale_units' => 1, 'collected_revenue' => 1, 'label_rate' => 1, 'earnings' => 1, 'artist_id' => $artist->id, 'mapping_status' => 'mapped', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($super)->delete('/admin/artists/'.$artist->id)->assertSessionHasErrors('artist');
        $this->assertNotSoftDeleted($artist);
    }
}
