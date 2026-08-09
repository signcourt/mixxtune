<?php

namespace App\Http\Controllers\V2\Label;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Services\V2\LabelRevenueShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RevenueSharingController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $master = $this->masterLabel(
            $request
        );

        $shares = LabelRevenueShare::query()
            ->where(
                'master_label_id',
                $master->id
            )
            ->get()
            ->keyBy(
                fn (LabelRevenueShare $share) =>
                    $share->beneficiary_type
                    .':'
                    .$share->beneficiary_id
            );

        $labels = Label::query()
            ->where(
                'parent_label_id',
                $master->id
            )
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(function (
                Label $label
            ) use ($shares) {
                $share = $shares->get(
                    'label:'.$label->id
                );

                return $this->row(
                    'label',
                    $label->id,
                    $label->name,
                    $label->status,
                    $share
                );
            })
            ->values();

        $artists = Artist::query()
            ->where(
                'label_id',
                $master->id
            )
            ->whereNull('deleted_at')
            ->orderBy('stage_name')
            ->get()
            ->map(function (
                Artist $artist
            ) use ($shares) {
                $share = $shares->get(
                    'artist:'.$artist->id
                );

                return $this->row(
                    'artist',
                    $artist->id,
                    $artist->stage_name,
                    $artist->account_status,
                    $share
                );
            })
            ->values();

        return Inertia::render(
            'V2/Label/RevenueSharing/Index',
            [
                'master' => [
                    'id' => $master->id,
                    'name' => $master->name,
                    'currency' =>
                        $master->currency
                        ?: 'INR',
                ],

                'beneficiaries' =>
                    $labels
                        ->concat($artists)
                        ->values(),
            ]
        );
    }

    public function update(
        Request $request,
        string $type,
        int $id,
        LabelRevenueShareService $service
    ): RedirectResponse {
        $master = $this->masterLabel(
            $request
        );

        abort_unless(
            in_array(
                $type,
                [
                    'label',
                    'artist',
                ],
                true
            ),
            404
        );

        $validated = $request->validate([
            'revenue_share_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'show_revenue_share' => [
                'required',
                'boolean',
            ],
        ]);

        if ($type === 'label') {
            $child = Label::query()
                ->whereKey($id)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $service->saveForLabel(
                $master,
                $child,
                (float) $validated[
                    'revenue_share_percent'
                ],
                (bool) $validated[
                    'show_revenue_share'
                ],
                $request->user()
            );
        } else {
            $artist = Artist::query()
                ->whereKey($id)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $service->saveForArtist(
                $master,
                $artist,
                (float) $validated[
                    'revenue_share_percent'
                ],
                (bool) $validated[
                    'show_revenue_share'
                ],
                $request->user()
            );
        }

        return back()->with(
            'success',
            'Revenue share updated.'
        );
    }

    public function toggle(
        Request $request,
        LabelRevenueShare $share
    ): RedirectResponse {
        $master = $this->masterLabel(
            $request
        );

        abort_unless(
            (int) $share->master_label_id
                === (int) $master->id,
            403
        );

        $share->update([
            'is_active' =>
                ! $share->is_active,

            'updated_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'success',
            $share->is_active
                ? 'Revenue share activated.'
                : 'Revenue share deactivated.'
        );
    }

    private function masterLabel(
        Request $request
    ): Label {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        abort_unless(
            $user->role === 'label',
            403,
            'Label access required.'
        );

        $label = Label::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_if(
            $label->status !== 'active',
            403,
            'Label account is not active.'
        );

        /*
         * Two-tier contract:
         *
         * Master
         *   ├── Sub Label
         *   └── Artist
         *
         * A sub-label can never manage
         * another revenue hierarchy.
         */
        abort_if(
            $label->parent_label_id !== null,
            403,
            'Revenue sharing is available only to master labels.'
        );

        return $label;
    }

    private function row(
        string $type,
        int $id,
        string $name,
        ?string $status,
        ?LabelRevenueShare $share
    ): array {
        $percent = $share
            ? (float)
                $share->revenue_share_percent
            : 0.0;

        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'status' => $status,

            'share_id' =>
                $share?->id,

            'share_configured' =>
                $share !== null,

            'revenue_share_percent' =>
                $percent,

            'master_share_percent' =>
                100 - $percent,

            'show_revenue_share' =>
                $share
                    ? (bool)
                        $share
                            ->show_revenue_share
                    : true,

            'is_active' =>
                $share
                    ? (bool)
                        $share->is_active
                    : false,

            'effective_from' =>
                $share?->effective_from
                    ?->format('Y-m-d'),

            'effective_to' =>
                $share?->effective_to
                    ?->format('Y-m-d'),
        ];
    }
}
