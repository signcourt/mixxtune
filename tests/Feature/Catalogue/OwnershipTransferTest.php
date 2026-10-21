<?php

namespace Tests\Feature\Catalogue;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* MIXXTUNE_OWNERSHIP_SAFE_V1 (W56) */
class OwnershipTransferTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        /* production has this table from the schema dump; the sqlite test DB is built from migrations */
        if (!Schema::hasTable('catalogue_transfers')) {
            Schema::create('catalogue_transfers', function (Blueprint $t) {
                $t->id();
                $t->string('public_id', 30)->unique();
                $t->string('transfer_type', 40);
                $t->string('entity_type', 40);
                $t->unsignedBigInteger('entity_id');
                $t->string('from_owner_type', 20)->nullable();
                $t->unsignedBigInteger('from_owner_id')->nullable();
                $t->string('to_owner_type', 20)->nullable();
                $t->unsignedBigInteger('to_owner_id')->nullable();
                $t->unsignedBigInteger('from_user_id')->nullable();
                $t->unsignedBigInteger('to_user_id')->nullable();
                $t->string('revenue_scope', 40)->default('future_only');
                $t->text('reason')->nullable();
                $t->json('old_values')->nullable();
                $t->json('new_values')->nullable();
                $t->unsignedBigInteger('transferred_by')->nullable();
                $t->timestamp('transferred_at')->nullable();
                $t->timestamps();
            });
        }
    }

    private function user(string $role, string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => $status, 'email_verified_at' => now()]);
    }

    public function test_only_super_admin_reaches_the_page_and_search(): void
    {
        $this->actingAs($this->user('artist'))->get('/v2/admin/ownership')->assertForbidden();
        $this->actingAs($this->user('label'))->getJson('/v2/admin/ownership/search?type=release&q=a')->assertForbidden();
    }

    public function test_search_covers_the_whole_catalogue(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id, 'name' => 'Omega Records']);
        Release::factory()->create(['label_id' => $label->id, 'title' => 'Very Old Song', 'created_by' => $super->id, 'updated_by' => $super->id]);

        $this->actingAs($super)->getJson('/v2/admin/ownership/search?type=release&q=very%20old')
            ->assertOk()->assertJsonPath('results.0.title', 'Very Old Song');

        $this->actingAs($super)->get('/v2/admin/ownership')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Ownership/Index')->missing('releases')->missing('users')->has('history.data', 0));
    }

    public function test_label_login_must_be_an_active_label_account_and_needs_the_code(): void
    {
        $super = $this->user('super_admin');
        $old = $this->user('label');
        $label = Label::factory()->create(['user_id' => $old->id]);
        $artistLogin = $this->user('artist');
        $suspended = $this->user('label', 'suspended');
        $new = $this->user('label');

        $post = fn (int $userId) => $this->actingAs($super)->post('/v2/admin/ownership/label-user', [
            'label_id' => $label->id, 'user_id' => $userId, 'reason' => 'Catalogue sold to new owner',
        ]);

        $this->unlockActionOtp($super, 'ownership.label:'.$label->id.':'.$artistLogin->id);
        $post($artistLogin->id)->assertSessionHasErrors('user_id');
        $this->unlockActionOtp($super, 'ownership.label:'.$label->id.':'.$suspended->id);
        $post($suspended->id)->assertSessionHasErrors('user_id');
        $this->assertSame($old->id, (int) $label->fresh()->user_id);

        $this->viaActionOtp($super, fn () => $post($new->id))->assertSessionHasNoErrors();
        $this->assertSame($new->id, (int) $label->fresh()->user_id);

        $this->actingAs($super)->get('/v2/admin/ownership?section=history')->assertInertia(fn (Assert $p) => $p
            ->has('history.data', 1)
            ->where('history.data.0.from', $old->email)
            ->where('history.data.0.to', $new->email)
            ->where('history.data.0.reason', 'Catalogue sold to new owner'));
    }

    public function test_release_move_needs_reason_and_refuses_the_same_owner(): void
    {
        $super = $this->user('super_admin');
        $a = Label::factory()->create(['user_id' => $this->user('label')->id, 'name' => 'A Music']);
        $b = Label::factory()->create(['user_id' => $this->user('label')->id, 'name' => 'B Music']);
        $release = Release::factory()->create(['label_id' => $a->id, 'created_by' => $super->id, 'updated_by' => $super->id]);

        $this->actingAs($super)->post('/v2/admin/ownership/release', [
            'release_id' => $release->id, 'owner_type' => 'label', 'owner_id' => $b->id, 'scope' => 'future_only', 'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->unlockActionOtp($super, 'ownership.release:'.$release->id.':label:'.$a->id);
        $this->actingAs($super)->post('/v2/admin/ownership/release', [
            'release_id' => $release->id, 'owner_type' => 'label', 'owner_id' => $a->id, 'scope' => 'future_only', 'reason' => 'Same owner test',
        ])->assertSessionHasErrors('owner_id');

        $this->unlockActionOtp($super, 'ownership.release:'.$release->id.':label:'.$b->id);
        $this->actingAs($super)->post('/v2/admin/ownership/release', [
            'release_id' => $release->id, 'owner_type' => 'label', 'owner_id' => $b->id, 'scope' => 'future_only', 'reason' => 'Label B bought it',
        ])->assertSessionHasNoErrors();
        $this->assertSame($b->id, (int) $release->fresh()->label_id);

        $this->actingAs($super)->get('/v2/admin/ownership?section=history')->assertInertia(fn (Assert $p) => $p
            ->where('history.data.0.from', 'Label: A Music')
            ->where('history.data.0.to', 'Label: B Music'));
    }

    public function test_track_moved_onto_a_used_number_goes_to_the_end(): void
    {
        $super = $this->user('super_admin');
        $label = Label::factory()->create(['user_id' => $this->user('label')->id]);
        $one = Release::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'updated_by' => $super->id]);
        $two = Release::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'updated_by' => $super->id]);
        $moving = Track::factory()->create(['release_id' => $one->id, 'track_number' => 1, 'created_by' => $super->id, 'updated_by' => $super->id]);
        Track::factory()->create(['release_id' => $two->id, 'track_number' => 1, 'created_by' => $super->id, 'updated_by' => $super->id]);

        $this->actingAs($super)->getJson('/v2/admin/ownership/preview?kind=track&track_id='.$moving->id.'&release_id='.$two->id)
            ->assertOk()->assertJsonPath('ok', true);

        $this->unlockActionOtp($super, 'ownership.track:'.$moving->id.':'.$two->id);
        $this->actingAs($super)->post('/v2/admin/ownership/track', [
            'track_id' => $moving->id, 'release_id' => $two->id, 'scope' => 'future_only', 'reason' => 'Wrong album fixed',
        ])->assertSessionHasNoErrors();

        $fresh = $moving->fresh();
        $this->assertSame($two->id, (int) $fresh->release_id);
        $this->assertSame(2, (int) $fresh->track_number);
        $this->assertSame(1, DB::table('catalogue_transfers')->count());
    }

    public function test_rows_still_in_review_move_and_get_the_new_label_percent(): void
    {
        $super = $this->user('super_admin');
        $a = Label::factory()->create(['user_id' => $this->user('label')->id, 'revenue_share_percentage' => 50]);
        $b = Label::factory()->create(['user_id' => $this->user('label')->id, 'revenue_share_percentage' => 80]);
        $release = Release::factory()->create(['label_id' => $a->id, 'created_by' => $super->id, 'updated_by' => $super->id]);

        $row = function (string $fin) use ($release, $a) {
            $import = DB::table('report_imports')->insertGetId([
                'public_id' => (string) \Illuminate\Support\Str::ulid(), 'original_filename' => $fin.'.csv', 'stored_path' => 'x',
                'status' => 'completed', 'financial_status' => $fin, 'reporting_month' => '2026-07', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('report_rows')->insertGetId([
                'report_import_id' => $import, 'row_hash' => hash('sha256', $fin), 'track_title' => 'Song', 'track_artist' => 'X',
                'isrc' => 'INAAA2600001', 'platform' => 'Spotify', 'currency' => 'INR', 'sale_type' => 'Stream', 'sale_month' => '2026-07',
                'streams' => 1, 'sale_units' => 1, 'collected_revenue' => 100, 'label_rate' => 0.5, 'earnings' => 50,
                'release_id' => $release->id, 'label_id' => $a->id, 'mapping_status' => 'mapped', 'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $review = $row('review_pending');
        $published = $row('published');

        $this->unlockActionOtp($super, 'ownership.release:'.$release->id.':label:'.$b->id);
        $this->actingAs($super)->post('/v2/admin/ownership/release', [
            'release_id' => $release->id, 'owner_type' => 'label', 'owner_id' => $b->id, 'scope' => 'pending_and_future', 'reason' => 'Moved with July review rows',
        ])->assertSessionHasNoErrors();

        $r = DB::table('report_rows')->find($review);
        $this->assertSame($b->id, (int) $r->label_id);
        $this->assertEqualsWithDelta(80.0, (float) $r->earnings, 0.0001);

        $p = DB::table('report_rows')->find($published);
        $this->assertSame($a->id, (int) $p->label_id);
        $this->assertEqualsWithDelta(50.0, (float) $p->earnings, 0.0001);
    }
}
