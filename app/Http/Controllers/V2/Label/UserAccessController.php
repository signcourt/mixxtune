<?php

namespace App\Http\Controllers\V2\Label;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Track;
use App\Models\LabelAccess\LabelTeamMember;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessController extends Controller
{
    public function __construct(
        private readonly LabelTeamAccessService $teamAccess
    ) {
    }

    public function index(Request $request): Response
    {
        $owner = $request->user();

        /*
         * Central service is the source of truth:
         * only an actual Master Label owner can manage users.
         */
        $label = $this->teamAccess->assertOwner($owner);

        $members = LabelTeamMember::query()
            ->where('label_id', $label->id)
            ->with([
                'user:id,name,email',
                'scopes',
            ])
            ->latest('id')
            ->get()
            ->map(
                fn (LabelTeamMember $member) => [
                    'id' => $member->id,

                    'name' =>
                        $member->user?->name,

                    'email' =>
                        $member->user?->email,

                    'permission_level' =>
                        $member->permission_level,

                    'scope_level' =>
                        $member->scope_level,

                    'status' =>
                        $member->status,

                    'permissions' =>
                        $member->permissions ?? [],

                    'last_access_at' =>
                        $member->last_access_at,

                    'scopes' =>
                        $member->scopes
                            ->map(
                                fn ($scope) => [
                                    'type' =>
                                        $scope->scope_type,

                                    'id' =>
                                        (int) $scope->scope_id,
                                ]
                            )
                            ->values(),
                ]
            )
            ->values();

        $catalogue = $this->teamAccess
            ->catalogue();

        /*
         * Scope choices:
         * - artists owned directly by Master
         * - direct child labels only
         *
         * No recursive grandchildren.
         */
        $artists = Artist::query()
            ->where(
                'label_id',
                $label->id
            )
            ->orderBy('stage_name')
            ->get([
                'id',
                'stage_name',
            ])
            ->map(fn (Artist $artist) => [
                'id' => $artist->id,
                'name' => $artist->stage_name,
            ])
            ->values();

        $childLabels = Label::query()
            ->where(
                'parent_label_id',
                $label->id
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);


        /*
         * Individual Track scope choices from the strict
         * two-tier Master Label catalogue.
         */
        $allowedLabelIds = collect([
            (int) $label->id,
        ])
            ->merge(
                $childLabels
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
            )
            ->unique()
            ->values();

        $tracks = Track::query()
            ->whereHas(
                'release',
                fn ($query) =>
                    $query->whereIn(
                        'label_id',
                        $allowedLabelIds
                    )
            )
            ->with([
                'release:id,title,label_id',
            ])
            ->orderBy('title')
            ->get([
                'id',
                'release_id',
                'title',
                'isrc',
            ])
            ->map(fn (Track $track) => [
                'id' => $track->id,
                'name' => $track->title,
                'isrc' => $track->isrc,
                'release' =>
                    $track->release?->title,
            ])
            ->values();

        return Inertia::render(
            'V2/Label/UserAccess/Index',
            [
                'members' =>
                    $members,

                'permissionCatalogue' =>
                    $catalogue,

                'artists' =>
                    $artists,

                'childLabels' =>
                    $childLabels,

                'tracks' =>
                    $tracks,
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $owner = $request->user();

        /*
         * Assert before looking up or changing
         * any team membership.
         */
        $this->teamAccess
            ->assertOwner($owner);

        $data = $this->validatePayload(
            $request,
            true
        );

        $teamUser = User::query()
            ->where(
                'email',
                $data['email']
            )
            ->first();

        if (!$teamUser) {
            return back()
                ->withErrors([
                    'email' =>
                        'No Mixx Tune user exists with this email.',
                ])
                ->withInput();
        }

        $this->teamAccess->createMember(
            $owner,
            $teamUser,
            $data['permission_level'],
            $data['scope_level'],
            $data['permissions'] ?? [],
            $data['artist_ids'] ?? [],
            $data['label_ids'] ?? [],
            $data['track_ids'] ?? []
        );

        return back()->with(
            'success',
            'User access added successfully.'
        );
    }

    public function update(
        Request $request,
        LabelTeamMember $member
    ): RedirectResponse {
        $owner = $request->user();

        $data = $this->validatePayload(
            $request,
            false
        );

        $this->teamAccess->updateMember(
            $owner,
            $member,
            $data['permission_level'],
            $data['scope_level'],
            $data['permissions'] ?? [],
            $data['artist_ids'] ?? [],
            $data['label_ids'] ?? [],
            $data['track_ids'] ?? []
        );

        return back()->with(
            'success',
            'User access updated successfully.'
        );
    }

    public function status(
        Request $request,
        LabelTeamMember $member
    ): RedirectResponse {
        $owner = $request->user();

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'suspended',
                    'disabled',
                ]),
            ],
        ]);

        $this->teamAccess->setStatus(
            $owner,
            $member,
            $data['status']
        );

        return back()->with(
            'success',
            'User status updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        LabelTeamMember $member
    ): RedirectResponse {
        $owner = $request->user();

        $label = $this->teamAccess
            ->assertOwner($owner);

        abort_unless(
            (int) $member->label_id ===
            (int) $label->id,
            404
        );

        $member->delete();

        return back()->with(
            'success',
            'User access removed successfully.'
        );
    }

    private function validatePayload(
        Request $request,
        bool $requireEmail
    ): array {
        $rules = [
            'permission_level' => [
                'required',
                Rule::in([
                    'standard',
                    'advanced',
                ]),
            ],

            'scope_level' => [
                'required',
                Rule::in([
                    'entire_label',
                    'selected',
                ]),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'string',
            ],

            'artist_ids' => [
                'nullable',
                'array',
            ],

            'artist_ids.*' => [
                'integer',
                'distinct',
            ],

            'label_ids' => [
                'nullable',
                'array',
            ],

            'label_ids.*' => [
                'integer',
                'distinct',
            ],

            'track_ids' => [
                'nullable',
                'array',
            ],

            'track_ids.*' => [
                'integer',
                'distinct',
                'exists:tracks,id',
            ],
        ];

        if ($requireEmail) {
            $rules['email'] = [
                'required',
                'email',
            ];
        }

        return $request->validate(
            $rules
        );
    }
}
