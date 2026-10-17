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
        $rules = [
            'label_ids' => ['required', 'array', 'min:1', 'max:500'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('labels', 'id')->whereNull('deleted_at')],
            'mode' => ['required', 'in:existing,new'],
            'target_user_id' => ['nullable', 'required_if:mode,existing', 'integer', Rule::exists('users', 'id')],
            'new_name' => ['nullable', 'required_if:mode,new', 'string', 'max:255'],
            'new_email' => ['nullable', 'required_if:mode,new', 'email', 'max:255', Rule::unique('users', 'email')],
            'new_phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($withOtp) {
            $rules['otp'] = ['required', 'digits:6'];
        }

        $v = $request->validate($rules);
        $ids = array_values(array_unique(array_map('intval', $v['label_ids'])));
        $targetId = $v['mode'] === 'existing' ? (int) $v['target_user_id'] : null;
        $new = $v['mode'] === 'new'
            ? ['name' => $v['new_name'], 'email' => $v['new_email'], 'phone' => $v['new_phone'] ?? '']
            : null;

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
            $description = 'NEW: '.trim((string) $new['name']).' <'.strtolower(trim((string) $new['email'])).'>';
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
