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
    /*
     * MIXXTUNE_INVOICES_V2 (W42)
     * - the PDF "Net Payable" read invoices.net_payable, which was never
     *   filled for statement invoices -> every PDF showed 0.00;
     * - tax_amount was taken off AGAIN from net_payable (net is already
     *   after tax) -> total too low when a statement had TDS;
     * - number = next free number (was highest id + 1);
     * - two clicks at once could create two invoices (statement row locked);
     * - label, billing name / e-mail, company details from Master Settings
     *   and the gross / commission / deductions breakdown are saved.
     */
    public function generateFromStatement(
        RoyaltyStatement $statement,
        User $admin
    ): Invoice {
        $existing = Invoice::query()
            ->where('royalty_statement_id', $statement->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $owner = $this->ownerUser($statement);

        abort_unless($owner, 422, 'Royalty statement owner user is missing.');

        return DB::transaction(function () use ($statement, $owner, $admin) {
            $locked = RoyaltyStatement::query()->whereKey($statement->id)->lockForUpdate()->firstOrFail();

            $again = Invoice::query()->where('royalty_statement_id', $locked->id)->first();
            if ($again) {
                return $again;
            }

            $net = round((float) $locked->net_payable, 2);
            $tax = round((float) $locked->tax_amount, 2);
            $gross = round((float) $locked->gross_earnings, 2);
            $commission = round((float) $locked->commission_amount, 2);
            $other = round((float) $locked->other_deductions, 2);
            $subtotal = round($net + $tax, 2);
            $month = $this->monthLabel($locked->statement_month);
            $company = $this->company();

            $breakdown = [];
            if ($gross > 0) {
                $breakdown[] = 'gross '.number_format($gross, 2);
            }
            if ($commission > 0) {
                $breakdown[] = 'commission -'.number_format($commission, 2);
            }
            if ($other > 0) {
                $breakdown[] = 'deductions / advance recovery -'.number_format($other, 2);
            }

            $invoice = new Invoice();
            $invoice->forceFill(array_filter([
                'public_id' => (string) Str::ulid(),
                'invoice_number' => InvoiceNumber::next(now()->format('Y')),
                'user_id' => $owner->id,
                'royalty_statement_id' => $locked->id,
                'label_id' => $this->has('label_id') ? $this->labelIdFor($locked) : null,
                'invoice_type' => 'royalty',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'currency' => $locked->currency ?: 'INR',
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'tds_amount' => $tax,
                'tds_percentage' => $this->has('tds_percentage') && $subtotal > 0 && $tax > 0 ? round($tax / $subtotal * 100, 4) : null,
                'total_amount' => $subtotal,
                'net_payable' => $this->has('net_payable') ? $net : null,
                'status' => $locked->status === 'paid' ? 'paid' : 'generated',
                'billing_name' => $this->has('billing_name') ? ($owner->name ?: $owner->email) : null,
                'billing_email' => $this->has('billing_email') ? $owner->email : null,
                'billing_details' => ['name' => $owner->name, 'email' => $owner->email],
                'company_details' => $company,
                'notes' => "Royalty invoice for {$locked->statement_month} ({$month})",
                'created_by' => $admin->id,
            ], fn ($v) => $v !== null));
            $invoice->save();

            $invoice->items()->create([
                'description' => mb_substr(
                    "Royalty earnings for {$locked->statement_month} ({$month})"
                        .($breakdown ? ': '.implode(', ', $breakdown) : ''),
                    0,
                    255
                ),
                'quantity' => 1,
                'rate' => $subtotal,
                'amount' => $subtotal,
                'meta' => [
                    'statement_id' => $locked->id,
                    'gross' => $gross,
                    'commission' => $commission,
                    'other_deductions' => $other,
                    'tax' => $tax,
                    'net_payable' => $net,
                ],
            ]);

            return $invoice->fresh(['items', 'user', 'statement']);
        });
    }

    private function has(string $column): bool
    {
        static $cols = null;
        $cols ??= array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('invoices'));

        return isset($cols[$column]);
    }

    private function labelIdFor(RoyaltyStatement $statement): ?int
    {
        if ($statement->label_id) {
            return (int) $statement->label_id;
        }

        $id = $statement->artist_id
            ? Artist::query()->whereKey($statement->artist_id)->value('label_id')
            : null;

        return $id ? (int) $id : null;
    }

    public function monthLabel(?string $month): string
    {
        if (! $month) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse(strlen($month) === 7 ? $month.'-01' : $month)->format('F Y');
        } catch (\Throwable $e) {
            return (string) $month;
        }
    }

    /** Company block from Master Settings (falls back to the app name). */
    private function company(): array
    {
        $rows = DB::table('system_settings')->where('group', 'company')->pluck('value', 'key')->all();
        $pick = fn ($key) => isset($rows['company.'.$key]) && $rows['company.'.$key] !== '' ? $rows['company.'.$key] : ($rows[$key] ?? null);

        return array_filter([
            'name' => $pick('name') ?: config('app.name', 'Backstage Mixx Tune'),
            'legal_name' => $pick('legal_name'),
            'address' => $pick('address') ?: 'India',
            'gst_number' => $pick('gst_number') ?: $pick('gstin'),
            'pan' => $pick('pan_number'),
            'email' => $pick('email') ?: config('mail.from.address'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function nextNumber(): string
    {
        return InvoiceNumber::next(now()->format('Y'));
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
