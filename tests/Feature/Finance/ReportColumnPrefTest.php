<?php

namespace Tests\Feature\Finance;

use App\Models\Core\Label;
use App\Models\Finance\ReportColumnPref;
use App\Models\Finance\RevenueImport;
use App\Models\Finance\RevenueMatch;
use App\Models\Finance\RevenueRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/* W137 MIXXTUNE_REPORT_COLUMN_PREFS */
class ReportColumnPrefTest extends TestCase
{
    use RefreshDatabase;

    private function makeData(): array
    {
        $super = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $labelUser = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create([
            'user_id' => $labelUser->id,
            'created_by' => $labelUser->id,
            'parent_label_id' => null,
            'status' => 'active',
            'royalty_share_percentage' => 80,
        ]);

        $import = RevenueImport::create([
            'dsp_name' => 'Test DSP',
            'statement_month' => '2026-06-01',
            'currency' => 'INR',
            'original_filename' => 'june.csv',
            'status' => 'completed',
            'total_rows' => 1,
            'imported_by' => $super->id,
        ]);

        $row = RevenueRow::create([
            'revenue_import_id' => $import->id,
            'net_amount' => 100,
            'gross_amount' => 100,
            'raw_data' => ['A' => 'a1', 'B' => 'b1', 'C' => 'c1', 'Track' => 'My Song', 'Amount' => '100'],
            'match_status' => 'matched',
        ]);

        RevenueMatch::create(['revenue_row_id' => $row->id, 'label_id' => $label->id, 'match_type' => 'isrc', 'is_confirmed' => true]);

        return compact('super', 'labelUser', 'label', 'import', 'row');
    }

    public function test_super_admin_saves_the_default_shared_columns(): void
    {
        ['super' => $super] = $this->makeData();

        $this->actingAs($super)
            ->post(route('single.admin.revenue-reports.columns.default'), [
                'columns' => ['A', 'Track'],
                'show_label_rate' => true,
                'show_earning' => true,
            ])
            ->assertSessionHasNoErrors();

        $pref = ReportColumnPref::query()->whereNull('label_id')->first();
        $this->assertNotNull($pref);
        $this->assertSame(['A', 'Track'], $pref->columns);
    }

    public function test_label_sees_only_shared_columns_plus_earning(): void
    {
        $d = $this->makeData();
        ReportColumnPref::create(['label_id' => null, 'columns' => ['A', 'Track'], 'show_label_rate' => true, 'show_earning' => true]);

        $this->actingAs($d['labelUser'])
            ->get(route('v2.label.revenue-reports.show', $d['import']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('V2/Label/RevenueReports/Show')
                ->where('header', ['A', 'Track', 'Label Rate', 'Earning'])
                ->where('rows.0.Earning', '80.00')
                ->where('rows.0.A', 'a1')
                ->where('totalEarning', fn ($v) => abs((float) $v - 80.0) < 0.01));
    }

    public function test_export_csv_has_only_shared_columns_and_earning(): void
    {
        $d = $this->makeData();
        ReportColumnPref::create(['label_id' => null, 'columns' => ['A', 'Track'], 'show_label_rate' => true, 'show_earning' => true]);

        $res = $this->actingAs($d['labelUser'])->get(route('v2.label.revenue-reports.export', $d['import']));
        $res->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringContainsString('Earning', $csv);
        $this->assertStringContainsString('80.00', $csv);
        $this->assertStringContainsString('My Song', $csv);
        $this->assertStringNotContainsString('b1', $csv);  // column B is hidden
    }

    public function test_per_label_override_narrows_further(): void
    {
        $d = $this->makeData();
        ReportColumnPref::create(['label_id' => null, 'columns' => ['A', 'Track'], 'show_label_rate' => true, 'show_earning' => true]);
        ReportColumnPref::create(['label_id' => $d['label']->id, 'columns' => ['A'], 'show_label_rate' => false, 'show_earning' => true]);

        $this->actingAs($d['labelUser'])
            ->get(route('v2.label.revenue-reports.show', $d['import']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('header', ['A', 'Earning']));
    }
}
