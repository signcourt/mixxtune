<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Services\V2\PermissionService;
use App\Services\V2\SupportDesk;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalOperationsController extends Controller
{
    /* W73 MIXXTUNE_LEGAL_OPS_V2: hub with the user's own case counts and latest cases */
    public function index(Request $request, PermissionService $permissions): Response
    {
        $base = SupportDesk::scopeLegal(SupportTicket::query()->where('user_id', $request->user()->id));

        $perSection = [];
        foreach (array_keys(SupportDesk::LEGAL_SECTIONS) as $key) {
            $perSection[$key] = SupportDesk::scopeLegal(SupportTicket::query()->where('user_id', $request->user()->id), $key)
                ->where('status', '!=', 'closed')
                ->count();
        }

        return Inertia::render('V2/LegalOperations/Index', [
            'role' => $permissions->role($request->user()),
            'counts' => [
                'open' => (clone $base)->where('status', '!=', 'closed')->count(),
                'your_turn' => (clone $base)->whereIn('status', ['admin_reply', 'waiting'])->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
                'sections' => $perSection,
            ],
            'recent' => (clone $base)
                ->orderByDesc('last_reply_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'ticket_number', 'subject', 'status', 'last_reply_at', 'created_at'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'subject' => $t->subject,
                    'section' => SupportDesk::legalSectionOf($t->subject),
                    'status' => $t->status,
                    'updated' => optional($t->last_reply_at ?? $t->created_at)->timezone('Asia/Kolkata')->format('d M Y'),
                ])
                ->values(),
        ]);
    }
}
