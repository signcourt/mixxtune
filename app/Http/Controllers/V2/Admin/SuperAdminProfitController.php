<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\Admin\SuperAdminProfitService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminProfitController extends Controller
{
    public function index(
        Request $request,
        SuperAdminProfitService $profit
    ) {
        $this->authorizeSuperAdmin(
            $request
        );

        $filters =
            $this->filters($request);

        $query =
            $profit->baseQuery(
                $filters['month'],
                $filters['platform'],
                $filters['owner_type'],
                $filters['owner_id']
            );

        return inertia(
            'V2/Admin/Profit/Index',
            [
                'summary' =>
                    $profit->summary(
                        clone $query
                    ),

                'breakdown' =>
                    $profit->breakdown(
                        clone $query
                    ),

                'filters' =>
                    $filters,

                'months' =>
                    $profit->months(),

                'platforms' =>
                    $profit->platforms(),

                'owners' =>
                    $profit->owners(),
            ]
        );
    }

    public function export(
        Request $request,
        SuperAdminProfitService $profit
    ): StreamedResponse {
        $this->authorizeSuperAdmin(
            $request
        );

        $filters =
            $this->filters($request);

        $query =
            $profit->baseQuery(
                $filters['month'],
                $filters['platform'],
                $filters['owner_type'],
                $filters['owner_id']
            );

        $filename =
            'super-admin-profit-'
            .now()->format(
                'Ymd-His'
            )
            .'.csv';

        return response()
            ->streamDownload(
                function () use (
                    $query,
                    $profit
                ) {
                    $handle =
                        fopen(
                            'php://output',
                            'w'
                        );

                    fwrite(
                        $handle,
                        "\xEF\xBB\xBF"
                    );

                    fputcsv(
                        $handle,
                        [
                            'Sale Date',
                            'Sale Month',
                            'Account Type',
                            'Account ID',
                            'Platform',
                            'Collected Revenue',
                            'Assigned Rate',
                            'User Earning',
                            'Super Admin Profit',
                        ]
                    );

                    foreach (
                        $query
                            ->orderBy('id')
                            ->cursor()
                        as $row
                    ) {
                        $calc =
                            $profit
                                ->calculateRow(
                                    $row
                                );

                        fputcsv(
                            $handle,
                            [
                                $row->sale_date,
                                $profit
                                    ->effectiveMonth(
                                        $row
                                    ),
                                $row->revenue_owner_type,
                                $row->revenue_owner_id,
                                $row->platform,
                                number_format(
                                    $calc[
                                        'collected_revenue'
                                    ],
                                    8,
                                    '.',
                                    ''
                                ),
                                number_format(
                                    $calc[
                                        'assigned_rate'
                                    ],
                                    4,
                                    '.',
                                    ''
                                ),
                                number_format(
                                    $calc[
                                        'user_earning'
                                    ],
                                    8,
                                    '.',
                                    ''
                                ),
                                number_format(
                                    $calc[
                                        'super_admin_profit'
                                    ],
                                    8,
                                    '.',
                                    ''
                                ),
                            ]
                        );
                    }

                    fclose($handle);
                },
                $filename,
                [
                    'Content-Type' =>
                        'text/csv; charset=UTF-8',
                ]
            );
    }

    private function filters(
        Request $request
    ): array {
        $validated =
            $request->validate([
                'month' => [
                    'nullable',
                    'date_format:Y-m',
                ],

                'platform' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'owner_type' => [
                    'nullable',
                    'in:label,artist',
                ],

                'owner_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],
            ]);

        return [
            'month' =>
                (string)
                    ($validated[
                        'month'
                    ] ?? ''),

            'platform' =>
                (string)
                    ($validated[
                        'platform'
                    ] ?? ''),

            'owner_type' =>
                (string)
                    ($validated[
                        'owner_type'
                    ] ?? ''),

            'owner_id' =>
                isset(
                    $validated[
                        'owner_id'
                    ]
                )
                    ? (int)
                        $validated[
                            'owner_id'
                        ]
                    : null,
        ];
    }

    private function authorizeSuperAdmin(
        Request $request
    ): void {
        $role = strtolower(
            trim(
                (string)
                    $request
                        ->user()
                        ->role
            )
        );

        abort_unless(
            in_array(
                $role,
                [
                    'super_admin',
                    'super-admin',
                ],
                true
            ),
            403
        );
    }
}
