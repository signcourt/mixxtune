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
    }
}
