<?php

namespace Tests\Feature\Delivery;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\DistributionStore;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* MIXXTUNE_DELIVERY_SAFE_V1 (W58) */
class DistributionSafetyTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (!\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            \Illuminate\Support\Facades\Schema::create('audit_logs', function ($table) {
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

    private function release(User $by, array $x = []): Release
    {
        $label = Label::factory()->create();

        return Release::factory()->create(array_merge([
            'label_id' => $label->id, 'status' => 'approved', 'catalog_number' => 'MXT-'.uniqid(),
            'created_by' => $by->id, 'updated_by' => $by->id,
        ], $x));
    }

    private function store(): DistributionStore
    {
        return DistributionStore::query()->create(['name' => 'Store '.uniqid(), 'slug' => 'store-'.uniqid(), 'is_active' => true]);
    }

    private function delivery(Release $release, DistributionStore $store, string $status, User $by): ReleaseStoreDelivery
    {
        return ReleaseStoreDelivery::query()->create([
            'public_id' => (string) Str::ulid(), 'release_id' => $release->id, 'distribution_store_id' => $store->id,
            'status' => $status, 'created_by' => $by->id, 'updated_by' => $by->id,
        ]);
    }

    public function test_start_is_refused_without_poster_and_tracks_and_says_why(): void
    {
        $super = $this->user('super_admin');
        $this->store();
        $release = $this->release($super, ['artwork_path' => null, 'upc' => null]);

        $this->actingAs($super)->get('/admin/distribution/'.$release->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Delivery/Show')
            ->where('readiness.blocking.0', 'The release has no tracks.')
            ->where('canStart', true));

        $this->actingAs($super)->post('/admin/distribution/'.$release->id.'/initialise')->assertSessionHasErrors('delivery');
        $this->assertNull($release->fresh()->upc);
        $this->assertSame(0, ReleaseStoreDelivery::query()->count());
    }

    public function test_old_catalogue_never_gets_a_generated_upc(): void
    {
        $super = $this->user('super_admin');
        $this->store();
        $release = $this->release($super, ['catalog_number' => 'WP-123', 'upc' => null, 'artwork_path' => 'a.jpg']);
        Track::factory()->withAudio()->create(['release_id' => $release->id, 'isrc' => 'INAAA2600001', 'created_by' => $super->id, 'updated_by' => $super->id]);

        $this->actingAs($super)->post('/admin/distribution/'.$release->id.'/initialise')->assertSessionHasErrors('delivery');
        $this->assertNull($release->fresh()->upc);

        $this->actingAs($super)->postJson('/admin/releases/'.$release->id.'/upc/generate')->assertStatus(422);
        $this->assertNull($release->fresh()->upc);
    }

    public function test_mark_live_needs_the_code_and_is_kept_in_history(): void
    {
        $super = $this->user('super_admin');
        $store = $this->store();
        $release = $this->release($super, ['status' => 'processing']);
        $d = $this->delivery($release, $store, 'delivered', $super);

        $send = fn () => $this->actingAs($super)->patch('/admin/distribution/deliveries/'.$d->id, ['status' => 'live', 'external_reference' => 'SPOT-1']);

        $this->viaActionOtp($super, $send)->assertSessionHasNoErrors();
        $this->assertSame('live', $d->fresh()->status);

        $this->actingAs($super)->get('/admin/distribution/'.$release->id)->assertInertia(fn (Assert $p) => $p
            ->has('history', 1)
            ->where('history.0.from', 'delivered')
            ->where('history.0.to', 'live'));
    }

    public function test_bulk_changes_all_or_nothing(): void
    {
        $super = $this->user('super_admin');
        $release = $this->release($super, ['status' => 'processing']);
        $a = $this->delivery($release, $this->store(), 'processing', $super);
        $b = $this->delivery($release, $this->store(), 'live', $super);

        $this->actingAs($super)->patch('/admin/distribution/'.$release->id.'/bulk', [
            'delivery_ids' => [$a->id, $b->id], 'status' => 'delivered',
        ])->assertSessionHasErrors('status');

        $this->assertSame('processing', $a->fresh()->status);
    }

    public function test_index_has_approved_all_and_store_failed_views(): void
    {
        $super = $this->user('super_admin');
        $release = $this->release($super, ['status' => 'processing']);
        $this->delivery($release, $this->store(), 'failed', $super);
        $this->release($super);

        $this->actingAs($super)->get('/admin/distribution?status=store_failed')->assertInertia(fn (Assert $p) => $p
            ->where('counts.approved', 1)
            ->where('counts.store_failed', 1)
            ->has('releases.data', 1)
            ->where('releases.data.0.stores_failed', 1));

        $this->actingAs($super)->get('/admin/distribution?status=all')->assertInertia(fn (Assert $p) => $p->has('releases.data', 2));
    }

    public function test_used_store_cannot_be_deleted_and_admin_cannot_change_stores(): void
    {
        $super = $this->user('super_admin');
        $store = $this->store();
        $this->delivery($this->release($super), $store, 'live', $super);

        $this->actingAs($super)->delete('/admin/stores/'.$store->id)->assertSessionHasErrors('store');
        $this->assertNotNull($store->fresh());

        $this->actingAs($this->user('admin'))->post('/admin/stores/'.$store->id.'/toggle')->assertForbidden();
    }

    public function test_isrc_with_and_without_dashes_is_one_code(): void
    {
        $super = $this->user('super_admin');
        $release = $this->release($super);
        Track::factory()->create(['release_id' => $release->id, 'track_number' => 1, 'isrc' => 'INMXT2600001', 'created_by' => $super->id, 'updated_by' => $super->id]);
        $other = Track::factory()->create(['release_id' => $release->id, 'track_number' => 2, 'isrc' => null, 'created_by' => $super->id, 'updated_by' => $super->id]);

        $this->actingAs($super)->postJson('/admin/tracks/'.$other->id.'/isrc/assign', ['isrc' => 'IN-MXT-26-00001'])
            ->assertStatus(422);

        /* generated code skips the number already used without dashes */
        $this->actingAs($super)->postJson('/admin/tracks/'.$other->id.'/isrc/generate', ['reference_year' => 2026])->assertOk();
        $this->assertNotSame('INMXT2600001', str_replace('-', '', (string) $other->fresh()->isrc));
    }
}
