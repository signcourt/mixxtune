<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SidebarPreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        // Label and Artist navigation is intentionally fixed.
        abort_if(
            in_array($user->role, ['label', 'artist'], true),
            403,
            'Sidebar ordering is fixed for this account type.'
        );

        $validated = $request->validate([
            'order' => [
                'required',
                'array',
                'max:100',
            ],

            'order.*' => [
                'required',
                'string',
                'max:100',
                'distinct',
            ],
        ]);

        $order = array_values(
            array_map(
                static fn ($id) => (string) $id,
                $validated['order']
            )
        );

        $user->sidebar_navigation_order = $order;
        $user->save();

        return response()->json([
            'saved' => true,
            'order' => $order,
        ]);
    }
}
