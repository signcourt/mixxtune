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
        //
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
    }
}
