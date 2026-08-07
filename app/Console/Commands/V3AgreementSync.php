<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class V3AgreementSync extends Command
{
    protected $signature =
        'v3:agreements-sync
        {--execute : Create missing Label agreements}
        {--label= : Process only one Label ID}';

    protected $description =
        'Preview or create V3 Label revenue agreements from current Label royalty settings';

    public function handle(): int
    {
        if (
            !Schema::hasTable(
                'revenue_agreements'
            )
        ) {
            $this->error(
                'revenue_agreements table is missing. Run migrations first.'
            );

            return self::FAILURE;
        }

        $labelId = trim(
            (string) $this->option(
                'label'
            )
        );

        $labels = DB::table('labels')
            ->whereNull('deleted_at')
            ->when(
                $labelId !== '',
                fn ($query) =>
                    $query->where(
                        'id',
                        (int) $labelId
                    )
            )
            ->orderBy('id')
            ->get();

        $preview = [];
        $created = 0;
        $skipped = 0;

        foreach ($labels as $label) {
            $beneficiary =
                $this->boundPercentage(
                    (float) (
                        $label
                            ->royalty_share_percentage
                        ?? 100
                    )
                );

            $companyRetention = round(
                100 - $beneficiary,
                4
            );

            $parentCommission =
                $this->boundPercentage(
                    (float) (
                        $label
                            ->parent_commission_percentage
                        ?? 0
                    )
                );

            $exists = DB::table(
                'revenue_agreements'
            )
                ->where(
                    'subject_type',
                    'label'
                )
                ->where(
                    'subject_id',
                    $label->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->exists();

            $action =
                $exists
                    ? 'skip'
                    : (
                        $this->option('execute')
                            ? 'create'
                            : 'would create'
                    );

            if (
                !$exists
                && $this->option(
                    'execute'
                )
            ) {
                DB::table(
                    'revenue_agreements'
                )->insert([
                    'public_id' =>
                        (string) Str::ulid(),

                    'subject_type' =>
                        'label',

                    'subject_id' =>
                        $label->id,

                    'agreement_name' =>
                        $label->name
                        .' Revenue Agreement',

                    'beneficiary_percentage' =>
                        $beneficiary,

                    'company_retention_percentage' =>
                        $companyRetention,

                    'parent_commission_percentage' =>
                        $parentCommission,

                    'currency' =>
                        $label->currency
                        ?: 'INR',

                    'effective_from' =>
                        null,

                    'effective_to' =>
                        null,

                    'status' =>
                        'active',

                    'priority' =>
                        100,

                    'metadata' =>
                        json_encode([
                            'source' =>
                                'labels_migration',

                            'label_type' =>
                                $label->label_type,
                        ]),

                    'created_by' =>
                        $label->updated_by
                        ?: $label->created_by,

                    'updated_by' =>
                        $label->updated_by
                        ?: $label->created_by,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

                $created++;
            } elseif ($exists) {
                $skipped++;
            }

            $preview[] = [
                $label->id,
                $label->name,
                number_format(
                    $beneficiary,
                    2
                ).'%',
                number_format(
                    $companyRetention,
                    2
                ).'%',
                number_format(
                    $parentCommission,
                    2
                ).'%',
                $action,
            ];
        }

        $this->newLine();

        $this->info(
            $this->option('execute')
                ? 'V3 Agreement Sync'
                : 'V3 Agreement Sync — Dry Run'
        );

        $this->table(
            [
                'Label ID',
                'Label',
                'Beneficiary',
                'Company',
                'Parent',
                'Action',
            ],
            $preview
        );

        $this->newLine();

        if ($this->option('execute')) {
            $this->info(
                "Created: {$created}"
            );

            $this->line(
                "Skipped: {$skipped}"
            );
        } else {
            $this->comment(
                'Dry run only: no agreement records were created.'
            );

            $this->line(
                'Run with --execute only after reviewing the percentages.'
            );
        }

        return self::SUCCESS;
    }

    private function boundPercentage(
        float $percentage
    ): float {
        return round(
            max(
                0,
                min(
                    100,
                    $percentage
                )
            ),
            4
        );
    }
}
