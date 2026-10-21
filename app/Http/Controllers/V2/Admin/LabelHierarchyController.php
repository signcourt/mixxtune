<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Label;
use App\Services\V2\LabelHierarchyService;
use App\Services\V2\LabelIdentityService;
use App\Services\V2\LabelRevenueShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LabelHierarchyController extends Controller
{
    private function authorizeAdmin(
        Request $request
    ): string {
        $role = (string) $request->user()?->role;

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403
        );

        return $role;
    }

    public function index(
        Request $request,
        LabelHierarchyService $hierarchy,
        LabelRevenueShareService $revenueShares
    ): Response {
        $this->authorizeAdmin($request);

        $labels = Label::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get([
                'id',
                'parent_label_id',
                'user_id',
                'name',
                'label_type',
                'status',
                'revenue_share_percentage',
                'parent_commission_percentage',
            ]);

        /*
         * W59: the tree is built in memory from one list (before: about
         * 1,500-2,000 queries for 303 labels). Shares are loaded at once
         * with the same rules as LabelRevenueShareService::resolve().
         */
        $parentOf = $labels->mapWithKeys(fn ($l) => [(int) $l->id => $l->parent_label_id ? (int) $l->parent_label_id : null])->all();
        $childrenOf = [];
        foreach ($parentOf as $id => $parent) {
            if ($parent !== null) {
                $childrenOf[$parent][] = $id;
            }
        }
        $chain = function (int $id) use ($parentOf): array {
            $seen = [];
            $cur = $id;
            while ($cur !== null && !isset($seen[$cur])) {
                $seen[$cur] = true;
                $cur = $parentOf[$cur] ?? null;
            }

            return array_keys($seen);
        };
        $countDesc = function (int $id) use (&$countDesc, $childrenOf): int {
            $n = 0;
            foreach ($childrenOf[$id] ?? [] as $child) {
                $n += 1 + $countDesc($child);
            }

            return $n;
        };
        $today = now()->toDateString();
        $shares = \App\Models\Finance\LabelRevenueShare::query()
            ->where('beneficiary_type', 'label')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderBy('id')
            ->get(['master_label_id', 'beneficiary_id', 'revenue_share_percent'])
            ->unique(fn ($r) => $r->master_label_id.':'.$r->beneficiary_id)
            ->keyBy(fn ($r) => $r->master_label_id.':'.$r->beneficiary_id);

        $rows = $labels->map(function (Label $label) use ($chain, $countDesc, $shares) {
            $ancestors = $chain((int) $label->id);
            $share = $label->parent_label_id !== null ? $shares->get((int) $label->parent_label_id.':'.(int) $label->id) : null;

            return [
                'id' => (int) $label->id,
                'parent_label_id' => $label->parent_label_id ? (int) $label->parent_label_id : null,
                'user_id' => $label->user_id ? (int) $label->user_id : null,
                'name' => $label->name,
                'label_type' => $label->label_type,
                'status' => $label->status,
                'depth' => max(0, count($ancestors) - 1),
                'root_id' => (int) end($ancestors),
                'descendant_count' => $countDesc((int) $label->id),
                'revenue_share_percentage' => $share ? (float) $share->revenue_share_percent : null,
                'parent_commission_percentage' => $share ? 100 - (float) $share->revenue_share_percent : null,
            ];
        })->values();

        return Inertia::render(
            'V2/Admin/LabelHierarchy/Index',
            [
                'role' => (string) $request->user()->role,
                'labels' => $rows,
            ]
        );
    }

    public function store(
        Request $request,
        LabelRevenueShareService $revenueShares
    ): RedirectResponse {
        abort_unless(
            (string) $request->user()?->role === 'super_admin',
            403,
            'Only Super Admin can manage catalogue levels.'
        );

        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'parent_label_id' => [
                'required',
                'integer',
                Rule::exists('labels', 'id')
                    ->whereNull('deleted_at'),
            ],
            'revenue_share_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],
        ]);

        $identity =
            app(LabelIdentityService::class);

        $existingLogicalLabel =
            $identity->representative(
                $data['name']
            );

        if ($existingLogicalLabel) {
            return back()->withErrors([
                'name' =>
                    'A logically identical label already exists as "'.
                    $existingLogicalLabel->name.
                    '" (ID '.
                    $existingLogicalLabel->id.
                    ').',
            ]);
        }

        $parent = Label::query()
            ->whereKey(
                (int) $data['parent_label_id']
            )
            ->whereNull('deleted_at')
            ->firstOrFail();

        /*
         * STRICT TWO-TIER RULE:
         *
         * Only a root/master label can receive
         * a direct child label.
         */
        if ($parent->parent_label_id !== null) {
            return back()->withErrors([
                'parent_label_id' =>
                    'A child label cannot contain another child label.',
            ]);
        }

        /*
         * Levels are hierarchy nodes.
         *
         * They do NOT receive an independent login
         * automatically. The root/master login remains
         * the account owner.
         */
        DB::transaction(
            function () use (
                $request,
                $data,
                $parent,
                $revenueShares
            ) {
                $slugBase = Str::slug(
                    $data['name']
                );

                if ($slugBase === '') {
                    $slugBase = 'level';
                }

                $slug = $slugBase;
                $counter = 2;

                while (
                    Label::withTrashed()
                        ->where('slug', $slug)
                        ->exists()
                ) {
                    $slug =
                        $slugBase.'-'.$counter;
                    $counter++;
                }

                $child = Label::query()->create([
                    'user_id' => null,
                    'parent_label_id' =>
                        (int) $parent->id,
                    'label_type' => 'label',
                    'public_id' =>
                        'LBL-' . strtoupper(
                            Str::random(12)
                        ),
                    'name' => trim(
                        $data['name']
                    ),
                    'slug' => $slug,
                    'country' =>
                        $parent->country,
                    'timezone' =>
                        $parent->timezone,
                    'currency' =>
                        $parent->currency,
                    'payout_cycle' =>
                        $parent->payout_cycle,
                    'minimum_withdrawal_amount' =>
                        $parent
                            ->minimum_withdrawal_amount,
                    'status' =>
                        $data['status']
                            ?? 'active',
                    'can_access_catalogue' => true,
                    'can_access_royalties' => true,
                    'can_access_reports' => true,
                    'can_access_wallet' => false,
                    'can_withdraw' => false,
                    'created_by' =>
                        $request->user()->id,
                    'updated_by' =>
                        $request->user()->id,
                ]);

                $revenueShares->saveForLabel(
                    $parent,
                    $child,
                    (float) $data[
                        'revenue_share_percent'
                    ],
                    true,
                    $request->user()
                );
            }
        );

        return back()->with(
            'success',
            'Level created successfully.'
        );
    }

    public function update(
        Request $request,
        Label $label,
        LabelHierarchyService $hierarchy,
        LabelRevenueShareService $revenueShares
    ): RedirectResponse {
        abort_unless(
            (string) $request->user()?->role === 'super_admin',
            403,
            'Only Super Admin can manage catalogue levels.'
        );

        $this->authorizeAdmin($request);

        abort_if(
            $label->trashed(),
            404
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'parent_label_id' => [
                'nullable',
                'integer',
                Rule::exists('labels', 'id')
                    ->whereNull('deleted_at'),
            ],
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],
            'revenue_share_percent' => [
                Rule::requiredIf(
                    $label->parent_label_id !== null
                ),
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $identity =
            app(LabelIdentityService::class);

        $currentKey =
            $identity->normalize(
                $label->name
            );

        $requestedKey =
            $identity->normalize(
                $data['name']
            );

        if (
            $requestedKey !== $currentKey
            && $identity->exists(
                $data['name']
            )
        ) {
            $collision =
                $identity->representative(
                    $data['name']
                );

            return back()->withErrors([
                'name' =>
                    'Another logical label already exists as "'.
                    ($collision?->name ?? $data['name']).
                    '".',
            ]);
        }

        $newParentId =
            isset($data['parent_label_id'])
            && $data['parent_label_id'] !== null
                ? (int) $data['parent_label_id']
                : null;

        /*
         * Root/master labels cannot accidentally be
         * converted into child levels here.
         *
         * Existing child levels may be moved anywhere
         * inside a valid hierarchy.
         */
        if (
            $label->parent_label_id === null
            && $newParentId !== null
        ) {
            return back()->withErrors([
                'parent_label_id' =>
                    'A master/root label cannot be moved under another level.',
            ]);
        }

        if ($label->parent_label_id !== null) {
            if ($newParentId === null) {
                return back()->withErrors([
                    'parent_label_id' =>
                        'A child level must remain inside a master hierarchy.',
                ]);
            }

            $hierarchy->assertValidParent(
                (int) $label->id,
                $newParentId
            );

            /*
             * STRICT TWO-TIER RULE:
             * the new parent must itself be a root/master.
             */
            $hierarchy->assertRootParent(
                $newParentId
            );

            /*
             * A level may move only inside its current
             * master/root tree. This prevents accidental
             * cross-account catalogue leakage.
             */
            $currentRoot =
                $hierarchy->rootLabelId(
                    (int) $label->id
                );

            $newParentRoot =
                $hierarchy->rootLabelId(
                    $newParentId
                );

            if (
                $currentRoot !== null
                && $newParentRoot !== null
                && $currentRoot !== $newParentRoot
            ) {
                return back()->withErrors([
                    'parent_label_id' =>
                        'A level cannot be moved to a different master account.',
                ]);
            }
        }

        DB::transaction(
            function () use (
                $request,
                $data,
                $label,
                $newParentId,
                $revenueShares
            ) {
                $label->forceFill([
                    'name' => trim($data['name']),
                    'parent_label_id' =>
                        $newParentId,
                    'status' => $data['status'],
                    'updated_by' =>
                        $request->user()->id,
                ])->save();

                if ($newParentId !== null) {
                    $master = Label::query()
                        ->whereKey($newParentId)
                        ->whereNull('deleted_at')
                        ->firstOrFail();

                    $revenueShares->saveForLabel(
                        $master,
                        $label,
                        (float) $data[
                            'revenue_share_percent'
                        ],
                        true,
                        $request->user()
                    );
                }
            }
        );

        return back()->with(
            'success',
            'Level updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        Label $label,
        LabelHierarchyService $hierarchy
    ): RedirectResponse {
        abort_unless(
            (string) $request->user()?->role === 'super_admin',
            403,
            'Only Super Admin can manage catalogue levels.'
        );

        $this->authorizeAdmin($request);

        abort_if(
            $label->trashed(),
            404
        );

        if ($label->parent_label_id === null) {
            return back()->withErrors([
                'label' =>
                    'Master/root labels cannot be deleted from Level Management.',
            ]);
        }

        $descendants = $hierarchy
            ->descendantIds(
                (int) $label->id,
                false
            );

        if ($descendants->isNotEmpty()) {
            return back()->withErrors([
                'label' =>
                    'Move or remove this level’s child levels before deleting it.',
            ]);
        }

        $hasReleases = DB::table('releases')
            ->where(
                'label_id',
                $label->id
            )
            ->whereNull('deleted_at')
            ->exists();

        if ($hasReleases) {
            return back()->withErrors([
                'label' =>
                    'This level contains catalogue releases and cannot be deleted.',
            ]);
        }

        $hasArtists = DB::table('artists')
            ->where(
                'label_id',
                $label->id
            )
            ->whereNull('deleted_at')
            ->exists();

        if ($hasArtists) {
            return back()->withErrors([
                'label' =>
                    'This level contains artists and cannot be deleted.',
            ]);
        }

        /* W59: revenue rows / mappings / statements also keep a level from being deleted */
        foreach ([
            'report_rows' => 'revenue report rows',
            'royalty_statements' => 'statements',
            'revenue_label_mappings' => 'revenue name mappings',
        ] as $table => $what) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'label_id') && DB::table($table)->where('label_id', $label->id)->exists()) {
                return back()->withErrors([
                    'label' => 'This level has '.$what.' and cannot be deleted.',
                ]);
            }
        }

        $label->forceFill([
            'updated_by' =>
                $request->user()->id,
        ])->save();

        $label->delete();

        /* its share stops counting once the level is gone */
        if (\Illuminate\Support\Facades\Schema::hasTable('label_revenue_shares')) {
            DB::table('label_revenue_shares')->where('beneficiary_type', 'label')->where('beneficiary_id', $label->id)->update(['is_active' => false, 'updated_at' => now()]);
        }

        return back()->with(
            'success',
            'Level deleted successfully.'
        );
    }
}
