<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_RELEASE_QUEUE_V2 (W63) */
class ReleaseQueueTest extends TestCase
{
    use RefreshDatabase;

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

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_queue_has_label_names_counts_and_page_links(): void
    {
        $super = $this->super();
        $label = Label::factory()->create(['name' => 'Queue Label Music']);
        Release::factory()->count(26)->create(['status' => 'submitted', 'label_id' => $label->id, 'submitted_at' => now()->subDays(2)]);
        Release::factory()->create(['status' => 'approved', 'label_id' => $label->id]);

        $this->actingAs($super)->get('/admin/release-reviews?status=submitted')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('counts.submitted', 26)
            ->where('counts.approved', 1)
            ->where('releases.total', 26)
            ->where('releases.data.0.label_name', 'Queue Label Music')
            ->where('releases.links', fn ($links) => count($links) > 3));
    }

    public function test_review_page_offers_next_release_and_locks_live_releases(): void
    {
        $super = $this->super();
        $first = Release::factory()->create(['status' => 'submitted', 'submitted_at' => now()->subDays(3)]);
        $second = Release::factory()->create(['status' => 'submitted', 'submitted_at' => now()->subDay()]);
        $live = Release::factory()->create(['status' => 'live']);

        $this->actingAs($super)->get('/admin/release-reviews/'.$first->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('nextInQueue.id', $second->id)
            ->where('canEditMetadata', true)
            ->where('deliveryUrl', null));

        $this->actingAs($super)->get('/admin/release-reviews/'.$live->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('canEditMetadata', false)
            ->where('deliveryUrl', '/admin/distribution/'.$live->id));
    }

    public function test_metadata_edit_says_what_changed_and_is_logged(): void
    {
        $super = $this->super();
        $release = Release::factory()->create(['status' => 'submitted', 'title' => 'Old Title']);
        $track = Track::factory()->create(['release_id' => $release->id, 'track_number' => 1]);

        $this->actingAs($super)->patch('/admin/release-reviews/'.$release->id.'/metadata', ['title' => 'New Title'])
            ->assertSessionHas('success', 'Saved: title.');
        $this->assertSame('New Title', $release->fresh()->title);
        $this->assertTrue(DB::table('audit_logs')->where('action', 'release.metadata_edited_in_review')->where('auditable_id', $release->id)->exists());

        $this->actingAs($super)->patch('/admin/release-reviews/'.$release->id.'/metadata', ['title' => 'New Title'])
            ->assertSessionHas('success', 'Nothing changed.');

        /* an emptied number is sent as null now, not 0 */
        $this->actingAs($super)->patch('/admin/release-reviews/'.$release->id.'/tracks/'.$track->id.'/metadata', ['disc_number' => null])
            ->assertSessionHasNoErrors();
    }

    public function test_hindi_label_filter_shows_only_that_label(): void
    {
        $super = $this->super();
        $a = Label::factory()->create(['name' => 'सनातन संगीत']);
        $b = Label::factory()->create(['name' => 'भक्ति धारा']);
        Release::factory()->create(['status' => 'submitted', 'label_id' => $a->id]);
        Release::factory()->create(['status' => 'submitted', 'label_id' => $b->id]);

        $this->actingAs($super)->get('/releases?label_id='.$a->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('releases.total', 1)
            ->where('releases.data.0.label_id', $a->id));
    }
}
