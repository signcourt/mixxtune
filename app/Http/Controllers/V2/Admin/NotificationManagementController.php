<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\PanelNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationManagementController extends Controller
{
    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless(
            $request->user()
                && $request->user()->role === 'super_admin',
            403
        );
    }

    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $countries = User::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->values();

        $states = User::query()
            ->whereNotNull('state_code')
            ->where('state_code', '!=', '')
            ->distinct()
            ->orderBy('state_code')
            ->pluck('state_code')
            ->values();

        $recent = PanelNotification::query()
            ->where('type', 'like', 'broadcast.%')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->groupBy(function ($item) {
                return data_get(
                    $item->data,
                    'broadcast_id',
                    $item->public_id
                );
            })
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'broadcast_id' =>
                        data_get(
                            $first->data,
                            'broadcast_id',
                            $first->public_id
                        ),

                    'title' =>
                        $first->title,

                    'message' =>
                        $first->message,

                    'severity' =>
                        $first->severity,

                    'target_type' =>
                        data_get(
                            $first->data,
                            'target_type',
                            'unknown'
                        ),

                    'target_value' =>
                        data_get(
                            $first->data,
                            'target_value'
                        ),

                    'recipients' =>
                        $items->count(),

                    'created_at' =>
                        optional(
                            $first->created_at
                        )->format('d M Y, h:i A'),
                ];
            })
            ->values()
            ->take(20);

        return Inertia::render(
            'V2/Admin/Notifications/Index',
            [
                'countries' => $countries,
                'states' => $states,
                'recentBroadcasts' => $recent,
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],

            'category' => [
                'required',
                Rule::in([
                    'primary',
                    'announcement',
                    'promotion',
                ]),
            ],

            'severity' => [
                'required',
                Rule::in([
                    'info',
                    'success',
                    'warning',
                    'danger',
                ]),
            ],

            'action_url' => [
                'nullable',
                'string',
                'max:500',
            ],

            'target_type' => [
                'required',
                Rule::in([
                    'all',
                    'role',
                    'country',
                    'state',
                    'client_id',
                ]),
            ],

            'target_value' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        if (
            $validated['target_type'] !== 'all'
            && blank($validated['target_value'] ?? null)
        ) {
            return back()
                ->withErrors([
                    'target_value' =>
                        'Please select or enter a target.',
                ])
                ->withInput();
        }

        $query = User::query()
            ->where('account_status', 'active');

        switch ($validated['target_type']) {
            case 'role':
                $query->where(
                    'role',
                    $validated['target_value']
                );
                break;

            case 'country':
                $query->where(
                    'country',
                    $validated['target_value']
                );
                break;

            case 'state':
                $query->where(
                    'state_code',
                    $validated['target_value']
                );
                break;

            case 'client_id':
                $query->where(
                    'client_id',
                    $validated['target_value']
                );
                break;
        }

        $users = $query
            ->select('id')
            ->get();

        if ($users->isEmpty()) {
            return back()
                ->withErrors([
                    'target_value' =>
                        'No active users matched this target.',
                ])
                ->withInput();
        }

        $broadcastId = (string) Str::ulid();
        $now = now();

        $rows = $users
            ->map(function ($user) use (
                $validated,
                $broadcastId,
                $now
            ) {
                return [
                    'public_id' =>
                        (string) Str::ulid(),

                    'user_id' =>
                        $user->id,

                    'type' =>
                        'broadcast.manual',

                    'title' =>
                        $validated['title'],

                    'message' =>
                        $validated['message'],

                    'action_url' =>
                        $validated['action_url']
                        ?: null,

                    'severity' =>
                        $validated['severity'],
                    
                    'category' =>
                        $validated['category'],

                    'related_type' =>
                        null,

                    'related_id' =>
                        null,

                    'data' =>
                        json_encode([
                            'broadcast_id' =>
                                $broadcastId,

                            'target_type' =>
                                $validated['target_type'],

                            'target_value' =>
                                $validated['target_value']
                                ?? null,

                            'sent_by' =>
                                $request->user()->id,
                        ]),

                    'read_at' =>
                        null,

                    'dismissed_at' =>
                        null,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ];
            })
            ->all();

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('panel_notifications')
                    ->insert($chunk);
            }
        });

        return back()->with(
            'success',
            count($rows)
                . ' notification(s) sent successfully.'
        );
    }
}
