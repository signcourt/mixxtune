<?php

namespace App\Console\Commands;

use App\Services\V3\RevenueCalculationService;
use Illuminate\Console\Command;

class V3RevenuePreview extends Command
{
    protected $signature =
        'v3:revenue-preview
        {--month= : Preview one sale month, such as 2025-07}
        {--labels : Show label-level detail}';

    protected $description =
        'Preview Revenue Engine V3 calculations without changing database records';

    public function handle(
        RevenueCalculationService $service
    ): int {
        $month = trim(
            (string) $this->option(
                'month'
            )
        );

        $result = $service->preview(
            $month !== ''
                ? $month
                : null
        );

        $summary =
            $result['summary'];

        $scope =
            $month !== ''
                ? "Sale month {$month}"
                : 'All mapped sale months';

        $this->newLine();

        $this->info(
            "Revenue Engine V3 Preview — {$scope}"
        );

        $this->table(
            [
                'Metric',
                'Amount / Count',
            ],
            [
                [
                    'Rows',
                    number_format(
                        $summary['rows']
                    ),
                ],
                [
                    'Source Revenue',
                    number_format(
                        $summary[
                            'source_revenue'
                        ],
                        8
                    ),
                ],
                [
                    'Company Retention',
                    number_format(
                        $summary[
                            'company_retention'
                        ],
                        8
                    ),
                ],
                [
                    'Beneficiary Pool',
                    number_format(
                        $summary[
                            'beneficiary_pool'
                        ],
                        8
                    ),
                ],
                [
                    'Parent Commission',
                    number_format(
                        $summary[
                            'parent_commission'
                        ],
                        8
                    ),
                ],
                [
                    'Artist Payable',
                    number_format(
                        $summary[
                            'artist_payable'
                        ],
                        8
                    ),
                ],
                [
                    'Unallocated',
                    number_format(
                        $summary[
                            'unallocated_amount'
                        ],
                        8
                    ),
                ],
                [
                    'Reconciliation Difference',
                    number_format(
                        $summary[
                            'reconciliation_difference'
                        ],
                        8
                    ),
                ],
                [
                    'Rows Without Track',
                    number_format(
                        $result[
                            'rows_without_track'
                        ]
                    ),
                ],
            ]
        );

        $this->newLine();

        $this->info(
            'Monthly Reconciliation'
        );

        $this->table(
            [
                'Sale Month',
                'Source',
                'Company',
                'Beneficiary',
                'Artist',
                'Unallocated',
                'Difference',
            ],
            $result['by_month']
                ->map(
                    fn ($row) => [
                        $row['sale_month'],

                        number_format(
                            $row[
                                'source_revenue'
                            ],
                            8
                        ),

                        number_format(
                            $row[
                                'company_retention'
                            ],
                            8
                        ),

                        number_format(
                            $row[
                                'beneficiary_pool'
                            ],
                            8
                        ),

                        number_format(
                            $row[
                                'artist_payable'
                            ],
                            8
                        ),

                        number_format(
                            $row[
                                'unallocated_amount'
                            ],
                            8
                        ),

                        number_format(
                            $row[
                                'reconciliation_difference'
                            ],
                            8
                        ),
                    ]
                )
                ->all()
        );

        if (
            $this->option('labels')
        ) {
            $this->newLine();

            $this->info(
                'Label-Level Preview'
            );

            $this->table(
                [
                    'Label',
                    'Rate',
                    'Source',
                    'Company',
                    'Artist',
                    'Unallocated',
                ],
                $result['by_label']
                    ->map(
                        fn ($row) => [
                            $row[
                                'label_name'
                            ],

                            number_format(
                                $row[
                                    'beneficiary_percentage'
                                ],
                                2
                            ).'%',

                            number_format(
                                $row[
                                    'source_revenue'
                                ],
                                8
                            ),

                            number_format(
                                $row[
                                    'company_retention'
                                ],
                                8
                            ),

                            number_format(
                                $row[
                                    'artist_payable'
                                ],
                                8
                            ),

                            number_format(
                                $row[
                                    'unallocated_amount'
                                ],
                                8
                            ),
                        ]
                    )
                    ->all()
            );
        }

        $difference =
            abs(
                $summary[
                    'reconciliation_difference'
                ]
            );

        $this->newLine();

        if ($difference <= 0.01) {
            $this->info(
                'PASS: Revenue reconciles successfully.'
            );
        } else {
            $this->error(
                'FAIL: Revenue reconciliation difference is '
                .number_format(
                    $difference,
                    8
                )
            );
        }

        if (
            $result[
                'rows_without_track'
            ] > 0
        ) {
            $this->warn(
                'Some mapped rows have no track and remain unallocated.'
            );
        }

        $this->comment(
            'Preview only: no database records were changed.'
        );

        return $difference <= 0.01
            ? self::SUCCESS
            : self::FAILURE;
    }
}
