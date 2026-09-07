<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalOperationsController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        return Inertia::render(
            'V2/LegalOperations/Index',
            [
                'role' => $permissions->role(
                    $request->user()
                ),
            ]
        );
    }
}
