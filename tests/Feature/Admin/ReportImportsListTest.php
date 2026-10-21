<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_REPORT_IMPORTS_V2 (W47) */
class ReportImportsListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_list_has_review_step_short_error_and_real_upload_limit(): void
    {
        DB::table('report_imports')->insert([
            'public_id' => (string) Str::ulid(), 'original_filename' => 'bad.csv', 'stored_path' => 'x',
            'status' => 'failed', 'error_message' => str_repeat('E', 5000), 'reporting_month' => '2026-07',
            'financial_status' => 'review_pending', 'column_map' => json_encode(['huge' => str_repeat('x', 1000)]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $limit = min(
            \App\Services\V2\PlatformRules::reportMaxMb(),
            $this->toMb(ini_get('upload_max_filesize')),
            $this->toMb(ini_get('post_max_size'))
        );

        $this->actingAs($this->super())->get('/v2/admin/reports/imports')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Reports/Imports')
            ->where('imports.data.0.financial_status', 'review_pending')
            ->where('imports.data.0.error_short', str_repeat('E', 300))
            ->missing('imports.data.0.column_map')
            ->missing('imports.data.0.error_message')
            ->where('uploadLimitMb', max(1, $limit)));
    }

    public function test_too_big_file_gets_a_clear_message(): void
    {
        $limit = (int) $this->app->call(function () {
            $c = app(\App\Http\Controllers\V2\Admin\ReportImportController::class);
            $m = new \ReflectionMethod($c, 'uploadLimitMb');
            $m->setAccessible(true);

            return $m->invoke($c);
        });

        $file = UploadedFile::fake()->create('big.csv', ($limit * 1024) + 10, 'text/csv');

        $this->actingAs($this->super())
            ->post('/v2/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $file])
            ->assertSessionHasErrors(['report_file' => 'The file is bigger than '.$limit.' MB, the most this server accepts. Split the report into smaller CSV files (same reporting month) and upload each.']);
    }

    private function toMb($v): int
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '0' || $v === '-1') {
            return PHP_INT_MAX;
        }
        $n = (float) $v;

        return (int) floor(match (strtolower(substr($v, -1))) {
            'g' => $n * 1024, 'm' => $n, 'k' => $n / 1024, default => $n / 1048576,
        });
    }
}
