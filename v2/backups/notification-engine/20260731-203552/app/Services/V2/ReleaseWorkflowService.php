<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReleaseWorkflowService
{
    private const TRANSITIONS = [
        'draft' => [
            'submitted',
            'archived',
        ],

        'changes_requested' => [
            'submitted',
            'archived',
        ],

        'rejected' => [
            'draft',
            'submitted',
            'archived',
        ],

        'submitted' => [
            'changes_requested',
            'approved',
            'rejected',
        ],

        'approved' => [
            'processing',
            'rejected',
        ],

        'processing' => [
            'delivered',
            'failed',
        ],

        'delivered' => [
            'live',
            'failed',
        ],

        'live' => [
            'takedown_requested',
        ],

        'takedown_requested' => [
            'taken_down',
            'live',
        ],

        'failed' => [
            'processing',
            'rejected',
        ],

        'taken_down' => [
            'archived',
        ],

        'archived' => [],
    ];

    public function __construct(
        private readonly ReleaseValidationService $validator
    ) {
    }

    public function canTransition(
        Release $release,
        string $newStatus
    ): bool {
        $currentStatus = (string) $release->status;

        return in_array(
            $newStatus,
            self::TRANSITIONS[$currentStatus] ?? [],
            true
        );
    }

    public function submit(
        Release $release,
        User $user,
        array $context = []
    ): Release {
        $errors = $this->validator
            ->validateForSubmission($release);

        if (!empty($errors)) {
            throw ValidationException::withMessages(
                $errors
            );
        }

        return $this->transition(
            $release,
            'submitted',
            $user,
            [
                'action' =>
                    'submitted_for_review',

                'remarks' =>
                    'Release submitted for review.',

                ...$context,
            ]
        );
    }

    public function transition(
        Release $release,
        string $newStatus,
        User $user,
        array $context = []
    ): Release {
        $oldStatus = (string) $release->status;

        abort_unless(
            $this->canTransition(
                $release,
                $newStatus
            ),
            422,
            "Release cannot move from {$oldStatus} to {$newStatus}."
        );

        return DB::transaction(function () use (
            $release,
            $oldStatus,
            $newStatus,
            $user,
            $context
        ) {
            $updates = [
                'status' => $newStatus,
                'updated_by' => $user->id,
            ];

            $this->addStatusTimestamps(
                $updates,
                $newStatus,
                $user
            );

            if ($newStatus === 'submitted') {
                $updates['wizard_step'] = 4;
                $updates[
                    'completion_percentage'
                ] = 100;
                $updates['review_notes'] = null;
                $updates['rejection_reason'] = null;
                $updates['rejected_at'] = null;
                $updates['rejected_by'] = null;
            }

            if ($newStatus === 'changes_requested') {
                $updates['review_notes'] =
                    $context['remarks']
                    ?? $context['notes']
                    ?? null;
            }

            if ($newStatus === 'rejected') {
                $updates['rejection_reason'] =
                    $context['remarks']
                    ?? $context['reason']
                    ?? null;
            }

            $release->update($updates);

            $this->writeStatusLog(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context
            );

            return $release->fresh();
        });
    }

    public function availableTransitions(
        Release $release
    ): array {
        return self::TRANSITIONS[
            (string) $release->status
        ] ?? [];
    }

    public function isEditable(
        Release $release
    ): bool {
        return in_array(
            $release->status,
            [
                'draft',
                'changes_requested',
                'rejected',
            ],
            true
        );
    }

    private function addStatusTimestamps(
        array &$updates,
        string $status,
        User $user
    ): void {
        $timestampColumns = [
            'submitted' => 'submitted_at',
            'approved' => 'approved_at',
            'rejected' => 'rejected_at',
            'processing' => 'processing_at',
            'delivered' => 'delivered_at',
            'live' => 'live_at',
            'taken_down' => 'taken_down_at',
        ];

        $userColumns = [
            'approved' => 'approved_by',
            'rejected' => 'rejected_by',
        ];

        if (
            isset($timestampColumns[$status])
            && Schema::hasColumn(
                'releases',
                $timestampColumns[$status]
            )
        ) {
            $updates[
                $timestampColumns[$status]
            ] = now();
        }

        if (
            isset($userColumns[$status])
            && Schema::hasColumn(
                'releases',
                $userColumns[$status]
            )
        ) {
            $updates[
                $userColumns[$status]
            ] = $user->id;
        }
    }

    private function writeStatusLog(
        Release $release,
        string $oldStatus,
        string $newStatus,
        User $user,
        array $context
    ): void {
        if (
            !Schema::hasTable(
                'release_status_logs'
            )
        ) {
            return;
        }

        $columns = Schema::getColumnListing(
            'release_status_logs'
        );

        $possibleValues = [
            'public_id' =>
                (string) Str::ulid(),

            'release_id' =>
                $release->id,

            'old_status' =>
                $oldStatus,

            'previous_status' =>
                $oldStatus,

            'from_status' =>
                $oldStatus,

            'new_status' =>
                $newStatus,

            'to_status' =>
                $newStatus,

            'status' =>
                $newStatus,

            'action' =>
                $context['action']
                ?? $newStatus,

            'remarks' =>
                $context['remarks']
                ?? null,

            'notes' =>
                $context['notes']
                ?? $context['remarks']
                ?? null,

            'reason' =>
                $context['reason']
                ?? null,

            'ip_address' =>
                $context['ip_address']
                ?? request()?->ip(),

            'user_agent' =>
                $context['user_agent']
                ?? request()?->userAgent(),

            'changed_by' =>
                $user->id,

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,

            'user_id' =>
                $user->id,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ];

        $log = [];

        foreach (
            $possibleValues as $column => $value
        ) {
            if (
                in_array(
                    $column,
                    $columns,
                    true
                )
            ) {
                $log[$column] = $value;
            }
        }

        DB::table(
            'release_status_logs'
        )->insert($log);
    }
}
