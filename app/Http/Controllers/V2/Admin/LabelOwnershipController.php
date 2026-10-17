<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\LabelOwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 * Super-admin only JSON endpoints used by the Labels page.
 */
class LabelOwnershipController extends Controller
{
    private const ATTRIBUTES = [
        'new_user.name' => 'owner name',
        'new_user.username' => 'username',
        'new_user.email' => 'owner e-mail',
        'new_user.phone' => 'phone',
        'new_user.state_code' => 'state',
        'new_user.state' => 'state',
        'new_user.address_line_1' => 'address line 1',
        'new_user.address_line_2' => 'address line 2',
        'new_user.city' => 'city',
        'new_user.postal_code' => 'pincode',
        'new_user.country_code' => 'country code',
        'new_user.revenue_share_percentage' => 'revenue %',
        'new_user.account_holder_name' => 'account holder name',
        'new_user.bank_account_number' => 'bank account number',
        'new_user.bank_name' => 'bank name',
        'new_user.ifsc_code' => 'IFSC code',
        'new_user.branch_name' => 'branch name',
        'new_user.upi_id' => 'UPI ID',
        'new_user.pan_number' => 'PAN',
        'new_user.gst_number' => 'GST',
    ];

    private function superAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'super_admin', 403);

        return $user;
    }

    public function users(Request $request): JsonResponse
    {
        $this->superAdmin($request);
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('role', 'label')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'email', 'account_status']);

        return response()->json(['users' => $users]);
    }

    /*
     * Label picker for the Labels page panel: search by name, optionally
     * only labels that have no owner yet.
     */
    public function labels(Request $request): JsonResponse
    {
        $this->superAdmin($request);
        $q = trim((string) $request->query('q', ''));
        $onlyUnowned = $request->boolean('unowned');

        $rows = DB::table('labels as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->whereNull('l.deleted_at')
            ->when($q !== '', fn ($query) => $query->where('l.name', 'like', '%'.$q.'%'))
            ->when($onlyUnowned, fn ($query) => $query->whereNull('l.user_id'))
            ->orderBy('l.name')
            ->limit(50)
            ->get(['l.id', 'l.name', 'u.name as owner_name', 'u.email as owner_email']);

        return response()->json([
            'labels' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'owner' => $r->owner_name ? $r->owner_name.' <'.$r->owner_email.'>' : null,
            ])->values(),
        ]);
    }

    private function validatedPayload(Request $request, bool $withOtp): array
    {
        $req = fn (string $max) => ['nullable', 'required_if:mode,new', 'string', $max];
        $opt = fn (string $max) => ['nullable', 'string', $max];

        $rules = [
            'label_ids' => ['required', 'array', 'min:1', 'max:500'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('labels', 'id')->whereNull('deleted_at')],
            'mode' => ['required', 'in:existing,new'],
            'target_user_id' => ['nullable', 'required_if:mode,existing', 'integer', Rule::exists('users', 'id')],
            'new_user' => ['nullable', 'required_if:mode,new', 'array'],
            'new_user.name' => $req('max:150'),
            'new_user.username' => $opt('max:40'),
            'new_user.email' => ['nullable', 'required_if:mode,new', 'email', 'max:190', Rule::unique('users', 'email')],
            'new_user.phone' => $opt('max:30'),
            'new_user.country' => $opt('max:100'),
            'new_user.country_code' => ['nullable', 'required_if:mode,new', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'new_user.state_code' => ['nullable', 'required_if:mode,new', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'new_user.state' => $req('max:120'),
            'new_user.address_line_1' => $req('max:255'),
            'new_user.address_line_2' => $opt('max:255'),
            'new_user.city' => $req('max:120'),
            'new_user.postal_code' => $req('max:30'),
            'new_user.revenue_share_percentage' => ['nullable', 'required_if:mode,new', 'numeric', 'min:0', 'max:100'],
            'new_user.account_holder_name' => $req('max:190'),
            'new_user.bank_account_number' => $req('max:60'),
            'new_user.bank_name' => $req('max:190'),
            'new_user.ifsc_code' => $req('max:30'),
            'new_user.branch_name' => $opt('max:190'),
            'new_user.upi_id' => $opt('max:190'),
            'new_user.pan_number' => $req('max:30'),
            'new_user.gst_number' => $opt('max:40'),
        ];

        if ($withOtp) {
            $rules['otp'] = ['required', 'digits:6'];
        }

        $v = $request->validate($rules, [], self::ATTRIBUTES);
        $ids = array_values(array_unique(array_map('intval', $v['label_ids'])));
        $targetId = $v['mode'] === 'existing' ? (int) $v['target_user_id'] : null;
        $new = $v['mode'] === 'new' ? (array) ($v['new_user'] ?? []) : null;

        return [$ids, $targetId, $new, $v];
    }

    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }

    public function sendOtp(Request $request, LabelOwnershipService $service): JsonResponse
    {
        $admin = $this->superAdmin($request);
        [$ids, $targetId, $new] = $this->validatedPayload($request, false);

        if ($targetId) {
            $target = User::query()->find($targetId);

            if (!$target || $target->role !== 'label') {
                return response()->json([
                    'message' => 'Owner must be a Label account.',
                    'errors' => ['target_user_id' => ['Owner must be a Label account.']],
                ], 422);
            }

            $description = $target->name.' <'.$target->email.'>';
        } else {
            $normalized = $service->normalizeNewUser($new);
            $description = 'NEW: '.$normalized['name'].' <'.$normalized['email'].'>';
        }

        $service->sendOtp($admin, $service->fingerprint($ids, $targetId, $new), count($ids), $description);

        return response()->json([
            'ok' => true,
            'sent_to' => $this->mask((string) $admin->email),
            'target' => $description,
            'preview' => $service->preview($ids),
            'expires_minutes' => LabelOwnershipService::EXPIRY_MINUTES,
        ]);
    }

    public function apply(Request $request, LabelOwnershipService $service): JsonResponse
    {
        $admin = $this->superAdmin($request);
        [$ids, $targetId, $new, $v] = $this->validatedPayload($request, true);

        if (!$service->verifyOtp($admin, $service->fingerprint($ids, $targetId, $new), (string) $v['otp'])) {
            return response()->json([
                'message' => 'Invalid or expired confirmation code. Please request a new code.',
                'errors' => ['otp' => ['Invalid or expired confirmation code.']],
            ], 422);
        }

        return response()->json(['ok' => true] + $service->apply($admin, $ids, $targetId, $new, $request));
    }
}
