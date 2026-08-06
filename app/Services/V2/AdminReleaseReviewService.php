<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminReleaseReviewService
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ReleaseAccessService $access,
        private readonly ReleaseValidationService $validator,
        private readonly ReleaseWorkflowService $workflow
    ) {
    }

    public function authorizeReviewer(
        ?User $user
    ): void {
        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        $role = $this->permissions->role($user);

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Only Admin or Super Admin can review releases.'
        );

        $this->permissions->authorize(
            $user,
            'releases.review'
        );
    }

    public function approve(
        Release $release,
        User $user,
        ?string $remarks = null
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'submitted',
            422,
            'Only submitted releases can be approved.'
        );

        /*
         * Revalidate before approval so incomplete
         * data cannot bypass the submission engine.
         */
        $errors = $this->validator
            ->validateForSubmission($release);

        if (!empty($errors)) {
            throw ValidationException::withMessages(
                $errors
            );
        }

        $this->permissions->authorize(
            $user,
            'releases.approve'
        );

        return $this->workflow->transition(
            $release,
            'approved',
            $user,
            [
                'action' =>
                    'approved_by_admin',

                'remarks' =>
                    $remarks
                    ?: 'Release approved.',

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }

    public function reject(
        Release $release,
        User $user,
        string $reason
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'submitted',
            422,
            'Only submitted releases can be rejected.'
        );

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' =>
                    'Rejection reason is required.',
            ]);
        }

        $this->permissions->authorize(
            $user,
            'releases.reject'
        );

        return $this->workflow->transition(
            $release,
            'rejected',
            $user,
            [
                'action' =>
                    'rejected_by_admin',

                'reason' => $reason,

                'remarks' => $reason,

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }

    public function requestChanges(
        Release $release,
        User $user,
        string $notes
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'submitted',
            422,
            'Changes can only be requested for submitted releases.'
        );

        $notes = trim($notes);

        if ($notes === '') {
            throw ValidationException::withMessages([
                'notes' =>
                    'Change request notes are required.',
            ]);
        }

        $this->permissions->authorize(
            $user,
            'releases.request_changes'
        );

        return $this->workflow->transition(
            $release,
            'changes_requested',
            $user,
            [
                'action' =>
                    'changes_requested_by_admin',

                'notes' => $notes,

                'remarks' => $notes,

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }

    public function startProcessing(
        Release $release,
        User $user,
        ?string $remarks = null
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'approved',
            422,
            'Only approved releases can enter processing.'
        );

        $this->permissions->authorize(
            $user,
            'releases.processing'
        );

        return $this->workflow->transition(
            $release,
            'processing',
            $user,
            [
                'action' =>
                    'distribution_processing_started',

                'remarks' =>
                    $remarks
                    ?: 'Distribution processing started.',

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }
}
