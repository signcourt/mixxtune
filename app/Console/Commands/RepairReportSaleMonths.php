<?php

namespace App\Console\Commands;

use App\Models\Reports\ReportRow;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RepairReportSaleMonths extends Command
{
    protected $signature = 'reports:repair-sale-months';

    protected $description =
        'Repair sale_month values using original raw_data including Excel serial dates';

    public function handle(): int
    {
        $updated = 0;
        $failed = 0;

        $this->info('Repairing report sale months...');

        ReportRow::query()
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$updated, &$failed) {

                foreach ($rows as $row) {
                    $raw = is_array($row->raw_data)
                        ? $row->raw_data
                        : json_decode(
                            $row->raw_data ?? '{}',
                            true
                        );

                    if (!is_array($raw)) {
                        $failed++;
                        continue;
                    }

                    $value =
                        $raw['sales month']
                        ?? $raw['sale month']
                        ?? $raw['sales_month']
                        ?? $raw['sale_month']
                        ?? null;

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $value = trim((string) $value);
                    $month = null;

                    /*
                     * Excel date serial.
                     *
                     * Excel serial 1 = 1900-01-01, but because of
                     * Excel's historic leap-year behaviour the
                     * standard conversion base is 1899-12-30.
                     *
                     * Example:
                     * 46143 -> 2026-05
                     */
                    if (
                        is_numeric($value)
                        && (float) $value >= 1
                        && (float) $value <= 100000
                    ) {
                        try {
                            $month = Carbon::create(
                                1899,
                                12,
                                30,
                                0,
                                0,
                                0
                            )
                                ->addDays((int) floor((float) $value))
                                ->startOfMonth()
                                ->format('Y-m');
                        } catch (\Throwable) {
                            $month = null;
                        }
                    }

                    if (
                        !$month
                        && preg_match(
                            '/^FY\s*\d{2,4}/i',
                            $value
                        )
                    ) {
                        $month = null;
                    }

                    if (!$month) {
                        foreach (
                            [
                                'Y-m',
                                'Y/m',
                                'm/Y',
                                'm-Y',
                                'M-y',
                                'F-y',
                                'M Y',
                                'F Y',
                            ] as $format
                        ) {
                            try {
                                $parsed = Carbon::createFromFormat(
                                    '!'.$format,
                                    $value
                                );

                                if ($parsed !== false) {
                                    $month = $parsed->format('Y-m');
                                    break;
                                }
                            } catch (\Throwable) {
                            }
                        }
                    }

                    if (!$month) {
                        try {
                            $month = Carbon::parse($value)
                                ->startOfMonth()
                                ->format('Y-m');
                        } catch (\Throwable) {
                            $month = null;
                        }
                    }

                    if (!$month) {
                        $failed++;
                        continue;
                    }

                    if ($row->sale_month !== $month) {
                        $row->sale_month = $month;
                        $row->save();

                        $updated++;
                    }
                }

                $this->line(
                    'Updated: '.$updated.
                    ' | Unresolved: '.$failed
                );
            });

        $this->newLine();

        $this->info(
            'Finished. Total updated: '.$updated
        );

        $this->line(
            'Unresolved values: '.$failed
        );

        return self::SUCCESS;
    }
}
