<?php

namespace App\Services\Iprs;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/*
 * Parses an IPRS "overseasMemberDetail" statement into clean royalty lines.
 *
 * The sheet is NOT a flat table: member info sits in key/value rows above a
 * header row, then each work is a block whose WORK INT NO / TITLE / LANGUAGE
 * are filled only on the first row and blank on the following credit rows.
 * MIXX TUNE is the publisher (ROLE "E"); its rows carry POOL, SOURCE and the
 * ROYALTY AMT. Parsing stops at "TOTAL ROYALTIES".
 *
 * Columns are mapped by header NAME (not position), so an extra/missing column
 * such as AV does not break parsing.
 */
class IprsStatementParser
{
    /** Header labels we care about (normalised). */
    private const COL_WORK = 'WORK INT NO';
    private const COL_TITLE = 'TITLE';
    private const COL_LANGUAGE = 'LANGUAGE';
    private const COL_NAME = 'NAME';
    private const COL_ROLE = 'ROLE';
    private const COL_SOCIETY = 'SOCIETY';
    private const COL_OWN = 'OWN';
    private const COL_COLL = 'COLL';
    private const COL_POOL = 'POOL';
    private const COL_SOURCE = 'SOURCE';
    private const COL_AMOUNT = 'ROYALTY AMT';

    private const MONTHS = [
        'JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH' => 3, 'APRIL' => 4,
        'MAY' => 5, 'JUNE' => 6, 'JULY' => 7, 'AUGUST' => 8,
        'SEPTEMBER' => 9, 'OCTOBER' => 10, 'NOVEMBER' => 11, 'DECEMBER' => 12,
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'SEPT' => 9, 'OCT' => 10,
        'NOV' => 11, 'DEC' => 12,
    ];

    /**
     * @return array{member:array,works:array,lines:array,file_total:?float,parsed_total:float,source_totals:array,summary_source_totals:array}
     */
    public function parse(string $path, ?string $sheetName = 'overseasMemberDetail'): array
    {
        $spreadsheet = IOFactory::load($path);

        $sheet = ($sheetName && $spreadsheet->sheetNameExists($sheetName))
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        // Raw values (numbers as numbers), 0-indexed columns.
        $rows = $sheet->toArray(null, true, false, false);

        $headerRow = $this->findHeaderRow($rows);
        if ($headerRow === null) {
            throw new RuntimeException('Could not find the IPRS header row (no "WORK INT NO" cell).');
        }

        $cols = $this->mapColumns($rows[$headerRow]);
        foreach ([self::COL_WORK, self::COL_NAME, self::COL_AMOUNT] as $required) {
            if (! array_key_exists($required, $cols)) {
                throw new RuntimeException('IPRS header is missing the "'.$required.'" column.');
            }
        }

        $member = $this->readMember(array_slice($rows, 0, $headerRow));

        $works = [];
        $lines = [];
        $sourceTotals = [];
        $parsedTotal = 0.0;
        $fileTotal = null;

        $curWork = null;
        $curTitle = null;
        $curLang = null;
        $stopRow = null;

        $count = count($rows);
        for ($r = $headerRow + 1; $r < $count; $r++) {
            $row = $rows[$r];

            // Stop markers.
            $joined = $this->rowText($row);
            if (str_contains($joined, 'TOTAL ROYALTIES')) {
                $fileTotal = $this->firstNumber($row); // grand total sits in the amount column
                $stopRow = $r;
                break;
            }
            if (str_contains($joined, 'SUMMARY OF ROYALTY')) {
                $stopRow = $r;
                break;
            }

            $workCell = $this->cell($row, $cols[self::COL_WORK]);
            if ($workCell !== '') {
                // New block.
                $curWork = $this->workNo($workCell);
                $curTitle = isset($cols[self::COL_TITLE]) ? $this->cell($row, $cols[self::COL_TITLE]) : null;
                $curLang = isset($cols[self::COL_LANGUAGE]) ? $this->cell($row, $cols[self::COL_LANGUAGE]) : null;
                if ($curWork !== '' && ! isset($works[$curWork])) {
                    $works[$curWork] = [
                        'work_int_no' => $curWork,
                        'title' => $curTitle ?: null,
                        'language' => $curLang ?: null,
                        'credits' => [],
                    ];
                }
            }

            if ($curWork === null || $curWork === '') {
                continue;
            }

            $name = $this->trimName($this->cell($row, $cols[self::COL_NAME]));
            $role = isset($cols[self::COL_ROLE]) ? strtoupper($this->cell($row, $cols[self::COL_ROLE])) : '';
            $amount = $this->num($this->cell($row, $cols[self::COL_AMOUNT]));

            // A row with a NAME is a credit line (writer) for the work's metadata.
            if ($name !== '') {
                $credit = [
                    'name' => $name,
                    'role' => $role,
                    'society' => isset($cols[self::COL_SOCIETY]) ? $this->cell($row, $cols[self::COL_SOCIETY]) : '',
                    'own_pct' => isset($cols[self::COL_OWN]) ? $this->num($this->cell($row, $cols[self::COL_OWN])) : null,
                    'coll_pct' => isset($cols[self::COL_COLL]) ? $this->num($this->cell($row, $cols[self::COL_COLL])) : null,
                ];
                $key = $name.'|'.$role.'|'.$credit['society'];
                $works[$curWork]['credits'][$key] = $credit;
            }

            // A royalty line = ROYALTY AMT > 0 AND a NAME (ignore subtotal rows).
            if ($amount > 0 && $name !== '') {
                $source = isset($cols[self::COL_SOURCE]) ? $this->cell($row, $cols[self::COL_SOURCE]) : '';
                $lines[] = [
                    'work_int_no' => $curWork,
                    'title' => $works[$curWork]['title'],
                    'language' => $works[$curWork]['language'],
                    'name' => $name,
                    'role' => $role,
                    'pool' => isset($cols[self::COL_POOL]) ? ($this->cell($row, $cols[self::COL_POOL]) ?: null) : null,
                    'source' => $source ?: null,
                    'source_month' => $this->monthFrom($source),
                    'own_pct' => isset($cols[self::COL_OWN]) ? $this->num($this->cell($row, $cols[self::COL_OWN])) : null,
                    'coll_pct' => isset($cols[self::COL_COLL]) ? $this->num($this->cell($row, $cols[self::COL_COLL])) : null,
                    'amount' => round($amount, 4),
                ];
                $parsedTotal += $amount;
                $srcKey = strtoupper(trim((string) $source));
                $sourceTotals[$srcKey] = round(($sourceTotals[$srcKey] ?? 0) + $amount, 4);
            }
        }

        // Flatten credits (drop the dedupe keys).
        foreach ($works as $k => $w) {
            $works[$k]['credits'] = array_values($w['credits']);
        }

        $summarySourceTotals = $stopRow !== null
            ? $this->readSourceSummary(array_slice($rows, $stopRow), $cols)
            : [];

        return [
            'member' => $member,
            'works' => $works,
            'lines' => $lines,
            'file_total' => $fileTotal !== null ? round($fileTotal, 4) : null,
            'parsed_total' => round($parsedTotal, 4),
            'source_totals' => $sourceTotals,
            'summary_source_totals' => $summarySourceTotals,
        ];
    }

