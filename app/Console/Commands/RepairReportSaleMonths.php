<?php

namespace App\Console\Commands;

use App\Models\Reports\ReportRow;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RepairReportSaleMonths extends Command
{
    protected $signature = 'reports:repair-sale-months';

    protected $description =
        'Repair sale_month values using original raw_data';

    public function handle(): int
    {
        $updated = 0;

        ReportRow::query()
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$updated) {

                foreach ($rows as $row) {

                    $raw = is_array($row->raw_data)
                        ? $row->raw_data
                        : json_decode(
                            $row->raw_data ?? '{}',
                            true
                        );

                    $value =
                        $raw['sales month']
                        ?? $raw['sale month']
                        ?? null;

                    if (!$value) {
                        continue;
                    }

                    $value = trim($value);

                    if (
                        preg_match(
                            '/^FY\s*\d{2,4}/i',
                            $value
                        )
                    ) {
                        $month = null;
                    } else {

                        $month = null;

                        foreach (
                            ['M-y','F-y','M y','F y']
                            as $format
                        ) {
                            try {

                                $month =
                                    Carbon::createFromFormat(
                                        '!'.$format,
                                        $value
                                    )
                                    ->format('Y-m');

                                break;

                            } catch (\Throwable) {
                            }
                        }

                        if (!$month) {
                            try {
                                $month =
                                    Carbon::parse($value)
                                    ->startOfMonth()
                                    ->format('Y-m');
                            } catch (\Throwable) {
                                $month = null;
                            }
                        }
                    }

                    if (
                        $row->sale_month !== $month
                    ) {
                        $row->sale_month = $month;
                        $row->save();

                        $updated++;
                    }
                }

                $this->info(
                    "Updated: ".$updated
                );

            });

        $this->newLine();

        $this->info(
            "Finished. Total updated: ".$updated
        );

        return self::SUCCESS;
    }
}
