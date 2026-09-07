<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ArtistController extends Controller
{
    public function index(): Response
    {
        $artists = User::query()
            ->where('role', 'artist')
            ->latest()
            ->get()
            ->map(fn (User $artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'email' => $artist->email,
                'phone' => $artist->phone,
                'label_name' => $artist->label_name,
                'country' => $artist->country,
                'account_status' => $artist->account_status,
                'kyc_status' => $artist->kyc_status,
                'wallet_balance' => $artist->wallet_balance,
                'invitation_status' => $artist->invitation_status,
                'invitation_sent_at' => $artist->invitation_sent_at
                    ?->format('d M Y, h:i A'),
                'invitation_count' => $artist->invitation_count,
                'invitation_error' => $artist->invitation_error,
                'created_at' => $artist->created_at?->format('d M Y'),
            ]);

        return Inertia::render('Admin/Artists/Index', [
            'artists' => $artists,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Artists/Create');
    }

    public function store(
        Request $request,
        UserInvitationService $invitationService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'label_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'account_status' => [
                'required',
                Rule::in(['active', 'suspended']),
            ],
            'kyc_status' => [
                'required',
                Rule::in(['pending', 'verified', 'rejected']),
            ],
        ]);

        $artist = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'label_name' => $validated['label_name'] ?? null,
            'country' => $validated['country'] ?? null,
            'account_status' => $validated['account_status'],
            'kyc_status' => $validated['kyc_status'],
            'wallet_balance' => 0,
            'role' => 'artist',
            'email_verified_at' => null,

            // Database password column अभी required है।
            // यह password artist को पता नहीं होगा।
            'password' => Str::random(40),

            'invitation_status' => 'pending',
            'invitation_token' => null,
            'invitation_sent_at' => null,
            'invitation_expires_at' => null,
            'invitation_count' => 0,
            'invitation_error' => null,
        ]);

        try {
            $invitationService->send($artist);

            return redirect()
                ->route('admin.artists.index')
                ->with('success', 'Artist invitation sent successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.artists.index')
                ->withErrors([
                    'invitation' => 'Artist created, but the invitation email failed.',
                ]);
        }
    }

    public function resendInvitation(
        User $artist,
        UserInvitationService $invitationService
    ): RedirectResponse
    {
        abort_unless($artist->role === 'artist', 404);

        if ($artist->invitation_status === 'active') {
            return back()->withErrors([
                'invitation' => 'This artist account is already active.',
            ]);
        }

        if (
            $artist->invitation_sent_at &&
            $artist->invitation_sent_at->gt(now()->subMinutes(2))
        ) {
            return back()->withErrors([
                'invitation' => 'Please wait 2 minutes before resending.',
            ]);
        }

        try {
            $invitationService->send($artist);

            return back()->with(
                'success',
                'Invitation resent successfully.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'invitation' => 'Invitation could not be resent.',
            ]);
        }
    }

    public function edit(User $artist): Response
    {
        abort_unless($artist->role === 'artist', 404);

        return Inertia::render('Admin/Artists/Edit', [
            'artist' => [
                'id' => $artist->id,
                'name' => $artist->name,
                'email' => $artist->email,
                'phone' => $artist->phone,
                'label_name' => $artist->label_name,
                'country' => $artist->country,
                'account_status' => $artist->account_status,
                'kyc_status' => $artist->kyc_status,
            ],
        ]);
    }

    public function update(
        Request $request,
        User $artist
    ): RedirectResponse {
        abort_unless($artist->role === 'artist', 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($artist->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'label_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'account_status' => [
                'required',
                Rule::in(['active', 'suspended']),
            ],
            'kyc_status' => [
                'required',
                Rule::in(['pending', 'verified', 'rejected']),
            ],
        ]);

        $artist->update($validated);

        return redirect()
            ->route('admin.artists.index')
            ->with('success', 'Artist updated successfully.');
    }

    public function destroy(User $artist): RedirectResponse
    {
        abort_unless($artist->role === 'artist', 404);

        $artist->delete();

        return redirect()
            ->route('admin.artists.index')
            ->with('success', 'Artist deleted successfully.');
    }
}
