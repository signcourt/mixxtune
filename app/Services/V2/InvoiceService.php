<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\Invoice;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function generateFromStatement(
        RoyaltyStatement $statement,
        User $admin
    ): Invoice {
        $existing = Invoice::query()
            ->where(
                'royalty_statement_id',
                $statement->id
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        $owner = $this->ownerUser(
            $statement
        );

        abort_unless(
            $owner,
            422,
            'Royalty statement owner user is missing.'
        );

        return DB::transaction(
            function () use (
                $statement,
                $owner,
                $admin
            ) {
                $subtotal =
                    (float) $statement
                        ->net_payable;

                $taxAmount = 0;
                $tdsAmount =
                    (float) $statement
                        ->tax_amount;

                $total =
                    $subtotal
                    + $taxAmount
                    - $tdsAmount;

                $invoice = Invoice::query()
                    ->create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'invoice_number' =>
                            $this->nextNumber(),

                        'user_id' =>
                            $owner->id,

                        'royalty_statement_id' =>
                            $statement->id,

                        'invoice_type' =>
                            'royalty',

                        'invoice_date' =>
                            now()->toDateString(),

                        'due_date' =>
                            now()
                                ->addDays(15)
                                ->toDateString(),

                        'currency' =>
                            $statement->currency,

                        'subtotal' =>
                            $subtotal,

                        'tax_amount' =>
                            $taxAmount,

                        'tds_amount' =>
                            $tdsAmount,

                        'total_amount' =>
                            $total,

                        'status' =>
                            $statement->status ===
                            'paid'
                                ? 'paid'
                                : 'generated',

                        'billing_details' => [
                            'name' =>
                                $owner->name,

                            'email' =>
                                $owner->email,
                        ],

                        'company_details' => [
                            'name' =>
                                config(
                                    'app.name',
                                    'Backstage Mixx Tune'
                                ),

                            'address' =>
                                'India',

                            'email' =>
                                config(
                                    'mail.from.address'
                                ),
                        ],

                        'notes' =>
                            "Royalty invoice for {$statement->statement_month}",

                        'created_by' =>
                            $admin->id,
                    ]);

                $invoice->items()->create([
                    'description' =>
                        "Royalty earnings for {$statement->statement_month}",

                    'quantity' => 1,

                    'rate' =>
                        $subtotal,

                    'amount' =>
                        $subtotal,

                    'meta' => [
                        'statement_id' =>
                            $statement->id,
                    ],
                ]);

                return $invoice->fresh([
                    'items',
                    'user',
                    'statement',
                ]);
            }
        );
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');

        $lastId = Invoice::query()
            ->whereYear(
                'invoice_date',
                $year
            )
            ->max('id') ?? 0;

        return sprintf(
            'INV-%s-%06d',
            $year,
            $lastId + 1
        );
    }

    private function ownerUser(
        RoyaltyStatement $statement
    ): ?User {
        if ($statement->artist_id) {
            $userId = Artist::query()
                ->where(
                    'id',
                    $statement->artist_id
                )
                ->value('user_id');

            return $userId
                ? User::query()->find(
                    $userId
                )
                : null;
        }

        if ($statement->label_id) {
            $userId = Label::query()
                ->where(
                    'id',
                    $statement->label_id
                )
                ->value('user_id');

            return $userId
                ? User::query()->find(
                    $userId
                )
                : null;
        }

        return null;
    }
}
