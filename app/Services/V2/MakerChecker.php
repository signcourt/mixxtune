<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/*
 * W118 MIXXTUNE_MAKER_CHECKER
 * Second approval for money actions. OFF until a Super Admin switches it on (needs 2 active Super Admins).
 * When ON: the first press of a money button makes a request; ANOTHER Super Admin presses Approve in
 * Administration -> Second approval; then the same button works once (within 24 hours) - after the usual
 * e-mailed code. The one who approved can not be the one who then does it.
 */
class MakerChecker
{
    public const KEY = 'finance.maker_checker';

    public const VALID_HOURS = 24;

    /* money actions (the scopes of the e-mailed code) */
    public const PREFIXES = [
        'withdrawal.paid:', 'withdrawal.razorpay:', 'salary.pay:', 'salary.paid:', 'payout.close:', 'razorpay.settings',
        'adjustment.post:', 'adjustment.reverse:', 'recoupment.', 'statement.approve:', 'statement.available:', 'hold.release:',
        'report.approve:', 'rights.approve:', 'rights.publish:', 'label.share:', 'makerchecker.off',
    ];

    public static function on(): bool
    {
        try {
            return in_array((string) DB::table('system_settings')->where('key', self::KEY)->value('value'), ['1', 'true', 'on'], true);
        } catch (Throwable) {
            return false;
        }
    }

    public static function covers(string $scope): bool
    {
        foreach (self::PREFIXES as $p) {
            if (str_starts_with($scope, $p)) {
                return true;
            }
        }

        return false;
    }

    /** stop the action unless another Super Admin approved this exact action */
    public function require(User $actor, string $scope, string $what): ?int
    {
        if (!self::on() || !self::covers($scope)) {
            return null;
        }
        $ok = DB::table('pending_approvals')->where('scope', $scope)->where('status', 'approved')->whereNull('used_at')
            ->where('decided_at', '>', now()->subHours(self::VALID_HOURS))->where('decided_by', '!=', $actor->id)->orderBy('id')->first();
        if ($ok) {
            return (int) $ok->id;
        }
        $open = DB::table('pending_approvals')->where('scope', $scope)->where('status', 'open')->first();
        if (!$open) {
            $id = DB::table('pending_approvals')->insertGetId([
                'scope' => mb_substr($scope, 0, 190), 'what' => $what, 'url' => mb_substr((string) (request()?->headers->get('referer') ?? ''), 0, 500) ?: null,
                'requested_by' => $actor->id, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            try {
                $others = app(StaffAlerts::class)->superAdmins()->reject(fn ($u) => (int) $u->id === (int) $actor->id);
                app(StaffAlerts::class)->bell($others, 'approval.requested', 'Second approval needed', ($actor->name ?: 'A Super Admin').' wants to '.$what.'. Approve or reject it in Administration → Second approval.', '/admin/approvals', 'warning', ['approval_id' => $id]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        throw ValidationException::withMessages([
            'approval' => 'Second approval is ON: to '.$what.' another Super Admin must approve it first (Administration → Second approval). They have been told. After their "yes", press the same button again.',
        ]);
    }

    public function used(int $approvalId, User $actor): void
    {
        DB::table('pending_approvals')->where('id', $approvalId)->whereNull('used_at')->update(['used_at' => now(), 'used_by' => $actor->id, 'status' => 'used', 'updated_at' => now()]);
    }

    public static function activeSuperAdmins(): int
    {
        return User::query()->where('role', 'super_admin')->where('account_status', 'active')->count();
    }
}
