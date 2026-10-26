<?php

namespace App\Console\Commands\V2;

use App\Models\Finance\BankPayout;
use App\Services\V2\Payouts\BankPayoutService;
use App\Services\V2\Payouts\RazorpayX;
use Illuminate\Console\Command;

/* W115 MIXXTUNE_RAZORPAY_PAYOUTS: ask RazorpayX about every payout that is not finished yet */
class SyncBankPayouts extends Command
{
    protected $signature = 'payouts:sync {--limit=100}';

    protected $description = 'Update RazorpayX payouts that are still open (and book paid ones not booked yet)';

    public function handle(BankPayoutService $svc, RazorpayX $rzp): int
    {
        if (!$rzp->configured()) {
            $this->line('Razorpay not set up - nothing to do.');

            return self::SUCCESS;
        }
        $list = BankPayout::query()
            ->where(fn ($q) => $q->whereIn('status', BankPayout::OPEN)->orWhere(fn ($w) => $w->where('status', 'processed')->where('settled', false)))
            ->where('created_at', '<', now()->subMinute())
            ->orderBy('last_checked_at')->limit((int) $this->option('limit'))->get();
        $n = 0;
        foreach ($list as $p) {
            $svc->sync($p);
            $n++;
        }
        $this->line("checked {$n} payout(s)");

        return self::SUCCESS;
    }
}
