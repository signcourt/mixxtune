<?php

namespace Tests\Feature\Releases;

use App\Mail\ActionOtpMail;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W120 MIXXTUNE_RELEASE_TRASH (generated test data only) */
class ReleaseTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Storage::fake('public');
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

    private function withCode(User $u, callable $press)
    {
        $before = Mail::sent(ActionOtpMail::class)->count();
        $r = $press();
        if (Mail::sent(ActionOtpMail::class)->count() > $before) {
            $code = null;
            Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) {
                $code = $m->otp;

                return true;
            });
            $this->actingAs($u)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();
            $r = $press();
        }

        return $r;
    }

    private function trialRelease(string $status = 'approved'): array
    {
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);
        Storage::disk('public')->put('releases/artwork/trial.jpg', 'x');
        Storage::disk('public')->put('releases/audio/trial.wav', 'x');
        $release = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'MXT-TR-'.uniqid(), 'title' => 'Trial Song', 'status' => $status, 'upc' => '890000000016', 'artwork_path' => 'releases/artwork/trial.jpg', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $track = Track::factory()->create(['release_id' => $release->id, 'title' => 'Trial Track', 'isrc' => 'INABC2600009', 'audio_path' => 'releases/audio/trial.wav']);
        DB::table('upc_codes')->insert(['public_id' => (string) Str::ulid(), 'code' => '890000000016', 'prefix' => '890000', 'status' => 'assigned', 'release_id' => $release->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('catalogue_items')->insert(['public_id' => (string) Str::ulid(), 'release_id' => $release->id, 'artist_id' => $artist->id, 'label_id' => $label->id, 'title' => 'Trial Song', 'is_visible' => true, 'created_at' => now(), 'updated_at' => now()]);

        return [$owner, $release, $track];
    }

    public function test_trash_hides_everywhere_restore_brings_back_purge_removes_all(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        [$owner, $release, $track] = $this->trialRelease();

        /* only the Super Admin */
        $this->actingAs($owner)->post("/admin/releases/{$release->id}/trash")->assertForbidden();

        $this->withCode($super, fn () => $this->actingAs($super)->post("/admin/releases/{$release->id}/trash", ['reason' => 'trial upload']))
            ->assertRedirect('/admin/release-trash');
        $this->assertNull(Release::query()->find($release->id));
        $this->assertNull(Track::query()->find($track->id));
        $this->assertSame(0, DB::table('catalogue_items')->where('release_id', $release->id)->count());
        $this->actingAs($owner)->get(route('v2.releases.index'))->assertInertia(fn (Assert $p) => $p->where('releases.total', 0));
        $this->actingAs($super)->get('/admin/release-trash')->assertInertia(fn (Assert $p) => $p->component('V2/Admin/ReleaseTrash/Index')->has('rows', 1)->where('rows.0.reason', 'trial upload')->where('rows.0.days_left', 90));

        /* restore: all back */
        $row = DB::table('release_trash')->first();
        $this->actingAs($super)->post("/admin/release-trash/{$row->id}/restore")->assertSessionHasNoErrors();
        $this->assertNotNull(Release::query()->find($release->id));
        $this->assertNotNull(Track::query()->find($track->id));
        $this->assertSame(1, DB::table('catalogue_items')->where('release_id', $release->id)->count());

        /* trash again, then delete for good */
        $this->withCode($super, fn () => $this->actingAs($super)->post("/admin/releases/{$release->id}/trash"));
        $row = DB::table('release_trash')->first();
        $this->withCode($super, fn () => $this->actingAs($super)->post("/admin/release-trash/{$row->id}/purge"))->assertSessionHasNoErrors();
        $this->assertNull(Release::withTrashed()->find($release->id));
        $this->assertNull(Track::withTrashed()->find($track->id));
        $this->assertSame(0, DB::table('release_trash')->count());
        $this->assertSame(0, DB::table('catalogue_items')->where('release_id', $release->id)->count());
        Storage::disk('public')->assertMissing('releases/artwork/trial.jpg');
        Storage::disk('public')->assertMissing('releases/audio/trial.wav');
        /* approved, never reached stores: the UPC goes back to the list */
        $this->assertSame('available', DB::table('upc_codes')->where('code', '890000000016')->value('status'));
        $this->assertNull(DB::table('upc_codes')->where('code', '890000000016')->value('release_id'));
    }

    public function test_money_on_it_stops_the_delete_and_trash_empties_after_90_days(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        [$owner, $release, $track] = $this->trialRelease('live');
        DB::table('royalty_ledgers')->insert(['public_id' => (string) Str::ulid(), 'release_id' => $release->id, 'track_id' => $track->id, 'statement_month' => '2026-07', 'store_name' => 'Test Store', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($super)->post("/admin/releases/{$release->id}/trash")->assertSessionHasErrors('release');
        $this->assertNotNull(Release::query()->find($release->id));

        DB::table('royalty_ledgers')->delete();
        $this->withCode($super, fn () => $this->actingAs($super)->post("/admin/releases/{$release->id}/trash"))->assertSessionHasNoErrors();
        $this->artisan('releases:purge-trash')->assertSuccessful();
        $this->assertSame(1, DB::table('release_trash')->count()); /* not yet */

        $this->travel(91)->days();
        $this->artisan('releases:purge-trash')->assertSuccessful();
        $this->assertSame(0, DB::table('release_trash')->count());
        $this->assertNull(Release::withTrashed()->find($release->id));
        /* it was live: the UPC is kept aside, never given out again */
        $this->assertSame('retired', DB::table('upc_codes')->where('code', '890000000016')->value('status'));
    }
}
