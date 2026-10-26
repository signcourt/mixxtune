<?php

namespace App\Services\V2\Payouts;

use App\Models\Finance\PaymentGatewaySetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/*
 * W115 MIXXTUNE_RAZORPAY_PAYOUTS
 * The few RazorpayX Payouts API calls the panel needs (https://razorpay.com/docs/api/x/payouts):
 * contacts, fund accounts (bank), payouts (always with X-Payout-Idempotency), fetch a payout.
 * Keys come from payment_gateway_settings (Super Admin -> Razorpay Payouts), never from the page.
 */
class RazorpayX
{
    public const BASE = 'https://api.razorpay.com/v1';

    public function settings(): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::razorpay();
    }

    /** keys and the debit account are filled in */
    public function configured(?PaymentGatewaySetting $s = null): bool
    {
        $s ??= $this->settings();

        return (string) $s->key_id !== '' && (string) $s->key_secret !== '' && (string) $s->account_number !== '';
    }

    /** switched on by the Super Admin and filled in */
    public function ready(): bool
    {
        $s = $this->settings();

        return $s->enabled && $this->configured($s);
    }

    public static function isLiveKey(?string $keyId): bool
    {
        return str_starts_with((string) $keyId, 'rzp_live_');
    }

    public function live(): bool
    {
        return self::isLiveKey($this->settings()->key_id);
    }

    /** test keys pay nothing - so they may only book requests on the staging site (and in tests) */
    public static function testKeysAllowed(): bool
    {
        return app()->environment(['local', 'testing']) || str_contains((string) parse_url((string) config('app.url'), PHP_URL_HOST), 'staging');
    }

    /** test: list one payout of the debit account (proves keys + account + IP allow-list) */
    public function testConnection(): array
    {
        $s = $this->settings();
        $this->call('GET', '/payouts', ['account_number' => $s->account_number, 'count' => 1]);

        return ['ok' => true, 'live' => self::isLiveKey($s->key_id)];
    }

    public function createContact(string $name, string $type, string $referenceId, ?string $email = null, ?string $phone = null): array
    {
        return $this->call('POST', '/contacts', array_filter([
            'name' => self::cleanName($name, 50),
            'type' => $type,
            'reference_id' => mb_substr($referenceId, 0, 40),
            'email' => $email ?: null,
            'contact' => $phone ? preg_replace('/\D+/', '', $phone) : null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function createBankFundAccount(string $contactId, string $holder, string $ifsc, string $account): array
    {
        return $this->call('POST', '/fund_accounts', [
            'contact_id' => $contactId,
            'account_type' => 'bank_account',
            'bank_account' => [
                'name' => self::cleanName($holder, 120),
                'ifsc' => strtoupper(trim($ifsc)),
                'account_number' => preg_replace('/[^A-Za-z0-9]/', '', $account),
            ],
        ]);
    }

    /** amount in paise; the same idempotency key must be used for every try of the same payout */
    public function createPayout(string $fundAccountId, int $amountPaise, string $mode, string $purpose, string $referenceId, string $narration, string $idempotencyKey, array $notes = []): array
    {
        $s = $this->settings();

        return $this->call('POST', '/payouts', [
            'account_number' => $s->account_number,
            'fund_account_id' => $fundAccountId,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'mode' => $mode,
            'purpose' => $purpose,
            'queue_if_low_balance' => true,
            'reference_id' => mb_substr($referenceId, 0, 40),
            'narration' => self::narration($narration),
            'notes' => (object) array_slice(array_map(fn ($v) => mb_substr((string) $v, 0, 256), $notes), 0, 15, true),
        ], ['X-Payout-Idempotency' => $idempotencyKey]);
    }

    /** the payout the panel made (found by our reference in its notes) - null when Razorpay has none */
    public function findPanelPayout(string $fundAccountId, string $panelRef, ?int $fromUnix = null): ?array
    {
        $s = $this->settings();
        $res = $this->call('GET', '/payouts', array_filter(['account_number' => $s->account_number, 'fund_account_id' => $fundAccountId, 'from' => $fromUnix, 'count' => 100], fn ($v) => $v !== null));
        foreach ((array) ($res['items'] ?? []) as $po) {
            if (is_array($po) && (string) data_get($po, 'notes.panel_payout') === $panelRef) {
                return $po;
            }
        }

        return null;
    }

    public function fetchPayout(string $payoutId): array
    {
        return $this->call('GET', '/payouts/'.rawurlencode($payoutId));
    }

    /** bank statement text: letters, digits, space; max 30 */
    public static function narration(string $text): string
    {
        $t = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', $text)));

        return mb_substr($t !== '' ? $t : 'Mixx Tune', 0, 30);
    }

    /** names: letters, digits, space and ' - _ / ( ) . ; not ending with another sign; min 3 */
    public static function cleanName(string $name, int $max): string
    {
        $n = trim(preg_replace('/\s+/', ' ', preg_replace("/[^A-Za-z0-9 '’\\-_\\/().]/u", ' ', $name)));
        $n = preg_replace('/^[^A-Za-z0-9]+/', '', $n);
        $n = rtrim(mb_substr($n, 0, $max), " '’-_/(");

        return $n;
    }

    /** a name Razorpay accepts (at least 3 letters / digits in English letters) */
    public static function nameOk(string $name): bool
    {
        return preg_match_all('/[A-Za-z0-9]/', self::cleanName($name, 120)) >= 3;
    }

    /**
     * @throws RazorpayError
     */
    private function call(string $method, string $path, array $data = [], array $headers = []): array
    {
        $s = $this->settings();
        if (!$this->configured($s)) {
            throw new RazorpayError('Razorpay is not set up yet (keys / account number missing).', 0, true);
        }
        try {
            $req = Http::withBasicAuth((string) $s->key_id, (string) $s->key_secret)
                ->acceptJson()
                ->withHeaders($headers)
                ->connectTimeout(10)
                ->timeout(30);
            $res = $method === 'GET' ? $req->get(self::BASE.$path, $data) : $req->post(self::BASE.$path, $data);
        } catch (ConnectionException $e) {
            throw new RazorpayError('Could not reach Razorpay: '.$e->getMessage(), 0, false);
        } catch (Throwable $e) {
            throw new RazorpayError('Razorpay call failed: '.$e->getMessage(), 0, false);
        }
        $json = $res->json();
        if ($res->successful() && is_array($json)) {
            return $json;
        }
        $desc = is_array($json) ? (string) data_get($json, 'error.description', '') : '';
        $code = $res->status();
        /* 4xx (except time-out / too many / conflict) = Razorpay refused it for sure; others = not sure */
        $definite = $code >= 400 && $code < 500 && !in_array($code, [408, 409, 425, 429], true);

        throw new RazorpayError(trim('Razorpay said '.$code.($desc !== '' ? ': '.$desc : '')), $code, $definite);
    }
}
