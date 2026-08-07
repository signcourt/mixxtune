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
                ?: Str::slug(
                    $validated['name']
                ),

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
            ?: Str::slug(
                $validated['name']
            );

        $distributionStore->update(
            $validated
        );

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

        $distributionStore->update([
            'is_active' =>
                !$distributionStore
                    ->is_active,
        ]);

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
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
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
