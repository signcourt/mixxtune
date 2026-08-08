<?php

namespace Tests\Feature\Royalties;

use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Services\V2\MasterLabelRevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterLabelRevenueServiceTest extends TestCase
{
    use RefreshDatabase;

    private function label(
        string $name,
        ?int $parentId = null
    ): Label {
        return Label::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'parent_label_id' =>
                $parentId,

            'label_type' =>
                $parentId
                    ? 'sub_label'
                    : 'master',

            'name' =>
                $name,

            'slug' =>
                Str::slug($name)
                .'-'
                .Str::lower(
                    Str::random(6)
                ),

            'currency' =>
                'INR',

            'royalty_share_percentage' =>
                100,

            'parent_commission_percentage' =>
                0,

            'status' =>
                'active',
        ]);
    }

    public function test_80_percent_child_share_splits_100_correctly(): void
    {
        $master = $this->label(
            'Sanatan Records'
        );

        $child = $this->label(
            'Mixx Tune',
            $master->id
        );

        LabelRevenueShare::query()->create([
            'master_label_id' =>
                $master->id,

            'beneficiary_type' =>
                'label',

            'beneficiary_id' =>
                $child->id,

            'revenue_share_percent' =>
                80,

            'show_revenue_share' =>
                true,

            'is_active' =>
                true,
        ]);

        $result = app(
            MasterLabelRevenueService::class
        )->calculate(
            $master,
            'label',
            $child->id,
            100
        );

        $this->assertSame(
            100.0,
            $result['gross_revenue']
        );

        $this->assertSame(
            80.0,
            $result['child_share_percent']
        );

        $this->assertSame(
            20.0,
            $result['master_share_percent']
        );

        $this->assertSame(
            80.0,
            $result['child_revenue']
        );

        $this->assertSame(
            20.0,
            $result['master_revenue']
        );

        $this->assertTrue(
            $result['show_revenue_share']
        );
    }

    public function test_no_split_means_master_keeps_100_percent(): void
    {
        $master = $this->label(
            'Sanatan Records'
        );

        $child = $this->label(
            'Child Label',
            $master->id
        );

        $result = app(
            MasterLabelRevenueService::class
        )->calculate(
            $master,
            'label',
            $child->id,
            100
        );

        $this->assertSame(
            0.0,
            $result['child_revenue']
        );

        $this->assertSame(
            100.0,
            $result['master_revenue']
        );

        $this->assertFalse(
            $result['share_configured']
        );
    }

    public function test_split_never_creates_extra_revenue(): void
    {
        $master = $this->label(
            'Sanatan Records'
        );

        $child = $this->label(
            'Mixx Tune',
            $master->id
        );

        LabelRevenueShare::query()->create([
            'master_label_id' =>
                $master->id,

            'beneficiary_type' =>
                'label',

            'beneficiary_id' =>
                $child->id,

            'revenue_share_percent' =>
                73.25,

            'show_revenue_share' =>
                false,

            'is_active' =>
                true,
        ]);

        $result = app(
            MasterLabelRevenueService::class
        )->calculate(
            $master,
            'label',
            $child->id,
            123.456789
        );

        $this->assertEqualsWithDelta(
            $result['gross_revenue'],
            $result['child_revenue']
                + $result['master_revenue'],
            0.00000001
        );
    }
}
