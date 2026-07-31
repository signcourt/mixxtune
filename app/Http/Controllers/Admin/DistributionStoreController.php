<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistributionStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DistributionStoreController extends Controller
{
    public function index()
    {
        return inertia('Admin/Settings/DistributionStores', [
            'stores' => DistributionStore::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                'unique:distribution_stores,name',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                'unique:distribution_stores,slug',
            ],
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:5120',
            ],
            'is_active' => ['required', 'boolean'],
            'default_selected' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $logoPath = null;

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store(
                'distribution-stores',
                'public'
            );
        }

        $nextOrder = DistributionStore::query()->max('sort_order') + 1;

        DistributionStore::create([
            'name' => $validated['name'],
            'slug' => $validated['slug']
                ?: Str::slug($validated['name']),
            'logo_path' => $logoPath,
            'is_active' => $validated['is_active'],
            'default_selected' => $validated['default_selected'],
            'sort_order' => $validated['sort_order'] ?? $nextOrder,
        ]);

        return back()->with('success', 'DSP added successfully.');
    }

    public function update(
        Request $request,
        DistributionStore $distributionStore
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('distribution_stores', 'name')
                    ->ignore($distributionStore->id),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('distribution_stores', 'slug')
                    ->ignore($distributionStore->id),
            ],
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:5120',
            ],
            'is_active' => ['required', 'boolean'],
            'default_selected' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        if ($request->hasFile('logo')) {
            if ($distributionStore->logo_path) {
                Storage::disk('public')->delete(
                    $distributionStore->logo_path
                );
            }

            $validated['logo_path'] = $request->file('logo')->store(
                'distribution-stores',
                'public'
            );
        }

        unset($validated['logo']);

        $validated['slug'] = $validated['slug']
            ?: Str::slug($validated['name']);

        $distributionStore->update($validated);

        return back()->with('success', 'DSP updated successfully.');
    }

    public function toggle(
        DistributionStore $distributionStore
    ) {
        $distributionStore->update([
            'is_active' => !$distributionStore->is_active,
        ]);

        return back()->with(
            'success',
            $distributionStore->is_active
                ? 'DSP activated successfully.'
                : 'DSP deactivated successfully.'
        );
    }

    public function destroy(
        DistributionStore $distributionStore
    ) {
        if ($distributionStore->logo_path) {
            Storage::disk('public')->delete(
                $distributionStore->logo_path
            );
        }

        $distributionStore->delete();

        return back()->with('success', 'DSP deleted successfully.');
    }
}
