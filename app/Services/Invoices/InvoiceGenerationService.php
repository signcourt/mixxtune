<?php

namespace App\Services\Invoices;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceGenerationService
{
    public function generateFromWithdrawal(
        int $withdrawalId,
        ?int $userId = null,
        float $gstPercentage = 0,
        float $tdsPercentage = 0
    ): array {
        return DB::transaction(function () use (
            $withdrawalId,
            $userId,
            $gstPercentage,
            $tdsPercentage
        ) {
            $withdrawal = DB::table('withdrawals')
                ->leftJoin(
                    'labels',
                    'labels.id',
                    '=',
                    'withdrawals.label_id'
                )
                ->where('withdrawals.id', $withdrawalId)
                ->select([
                    'withdrawals.*',
                    'labels.name as label_name',
                ])
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                throw new RuntimeException(
                    'Withdrawal request nahi mili.'
                );
            }

            if ($withdrawal->status !== 'paid') {
                throw new RuntimeException(
                    'Sirf paid withdrawal ka invoice ban sakta hai.'
                );
            }

            $existing = DB::table('invoices')
                ->where('withdrawal_id', $withdrawal->id)
                ->first();

            if ($existing) {
                return [
                    'created' => false,
                    'invoice_id' => (int) $existing->id,
                    'invoice_number' => $existing->invoice_number,
                    'message' => 'Invoice pehle se bana hua hai.',
                ];
            }

            $subtotal = round(
                (float) $withdrawal->amount,
                8
            );

            $gstPercentage = max(
                0,
                min(100, $gstPercentage)
            );

            $tdsPercentage = max(
                0,
                min(100, $tdsPercentage)
            );

            $gstAmount = round(
                $subtotal * $gstPercentage / 100,
                8
            );

            $tdsAmount = round(
                $subtotal * $tdsPercentage / 100,
                8
            );

            $totalAmount = round(
                $subtotal + $gstAmount,
                8
            );

            $netPayable = round(
                $totalAmount - $tdsAmount,
                8
            );

            $sequence = DB::table('invoices')
                ->whereYear('invoice_date', now()->year)
                ->lockForUpdate()
                ->count() + 1;

            $invoiceNumber = sprintf(
                'INV-%s-%06d',
                now()->format('Ym'),
                $sequence
            );

            $invoiceId = DB::table('invoices')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'invoice_number' => $invoiceNumber,
                'withdrawal_id' => $withdrawal->id,
                'wallet_id' => $withdrawal->wallet_id,
                'label_id' => $withdrawal->label_id,
                'user_id' => $withdrawal->user_id,
                'invoice_type' => 'withdrawal',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'currency' => $withdrawal->currency ?: 'INR',
                'subtotal' => $subtotal,
                'gst_percentage' => $gstPercentage,
                'gst_amount' => $gstAmount,
                'tds_percentage' => $tdsPercentage,
                'tds_amount' => $tdsAmount,
                'total_amount' => $totalAmount,
                'net_payable' => $netPayable,
                'status' => 'issued',
                'billing_name' =>
                    $withdrawal->label_name ?: 'Unknown Label',
                'billing_email' => null,
                'billing_phone' => null,
                'billing_address' => null,
                'gstin' => null,
                'pan' => null,
                'notes' => sprintf(
                    'Invoice against withdrawal %s',
                    $withdrawal->withdrawal_number
                ),
                'metadata' => json_encode([
                    'withdrawal_number' =>
                        $withdrawal->withdrawal_number,
                    'payment_reference' =>
                        $withdrawal->payment_reference,
                    'payment_method' =>
                        $withdrawal->payment_method,
                    'paid_at' => $withdrawal->paid_at,
                ]),
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'description' => sprintf(
                    'Royalty withdrawal payment - %s',
                    $withdrawal->withdrawal_number
                ),
                'quantity' => 1,
                'rate' => $subtotal,
                'amount' => $subtotal,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id,
                'metadata' => json_encode([
                    'label_name' => $withdrawal->label_name,
                    'payment_reference' =>
                        $withdrawal->payment_reference,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'created' => true,
                'invoice_id' => (int) $invoiceId,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal,
                'gst_amount' => $gstAmount,
                'tds_amount' => $tdsAmount,
                'net_payable' => $netPayable,
            ];
        });
    }
}
