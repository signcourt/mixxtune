<?php

namespace App\Services\V2;

use App\Models\Finance\PayoutProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawalTaxDocumentPolicyService
{
    public function policyFor(
        User $user,
        ?PayoutProfile $profile = null
    ): array {
        $profile ??=
            PayoutProfile::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        $company = $this->settings(
            'company'
        );

        $invoice = $this->settings(
            'invoice'
        );

        $companyReady =
            filled(
                $company[
                    'company.legal_name'
                ] ?? null
            )
            && filled(
                $company[
                    'company.address'
                ] ?? null
            );

        $hasGstin =
            $profile
            && filled(
                $profile->gst_number
            );

        /*
         * IMPORTANT:
         *
         * GST registration and TDS are separate concepts.
         *
         * GSTIN decides whether the payee is treated as
         * GST-registered for document presentation.
         *
         * TDS remains a separate deduction policy.
         */
        $taxTreatment =
            $hasGstin
                ? 'gst_registered'
                : 'non_gst';

        $documentType =
            $hasGstin
                ? 'gst_royalty_document'
                : 'royalty_payment_statement';

        return [
            'company_ready' =>
                $companyReady,

            'profile_exists' =>
                (bool) $profile,

            'kyc_ready' =>
                $profile?->kyc_status
                    === 'verified',

            'tax_treatment' =>
                $taxTreatment,

            'document_type' =>
                $documentType,

            'gst_registered' =>
                $hasGstin,

            /*
             * These are configuration values only.
             * They are NOT automatically applied merely
             * because they exist.
             */
            'configured_gst_percent' =>
                (float) (
                    $invoice[
                        'invoice.gst_percent'
                    ] ?? 0
                ),

            'configured_tds_percent' =>
                (float) (
                    $invoice[
                        'invoice.tds_percent'
                    ] ?? 0
                ),

            'company' => [
                'name' =>
                    $company[
                        'company.name'
                    ] ?? null,

                'legal_name' =>
                    $company[
                        'company.legal_name'
                    ] ?? null,

                'address' =>
                    $company[
                        'company.address'
                    ] ?? null,

                'gst_number' =>
                    $company[
                        'company.gst_number'
                    ] ?? null,

                'pan_number' =>
                    $company[
                        'company.pan_number'
                    ] ?? null,

                'email' =>
                    $company[
                        'company.email'
                    ] ?? null,

                'phone' =>
                    $company[
                        'company.phone'
                    ] ?? null,
            ],

            'payee' =>
                $profile
                    ? [
                        'account_holder_name' =>
                            $profile
                                ->account_holder_name,

                        'gst_number' =>
                            $profile
                                ->gst_number,

                        'masked_pan' =>
                            $profile
                                ->masked_pan,

                        'address' =>
                            $this
                                ->profileAddress(
                                    $profile
                                ),

                        'country_code' =>
                            $profile
                                ->country_code,

                        'kyc_status' =>
                            $profile
                                ->kyc_status,
                    ]
                    : null,
        ];
    }

    public function assertReady(
        User $user
    ): array {
        $policy =
            $this->policyFor(
                $user
            );

        $errors = [];

        if (
            !$policy[
                'company_ready'
            ]
        ) {
            $errors[] =
                'Company legal name and address are required.';
        }

        if (
            !$policy[
                'profile_exists'
            ]
        ) {
            $errors[] =
                'Payout profile is required.';
        }

        if (
            !$policy[
                'kyc_ready'
            ]
        ) {
            $errors[] =
                'Verified KYC is required.';
        }

        return [
            ...$policy,

            'ready' =>
                empty($errors),

            'errors' =>
                $errors,
        ];
    }

    private function settings(
        string $group
    ): array {
        return DB::table(
            'system_settings'
        )
            ->where(
                'group',
                $group
            )
            ->pluck(
                'value',
                'key'
            )
            ->all();
    }

    private function profileAddress(
        PayoutProfile $profile
    ): string {
        return collect([
            $profile
                ->address_line_1,

            $profile
                ->address_line_2,

            $profile
                ->city,

            $profile
                ->state,

            $profile
                ->postal_code,
        ])
            ->filter(
                fn ($value) =>
                    filled($value)
            )
            ->implode(', ');
    }
}
