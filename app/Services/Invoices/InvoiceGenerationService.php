<?php

namespace App\Services\Invoices;

use App\Models\Finance\PayoutProfile;
use App\Models\User;
use App\Services\V2\WithdrawalTaxDocumentPolicyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceGenerationService
{
    public function __construct(
        private WithdrawalTaxDocumentPolicyService $taxPolicy
    ) {
    }

    public function generateFromWithdrawal(
        int $withdrawalId,
        ?int $userId = null,
        ?float $gstPercentage = null,
        ?float $tdsPercentage = null
    ): array {
        return DB::transaction(function () use (
            $withdrawalId,
            $userId
        ) {
            $withdrawal = DB::table('withdrawals')
                ->leftJoin(
                    'labels',
                    'labels.id',
                    '=',
                    'withdrawals.label_id'
                )
                ->where(
                    'withdrawals.id',
                    $withdrawalId
                )
                ->select([
                    'withdrawals.*',
                    'labels.name as label_name',
                ])
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                throw new RuntimeException(
                    'Withdrawal request not found.'
                );
            }

            if ($withdrawal->status !== 'paid') {
                throw new RuntimeException(
                    'Financial document can only be generated for a paid withdrawal.'
                );
            }

            $existing = DB::table('invoices')
                ->where(
                    'withdrawal_id',
                    $withdrawal->id
                )
                ->first();

            if ($existing) {
                return [
                    'created' => false,
                    'invoice_id' =>
                        (int) $existing->id,
                    'invoice_number' =>
                        $existing->invoice_number,
                    'message' =>
                        'Financial document already exists.',
                ];
            }

            $payee = User::query()
                ->find($withdrawal->user_id);

            if (!$payee) {
                throw new RuntimeException(
                    'Withdrawal payee account not found.'
                );
            }

            $profile = PayoutProfile::query()
                ->where(
                    'user_id',
                    $payee->id
                )
                ->first();

            $policy = $this->taxPolicy->policyFor(
                $payee,
                $profile
            );

            if (!$policy['company_ready']) {
                throw new RuntimeException(
                    'Company billing configuration is incomplete.'
                );
            }

            if (!$policy['profile_exists']) {
                throw new RuntimeException(
                    'Payee payout profile is missing.'
                );
            }

            if (!$policy['kyc_ready']) {
                throw new RuntimeException(
                    'Payee KYC must be verified before generating the financial document.'
                );
            }

            $subtotal = round(
                (float) $withdrawal->amount,
                8
            );

            /*
             * GST registration and TDS are independent.
             *
             * GST:
             * Applied only when the payee has GSTIN.
             *
             * TDS:
             * Applied according to configured payout
             * deduction policy independently of GST.
             */

            $gstPercentage =
                $policy['gst_registered']
                    ? max(
                        0,
                        min(
                            100,
                            (float) $policy[
                                'configured_gst_percent'
                            ]
                        )
                    )
                    : 0.0;

            $tdsPercentage = max(
                0,
                min(
                    100,
                    (float) $policy[
                        'configured_tds_percent'
                    ]
                )
            );

            $gstAmount = round(
                $subtotal
                * $gstPercentage
                / 100,
                8
            );

            $tdsAmount = round(
                $subtotal
                * $tdsPercentage
                / 100,
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
                ->whereYear(
                    'invoice_date',
                    now()->year
                )
                ->lockForUpdate()
                ->count() + 1;

            $prefix = 'INV';

            $invoiceNumber = sprintf(
                '%s-%s-%06d',
                $prefix,
                now()->format('Ym'),
                $sequence
            );

            $billingName =
                $policy['payee'][
                    'account_holder_name'
                ]
                ?? $withdrawal->label_name
                ?? $payee->name
                ?? 'Payee';

            $billingAddress =
                $policy['payee']['address']
                ?? null;

            $gstin =
                $policy['payee']['gst_number']
                ?? null;

            $taxSnapshot = [
                'tax_treatment' =>
                    $policy['tax_treatment'],

                'document_type' =>
                    $policy['document_type'],

                'gst_registered' =>
                    $policy['gst_registered'],

                'gst_percentage' =>
                    $gstPercentage,

                'tds_percentage' =>
                    $tdsPercentage,

                'payee' =>
                    $policy['payee'],

                'company' =>
                    $policy['company'],
            ];

            $invoiceId = DB::table('invoices')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),

                    'invoice_number' =>
                        $invoiceNumber,

                    'withdrawal_id' =>
                        $withdrawal->id,

                    'wallet_id' =>
                        $withdrawal->wallet_id,

                    'label_id' =>
                        $withdrawal->label_id,

                    'user_id' =>
                        $withdrawal->user_id,

                    'invoice_type' =>
                        $policy['document_type'],

                    'tax_treatment' =>
                        $policy['tax_treatment'],

                    'invoice_date' =>
                        now()->toDateString(),

                    'due_date' =>
                        now()->toDateString(),

                    'currency' =>
                        $withdrawal->currency
                        ?: 'INR',

                    'subtotal' =>
                        $subtotal,

                    'gst_percentage' =>
                        $gstPercentage,

                    'gst_amount' =>
                        $gstAmount,

                    'tds_percentage' =>
                        $tdsPercentage,

                    'tds_amount' =>
                        $tdsAmount,

                    'tax_amount' =>
                        $tdsAmount,

                    'total_amount' =>
                        $totalAmount,

                    'net_payable' =>
                        $netPayable,

                    'status' =>
                        'issued',

                    'billing_name' =>
                        $billingName,

                    'billing_email' =>
                        $payee->email,

                    'billing_phone' =>
                        null,

                    'billing_address' =>
                        $billingAddress,

                    'gstin' =>
                        $gstin,

                    'pan' =>
                        null,

                    'billing_details' =>
                        json_encode(
                            $policy['payee']
                        ),

                    'company_details' =>
                        json_encode(
                            $policy['company']
                        ),

                    'tax_snapshot' =>
                        json_encode(
                            $taxSnapshot
                        ),

                    'notes' =>
                        sprintf(
                            'Royalty payout document against withdrawal %s',
                            $withdrawal
                                ->withdrawal_number
                        ),

                    'metadata' =>
                        json_encode([
                            'withdrawal_number' =>
                                $withdrawal
                                    ->withdrawal_number,

                            'payment_reference' =>
                                $withdrawal
                                    ->payment_reference,

                            'payment_method' =>
                                $withdrawal
                                    ->payment_method,

                            'paid_at' =>
                                $withdrawal
                                    ->paid_at,

                            'generated_by_policy' =>
                                true,
                        ]),

                    'document_generated_at' =>
                        now(),

                    'created_by' =>
                        $userId,

                    'updated_by' =>
                        $userId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            DB::table('invoice_items')
                ->insert([
                    'invoice_id' =>
                        $invoiceId,

                    'description' =>
                        sprintf(
                            'Royalty payout - %s',
                            $withdrawal
                                ->withdrawal_number
                        ),

                    'quantity' =>
                        1,

                    'rate' =>
                        $subtotal,

                    'amount' =>
                        $subtotal,

                    'reference_type' =>
                        'withdrawal',

                    'reference_id' =>
                        $withdrawal->id,

                    'metadata' =>
                        json_encode([
                            'label_name' =>
                                $withdrawal
                                    ->label_name,

                            'document_type' =>
                                $policy[
                                    'document_type'
                                ],

                            'tax_treatment' =>
                                $policy[
                                    'tax_treatment'
                                ],
                        ]),

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            return [
                'created' => true,

                'invoice_id' =>
                    (int) $invoiceId,

                'invoice_number' =>
                    $invoiceNumber,

                'document_type' =>
                    $policy['document_type'],

                'tax_treatment' =>
                    $policy['tax_treatment'],

                'subtotal' =>
                    $subtotal,

                'gst_percentage' =>
                    $gstPercentage,

                'gst_amount' =>
                    $gstAmount,

                'tds_percentage' =>
                    $tdsPercentage,

                'tds_amount' =>
                    $tdsAmount,

                'total_amount' =>
                    $totalAmount,

                'net_payable' =>
                    $netPayable,
            ];
        });
    }
}
