<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /* W81 MIXXTUNE_PANEL_ACCESS: one copy per request (its lookups are cached inside) */
        $this->app->scoped(\App\Services\V2\PanelAccess::class);

        /* W225: Analytics totals are remembered until report data changes (same numbers, much faster) */
        $this->app->bind(\App\Services\V2\ReportAnalyticsService::class, \App\Services\V2\CachedReportAnalyticsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /* MIXXTUNE_LABEL_RATE_HISTORY_V1 */
        \App\Models\Core\Label::observe(\App\Observers\LabelRateObserver::class);
        /* MIXXTUNE_BANK_CHANGE_PROTECTION_V1 */
        \App\Models\Finance\PayoutProfile::observe(\App\Observers\PayoutProfileBankObserver::class);
        /* MIXXTUNE_TWO_STEP_ACTIONS_V1 */
        \App\Models\Core\Label::observe(\App\Observers\LabelRateConfirmObserver::class);

        /* W230: a new / changed distributor changes the list of addresses we answer on */
        \App\Models\Distributor::saved(fn () => \App\Support\DistributorHost::forgetCache());

        /* W226: when a sub-label's share % is changed anywhere, months already reported keep the old % */
        \App\Models\Finance\LabelRevenueShare::updating(function ($share) {
            try {
                if ((string) $share->beneficiary_type === 'label' && ($share->isDirty('revenue_share_percent') || $share->isDirty('is_active')) && $share->getOriginal('is_active')) {
                    $d = \App\Services\V2\DistributorService::ready() ? \App\Models\Distributor::query()->where('label_id', $share->master_label_id)->first() : null;
                    if ($d) {
                        app(\App\Services\V2\DistributorMoneyService::class)->freezeLabel((int) $d->id, (int) $share->master_label_id, (int) $share->beneficiary_id, (float) $share->getOriginal('revenue_share_percent'));
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
