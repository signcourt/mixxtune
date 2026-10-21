<?php

namespace Tests\Feature\Accounts;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Finance\LabelRevenueShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* MIXXTUNE_LEVELS_V2 (W61) */
class LevelManagementTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('module', 100)->nullable();
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('request_method', 10)->nullable();
                $table->text('request_url')->nullable();
                $table->timestamps();
            });
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function share(Label $master, Label $child, float $pct, User $by, string $from): void
    {
        LabelRevenueShare::query()->create([
            'master_label_id' => $master->id, 'beneficiary_type' => 'label', 'beneficiary_id' => $child->id,
            'revenue_share_percent' => $pct, 'show_revenue_share' => true, 'is_active' => true,
            'effective_from' => $from, 'created_by' => $by->id, 'updated_by' => $by->id,
        ]);
    }

    public function test_page_shows_owner_counts_missing_share_and_totals(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $master = Label::factory()->create(['name' => 'Owner Music', 'user_id' => $owner->id]);
        $withShare = Label::factory()->create(['name' => 'Kid One', 'parent_label_id' => $master->id]);
        $noShare = Label::factory()->create(['name' => 'Kid Two', 'parent_label_id' => $master->id]);
        Label::factory()->create(['name' => 'Lonely Music', 'user_id' => null]);
        $this->share($master, $withShare, 40, $super, now()->subMonth()->toDateString());
        Release::factory()->count(2)->create(['label_id' => $master->id]);

        /* the release factory may add its own labels - count them from the table */
        $masters = Label::query()->whereNull('parent_label_id')->count();
        $noLogin = Label::query()->whereNull('parent_label_id')->whereNull('user_id')->count();

        $this->actingAs($super)->get('/super-admin/label-hierarchy')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('totals.masters', $masters)
            ->where('totals.sub_labels', 2)
            ->where('totals.masters_with_sub', 1)
            ->where('totals.sub_without_share', 1)
            ->where('totals.masters_without_login', $noLogin)
            ->where('labels', function ($rows) use ($master, $noShare, $withShare, $owner) {
                $rows = collect($rows);
                $m = $rows->firstWhere('id', $master->id);

                return $m['owner_email'] === $owner->email
                    && $m['releases_count'] === 2
                    && $m['children_count'] === 2
                    && $rows->firstWhere('id', $noShare->id)['share_missing'] === true
                    && $rows->firstWhere('id', $withShare->id)['share_missing'] === false;
            }));
    }

    public function test_renaming_a_master_needs_the_code_and_is_logged(): void
    {
        $super = $this->user('super_admin');
        $master = Label::factory()->create(['name' => 'Old Name Music', 'status' => 'active']);

        $send = fn () => $this->actingAs($super)->put('/super-admin/label-hierarchy/'.$master->id, [
            'name' => 'New Name Music', 'status' => 'active',
        ]);

        $this->viaActionOtp($super, $send)->assertSessionHasNoErrors();
        $this->assertSame('New Name Music', $master->fresh()->name);
        $this->assertTrue(DB::table('audit_logs')->where('action', 'level.updated')->where('auditable_id', $master->id)->exists());
    }

    public function test_editing_a_sub_label_name_keeps_its_share_date(): void
    {
        $super = $this->user('super_admin');
        $master = Label::factory()->create(['name' => 'Master Tunes']);
        $child = Label::factory()->create(['name' => 'Child Tunes', 'parent_label_id' => $master->id]);
        $this->share($master, $child, 30, $super, '2026-01-01');

        $this->actingAs($super)->put('/super-admin/label-hierarchy/'.$child->id, [
            'name' => 'Child Tunes Renamed', 'parent_label_id' => $master->id, 'status' => 'active', 'revenue_share_percent' => 30,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Saved "Child Tunes Renamed": name changed.');

        $row = LabelRevenueShare::query()->where('beneficiary_id', $child->id)->first();
        $this->assertSame('2026-01-01', substr((string) $row->effective_from, 0, 10));
        $this->assertEquals(30, (float) $row->revenue_share_percent);
    }
}
