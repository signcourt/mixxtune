<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** W228 - Super Admin's controls over one distributor (hold the tree, limits, Manager, agreement file). */
class DistributorControlController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless((string) $request->user()?->role === 'super_admin', 403, 'Only a Super Admin manages distributors.');
        abort_unless(DistributorControlService::ready(), 503, 'Distributor controls are not set up yet.');
    }

    private function save(Distributor $d, array $values, Request $request): void
    {
        $values['updated_by'] = $request->user()->id;
        $values['updated_at'] = now();
        if (DB::table('distributor_controls')->where('distributor_id', $d->id)->exists()) {
            DB::table('distributor_controls')->where('distributor_id', $d->id)->update($values);
        } else {
            DB::table('distributor_controls')->insert($values + ['distributor_id' => $d->id, 'created_at' => now()]);
        }
        DistributorControlService::forget();
    }

    private function audit(string $action, string $text, array $new, Distributor $d, Request $request): void
    {
        try {
            app(AuditLogService::class)->record($action, 'labels', $text, [], $new, $d, $request);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function name(Distributor $d): string
    {
        return trim((string) $d->brand_name) ?: $d->slug;
    }

    /** limits + notes */
    public function limits(Request $request, Distributor $distributor): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'max_sub_labels' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_monthly_releases' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $values = [
            'max_sub_labels' => ($data['max_sub_labels'] ?? null) === null || $data['max_sub_labels'] === '' ? null : (int) $data['max_sub_labels'],
            'max_monthly_releases' => ($data['max_monthly_releases'] ?? null) === null || $data['max_monthly_releases'] === '' ? null : (int) $data['max_monthly_releases'],
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
        $this->save($distributor, $values, $request);
        $this->audit('distributor.limits', 'Limits of distributor "'.$this->name($distributor).'" saved.', $values, $distributor, $request);

        return back()->with('success', 'Limits saved for "'.$this->name($distributor).'".');
    }

    /** put the whole tree on hold / lift it (e-mailed code) */
    public function hold(Request $request, Distributor $distributor, DistributorControlService $controls): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'hold' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:300', 'required_if:hold,true,1', 'min:3'],
        ], ['reason.required_if' => 'Write why the tree is put on hold.']);
        $hold = (bool) $data['hold'];
        $now = (bool) ($controls->row((int) $distributor->id)->tree_hold ?? false);
        if ($hold === $now) {
            return back()->with('error', $hold ? 'This tree is already on hold.' : 'This tree is not on hold.');
        }

        app(ActionOtpGuard::class)->require($request->user(), 'distributor.hold:'.$distributor->id.':'.($hold ? 1 : 0), ($hold ? 'put the whole tree of "' : 'lift the hold on "').$this->name($distributor).'"'.($hold ? ' on hold (nobody of it can log in)' : ''));

        $this->save($distributor, ['tree_hold' => $hold, 'hold_reason' => $hold ? trim((string) $data['reason']) : null, 'hold_at' => $hold ? now() : null], $request);
        $this->audit($hold ? 'distributor.tree_hold' : 'distributor.tree_release', ($hold ? 'Whole tree of distributor "' : 'Hold lifted for distributor "').$this->name($distributor).'"'.($hold ? ' put on hold: '.trim((string) $data['reason']) : '.'), ['tree_hold' => $hold], $distributor, $request);

        return back()->with('success', $hold
            ? 'The whole tree of "'.$this->name($distributor).'" is on hold. The distributor, his sub-labels and their staff are signed out on their next click. Nothing of their data is changed.'
            : 'Hold lifted. The tree of "'.$this->name($distributor).'" can log in again.');
    }

    /** put a Manager over the distributor and every sub-label (rows are only added) */
    public function manager(Request $request, Distributor $distributor, DistributorControlService $controls): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['manager_id' => ['required', 'integer']]);
        $manager = DB::table('users')->where('id', (int) $data['manager_id'])->where('role', 'admin')->first(['id', 'name', 'account_status']);
        if (!$manager) {
            return back()->withErrors(['manager_id' => 'Choose a Manager account.']);
        }

        DB::table('admin_label_assignments')->insertOrIgnore([
            'user_id' => $manager->id, 'label_id' => $distributor->label_id, 'assignment_role' => 'manager',
            'can_view' => true, 'can_edit' => true, 'can_manage_releases' => true, 'can_manage_team' => false, 'can_manage_splits' => true,
            'assigned_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $added = $controls->syncManagers($distributor, (int) $request->user()->id);
        $this->audit('distributor.manager_assigned', 'Manager "'.$manager->name.'" put over distributor "'.$this->name($distributor).'" and his sub-labels.', ['manager_id' => (int) $manager->id, 'sub_labels_added' => $added], $distributor, $request);

        return back()->with('success', '"'.$manager->name.'" is now a Manager of "'.$this->name($distributor).'" and his sub-labels. To take a Manager off, use Accounts → Managers.');
    }

    /** agreement file (PDF) - kept in private storage, only a Super Admin can open it */
    public function agreement(Request $request, Distributor $distributor, DistributorControlService $controls): RedirectResponse
    {
        $this->guard($request);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:15360']], ['file.mimes' => 'Upload a PDF file.', 'file.mimetypes' => 'Upload a PDF file.', 'file.max' => 'The file may be 15 MB at most.']);
        $file = $request->file('file');
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 5);
        if ($head !== '%PDF-') {
            return back()->withErrors(['file' => 'This is not a PDF file.']);
        }

        /* an older file is kept on disk (nothing is deleted); the newest one is the current agreement */
        $path = $file->storeAs('distributor-agreements/'.$distributor->id, now()->format('Ymd-His').'-'.Str::random(8).'.pdf', 'local');
        $name = mb_substr(preg_replace('/[^\w .()\-]+/u', '_', (string) $file->getClientOriginalName()) ?: 'agreement.pdf', 0, 180);
        $this->save($distributor, ['agreement_path' => $path, 'agreement_name' => $name, 'agreement_at' => now()], $request);
        $this->audit('distributor.agreement_uploaded', 'Agreement uploaded for distributor "'.$this->name($distributor).'".', ['file' => $name], $distributor, $request);

        return back()->with('success', 'Agreement saved for "'.$this->name($distributor).'".');
    }

    public function agreementDownload(Request $request, Distributor $distributor, DistributorControlService $controls): StreamedResponse
    {
        $this->guard($request);
        $c = $controls->row((int) $distributor->id);
        abort_unless($c && $c->agreement_path && str_starts_with((string) $c->agreement_path, 'distributor-agreements/'.$distributor->id.'/') && Storage::disk('local')->exists($c->agreement_path), 404);

        return Storage::disk('local')->download($c->agreement_path, $c->agreement_name ?: 'agreement.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }
}
