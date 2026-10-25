<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\AccountAgreement;
use App\Models\User;
use App\Services\V2\AccountAgreements;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * W94 MIXXTUNE_ACCOUNT_AGREEMENTS
 * Super Admin records the signed agreement of a Label / Artist account
 * (signed on + 1-10 years or months), edits it, renews it or marks it
 * "not renewing". The dashboard pop-up warns from 90 days before the end.
 */
class AccountAgreementController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can manage agreements.');
    }

    private function rules(?string $latest = null): array
    {
        /* a renewal may start after the current agreement ends: up to a year after that end */
        $max = max(AccountAgreements::today()->addYear()->toDateString(), (string) $latest);

        return [
            'signed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.$max],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }

    private function messages(): array
    {
        return [
            'signed_on.required' => 'Enter the date the agreement was signed.',
            'signed_on.before_or_equal' => 'The signed date is too far ahead.',
            'term_months.required' => 'Choose how long the agreement runs.',
            'term_months.max' => 'An agreement can run for at most 10 years.',
            'document.mimes' => 'The signed copy must be a PDF, JPG or PNG.',
            'document.max' => 'The signed copy can be at most 20 MB.',
        ];
    }

    private function storeDocument(Request $request, User $user): array
    {
        if (!$request->hasFile('document')) {
            return [];
        }
        $file = $request->file('document');

        return [
            'document_path' => $file->store('agreements/'.$user->id, 'local'),
            'document_name' => mb_substr($file->getClientOriginalName(), 0, 255),
        ];
    }

    private function name(User $user): string
    {
        return $user->name ?: $user->email;
    }

    public function store(Request $request, User $user, PermissionService $permissions, AccountAgreements $agreements, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        abort_unless(in_array($user->role, AccountAgreements::ROLES, true), 422, 'Agreements are kept for Label and Artist accounts.');
        $data = $request->validate($this->rules(), $this->messages());

        if ($agreements->active((int) $user->id)) {
            return back()->withErrors(['agreement' => 'This account already has an agreement. Use Edit or Renew.']);
        }

        $a = AccountAgreement::query()->create([
            'user_id' => $user->id,
            'signed_on' => $data['signed_on'],
            'term_months' => (int) $data['term_months'],
            'ends_on' => AccountAgreements::endsOn($data['signed_on'], (int) $data['term_months'])->toDateString(),
            'notice_days' => AccountAgreements::NOTICE_DAYS,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ] + $this->storeDocument($request, $user));

        $audit->record('agreement.created', 'agreements', 'Agreement saved for '.$this->name($user).': signed '.$a->signed_on->toDateString().' for '.AccountAgreements::termText($a->term_months).', ends '.$a->ends_on->toDateString().'.', [], $agreements->row($a), $a, $request);

        return back()->with('success', 'Agreement saved. It ends on '.$a->ends_on->format('d M Y').'.');
    }

    public function update(Request $request, AccountAgreement $agreement, PermissionService $permissions, AccountAgreements $agreements, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        abort_unless($agreement->status === 'active', 422, 'Only the current agreement can be changed.');
        /* a renewed agreement may start in the future: keep the same room as for its renewal */
        $data = $request->validate($this->rules($agreement->signed_on->copy()->addYear()->toDateString()), $this->messages());
        $user = User::query()->findOrFail($agreement->user_id);
        $before = $agreements->row($agreement);

        $agreement->fill([
            'signed_on' => $data['signed_on'],
            'term_months' => (int) $data['term_months'],
            'ends_on' => AccountAgreements::endsOn($data['signed_on'], (int) $data['term_months'])->toDateString(),
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ] + $this->storeDocument($request, $user))->save();

        $audit->record('agreement.updated', 'agreements', 'Agreement of '.$this->name($user).' changed.', $before, $agreements->row($agreement), $agreement, $request);

        return back()->with('success', 'Agreement updated. It ends on '.$agreement->ends_on->format('d M Y').'.');
    }

    public function renew(Request $request, User $user, PermissionService $permissions, AccountAgreements $agreements, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $old = $agreements->active((int) $user->id);
        if (!$old) {
            return back()->withErrors(['agreement' => 'There is no current agreement to renew. Add a new one.']);
        }
        $data = $request->validate($this->rules($old->ends_on->copy()->addYear()->toDateString()), $this->messages());

        $new = DB::transaction(function () use ($old, $user, $data, $request) {
            $old->forceFill(['status' => 'renewed', 'closed_at' => now(), 'closed_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();

            return AccountAgreement::query()->create([
                'user_id' => $user->id,
                'signed_on' => $data['signed_on'],
                'term_months' => (int) $data['term_months'],
                'ends_on' => AccountAgreements::endsOn($data['signed_on'], (int) $data['term_months'])->toDateString(),
                'notice_days' => AccountAgreements::NOTICE_DAYS,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'renewed_from_id' => $old->id,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ] + $this->storeDocument($request, $user));
        });

        $audit->record('agreement.renewed', 'agreements', 'Agreement of '.$this->name($user).' renewed: now ends '.$new->ends_on->toDateString().' (was '.$old->ends_on->toDateString().').', $agreements->row($old), $agreements->row($new), $new, $request);

        return back()->with('success', 'Agreement renewed. The new one ends on '.$new->ends_on->format('d M Y').'.');
    }

    public function end(Request $request, User $user, PermissionService $permissions, AccountAgreements $agreements, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $a = $agreements->active((int) $user->id);
        if (!$a) {
            return back()->withErrors(['agreement' => 'There is no current agreement.']);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']], ['reason.required' => 'Write why the agreement is not renewed.']);

        $a->forceFill(['status' => 'ended', 'closed_at' => now(), 'closed_by' => $request->user()->id, 'close_reason' => trim($data['reason']), 'updated_by' => $request->user()->id])->save();
        $audit->record('agreement.not_renewed', 'agreements', 'Agreement of '.$this->name($user).' marked as not renewing: '.$a->close_reason, [], $agreements->row($a), $a, $request);

        return back()->with('success', 'Marked as not renewing. It will not be shown on the dashboard any more.');
    }

    public function document(Request $request, AccountAgreement $agreement, PermissionService $permissions): StreamedResponse
    {
        $this->superAdmin($request, $permissions);
        abort_unless($agreement->document_path && Storage::disk('local')->exists($agreement->document_path), 404, 'The signed copy is missing.');

        return Storage::disk('local')->download($agreement->document_path, $agreement->document_name ?: 'agreement-'.$agreement->id);
    }

    /* "Remind me tomorrow" on the dashboard pop-up */
    public function seen(Request $request, PermissionService $permissions, AccountAgreements $agreements): JsonResponse
    {
        $this->superAdmin($request, $permissions);
        $agreements->markSeen($request->user());

        return response()->json(['ok' => true]);
    }
}
