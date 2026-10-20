<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use App\Services\V2\AudioValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * MIXXTUNE_SUPER_ADMIN_FULL_EDIT
 * All data is generated inside the SQLite :memory: test database.
 */
class SuperAdminFullEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* audit_logs lives in the MySQL schema dump only; create it for SQLite. */
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

    private function context(string $status = 'live'): array
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['created_by' => $super->id, 'user_id' => $owner->id, 'name' => 'Gen Edit Records']);
        $artist = Artist::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'account_status' => 'active', 'stage_name' => 'Gen Singer']);
        $release = Release::factory()->create([
            'artist_id' => $artist->id, 'label_id' => $label->id, 'status' => $status, 'created_by' => $owner->id,
            'title' => 'Old Gen Title', 'upc' => '111111111111', 'artwork_path' => 'releases/artwork/old-gen.jpg',
            'wizard_step' => 5, 'completion_percentage' => 100,
        ]);
        DB::table('catalogue_items')->insert([
            'public_id' => (string) Str::ulid(), 'release_id' => $release->id, 'artist_id' => $artist->id, 'label_id' => $label->id,
            'title' => 'Old Gen Title', 'label_name' => $label->name, 'upc' => '111111111111', 'release_status' => $status,
            'track_count' => 0, 'is_visible' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$super, $owner, $label, $artist, $release];
    }

    private function payload(Artist $artist, Label $label, array $extra = []): array
    {
        return array_merge([
            'catalog_number' => 'GEN-EDIT-1', 'release_type' => 'single', 'title' => 'New Gen Title',
            'artist_id' => $artist->id, 'label_id' => $label->id,
            'primary_artists' => [['name' => 'Gen Singer']], 'featuring_artists' => [],
            'language' => 'Hindi', 'primary_genre' => 'Devotional', 'sub_genre' => 'Bhajan',
            'generate_upc' => false, 'upc' => '111111111111',
            'digital_release_date' => '2026-01-10',
            'copyright_owner' => 'Gen Owner', 'copyright_year' => 2026,
            'phonographic_owner' => 'Gen Owner', 'phonographic_year' => 2026,
        ], $extra);
    }

    private function wav(string $name = 'gen.wav'): UploadedFile
    {
        $data = str_repeat("\0\0", 800);
        $header = 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'.'fmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($data));

        return UploadedFile::fake()->createWithContent($name, $header.$data);
    }

    public function test_super_admin_can_edit_live_release_details_poster_and_upc(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('releases/artwork/old-gen.jpg', 'old');
        [$super, , $label, $artist, $release] = $this->context('live');

        $this->actingAs($super)->get(route('v2.releases.edit', $release))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('adminEdit', true));

        $this->actingAs($super)->get(route('v2.releases.show', $release))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('release.admin_edit', true)->where('release.editable', false));

        $this->actingAs($super)->patch(route('v2.releases.update', $release), $this->payload($artist, $label, [
            'upc' => '222222222222',
            'artwork' => UploadedFile::fake()->image('cover.jpg', 3000, 3000),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $release->refresh();
        $this->assertSame('live', $release->status);
        $this->assertSame('New Gen Title', $release->title);
        $this->assertSame('222222222222', $release->upc);
        $this->assertSame(5, (int) $release->wizard_step);
        $this->assertNotSame('releases/artwork/old-gen.jpg', $release->artwork_path);
        Storage::disk('public')->assertExists('releases/artwork/old-gen.jpg');

        $item = DB::table('catalogue_items')->where('release_id', $release->id)->first();
        $this->assertSame('New Gen Title', $item->title);
        $this->assertSame($release->artwork_path, $item->artwork_path);
        $this->assertSame('222222222222', $item->upc);

        $audit = DB::table('audit_logs')->where('action', 'release.admin_edit')->first();
        $this->assertNotNull($audit);
        $this->assertSame($super->id, (int) $audit->user_id);
        $this->assertStringContainsString('Old Gen Title', (string) $audit->old_values);
        $this->assertStringContainsString('222222222222', (string) $audit->new_values);
    }

    public function test_super_admin_can_save_only_a_poster_on_an_old_release_with_missing_fields(): void
    {
        Storage::fake('public');
        [$super, , $label, $artist, $release] = $this->context('live');
        $release->update(['sub_genre' => null, 'copyright_owner' => null, 'phonographic_owner' => null, 'language' => null, 'release_type' => 'compilation']);

        $this->actingAs($super)->post(route('v2.releases.update', $release), [
            '_method' => 'patch', 'title' => 'Old Gen Title', 'artist_id' => $artist->id, 'label_id' => $label->id,
            'catalog_number' => '', 'release_type' => 'compilation', 'primary_artists' => [['name' => 'Gen Singer']],
            'generate_upc' => false, 'upc' => '111111111111', 'sub_genre' => '', 'copyright_owner' => '',
            'phonographic_owner' => '', 'language' => '', 'digital_release_date' => '2019-05-01',
            'artwork' => UploadedFile::fake()->image('cover.jpg', 3000, 3000),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $release->refresh();
        $this->assertNotSame('releases/artwork/old-gen.jpg', $release->artwork_path);
        $this->assertStringStartsWith('releases/artwork/', $release->artwork_path);
        $this->assertSame('compilation', $release->release_type);
        $this->assertNotEmpty($release->catalog_number);
        $this->assertSame($release->artwork_path, DB::table('catalogue_items')->where('release_id', $release->id)->value('artwork_path'));
    }

    public function test_duplicate_upc_is_refused(): void
    {
        [$super, , $label, $artist, $release] = $this->context('approved');
        Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'status' => 'live', 'created_by' => $super->id, 'upc' => '333333333333']);

        $this->actingAs($super)->patch(route('v2.releases.update', $release), $this->payload($artist, $label, ['upc' => '333333333333']))
            ->assertSessionHasErrors('upc');

        $this->assertSame('111111111111', $release->fresh()->upc);
    }

    public function test_owner_still_cannot_edit_live_release(): void
    {
        [, $owner, $label, $artist, $release] = $this->context('live');

        $this->actingAs($owner)->patch(route('v2.releases.update', $release), $this->payload($artist, $label))
            ->assertForbidden();

        $this->assertSame('Old Gen Title', $release->fresh()->title);
    }

    public function test_super_admin_can_replace_audio_add_and_delete_tracks_on_live_release(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tracks/audio/old-gen.wav', 'old');
        /* ffprobe is not part of the test run; the audio checker itself is covered elsewhere. */
        $this->mock(AudioValidationService::class, fn ($mock) => $mock->shouldReceive('validate')->andReturn([]));
        [$super, , , , $release] = $this->context('delivered');
        $track = Track::factory()->create([
            'release_id' => $release->id, 'title' => 'Gen Track', 'primary_artist_name' => 'Gen Singer',
            'audio_path' => 'tracks/audio/old-gen.wav', 'isrc' => 'INGEN2600001', 'created_by' => $super->id,
        ]);

        $this->actingAs($super)->post(route('v2.release-tracks.update', $track), [
            '_method' => 'patch', 'title' => 'Gen Track Fixed', 'primary_artist_name' => 'Gen Singer',
            'isrc' => 'INGEN2600009', 'audio' => $this->wav(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $track->refresh();
        $this->assertSame('Gen Track Fixed', $track->title);
        $this->assertSame('INGEN2600009', $track->isrc);
        $this->assertNotSame('tracks/audio/old-gen.wav', $track->audio_path);
        Storage::disk('public')->assertExists('tracks/audio/old-gen.wav');

        $this->actingAs($super)->post(route('v2.release-tracks.store', $release), [
            'title' => 'Gen Bonus', 'primary_artist_name' => 'Gen Singer', 'isrc' => '', 'audio' => $this->wav('bonus.wav'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $bonus = Track::query()->where('title', 'Gen Bonus')->firstOrFail();

        $this->actingAs($super)->delete(route('v2.release-tracks.destroy', $bonus))->assertRedirect();
        $this->assertSoftDeleted('tracks', ['id' => $bonus->id]);

        $this->assertSame('delivered', $release->fresh()->status);
        $this->assertSame(1, (int) DB::table('catalogue_items')->where('release_id', $release->id)->value('track_count'));
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'release.admin_edit')->count());
    }

    public function test_catalogue_list_gives_edit_access_only_to_super_admin(): void
    {
        [$super, $owner] = $this->context('live');

        $this->actingAs($super)->get('/v2/catalogue')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('role', 'super_admin')->has('catalogue.data.0.release_id'));

        $this->actingAs($owner)->get('/v2/catalogue')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('role', 'label'));
    }
}
