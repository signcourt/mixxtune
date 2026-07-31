<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RevenueLabelMappingController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', ''));

        $query = DB::table('revenue_label_mappings')
            ->leftJoin('labels', 'labels.id', '=', 'revenue_label_mappings.label_id')
            ->select([
                'revenue_label_mappings.id',
                'revenue_label_mappings.revenue_label_name',
                'revenue_label_mappings.normalized_name',
                'revenue_label_mappings.label_id',
                'revenue_label_mappings.royalty_percentage',
                'revenue_label_mappings.status',
                'revenue_label_mappings.notes',
                'labels.name as mapped_label_name',
            ]);

        if ($status !== '') {
            $query->where('revenue_label_mappings.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('revenue_label_mappings.revenue_label_name', 'like', "%{$search}%")
                    ->orWhere('labels.name', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Admin/RevenueLabelMappings/Index', [
            'mappings' => $query
                ->orderBy('revenue_label_mappings.revenue_label_name')
                ->paginate(100)
                ->withQueryString(),

            'labels' => DB::table('labels')
                ->select('id', 'name')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),

            'filters' => [
                'status' => $status,
                'search' => $search,
            ],

            'summary' => [
                'total' => DB::table('revenue_label_mappings')->count(),
                'mapped' => DB::table('revenue_label_mappings')
                    ->where('status', 'mapped')
                    ->count(),
                'unmapped' => DB::table('revenue_label_mappings')
                    ->where('status', 'unmapped')
                    ->count(),
                'ignored' => DB::table('revenue_label_mappings')
                    ->where('status', 'ignored')
                    ->count(),
            ],
        ]);
    }

    public function update(Request $request, int $mapping)
    {
        $validated = $request->validate([
            'label_id' => ['nullable', 'integer', 'exists:labels,id'],
            'royalty_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:unmapped,mapped,ignored'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'mapped' && empty($validated['label_id'])) {
            return back()->withErrors([
                'label_id' => 'Mapped status ke liye system label select karna zaroori hai.',
            ]);
        }

        DB::table('revenue_label_mappings')
            ->where('id', $mapping)
            ->update([
                'label_id' => $validated['label_id'],
                'royalty_percentage' => $validated['royalty_percentage'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Label mapping updated.');
    }
    public function createLabel(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:labels,name'],
            'royalty_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (DB::table('labels')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $labelId = DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'label_type' => 'label',
            'name' => $validated['name'],
            'slug' => $slug,
            'royalty_share_percentage' => $validated['royalty_percentage'],
            'parent_commission_percentage' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            'System label created successfully.'
        );
    }


}
