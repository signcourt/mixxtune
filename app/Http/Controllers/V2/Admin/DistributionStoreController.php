<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistributionStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DistributionStoreController extends Controller
{
    private function authorizeAdmin(
        Request $request
    ): void {
        abort_unless(
            in_array(
                $request->user()?->role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );
    }

    /* W58: adding / changing / switching off / deleting a store is super admin only */
    private function authorizeChange(Request $request): void
    {
        abort_unless($request->user()?->role === 'super_admin', 403, 'Only the super admin can change stores.');
    }

    private function audit(string $action, string $description, array $old = [], array $new = [], $store = null): void
    {
        try {
            app(\App\Services\V2\AuditLogService::class)->record($action, 'delivery', $description, $old, $new, $store, request());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /* W58: a slug made from the name must also be unique ("Spotify " next to "spotify") */
    private function uniqueSlug(string $wanted, ?int $exceptId = null): string
    {
        $taken = DistributionStore::query()->where('slug', $wanted)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists();

        if ($taken) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => 'A store with almost the same name already exists ('.$wanted.').']);
        }

        return $wanted;
    }

    public function index(
        Request $request
    ): Response {
        $this->authorizeAdmin($request);

        $stores = DistributionStore::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'V2/Admin/Stores/Index',
            [
                'role' =>
                    $request->user()->role,

                'stores' =>
                    $stores,

                'stats' => [
                    'total' =>
                        $stores->count(),

                    'active' =>
                        $stores
                            ->where(
                                'is_active',
                                true
                            )
                            ->count(),

                    'inactive' =>
                        $stores
                            ->where(
                                'is_active',
                                false
                            )
                            ->count(),

                    'default_selected' =>
                        $stores
                            ->where(
                                'default_selected',
                                true
                            )
                            ->count(),
                ],
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $this->authorizeChange($request);

        $validated =
            $this->validateStore($request);

        $logoPath = null;

        if ($request->hasFile('logo')) {
            $logoPath =
                $request
                    ->file('logo')
                    ->store(
                        'distribution-stores',
                        'public'
                    );
        }

        $nextOrder =
            (
                DistributionStore::query()
                    ->max('sort_order')
                ?? 0
            ) + 1;

        DistributionStore::create([
            'name' =>
                $validated['name'],

            'slug' =>
                $validated['slug']
                ?: $this->uniqueSlug(Str::slug(
                    $validated['name']
                )),

            'logo_path' =>
                $logoPath,

            'is_active' =>
                $validated['is_active'],

            'default_selected' =>
                $validated[
                    'default_selected'
                ],

            'sort_order' =>
                $validated['sort_order']
                ?? $nextOrder,
        ]);

        $this->audit('delivery.store_added', 'Store "'.$validated['name'].'" added.', [], ['name' => $validated['name'], 'active' => (bool) $validated['is_active']]);

        return back()->with(
            'success',
            'DSP added successfully.'
        );
    }

    public function update(
        Request $request,
        DistributionStore $distributionStore
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $this->authorizeChange($request);

        $before = $distributionStore->only(['name', 'slug', 'is_active', 'default_selected', 'sort_order']);

        $validated =
            $this->validateStore(
                $request,
                $distributionStore
            );

        if ($request->hasFile('logo')) {
            if (
                $distributionStore->logo_path
            ) {
                Storage::disk('public')
                    ->delete(
                        $distributionStore
                            ->logo_path
                    );
            }

            $validated['logo_path'] =
                $request
                    ->file('logo')
                    ->store(
                        'distribution-stores',
                        'public'
                    );
        }

        unset($validated['logo']);

        $validated['slug'] =
            $validated['slug']
            ?: $this->uniqueSlug(Str::slug(
                $validated['name']
            ), (int) $distributionStore->id);

        $distributionStore->update(
            $validated
        );

        $this->audit('delivery.store_updated', 'Store "'.$distributionStore->name.'" changed.', $before, $distributionStore->only(['name', 'slug', 'is_active', 'default_selected', 'sort_order']), $distributionStore);

        return back()->with(
            'success',
            'DSP updated successfully.'
        );
    }

    public function toggle(
        Request $request,
        DistributionStore $distributionStore
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $this->authorizeChange($request);

        $distributionStore->update([
            'is_active' =>
                !$distributionStore
                    ->is_active,
        ]);

        $waiting = \App\Models\ReleaseStoreDelivery::query()->where('distribution_store_id', $distributionStore->id)->whereIn('status', ['pending', 'processing'])->count();

        $this->audit('delivery.store_toggled', 'Store "'.$distributionStore->name.'" '.($distributionStore->is_active ? 'switched on' : 'switched off').'.', [], ['active' => (bool) $distributionStore->is_active, 'waiting_deliveries' => $waiting], $distributionStore);

        if (!$distributionStore->is_active && $waiting > 0) {
            return back()->with('success', 'DSP deactivated. New releases will not be sent to it. '.$waiting.' delivery(ies) already waiting for this store were left as they are - finish or mark them failed on the Distribution page.');
        }

        return back()->with(
            'success',
            $distributionStore->is_active
                ? 'DSP activated successfully.'
                : 'DSP deactivated successfully.'
        );
    }

    public function destroy(
        Request $request,
        DistributionStore $distributionStore
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $this->authorizeChange($request);

        /*
         * W58: deleting a store used to delete its whole delivery history
         * (cascade). A store that was ever used can only be switched off.
         */
        $used = \App\Models\ReleaseStoreDelivery::query()->where('distribution_store_id', $distributionStore->id)->count();

        if ($used > 0) {
            return back()->withErrors([
                'store' => '"'.$distributionStore->name.'" has '.$used.' delivery record(s), so it cannot be deleted (that would erase the history). Switch it off instead.',
            ]);
        }

        $this->audit('delivery.store_deleted', 'Store "'.$distributionStore->name.'" deleted (never used).', $distributionStore->only(['name', 'slug']), []);

        if (
            $distributionStore->logo_path
        ) {
            Storage::disk('public')
                ->delete(
                    $distributionStore
                        ->logo_path
                );
        }

        $distributionStore->delete();

        return back()->with(
            'success',
            'DSP deleted successfully.'
        );
    }

    private function validateStore(
        Request $request,
        ?DistributionStore $store = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(
                    'distribution_stores',
                    'name'
                )->ignore(
                    $store?->id
                ),
            ],

            'slug' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique(
                    'distribution_stores',
                    'slug'
                )->ignore(
                    $store?->id
                ),
            ],

            'logo' => [
                'nullable',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'default_selected' => [
                'required',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);
    }
}
