<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\DistributorMoneyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * W226 - "My Earnings" of a sub-label that sits under a distributor.
 * It shows the sub-label's OWN share only (never what the distributor earns), month by month, the payments
 * the distributor has written for it, and what is still due. There is no withdrawal here: the distributor pays.
 */
class SubLabelEarningsController extends Controller
{
    private function context(Request $request, DistributorMoneyService $money): array
    {
        $c = $money->subContext($request->user());
        abort_unless($c, 404);
        abort_unless(DistributorMoneyService::ready(), 503, 'Earnings are not set up yet.');

        return $c;
    }

    private function mine(array $c, DistributorMoneyService $money): array
    {
        $l = $money->ledger($c['distributor'], (int) $c['label_id'])['labels'][0] ?? null;

        return [
            'streams' => (float) ($l['streams'] ?? 0),
            'share' => (float) ($l['share'] ?? 0),
            'paid' => (float) ($l['paid'] ?? 0),
            'balance' => (float) ($l['balance'] ?? 0),
            /* the distributor's own amount and the % stay out of this page */
            'months' => array_map(fn ($m) => ['month' => $m['month'], 'streams' => $m['streams'], 'share' => $m['share'], 'percent' => $m['percent']], $l['months'] ?? []),
            'payments' => array_values(array_map(
                fn ($p) => ['id' => $p['id'], 'amount' => $p['amount'], 'paid_on' => $p['paid_on'], 'method' => $p['method'], 'reference' => $p['reference'], 'note' => $p['note']],
                array_filter($l['payments'] ?? [], fn ($p) => !$p['cancelled'])
            )),
        ];
    }

    private function monthOf(Request $request, array $mine): ?array
    {
        $month = (string) $request->query('month', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }
        foreach ($mine['months'] as $m) {
            if ($m['month'] === $month) {
                return $m;
            }
        }

        return null;
    }

    public function index(Request $request, DistributorMoneyService $money): Response
    {
        $c = $this->context($request, $money);
        $mine = $this->mine($c, $money);
        $picked = $this->monthOf($request, $mine);
        $songs = $picked ? array_slice($money->monthSongs((int) $c['label_id'], $picked['month'], $picked['percent']), 0, 300) : [];

        $brand = trim((string) $c['distributor']->brand_name);
        foreach ($mine['months'] as &$m) {
            unset($m['percent']);
        }
        unset($m);

        return Inertia::render('V2/Distributor/MyEarnings', [
            'role' => 'label',
            'label' => $c['label_name'],
            'paidBy' => $brand !== '' ? $brand : 'your distributor',
            'mine' => $mine,
            'month' => $picked ? $picked['month'] : null,
            'songs' => $songs,
        ]);
    }

    public function export(Request $request, DistributorMoneyService $money): StreamedResponse
    {
        $c = $this->context($request, $money);
        $picked = $this->monthOf($request, $this->mine($c, $money));
        abort_unless($picked, 404);
        $songs = $money->monthSongs((int) $c['label_id'], $picked['month'], $picked['percent']);
        $safe = preg_replace('/[^A-Za-z0-9]+/', '-', $c['label_name']) ?: 'label';

        return response()->streamDownload(function () use ($songs) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['Song', 'ISRC', 'Store', 'Streams', 'Your earning (INR)']);
            foreach ($songs as $s) {
                /* a title starting with = + - @ must not run as a formula in Excel */
                $title = preg_match('/^[=+\-@\t\r]/', $s['title']) ? "'".$s['title'] : $s['title'];
                fputcsv($h, [$title, $s['isrc'], $s['platform'], (int) $s['streams'], number_format($s['share'], 4, '.', '')]);
            }
            fclose($h);
        }, 'earnings-'.$safe.'-'.$picked['month'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
