<?php

namespace Tests\Feature\Catalogue;

use App\Models\Catalogue\LegacyCatalogueImport;
use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* MIXXTUNE_LEGACY_IMPORT_SAFE_V1 (W56) */
class LegacyCatalogueImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function upload(User $super, string $csv)
    {
        return $this->actingAs($super)->post('/super-admin/legacy-catalogue-imports', [
            'catalogue_file' => UploadedFile::fake()->createWithContent('old.csv', $csv),
        ]);
    }

    public function test_same_upc_on_two_rows_is_blocked_at_check_time(): void
    {
        $super = $this->super();
        $csv = "label_name,release_title,release_type,primary_artist,track_title,isrc,upc\n"
            ."Old Label,Album,album,Singer,Song One,INAAA2600001,890000000017\n"
            ."Old Label,Album,album,Singer,Song Two,INAAA2600002,890000000017\n";

        $this->upload($super, $csv)->assertRedirect();
        $import = LegacyCatalogueImport::query()->latest('id')->first();

        $this->assertSame(1, (int) $import->blocked_rows);
        $this->assertStringContainsString('more than one row', json_encode($import->rows()->where('validation_status', 'blocked')->first()->validation_errors));
    }

    public function test_missing_columns_show_a_message_not_an_error_page(): void
    {
        $this->upload($this->super(), "title,artist\nA,B\n")->assertSessionHasErrors('catalogue_file');
    }

    public function test_ambiguous_label_name_is_never_created_again(): void
    {
        $super = $this->super();
        Label::factory()->create(['name' => 'XYZ Music']);
        Label::factory()->create(['name' => 'XYZ  Music ']);
        $csv = "label_name,release_title,release_type,primary_artist,track_title,isrc,upc\n"
            ."XYZ Music,Song,single,Singer,Song,INAAA2600009,890000000024\n";

        $this->upload($super, $csv)->assertRedirect();
        $import = LegacyCatalogueImport::query()->latest('id')->first();
        $before = Label::query()->count();

        $this->actingAs($super)->post('/super-admin/legacy-catalogue-imports/'.$import->id.'/approve-entities')->assertSessionHasNoErrors();
        $this->actingAs($super)->post('/super-admin/legacy-catalogue-imports/'.$import->id.'/approve-entities');

        $this->assertSame($before, Label::query()->count());
        $this->assertSame('blocked', $import->rows()->first()->validation_status);
    }

    public function test_approve_is_refused_for_an_imported_file(): void
    {
        $super = $this->super();
        $import = LegacyCatalogueImport::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(), 'original_filename' => 'x.csv', 'stored_path' => 'x',
            'status' => 'imported', 'total_rows' => 1, 'ready_rows' => 1, 'blocked_rows' => 0, 'warning_rows' => 0,
            'imported_rows' => 1, 'failed_rows' => 0, 'uploaded_by' => $super->id,
        ]);

        $this->actingAs($super)->post('/super-admin/legacy-catalogue-imports/'.$import->id.'/approve-entities')
            ->assertSessionHasErrors('approve');
    }
}