    private function findHeaderRow(array $rows): ?int
    {
        foreach ($rows as $i => $row) {
            if ($this->norm($this->cell($row, 0)) === self::COL_WORK) {
                return $i;
            }
        }

        return null;
    }

    /** @return array<string,int> header label => column index */
    private function mapColumns(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $val) {
            $label = $this->norm((string) $val);
            if ($label !== '' && ! isset($map[$label])) {
                $map[$label] = $idx;
            }
        }

        return $map;
    }

    /** @return array{internal_no:?string,name:?string,ipi_name_no:?string,ipi_base_no:?string} */
    private function readMember(array $aboveRows): array
    {
        $keys = [
            'INTERNAL NO' => 'internal_no',
            'IPI NAME NO' => 'ipi_name_no',
            'IPI BASE NO' => 'ipi_base_no',
            'NAME' => 'name',
        ];
        $out = ['internal_no' => null, 'name' => null, 'ipi_name_no' => null, 'ipi_base_no' => null];

        foreach ($aboveRows as $row) {
            $n = is_array($row) ? count($row) : 0;
            for ($c = 0; $c < $n; $c++) {
                $label = $this->norm($this->cell($row, $c));
                if (! isset($keys[$label])) {
                    continue;
                }
                // value = next non-empty cell to the right
                for ($d = $c + 1; $d < $n; $d++) {
                    $v = $this->cell($row, $d);
                    if ($v !== '') {
                        $field = $keys[$label];
                        if ($out[$field] === null) {
                            $out[$field] = $this->trimName($v);
                        }
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /** POOL | SOURCE | SOURCE DESCRIPTION | NO. OF WORKS | ROYALTY AMT block at the bottom (validation only). */
    private function readSourceSummary(array $tailRows, array $cols): array
    {
        $out = [];
        $amountIdx = $cols[self::COL_AMOUNT] ?? null;
        $sourceIdx = $cols[self::COL_SOURCE] ?? null;
        if ($amountIdx === null || $sourceIdx === null) {
            return $out;
        }

        $inSection = false;
        foreach ($tailRows as $row) {
            $text = $this->rowText($row);
            if (str_contains($text, 'SOURCE DESCRIPTION') || str_contains($text, 'NO. OF WORKS')) {
                $inSection = true;
                continue;
            }
            if (! $inSection) {
                continue;
            }
            $src = strtoupper(trim($this->cell($row, $sourceIdx)));
            $amt = $this->num($this->cell($row, $amountIdx));
            if ($src !== '' && $amt > 0) {
                $out[$src] = round(($out[$src] ?? 0) + $amt, 4);
            }
        }

        return $out;
    }

    private function monthFrom(?string $source): ?int
    {
        if (! $source) {
            return null;
        }
        foreach (preg_split('/\s+/', strtoupper(trim($source))) as $token) {
            if (isset(self::MONTHS[$token])) {
                return self::MONTHS[$token];
            }
        }

        return null;
    }

    private function workNo(string $raw): string
    {
        $raw = trim($raw);
        // Excel often gives "29099550.0" - strip a trailing .0 / decimals.
        if (preg_match('/^\d+\.0+$/', $raw)) {
            return (string) (int) round((float) $raw);
        }

        return $raw;
    }

    private function num(string $v): float
    {
        $v = str_replace([',', ' '], '', trim($v));

        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function firstNumber(array $row): ?float
    {
        $best = null;
        foreach ($row as $v) {
            $s = str_replace([',', ' '], '', trim((string) $v));
            if ($s !== '' && is_numeric($s)) {
                $best = (float) $s; // keep the last number on the row (grand total column)
            }
        }

        return $best;
    }

    private function cell(array $row, int $idx): string
    {
        return isset($row[$idx]) ? trim((string) $row[$idx]) : '';
    }

    private function rowText(array $row): string
    {
        return strtoupper(implode(' ', array_map(fn ($v) => trim((string) $v), $row)));
    }

    private function trimName(string $v): string
    {
        return trim(rtrim(trim($v), ', '));
    }

    private function norm(string $v): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $v)));
    }
}
