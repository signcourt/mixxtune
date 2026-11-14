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

            /*
             * MIXXTUNE_WITHDRAWAL_INVOICE_ON_REQUEST_V1: the invoice is raised as
             * soon as the owner requests a withdrawal (status 'issued'), and the
             * SAME invoice becomes final ('paid') when the payout is made. Only
             * a cancelled/rejected withdrawal gets no document.
             */
            if (! in_array($withdrawal->status, ['pending', 'approved', 'processing', 'paid'], true)) {
                throw new RuntimeException(
                    'A financial document is not generated for a '.$withdrawal->status.' withdrawal.'
                );
            }
            $isPaid = $withdrawal->status === 'paid';

            $existing = DB::table('invoices')
                ->where(
                    'withdrawal_id',
                    $withdrawal->id
                )
                ->first();

            if ($existing) {
                // If the payout is now genuinely paid (and the payee is still
                // payout-ready — same KYC/company gate as a fresh document), mark
                // the already-raised invoice final. If the payee is no longer
                // ready (e.g. KYC re-opened), leave it 'issued' and let the
                // existing "paid but not booked" safeguards stand.
                if ($isPaid && $existing->status !== 'paid') {
                    // Booking a paid withdrawal still requires the payee to be
                    // payout-ready (KYC + company). If they are not (e.g. KYC was
                    // re-opened), we must NOT silently book it paid — throw so the
                    // caller keeps the "paid but not booked" safeguard, exactly as
                    // before request-time invoices existed.
                    $payeeNow = User::query()->find($withdrawal->user_id);
                    $profileNow = $payeeNow ? PayoutProfile::query()->where('user_id', $payeeNow->id)->first() : null;
                    $policyNow = $payeeNow ? $this->taxPolicy->policyFor($payeeNow, $profileNow) : null;
                    if (! $policyNow || ! ($policyNow['kyc_ready'] ?? false)) {
                        throw new RuntimeException('Payee KYC must be verified before booking the payout document.');
                    }
                    if (! ($policyNow['company_ready'] ?? false)) {
                        throw new RuntimeException('Company billing configuration is incomplete.');
                    }
                    DB::table('invoices')->where('id', $existing->id)->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

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
             * MIXXTUNE_TDS_V1: GST and TDS come from one shared calculator
             * (rate, no-PAN rate and yearly limit from Master Settings), so this
             * document matches the admin screen and the bank payout file.
             */
            $deduction = app(
                \App\Services\V2\PayoutDeductionService::class
            )->preview(
                $payee,
                $subtotal,
                (int) $withdrawal->id,
                $profile
            );

            /* W115: paid through Razorpay - the document shows exactly what was worked out and sent then */
            $sentDeduction = \Illuminate\Support\Facades\Schema::hasTable('bank_payouts')
                ? DB::table('bank_payouts')->where('withdrawal_id', $withdrawal->id)->where('status', 'processed')->orderByDesc('id')->value('deduction')
                : null;
            $sentDeduction = $sentDeduction ? json_decode((string) $sentDeduction, true) : null;
            if (is_array($sentDeduction) && isset($sentDeduction['net'], $sentDeduction['tds_amount'], $sentDeduction['gst_amount'], $sentDeduction['gst_percent'], $sentDeduction['tds_percent'])) {
                foreach (['gst_percent', 'gst_amount', 'tds_percent', 'tds_amount', 'net'] as $k) {
                    $deduction[$k] = $sentDeduction[$k];
                }
                $deduction['total'] = $sentDeduction['total'] ?? round($subtotal + (float) $sentDeduction['gst_amount'], 2);
            }

            $gstPercentage = $deduction['gst_percent'];
            $tdsPercentage = $deduction['tds_percent'];
            $gstAmount = $deduction['gst_amount'];
            $tdsAmount = $deduction['tds_amount'];
            $totalAmount = $deduction['total'];
            $netPayable = $deduction['net'];

            /*
             * MIXXTUNE_INVOICES_V2: next free number (was count + 1, could repeat).
             * MIXXTUNE_LABEL_INVOICE_PREFIX_V1 (W180): each label raises its own
             * series, so the number is prefixed from the label's name
             * (e.g. "Sanatan Records" -> SR-202611-000001). Falls back to the
             * global prefix when the withdrawal has no label name.
             */
            $invoicePrefix = \App\Services\V2\InvoiceNumber::labelPrefix(
                $withdrawal->label_name
                    ?? ($policy['payee']['account_holder_name'] ?? null)
            );
            $invoiceNumber = \App\Services\V2\InvoiceNumber::next(
                now()->format('Ym'),
                $invoicePrefix
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

                'tds' => [
                    'reason' => $deduction['tds_reason'],
                    'pan' => $deduction['pan'],
                    'financial_year' => $deduction['financial_year'],
                    'paid_this_year_before' => $deduction['paid_this_year_before'],
                    'threshold' => $deduction['threshold'],
                ],

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
                        $isPaid ? 'paid' : 'issued',

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
                        $deduction['pan'],

                    /*
                     * MIXXTUNE_WITHDRAWAL_INVOICE_BANK_V1: the payee's own bank
                     * details go onto the withdrawal invoice (full account
                     * number, IFSC, bank, UPI, holder, address) so the document
                     * reads like the bill the owner raises on us. The company's
                     * own bank account is never put here.
                     */
                    'billing_details' =>
                        json_encode(
                            array_merge(
                                (array) $policy['payee'],
                                $profile ? array_filter([
                                    'account_holder_name' => $profile->account_holder_name,
                                    'bank_account_number' => $profile->bank_account_number,
                                    'ifsc_code' => $profile->ifsc_code,
                                    'bank_name' => $profile->bank_name,
                                    'branch_name' => $profile->branch_name ?? null,
                                    'upi_id' => $profile->upi_id,
                                    'pan_number' => $profile->pan_number,
                                    'gst_number' => $profile->gst_number ?? null,
                                    'address' => $billingAddress,
                                ], fn ($v) => $v !== null && $v !== '') : []
                            )
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

                            /*
                             * MIXXTUNE_WITHDRAWAL_INVOICE_PERIOD_V1 (W180): the
                             * payout period shown on the invoice. A withdrawal
                             * is not tied to one statement month, so we label it
                             * by the month the request was made.
                             */
                            'payout_period' =>
                                ($withdrawal->created_at
                                    ? \Illuminate\Support\Carbon::parse($withdrawal->created_at)
                                    : now())->format('F Y'),

                            /*
                             * MIXXTUNE_LABEL_INVOICE_PREFIX_V1 (W180): remember
                             * the label name used for this invoice's series.
                             */
                            'label_name' =>
                                $withdrawal->label_name,
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
