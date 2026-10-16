<?php

namespace Tests\Unit;

use App\Services\V2\ReportImportService;
use ReflectionMethod;
use Tests\TestCase;

class ReportImportRowHashTest extends TestCase
{
    private function rowHash(array $row): string
    {
        $service =
            app(ReportImportService::class);

        $method =
            new ReflectionMethod(
                ReportImportService::class,
                'rowHash'
            );

        $method->setAccessible(true);

        return $method->invoke(
            $service,
            $row
        );
    }

    private function baseRow(): array
    {
        return [
            'isrc' =>
                'IN-ABC-26-12345',

            'upc' =>
                '123456789012',

            'platform' =>
                'Spotify',

            'country_code' =>
                'IN',

            'sale_type' =>
                'Stream',

            'sale_date' =>
                '2026-08-15',

            'sale_month' =>
                '2026-08',

            'streams' =>
                100,

            'sale_units' =>
                100,

            'collected_revenue' =>
                100,
        ];
    }

    public function test_isrc_formatting_variants_have_same_hash(): void
    {
        $base =
            $this->baseRow();

        $dash =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'isrc' =>
                            'IN-ABC-26-12345',
                    ]
                )
            );

        $spaces =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'isrc' =>
                            'IN ABC 26 12345',
                    ]
                )
            );

        $compactLower =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'isrc' =>
                            'inabc2612345',
                    ]
                )
            );

        $this->assertSame(
            $dash,
            $spaces
        );

        $this->assertSame(
            $dash,
            $compactLower
        );
    }

    public function test_text_formatting_variants_have_same_hash(): void
    {
        $base =
            $this->baseRow();

        $normal =
            $this->rowHash(
                $base
            );

        $formatted =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'platform' =>
                            '  spotify  ',

                        'country_code' =>
                            'in',

                        'sale_type' =>
                            '  stream ',
                    ]
                )
            );

        $this->assertSame(
            $normal,
            $formatted
        );
    }

    public function test_different_financial_dimensions_have_different_hashes(): void
    {
        $base =
            $this->baseRow();

        $original =
            $this->rowHash(
                $base
            );

        $differentRevenue =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'collected_revenue' =>
                            101,
                    ]
                )
            );

        $differentDate =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'sale_date' =>
                            '2026-08-16',
                    ]
                )
            );

        $differentPlatform =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'platform' =>
                            'YouTube',
                    ]
                )
            );

        $this->assertNotSame(
            $original,
            $differentRevenue
        );

        $this->assertNotSame(
            $original,
            $differentDate
        );

        $this->assertNotSame(
            $original,
            $differentPlatform
        );
    }

    public function test_upc_normalization_does_not_change_hash(): void
    {
        $base =
            $this->baseRow();

        $normal =
            $this->rowHash(
                $base
            );

        $formatted =
            $this->rowHash(
                array_merge(
                    $base,
                    [
                        'upc' =>
                            '123-456-789-012',
                    ]
                )
            );

        $this->assertSame(
            $normal,
            $formatted
        );
    }
}
