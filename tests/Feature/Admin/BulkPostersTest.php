<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
 * MIXXTUNE_BULK_POSTERS_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class BulkPostersTest extends TestCase
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

    public function test_bulk_posters_match_exactly_and_save_with_audit(): void
    {
        Storage::fake('public');

        $super = $this->user('super_admin');
        $label = Label::factory()->create(['created_by' => $super->id]);
        $artist = Artist::factory()->create(['label_id' => $label->id, 'created_by' => $super->id]);
        $make = fn (array $extra) => Release::factory()->create(array_merge([
            'artist_id' => $artist->id, 'label_id' => $label->id, 'status' => 'live', 'created_by' => $super->id,
        ], $extra));

        $noPoster = $make(['upc' => '0890528501234', 'catalog_number' => 'GEN-A1', 'artwork_path' => null]);
        $byCat = $make(['upc' => null, 'catalog_number' => 'GEN-B2', 'artwork_path' => null]);
        $hasPoster = $make(['upc' => '777777777777', 'catalog_number' => 'GEN-C3', 'artwork_path' => 'releases/artwork/old-gen.jpg']);
        $draft = $make(['upc' => '666666666666', 'catalog_number' => 'GEN-D4', 'status' => 'draft', 'artwork_path' => null]);

        $this->actingAs($super)->get('/super-admin/posters')->assertOk();

        $match = $this->actingAs($super)->postJson('/super-admin/posters/match', [
            'names' => ['890528501234.jpg', 'gen-b2.png', '777777777777.jpg', '666666666666.jpg', 'nothing-here.jpg', 'GEN-A1.gif'],
        ])->assertOk()->json('results');

        $this->assertSame(['ready', 'ready', 'has_poster', 'draft', 'not_found', 'invalid'], array_column($match, 'result'));
        $this->assertSame($noPoster->id, $match[0]['release']['id']);

        $this->actingAs($super)->post('/super-admin/posters/upload', [
            'poster' => UploadedFile::fake()->image('890528501234.jpg', 3000, 3000),
        ])->assertOk()->assertJsonPath('result', 'saved');

        $path = $noPoster->fresh()->artwork_path;
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'release.admin_edit')->count());

        /* too small: refused, nothing saved */
        $this->actingAs($super)->post('/super-admin/posters/upload', [
            'poster' => UploadedFile::fake()->image('GEN-B2.jpg', 300, 300),
        ])->assertOk()->assertJsonPath('result', 'invalid');
        $this->assertNull($byCat->fresh()->artwork_path);

        /* existing poster kept unless replace */
        $this->actingAs($super)->post('/super-admin/posters/upload', [
            'poster' => UploadedFile::fake()->image('777777777777.jpg', 3000, 3000),
        ])->assertJsonPath('result', 'has_poster');
        $this->assertSame('releases/artwork/old-gen.jpg', $hasPoster->fresh()->artwork_path);

        $this->actingAs($super)->post('/super-admin/posters/upload', [
            'poster' => UploadedFile::fake()->image('777777777777.jpg', 3000, 3000), 'replace' => '1',
        ])->assertJsonPath('result', 'saved');
        $this->assertNotSame('releases/artwork/old-gen.jpg', $hasPoster->fresh()->artwork_path);

        /* drafts never change */
        $this->actingAs($super)->post('/super-admin/posters/upload', [
            'poster' => UploadedFile::fake()->image('666666666666.jpg', 3000, 3000), 'replace' => '1',
        ])->assertJsonPath('result', 'draft');
        $this->assertNull($draft->fresh()->artwork_path);

        $csv = $this->actingAs($super)->get('/super-admin/posters/missing.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('GEN-B2', $csv);
        $this->assertStringNotContainsString('GEN-A1', $csv);
        $this->assertStringNotContainsString('GEN-D4', $csv);

        $this->actingAs($this->user('admin'))->get('/super-admin/posters')->assertForbidden();
    }
}
