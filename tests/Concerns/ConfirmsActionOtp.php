<?php

namespace Tests\Concerns;

use App\Mail\ActionOtpMail;
use App\Models\User;
use App\Services\V2\ActionOtpGuard;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/*
 * MIXXTUNE_FINANCE_SAFETY_V1
 * Test helpers for actions that need the e-mailed confirmation code.
 */
trait ConfirmsActionOtp
{
    /** Pretend the actor already entered the code for this scope. */
    protected function unlockActionOtp(User $actor, string $scope): void
    {
        ActionOtpGuard::store()->put(
            'mixxtune:actotp:unlock:'.$actor->id.':'.sha1($scope),
            true,
            now()->addMinutes(ActionOtpGuard::UNLOCK_MINUTES)
        );
    }

    /** Full flow: request is stopped, code is mailed, code is entered, request runs. */
    protected function viaActionOtp(User $actor, callable $send): TestResponse
    {
        Mail::fake();

        $send()->assertSessionHasErrors('action_otp');

        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $mail) use (&$code) {
            $code = $mail->otp;

            return true;
        });

        $this->actingAs($actor)
            ->postJson('/v2/admin/action-otp/unlock', ['otp' => (string) $code])
            ->assertOk();

        return $send();
    }
}
