<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());

        $admins = User::query()
            ->where('role', 'admin')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->withCount([
                'assignedLabels',
                'assignedArtists',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/AdminManagement/Index', [
            'admins' => $admins,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Admin created successfully.');
    }

    public function toggleStatus(User $admin)
    {
        abort_unless($admin->role === 'admin', 404);

        abort_if(
            $admin->id === auth()->id(),
            422,
            'You cannot disable your own account.'
        );

        $admin->update([
            'account_status' =>
                $admin->account_status === 'active'
                    ? 'suspended'
                    : 'active',
        ]);

        return back()->with('success', 'Admin status updated.');
    }

    public function destroy(User $admin)
    {
        abort_unless($admin->role === 'admin', 404);

        abort_if(
            $admin->id === auth()->id(),
            422,
            'You cannot delete your own account.'
        );

        $admin->delete();

        return back()->with('success', 'Admin deleted successfully.');
    }
}
