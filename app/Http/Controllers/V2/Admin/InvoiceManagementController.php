<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\AdminFinancialAccessService;
use App\Services\V2\InvoiceService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/*
 * MIXXTUNE_INVOICES_V2 (W42)
 * Before: an empty table with no message (nothing approved yet), the owner
 * shown as a bare ID number, raw dates / amounts, no way to open an
 * invoice, no paging, one click per statement, errors as a blank 422 page,
 * payout invoices (made when a withdrawal is paid) not visible here.
 * Now: "Ready for invoice" + "All invoices" tabs, owner names, month names,
 * search / month filter, why-empty help, one-click "make all missing",
 * PDF links, owner told when an invoice is made.
 */
class InvoiceManagementController extends Controller
{
    private const READY = ['approved', 'available', 'paid'];

    private function guard(Request $request, PermissionService $permissions): string
    {
        $role = $permissions->role($request->user());

        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403);

        return $role;
    }

    private function statements(Request $request, AdminFinancialAccessService $access, bool $filters = true)
    {
        $q = RoyaltyStatement::query()
            ->from('royalty_statements as s')
            ->leftJoin('labels as l', 'l.id', '=', 's.label_id')
            ->leftJoin('artists as a', 'a.id', '=', 's.artist_id')
            ->leftJoin('invoices as i', 'i.royalty_statement_id', '=', 's.id')
            ->whereIn('s.status', self::READY);

        $access->applyFinancialOwnerScope($q, $request->user(), 's.label_id', 's.artist_id');

        if (! $filters) {
            return $q;
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $q->where(fn ($w) => $w->where('l.name', 'like', $like)->orWhere('a.stage_name', 'like', $like)->orWhere('a.legal_name', 'like', $like));
        }

        $month = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $q->where('s.statement_month', 'like', $month.'%');
        }

        return $q;
    }

    public function index(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $access,
        InvoiceService $service
    ): Response {
        $role = $this->guard($request, $permissions);
        $tab = $request->query('tab') === 'invoices' ? 'invoices' : 'ready';
        $show = $request->query('show') === 'all' ? 'all' : 'missing';

        $base = $this->statements($request, $access);

        $counts = [
            'missing' => (clone $base)->whereNull('i.id')->count(),
            'invoiced' => (clone $base)->whereNotNull('i.id')->count(),
            'waiting_approval' => $access->applyFinancialOwnerScope(
                RoyaltyStatement::query()->whereNotIn('status', self::READY), $request->user()
            )->count(),
        ];

        $statements = (clone $base)
            ->when($show === 'missing', fn ($w) => $w->whereNull('i.id'))
            ->select([
                's.id', 's.statement_month', 's.currency', 's.gross_earnings', 's.commission_amount',
                's.other_deductions', 's.tax_amount', 's.net_payable', 's.status', 's.label_id', 's.artist_id',
                'l.name as label_name', 'a.stage_name as artist_name', 'a.legal_name as artist_legal',
                'i.id as invoice_id', 'i.invoice_number',
            ])
            ->orderByDesc('s.statement_month')
            ->orderByDesc('s.id')
            ->paginate(25, ['*'], 'page')
            ->withQueryString()
            ->through(fn ($s) => [
                'id' => $s->id,
                'month' => $s->statement_month,
                'month_label' => $service->monthLabel($s->statement_month),
                'owner' => $s->label_name ?: ($s->artist_name ?: ($s->artist_legal ?: '—')),
                'owner_type' => $s->label_id ? 'Label' : ($s->artist_id ? 'Artist' : '—'),
                'currency' => $s->currency ?: 'INR',
                'gross' => round((float) $s->gross_earnings, 2),
                'deductions' => round((float) $s->commission_amount + (float) $s->other_deductions + (float) $s->tax_amount, 2),
                'net' => round((float) $s->net_payable, 2),
                'status' => $s->status,
                'invoice_id' => $s->invoice_id,
                'invoice_number' => $s->invoice_number,
            ]);

        $months = $this->statements($request, $access, false)
            ->select('s.statement_month')->distinct()->orderByDesc('s.statement_month')->limit(24)
            ->pluck('s.statement_month')
            ->map(fn ($m) => ['value' => substr((string) $m, 0, 7), 'label' => $service->monthLabel($m)])
            ->unique('value')->values();

        $invoices = null;
        if ($tab === 'invoices') {
            $iq = Invoice::query()
                ->from('invoices as v')
                ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
                ->leftJoin('royalty_statements as rs', 'rs.id', '=', 'v.royalty_statement_id');

            if ($request->user()->role !== 'super_admin') {
                $iq->whereIn('v.user_id', $access->accessibleUserIds($request->user()));
            }

            $search = trim((string) $request->query('search', ''));
            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $iq->where(fn ($w) => $w->where('v.invoice_number', 'like', $like)->orWhere('u.name', 'like', $like)->orWhere('u.email', 'like', $like));
            }

            $hasNet = \Illuminate\Support\Facades\Schema::hasColumn('invoices', 'net_payable');
            $hasWd = \Illuminate\Support\Facades\Schema::hasColumn('invoices', 'withdrawal_id');

            $invoices = $iq->select(array_merge([
                'v.id', 'v.invoice_number', 'v.invoice_date', 'v.currency', 'v.total_amount', 'v.tds_amount', 'v.status',
                'v.royalty_statement_id', 'u.name as owner', 'u.email as owner_email', 'rs.statement_month',
            ], $hasNet ? ['v.net_payable'] : [], $hasWd ? ['v.withdrawal_id'] : []))
                ->orderByDesc('v.id')
                ->paginate(25, ['*'], 'page')
                ->withQueryString()
                ->through(fn ($v) => [
                    'id' => $v->id,
                    'number' => $v->invoice_number,
                    'date' => $v->invoice_date ? \Illuminate\Support\Carbon::parse($v->invoice_date)->format('d M Y') : '—',
                    'kind' => ($hasWd && $v->withdrawal_id) ? 'Payout' : 'Monthly royalty',
                    'month_label' => $v->statement_month ? $service->monthLabel($v->statement_month) : null,
                    'owner' => $v->owner ?: '—',
                    'owner_email' => $v->owner_email,
                    'currency' => $v->currency ?: 'INR',
                    'total' => round((float) $v->total_amount, 2),
                    'tds' => round((float) $v->tds_amount, 2),
                    'net' => round((float) ($hasNet && $v->net_payable !== null && (float) $v->net_payable > 0 ? $v->net_payable : (float) $v->total_amount - (float) $v->tds_amount), 2),
                    'status' => $v->status,
                ]);
        }

        return Inertia::render('V2/Admin/Invoices/Index', [
            'role' => $role,
            'tab' => $tab,
            'counts' => $counts + ['invoices' => $this->invoiceCount($request, $access)],
            'statements' => $statements,
            'invoices' => $invoices,
            'months' => $months,
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'month' => (string) $request->query('month', ''),
                'show' => $show,
                'tab' => $tab,
            ],
        ]);
    }

    private function invoiceCount(Request $request, AdminFinancialAccessService $access): int
    {
        $q = Invoice::query();
        if ($request->user()->role !== 'super_admin') {
            $q->whereIn('user_id', $access->accessibleUserIds($request->user()));
        }

        return $q->count();
    }

    public function generate(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions,
        InvoiceService $service,
        AdminFinancialAccessService $financialAccess
    ): RedirectResponse {
        $this->guard($request, $permissions);

        abort_unless(
            $financialAccess->canAccessFinancialOwner(
                $request->user(),
                $statement->label_id ? (int) $statement->label_id : null,
                $statement->artist_id ? (int) $statement->artist_id : null
            ),
            403,
            'Royalty statement outside assigned scope.'
        );

        if (! in_array($statement->status, self::READY, true)) {
            return back()->withErrors(['invoice' => 'This statement is not approved yet - approve it in Statements first.']);
        }

        $had = Invoice::query()->where('royalty_statement_id', $statement->id)->exists();

        try {
            $invoice = $service->generateFromStatement($statement, $request->user());
        } catch (HttpExceptionInterface $e) {
            return back()->withErrors(['invoice' => $this->why($e->getMessage())]);
        }

        if (! $had) {
            $this->tellOwner($invoice, $service, $request->boolean('notify_owner', true));
        }

        return back()->with('success', $had ? "Invoice {$invoice->invoice_number} already existed." : "Invoice {$invoice->invoice_number} generated.");
    }

    /** Make the invoice for every ready statement that has none (current filters, up to 200). */
    public function generateMissing(
        Request $request,
        PermissionService $permissions,
        InvoiceService $service,
        AdminFinancialAccessService $access
    ): RedirectResponse {
        $this->guard($request, $permissions);

        $ids = $this->statements($request, $access)->whereNull('i.id')->orderBy('s.id')->limit(200)->pluck('s.id');

        $made = 0;
        $failed = [];

        foreach (RoyaltyStatement::query()->whereIn('id', $ids)->get() as $statement) {
            try {
                if (Invoice::query()->where('royalty_statement_id', $statement->id)->exists()) {
                    continue;
                }
                $invoice = $service->generateFromStatement($statement, $request->user());
                $this->tellOwner($invoice, $service, $request->boolean('notify_owner', true));
                $made++;
            } catch (\Throwable $e) {
                $failed[] = '#'.$statement->id.' ('.$this->why($e->getMessage()).')';
            }
        }

        \App\Services\V2\FinanceAudit::log('finance.invoices_bulk_generated', "Bulk invoices: {$made} made, ".count($failed).' skipped.', ['made' => $made, 'failed' => $failed]);

        $msg = $made === 0 && ! $failed ? 'Nothing to make - every ready statement already has an invoice.' : "{$made} invoice(s) generated.";

        return $failed
            ? back()->with('success', $msg)->withErrors(['invoice' => count($failed).' statement(s) skipped: '.implode('; ', array_slice($failed, 0, 5))])
            : back()->with('success', $msg);
    }

    private function why(string $message): string
    {
        return str_contains($message, 'owner user is missing')
            ? 'Nobody owns this label / artist yet - link an owner account in Labels first.'
            : ($message ?: 'Could not make the invoice.');
    }

    private function tellOwner($invoice, InvoiceService $service, bool $notify): void
    {
        if (! $notify || ! $invoice?->user) {
            return;
        }

        try {
            app(\App\Services\V2\PanelNotificationService::class)->send(
                $invoice->user,
                'invoice.ready',
                'Invoice '.$invoice->invoice_number.' is ready',
                'Your royalty invoice for '.$service->monthLabel($invoice->statement?->statement_month)
                    .' ('.($invoice->currency ?: 'INR').' '.number_format((float) ($invoice->net_payable ?? $invoice->total_amount), 2).') can be downloaded from Invoices.',
                '/invoices'
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
