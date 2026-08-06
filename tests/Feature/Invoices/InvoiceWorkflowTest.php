<?php

namespace Tests\Feature\Invoices;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\Invoice;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use App\Services\V2\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $owner = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'created_by' => $owner->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $owner->id,
            'label_id' => $label->id,
            'created_by' => $owner->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $statement = RoyaltyStatement::query()->create([
            'public_id' => (string) Str::ulid(),
            'artist_id' => $artist->id,
            'label_id' => null,
            'statement_month' => '2026-07',
            'currency' => 'INR',
            'gross_earnings' => 1000,
            'commission_amount' => 100,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 900,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        return [
            $owner,
            $label,
            $artist,
            $admin,
            $statement,
        ];
    }

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    public function test_invoice_is_generated_from_statement(): void
    {
        [
            $owner,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $this->assertInstanceOf(
            Invoice::class,
            $invoice
        );

        $this->assertSame(
            $owner->id,
            $invoice->user_id
        );

        $this->assertSame(
            $statement->id,
            $invoice->royalty_statement_id
        );

        $this->assertSame(
            'royalty',
            $invoice->invoice_type
        );
    }

    public function test_invoice_generation_is_idempotent(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $first = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $second = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            Invoice::query()
                ->where(
                    'royalty_statement_id',
                    $statement->id
                )
                ->count()
        );
    }

    public function test_invoice_number_has_expected_format(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{4}-\d{6}$/',
            $invoice->invoice_number
        );
    }

    public function test_invoice_item_is_created(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $this->assertCount(
            1,
            $invoice->items
        );

        $item = $invoice->items->first();

        $this->assertSame(
            $invoice->id,
            $item->invoice_id
        );

        $this->assertStringContainsString(
            $statement->statement_month,
            $item->description
        );

        $this->assertSame(
            $statement->id,
            (int) data_get(
                $item->meta,
                'statement_id'
            )
        );
    }

    public function test_invoice_preserves_currency_and_amount(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $this->assertSame(
            'INR',
            $invoice->currency
        );

        $this->assertSame(
            '900.00000000',
            $invoice->subtotal
        );

        $this->assertSame(
            '900.00000000',
            $invoice->total_amount
        );
    }

    public function test_billing_and_company_details_are_saved(): void
    {
        [
            $owner,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $this->assertSame(
            $owner->name,
            data_get(
                $invoice->billing_details,
                'name'
            )
        );

        $this->assertSame(
            $owner->email,
            data_get(
                $invoice->billing_details,
                'email'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $invoice->company_details,
                'name'
            )
        );
    }

    public function test_super_admin_can_generate_invoice_through_route(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $response = $this
            ->actingAs($admin)
            ->post(
                route(
                    'v2.admin.invoices.generate',
                    $statement
                )
            );

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas(
            'invoices',
            [
                'royalty_statement_id' =>
                    $statement->id,
            ]
        );
    }

    public function test_artist_cannot_generate_invoice(): void
    {
        [
            $owner,
            ,
            ,
            ,
            $statement,
        ] = $this->context();

        $response = $this
            ->actingAs($owner)
            ->post(
                route(
                    'v2.admin.invoices.generate',
                    $statement
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'invoices',
            [
                'royalty_statement_id' =>
                    $statement->id,
            ]
        );
    }

    public function test_owner_can_download_own_invoice(): void
    {
        [
            $owner,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $response = $this
            ->actingAs($owner)
            ->get(
                route(
                    'v2.invoices.download',
                    $invoice
                )
            );

        $response->assertOk();

        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get(
                'content-type'
            )
        );
    }

    public function test_other_artist_cannot_download_invoice(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $other = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($other)
            ->get(
                route(
                    'v2.invoices.download',
                    $invoice
                )
            );

        $response->assertForbidden();
    }

    public function test_super_admin_can_download_any_invoice(): void
    {
        [
            ,
            ,
            ,
            $admin,
            $statement,
        ] = $this->context();

        $invoice = $this->service()
            ->generateFromStatement(
                $statement,
                $admin
            );

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'v2.invoices.download',
                    $invoice
                )
            );

        $response->assertOk();
    }

    public function test_statement_without_owner_is_rejected(): void
    {
        [
            ,
            ,
            ,
            $admin,
            ,
        ] = $this->context();

        $statement = RoyaltyStatement::query()->create([
            'public_id' => (string) Str::ulid(),
            'artist_id' => null,
            'label_id' => null,
            'statement_month' => '2026-08',
            'currency' => 'INR',
            'gross_earnings' => 500,
            'commission_amount' => 0,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 500,
            'status' => 'approved',
        ]);

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        $this->service()->generateFromStatement(
            $statement,
            $admin
        );
    }
}
