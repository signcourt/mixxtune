<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_FINANCE_PAGE_V2 (W55) */
class FinancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function statement(array $extra): RoyaltyStatement
    {
        return RoyaltyStatement::query()->create(array_merge([
            'public_id' => (string) Str::ulid(), 'statement_month' => '2026-07', 'currency' => 'INR',
            'gross_earnings' => 1000, 'commission_amount' => 100, 'tax_amount' => 0, 'other_deductions' => 0,
            'net_payable' => 900, 'status' => 'pending',
        ], $extra));
    }

    private function importPaid(RoyaltyStatement $statement, int $labelId): void
    {
        $importId = (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => 'july.csv', 'stored_path' => 'x',
            'status' => 'completed', 'financial_status' => 'published', 'reporting_month' => '2026-07',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $publicationId = DB::table('report_import_publications')->insertGetId([
            'report_import_id' => $importId, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('report_import_postings')->insert([
            'publication_id' => $publicationId, 'report_import_id' => $importId, 'owner_type' => 'label',
            'owner_id' => $labelId, 'currency' => 'INR', 'posting_key' => 'w55-'.$statement->id,
            'status' => 'posted', 'royalty_statement_id' => $statement->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_pending_filter_excludes_import_statements_and_labels_are_named(): void
    {
        $super = $this->user('super_admin');
        $label = Label::factory()->create(['user_id' => $this->user('label')->id, 'name' => 'Omega Records']);
        $paid = $this->statement(['label_id' => $label->id]);
        $this->importPaid($paid, $label->id);
        $this->statement(['label_id' => $label->id, 'statement_month' => '2026-08']);

        $this->actingAs($super)->get('/v2/admin/finance?status=pending')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Finance/Index')
            ->where('statements.total', 1)
            ->where('statements.data.0.statement_month', '2026-08')
            ->where('statements.data.0.owner_type', 'label')
            ->where('statements.data.0.label_name', 'Omega Records'));

        $this->actingAs($super)->get('/v2/admin/finance?status=import_paid')->assertInertia(fn (Assert $p) => $p
            ->where('statements.total', 1)
            ->where('statements.data.0.statement_month', '2026-07'));
    }

    public function test_generate_with_nothing_published_says_so(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->from('/v2/admin/finance')
            ->post('/v2/admin/finance/statements/generate', ['month' => '2026-08', 'commission_percent' => 0])
            ->assertRedirect('/v2/admin/finance')
            ->assertSessionHas('success', 'Nothing to generate for 2026-08: no published report has revenue for that month yet.');
    }
}
