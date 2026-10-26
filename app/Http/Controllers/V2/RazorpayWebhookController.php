<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\BankPayout;
use App\Models\Finance\PaymentGatewaySetting;
use App\Services\V2\Payouts\BankPayoutService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 * W115 MIXXTUNE_RAZORPAY_PAYOUTS
 * POST /api/webhooks/razorpayx - RazorpayX tells us when a payout changes (payout.processed, .reversed, ...).
 * Checked with X-Razorpay-Signature (HMAC SHA256 of the raw body with the webhook secret); every event id
 * is handled once (x-razorpay-event-id). The answer is quick (Razorpay waits only 5 seconds).
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, BankPayoutService $svc): JsonResponse
    {
        $secret = (string) PaymentGatewaySetting::razorpay()->webhook_secret;
        $raw = (string) $request->getContent();
        $sig = (string) $request->header('X-Razorpay-Signature', '');
        if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
            return response()->json(['ok' => false, 'error' => 'bad signature'], 401);
        }
        $data = json_decode($raw, true);
        $event = is_array($data) ? (string) ($data['event'] ?? '') : '';
        $po = is_array($data) ? data_get($data, 'payload.payout.entity') : null;
        $eventId = (string) $request->header('x-razorpay-event-id', '') ?: 'nohdr-'.hash('sha256', $raw);
        if (!str_starts_with($event, 'payout.') || !is_array($po) || empty($po['id'])) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }
        try {
            DB::table('razorpay_webhook_events')->insert(['event_id' => mb_substr($eventId, 0, 80), 'event' => mb_substr($event, 0, 60), 'payout_id' => mb_substr((string) $po['id'], 0, 40), 'received_at' => now()]);
        } catch (QueryException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }
        $p = BankPayout::query()->where('razorpay_payout_id', $po['id'])->first();
        if (!$p && ($ref = data_get($po, 'notes.panel_payout'))) {
            $p = BankPayout::query()->where('public_id', $ref)->first();
        }
        if (!$p) {
            return response()->json(['ok' => true, 'unknown' => true]);
        }
        try {
            if ($svc->apply($p, $po) === null) {
                /* another process is on this payout right now: forget the event so Razorpay sends it again */
                DB::table('razorpay_webhook_events')->where('event_id', mb_substr($eventId, 0, 80))->delete();

                return response()->json(['ok' => false, 'busy' => true], 503);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }
}
