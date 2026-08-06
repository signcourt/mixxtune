<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionService $permissionService
    ) {
        $user = $request->user();
        $role = $user->role ?? 'artist';

        $query = DB::table('releases')
            ->whereNull('deleted_at');

        if ($role === 'artist') {
            $artist = DB::table('artists')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            if ($artist) {
                $query->where('artist_id', $artist->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($role === 'label') {
            $label = DB::table('labels')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            if ($label) {
                $query->where('label_id', $label->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $totalReleases = (clone $query)->count();
        $submittedReleases = (clone $query)
            ->where('status', 'submitted')
            ->count();
        $approvedReleases = (clone $query)
            ->where('status', 'approved')
            ->count();

        $recentReleases = (clone $query)
            ->orderByDesc('id')
            ->limit(8)
            ->get([
                'id',
                'title',
                'primary_artist_name',
                'status',
                'created_at',
            ]);

        $panelName = match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'label' => 'Label',
            default => 'Artist',
        };

        $quickActions = match ($role) {
            'super_admin', 'admin' => [
                ['label' => 'Review Releases', 'href' => '/release-reviews'],
                ['label' => 'Create Release', 'href' => '/releases/create'],
            ],
            'label', 'artist' => [
                ['label' => 'Create Release', 'href' => '/releases/create'],
            ],
            default => [],
        };

        return Inertia::render('V2/Dashboard', [
            'role' => $role,
            'permissions' =>
                $permissionService->permissions($user),
            'permissions' =>
                $permissionService->permissions($user),
            'panelName' => $panelName,
            'stats' => [
                'totalReleases' => $totalReleases,
                'submittedReleases' => $submittedReleases,
                'approvedReleases' => $approvedReleases,
                'walletBalance' => '₹0.00',
            ],
            'recentReleases' => $recentReleases,
            'quickActions' => $quickActions,
        ]);
    }
}
