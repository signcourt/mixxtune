#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/withdrawal-kyc-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Models/Finance \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Withdrawals \
    resources/js/Pages/V2/KycProfile \
    resources/js/Pages/V2/Admin/Withdrawals \
    v2/runtime/state

echo "=================================================="
echo "INSTALLING WITHDRAWAL + PAYOUT + KYC ENGINE"
echo "=================================================="

for FILE in \
    routes/web.php \
    app/Services/V2/WalletService.php \
    resources/js/V2/Shared/Config/panelRoutes.js
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/8] Creating migrations..."

MIGRATION="database/migrations/2026_08_01_000011_create_v2_withdrawal_kyc_tables.php"

if [ ! -f "$MIGRATION" ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payout_profiles')) {
            Schema::create(
                'payout_profiles',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    )->unique();

                    $table->string(
                        'account_holder_name'
                    )->nullable();

                    $table->text(
                        'bank_account_number'
                    )->nullable();

                    $table->string(
                        'bank_name'
                    )->nullable();

                    $table->string(
                        'ifsc_code',
                        30
                    )->nullable();

                    $table->string(
                        'branch_name'
                    )->nullable();

                    $table->string(
                        'upi_id'
                    )->nullable();

                    $table->string(
                        'pan_number',
                        20
                    )->nullable();

                    $table->string(
                        'gst_number',
                        30
                    )->nullable();

                    $table->string(
                        'address_line_1'
                    )->nullable();

                    $table->string(
                        'address_line_2'
                    )->nullable();

                    $table->string(
                        'city',
                        100
                    )->nullable();

                    $table->string(
                        'state',
                        100
                    )->nullable();

                    $table->string(
                        'postal_code',
                        20
                    )->nullable();

                    $table->string(
                        'country_code',
                        2
                    )->default('IN');

                    $table->string(
                        'kyc_status',
                        30
                    )->default('pending');

                    $table->text(
                        'kyc_notes'
                    )->nullable();

                    $table->timestamp(
                        'verified_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'verified_by'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();

                    $table->index('kyc_status');
                }
            );
        }

        if (!Schema::hasTable('withdrawal_requests')) {
            Schema::create(
                'withdrawal_requests',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'request_number',
                        50
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    );

                    $table->unsignedBigInteger(
                        'wallet_account_id'
                    );

                    $table->unsignedBigInteger(
                        'payout_profile_id'
                    );

                    $table->decimal(
                        'amount',
                        20,
                        8
                    );

                    $table->decimal(
                        'fee_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'net_amount',
                        20,
                        8
                    );

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->string(
                        'payment_method',
                        30
                    )->default('bank');

                    $table->string(
                        'status',
                        30
                    )->default('pending');

                    $table->text(
                        'request_note'
                    )->nullable();

                    $table->text(
                        'admin_note'
                    )->nullable();

                    $table->text(
                        'rejection_reason'
                    )->nullable();

                    $table->string(
                        'payment_reference',
                        150
                    )->nullable();

                    $table->timestamp(
                        'approved_at'
                    )->nullable();

                    $table->timestamp(
                        'rejected_at'
                    )->nullable();

                    $table->timestamp(
                        'paid_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'processed_by'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'wallet_account_id'
                    )
                        ->references('id')
                        ->on('wallet_accounts')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'payout_profile_id'
                    )
                        ->references('id')
                        ->on('payout_profiles');

                    $table->index([
                        'status',
                        'created_at',
                    ]);

                    $table->index([
                        'user_id',
                        'status',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial and KYC records should not be
         * removed automatically in production.
         */
    }
};
PHP
fi


echo "[2/8] Creating models..."

cat > app/Models/Finance/PayoutProfile.php <<'PHP'
<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PayoutProfile extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'account_holder_name',
        'bank_account_number',
        'bank_name',
        'ifsc_code',
        'branch_name',
        'upi_id',
        'pan_number',
        'gst_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'kyc_status',
        'kyc_notes',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'bank_account_number' => 'encrypted',
        'pan_number' => 'encrypted',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'bank_account_number',
        'pan_number',
    ];

    protected $appends = [
        'masked_bank_account',
        'masked_pan',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function withdrawals()
    {
        return $this->hasMany(
            WithdrawalRequest::class
        );
    }

    public function getMaskedBankAccountAttribute(): ?string
    {
        $number = $this->bank_account_number;

        if (!$number) {
            return null;
        }

        return str_repeat(
            '•',
            max(
                strlen($number) - 4,
                0
            )
        ) . substr($number, -4);
    }

    public function getMaskedPanAttribute(): ?string
    {
        $pan = $this->pan_number;

        if (!$pan) {
            return null;
        }

        return substr($pan, 0, 2)
            . '******'
            . substr($pan, -2);
    }
}
PHP

cat > app/Models/Finance/WithdrawalRequest.php <<'PHP'
<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'public_id',
        'request_number',
        'user_id',
        'wallet_account_id',
        'payout_profile_id',
        'amount',
        'fee_amount',
        'tax_amount',
        'net_amount',
        'currency',
        'payment_method',
        'status',
        'request_note',
        'admin_note',
        'rejection_reason',
        'payment_reference',
        'approved_at',
        'rejected_at',
        'paid_at',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee_amount' => 'decimal:8',
        'tax_amount' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function wallet()
    {
        return $this->belongsTo(
            WalletAccount::class,
            'wallet_account_id'
        );
    }

    public function payoutProfile()
    {
        return $this->belongsTo(
            PayoutProfile::class
        );
    }
}
PHP


echo "[3/8] Creating Withdrawal Service..."

cat > app/Services/V2/WithdrawalService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public const MINIMUM_AMOUNT = 1000;

    public function __construct(
        private readonly WalletService $walletService
    ) {
    }

    public function create(
        User $user,
        float $amount,
        string $paymentMethod,
        ?string $note = null
    ): WithdrawalRequest {
        if ($amount < self::MINIMUM_AMOUNT) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Minimum withdrawal amount is ₹'
                    . self::MINIMUM_AMOUNT
                    . '.',
            ]);
        }

        $profile = PayoutProfile::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (!$profile) {
            throw ValidationException::withMessages([
                'payout_profile' =>
                    'Complete your payout and KYC profile first.',
            ]);
        }

        if ($profile->kyc_status !== 'verified') {
            throw ValidationException::withMessages([
                'kyc' =>
                    'KYC verification must be completed before withdrawal.',
            ]);
        }

        if (
            $paymentMethod === 'bank'
            && (
                !$profile->bank_account_number
                || !$profile->ifsc_code
            )
        ) {
            throw ValidationException::withMessages([
                'payment_method' =>
                    'Verified bank details are required.',
            ]);
        }

        if (
            $paymentMethod === 'upi'
            && !$profile->upi_id
        ) {
            throw ValidationException::withMessages([
                'payment_method' =>
                    'UPI ID is required.',
            ]);
        }

        $wallet = $this->walletService
            ->account($user);

        if (
            (float) $wallet
                ->available_balance
            < $amount
        ) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Available wallet balance is insufficient.',
            ]);
        }

        $hasPending =
            WithdrawalRequest::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'approved',
                        'processing',
                    ]
                )
                ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'withdrawal' =>
                    'A withdrawal request is already being processed.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $wallet,
                $profile,
                $amount,
                $paymentMethod,
                $note
            ) {
                $number =
                    'WD-'
                    . now()->format('Ymd')
                    . '-'
                    . strtoupper(
                        Str::random(8)
                    );

                return WithdrawalRequest::query()
                    ->create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'request_number' =>
                            $number,

                        'user_id' =>
                            $user->id,

                        'wallet_account_id' =>
                            $wallet->id,

                        'payout_profile_id' =>
                            $profile->id,

                        'amount' =>
                            $amount,

                        'fee_amount' =>
                            0,

                        'tax_amount' =>
                            0,

                        'net_amount' =>
                            $amount,

                        'currency' =>
                            $wallet->currency,

                        'payment_method' =>
                            $paymentMethod,

                        'status' =>
                            'pending',

                        'request_note' =>
                            $note,
                    ]);
            }
        );
    }

    public function approve(
        WithdrawalRequest $withdrawal,
        User $admin,
        ?string $note = null
    ): WithdrawalRequest {
        abort_unless(
            $withdrawal->status === 'pending',
            422,
            'Only pending requests can be approved.'
        );

        $withdrawal->update([
            'status' =>
                'approved',

            'admin_note' =>
                $note,

            'approved_at' =>
                now(),

            'processed_by' =>
                $admin->id,
        ]);

        return $withdrawal->fresh();
    }

    public function reject(
        WithdrawalRequest $withdrawal,
        User $admin,
        string $reason
    ): WithdrawalRequest {
        abort_unless(
            in_array(
                $withdrawal->status,
                [
                    'pending',
                    'approved',
                ],
                true
            ),
            422,
            'This request cannot be rejected.'
        );

        $withdrawal->update([
            'status' =>
                'rejected',

            'rejection_reason' =>
                $reason,

            'rejected_at' =>
                now(),

            'processed_by' =>
                $admin->id,
        ]);

        return $withdrawal->fresh();
    }

    public function markPaid(
        WithdrawalRequest $withdrawal,
        User $admin,
        string $paymentReference,
        ?string $note = null
    ): WithdrawalRequest {
        abort_unless(
            in_array(
                $withdrawal->status,
                [
                    'approved',
                    'processing',
                ],
                true
            ),
            422,
            'Withdrawal must be approved first.'
        );

        return DB::transaction(
            function () use (
                $withdrawal,
                $admin,
                $paymentReference,
                $note
            ) {
                $withdrawal->loadMissing('user');

                $this->walletService
                    ->debitAvailable(
                        $withdrawal->user,
                        (float) $withdrawal
                            ->amount,
                        'withdrawal_paid',
                        [
                            'currency' =>
                                $withdrawal
                                    ->currency,

                            'reference_type' =>
                                WithdrawalRequest::class,

                            'reference_id' =>
                                $withdrawal->id,

                            'reference_code' =>
                                $withdrawal
                                    ->request_number,

                            'description' =>
                                'Withdrawal paid: '
                                . $withdrawal
                                    ->request_number,

                            'created_by' =>
                                $admin->id,
                        ]
                    );

                $withdrawal->update([
                    'status' =>
                        'paid',

                    'payment_reference' =>
                        $paymentReference,

                    'admin_note' =>
                        $note
                        ?: $withdrawal
                            ->admin_note,

                    'paid_at' =>
                        now(),

                    'processed_by' =>
                        $admin->id,
                ]);

                return $withdrawal->fresh();
            }
        );
    }
}
PHP


echo "[4/8] Creating user controllers..."

cat > app/Http/Controllers/V2/PayoutProfileController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PayoutProfileController extends Controller
{
    public function edit(
        Request $request,
        PermissionService $permissions
    ): Response {
        $profile = PayoutProfile::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        return Inertia::render(
            'V2/KycProfile/Edit',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'profile' =>
                    $profile,
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'account_holder_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bank_account_number' => [
                'nullable',
                'string',
                'min:6',
                'max:40',
            ],

            'bank_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'ifsc_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'branch_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'upi_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pan_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'gst_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address_line_1' => [
                'required',
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'required',
                'string',
                'max:100',
            ],

            'state' => [
                'required',
                'string',
                'max:100',
            ],

            'postal_code' => [
                'required',
                'string',
                'max:20',
            ],

            'country_code' => [
                'required',
                'string',
                'size:2',
            ],
        ]);

        $existing = PayoutProfile::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        if (
            empty(
                $validated[
                    'bank_account_number'
                ]
            )
            && $existing
        ) {
            unset(
                $validated[
                    'bank_account_number'
                ]
            );
        }

        if (
            empty(
                $validated[
                    'pan_number'
                ]
            )
            && $existing
        ) {
            unset(
                $validated[
                    'pan_number'
                ]
            );
        }

        PayoutProfile::query()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $request->user()->id,
                ],
                [
                    'public_id' =>
                        $existing?->public_id
                        ?: (string) Str::ulid(),

                    ...$validated,

                    'country_code' =>
                        strtoupper(
                            $validated[
                                'country_code'
                            ]
                        ),

                    'ifsc_code' =>
                        isset(
                            $validated[
                                'ifsc_code'
                            ]
                        )
                            ? strtoupper(
                                $validated[
                                    'ifsc_code'
                                ]
                            )
                            : null,

                    'pan_number' =>
                        isset(
                            $validated[
                                'pan_number'
                            ]
                        )
                            ? strtoupper(
                                $validated[
                                    'pan_number'
                                ]
                            )
                            : null,

                    'kyc_status' =>
                        $existing?->kyc_status
                            === 'verified'
                            ? 'verified'
                            : 'submitted',
                ]
            );

        return back()->with(
            'success',
            'Payout and KYC profile saved.'
        );
    }
}
PHP

cat > app/Http/Controllers/V2/WithdrawalController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Services\V2\PermissionService;
use App\Services\V2\WalletService;
use App\Services\V2\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        WalletService $walletService
    ): Response {
        $wallet = $walletService->account(
            $request->user()
        );

        $profile = PayoutProfile::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        return Inertia::render(
            'V2/Withdrawals/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'wallet' =>
                    $wallet,

                'profile' =>
                    $profile,

                'minimumAmount' =>
                    WithdrawalService::MINIMUM_AMOUNT,

                'withdrawals' =>
                    WithdrawalRequest::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->orderByDesc('id')
                        ->paginate(25),
            ]
        );
    }

    public function store(
        Request $request,
        WithdrawalService $withdrawals
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],

            'payment_method' => [
                'required',
                'string',
                'in:bank,upi',
            ],

            'request_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $withdrawal = $withdrawals->create(
            $request->user(),
            (float) $validated['amount'],
            $validated['payment_method'],
            $validated['request_note']
                ?? null
        );

        return back()->with(
            'success',
            'Withdrawal request '
            . $withdrawal->request_number
            . ' submitted.'
        );
    }
}
PHP


echo "[5/8] Creating Admin Controller..."

cat > app/Http/Controllers/V2/Admin/WithdrawalManagementController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Services\V2\PermissionService;
use App\Services\V2\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

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
            'Admin access required.'
        );

        $status = trim(
            (string) $request->input(
                'status',
                'pending'
            )
        );

        $query = WithdrawalRequest::query()
            ->with([
                'user:id,name,email',
                'payoutProfile',
            ]);

        if ($status !== '') {
            $query->where(
                'status',
                $status
            );
        }

        $countBase =
            WithdrawalRequest::query();

        return Inertia::render(
            'V2/Admin/Withdrawals/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' =>
                        $status,
                ],

                'counts' => [
                    'pending' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'pending'
                            )
                            ->count(),

                    'approved' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'paid' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'paid'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),
                ],

                'withdrawals' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function approve(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'admin_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $service->approve(
            $withdrawal,
            $request->user(),
            $validated['admin_note']
                ?? null
        );

        return back()->with(
            'success',
            'Withdrawal approved.'
        );
    }

    public function reject(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ]);

        $service->reject(
            $withdrawal,
            $request->user(),
            $validated[
                'rejection_reason'
            ]
        );

        return back()->with(
            'success',
            'Withdrawal rejected.'
        );
    }

    public function markPaid(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'payment_reference' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],

            'admin_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $service->markPaid(
            $withdrawal,
            $request->user(),
            $validated[
                'payment_reference'
            ],
            $validated['admin_note']
                ?? null
        );

        return back()->with(
            'success',
            'Withdrawal marked as paid.'
        );
    }

    public function verifyKyc(
        Request $request,
        PayoutProfile $profile,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:verified,rejected',
            ],

            'kyc_notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $profile->update([
            'kyc_status' =>
                $validated['status'],

            'kyc_notes' =>
                $validated['kyc_notes']
                ?? null,

            'verified_at' =>
                $validated['status']
                    === 'verified'
                    ? now()
                    : null,

            'verified_by' =>
                $request->user()->id,
        ]);

        return back()->with(
            'success',
            'KYC status updated.'
        );
    }

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        abort_unless(
            in_array(
                $permissions->role(
                    $request->user()
                ),
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );
    }
}
PHP


echo "[6/8] Creating frontend pages..."

cat > resources/js/Pages/V2/KycProfile/Edit.jsx <<'JSX'
import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Edit({
    role = 'artist',
    profile = null,
}) {
    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        account_holder_name:
            profile?.account_holder_name ?? '',

        bank_account_number: '',
        bank_name:
            profile?.bank_name ?? '',

        ifsc_code:
            profile?.ifsc_code ?? '',

        branch_name:
            profile?.branch_name ?? '',

        upi_id:
            profile?.upi_id ?? '',

        pan_number: '',
        gst_number:
            profile?.gst_number ?? '',

        address_line_1:
            profile?.address_line_1 ?? '',

        address_line_2:
            profile?.address_line_2 ?? '',

        city:
            profile?.city ?? '',

        state:
            profile?.state ?? '',

        postal_code:
            profile?.postal_code ?? '',

        country_code:
            profile?.country_code ?? 'IN',
    });

    const submit = (event) => {
        event.preventDefault();

        patch('/v2/kyc-profile', {
            preserveScroll: true,
        });
    };

    const fields = [
        ['account_holder_name', 'Account Holder Name'],
        ['bank_account_number', 'Bank Account Number'],
        ['bank_name', 'Bank Name'],
        ['ifsc_code', 'IFSC Code'],
        ['branch_name', 'Branch Name'],
        ['upi_id', 'UPI ID'],
        ['pan_number', 'PAN Number'],
        ['gst_number', 'GST Number'],
        ['address_line_1', 'Address Line 1'],
        ['address_line_2', 'Address Line 2'],
        ['city', 'City'],
        ['state', 'State'],
        ['postal_code', 'Postal Code'],
        ['country_code', 'Country Code'],
    ];

    return (
        <PanelLayout
            role={role}
            title="KYC & Payout Profile"
            subtitle="Payment and tax information"
        >
            <Head title="KYC & Payout Profile" />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="text-sm text-slate-500">
                                KYC Status
                            </div>

                            <div className="mt-1 text-lg font-semibold capitalize text-slate-900">
                                {profile?.kyc_status ??
                                    'Not submitted'}
                            </div>
                        </div>

                        {profile?.masked_bank_account && (
                            <div className="text-right">
                                <div className="text-sm text-slate-500">
                                    Saved Account
                                </div>

                                <div className="mt-1 font-semibold text-slate-900">
                                    {
                                        profile.masked_bank_account
                                    }
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        {fields.map(
                            ([name, label]) => (
                                <label
                                    key={name}
                                    className={
                                        name.startsWith(
                                            'address_'
                                        )
                                            ? 'md:col-span-2'
                                            : ''
                                    }
                                >
                                    <span className="text-sm font-semibold text-slate-700">
                                        {label}
                                    </span>

                                    <input
                                        type="text"
                                        value={data[name]}
                                        onChange={(event) =>
                                            setData(
                                                name,
                                                event.target
                                                    .value
                                            )
                                        }
                                        placeholder={
                                            name ===
                                                'bank_account_number' &&
                                            profile
                                                ? 'Leave blank to keep saved account'
                                                : ''
                                        }
                                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                    />

                                    {errors[name] && (
                                        <div className="mt-1 text-sm text-red-600">
                                            {errors[name]}
                                        </div>
                                    )}
                                </label>
                            )
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-6 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Save Profile'}
                    </button>
                </form>
            </div>
        </PanelLayout>
    );
}
JSX

cat > resources/js/Pages/V2/Withdrawals/Index.jsx <<'JSX'
import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    wallet = {},
    profile = null,
    withdrawals = {},
    minimumAmount = 1000,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        amount: '',
        payment_method: 'bank',
        request_note: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post('/v2/withdrawals', {
            preserveScroll: true,

            onSuccess: () => reset(),
        });
    };

    const canWithdraw =
        profile?.kyc_status === 'verified';

    return (
        <PanelLayout
            role={role}
            title="Withdrawals"
            subtitle="Request royalty payouts"
        >
            <Head title="Withdrawals" />

            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card
                        label="Available Balance"
                        value={`₹${Number(
                            wallet.available_balance ??
                                0
                        ).toFixed(2)}`}
                    />

                    <Card
                        label="Minimum Withdrawal"
                        value={`₹${minimumAmount}`}
                    />

                    <Card
                        label="KYC Status"
                        value={
                            profile?.kyc_status ??
                            'Not submitted'
                        }
                    />
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 className="text-lg font-semibold text-slate-900">
                        New Withdrawal Request
                    </h2>

                    {!canWithdraw && (
                        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                            Complete and verify your KYC
                            profile before requesting a
                            withdrawal.
                        </div>
                    )}

                    <div className="mt-5 grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Amount
                            </label>

                            <input
                                type="number"
                                min={minimumAmount}
                                step="0.01"
                                value={data.amount}
                                onChange={(event) =>
                                    setData(
                                        'amount',
                                        event.target.value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                            />

                            {errors.amount && (
                                <div className="mt-1 text-sm text-red-600">
                                    {errors.amount}
                                </div>
                            )}
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Payment Method
                            </label>

                            <select
                                value={
                                    data.payment_method
                                }
                                onChange={(event) =>
                                    setData(
                                        'payment_method',
                                        event.target.value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                            >
                                <option value="bank">
                                    Bank Transfer
                                </option>

                                <option value="upi">
                                    UPI
                                </option>
                            </select>
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Note
                            </label>

                            <input
                                type="text"
                                value={
                                    data.request_note
                                }
                                onChange={(event) =>
                                    setData(
                                        'request_note',
                                        event.target.value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={
                            processing ||
                            !canWithdraw
                        }
                        className="mt-5 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Submitting...'
                            : 'Request Withdrawal'}
                    </button>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Request',
                                    'Date',
                                    'Amount',
                                    'Method',
                                    'Status',
                                    'Reference',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(withdrawals.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.request_number}
                                        </Cell>

                                        <Cell>
                                            {item.created_at}
                                        </Cell>

                                        <Cell>
                                            ₹{item.amount}
                                        </Cell>

                                        <Cell>
                                            {item.payment_method}
                                        </Cell>

                                        <Cell>
                                            <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold capitalize text-violet-700">
                                                {item.status}
                                            </span>
                                        </Cell>

                                        <Cell>
                                            {item.payment_reference ||
                                                '—'}
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Card({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Admin/Withdrawals/Index.jsx <<'JSX'
import {
    Head,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    withdrawals = {},
    counts = {},
    filters = {},
}) {
    const [note, setNote] =
        useState('');

    const [reference, setReference] =
        useState('');

    const [activeId, setActiveId] =
        useState(null);

    const rows =
        withdrawals.data ?? [];

    const filterStatus = (status) => {
        router.get(
            '/v2/admin/withdrawals',
            { status },
            {
                preserveState: true,
            }
        );
    };

    const postAction = (
        endpoint,
        payload
    ) => {
        router.post(
            endpoint,
            payload,
            {
                preserveScroll: true,

                onSuccess: () => {
                    setNote('');
                    setReference('');
                    setActiveId(null);
                },
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Withdrawal Requests"
            subtitle="Review and process payout requests"
        >
            <Head title="Withdrawal Requests" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-4">
                    {[
                        ['pending', 'Pending'],
                        ['approved', 'Approved'],
                        ['paid', 'Paid'],
                        ['rejected', 'Rejected'],
                    ].map(([status, label]) => (
                        <button
                            key={status}
                            type="button"
                            onClick={() =>
                                filterStatus(status)
                            }
                            className={[
                                'rounded-2xl border bg-white p-5 text-left shadow-sm',
                                filters.status === status
                                    ? 'border-violet-500 ring-2 ring-violet-100'
                                    : 'border-slate-200',
                            ].join(' ')}
                        >
                            <div className="text-sm text-slate-500">
                                {label}
                            </div>

                            <div className="mt-2 text-3xl font-bold text-slate-900">
                                {counts[status] ?? 0}
                            </div>
                        </button>
                    ))}
                </div>

                <div className="space-y-4">
                    {rows.map((item) => (
                        <section
                            key={item.id}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div className="grid gap-5 xl:grid-cols-[1fr_320px]">
                                <div>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h2 className="font-semibold text-slate-900">
                                                {
                                                    item.request_number
                                                }
                                            </h2>

                                            <p className="mt-1 text-sm text-slate-500">
                                                {
                                                    item.user
                                                        ?.name
                                                }{' '}
                                                •{' '}
                                                {
                                                    item.user
                                                        ?.email
                                                }
                                            </p>
                                        </div>

                                        <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold capitalize text-violet-700">
                                            {item.status}
                                        </span>
                                    </div>

                                    <div className="mt-5 grid gap-3 sm:grid-cols-4">
                                        <Info
                                            label="Amount"
                                            value={`₹${item.amount}`}
                                        />

                                        <Info
                                            label="Method"
                                            value={
                                                item.payment_method
                                            }
                                        />

                                        <Info
                                            label="KYC"
                                            value={
                                                item.payout_profile
                                                    ?.kyc_status ??
                                                'Unknown'
                                            }
                                        />

                                        <Info
                                            label="Account"
                                            value={
                                                item.payout_profile
                                                    ?.masked_bank_account ??
                                                item.payout_profile
                                                    ?.upi_id ??
                                                '—'
                                            }
                                        />
                                    </div>
                                </div>

                                <div>
                                    {activeId === item.id ? (
                                        <div className="space-y-3">
                                            <textarea
                                                value={note}
                                                onChange={(event) =>
                                                    setNote(
                                                        event
                                                            .target
                                                            .value
                                                    )
                                                }
                                                placeholder="Admin note or rejection reason"
                                                className="min-h-24 w-full rounded-xl border border-slate-300 p-3 text-sm"
                                            />

                                            {item.status ===
                                                'approved' && (
                                                <input
                                                    value={
                                                        reference
                                                    }
                                                    onChange={(
                                                        event
                                                    ) =>
                                                        setReference(
                                                            event
                                                                .target
                                                                .value
                                                        )
                                                    }
                                                    placeholder="Payment reference / UTR"
                                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                                />
                                            )}

                                            <div className="flex flex-wrap gap-2">
                                                {item.status ===
                                                    'pending' && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                postAction(
                                                                    `/v2/admin/withdrawals/${item.id}/approve`,
                                                                    {
                                                                        admin_note:
                                                                            note,
                                                                    }
                                                                )
                                                            }
                                                            className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white"
                                                        >
                                                            Approve
                                                        </button>

                                                        <button
                                                            type="button"
                                                            disabled={
                                                                note.trim()
                                                                    .length <
                                                                3
                                                            }
                                                            onClick={() =>
                                                                postAction(
                                                                    `/v2/admin/withdrawals/${item.id}/reject`,
                                                                    {
                                                                        rejection_reason:
                                                                            note,
                                                                    }
                                                                )
                                                            }
                                                            className="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                                        >
                                                            Reject
                                                        </button>
                                                    </>
                                                )}

                                                {item.status ===
                                                    'approved' && (
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            reference
                                                                .trim()
                                                                .length <
                                                            3
                                                        }
                                                        onClick={() =>
                                                            postAction(
                                                                `/v2/admin/withdrawals/${item.id}/paid`,
                                                                {
                                                                    payment_reference:
                                                                        reference,
                                                                    admin_note:
                                                                        note,
                                                                }
                                                            )
                                                        }
                                                        className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                                    >
                                                        Mark Paid
                                                    </button>
                                                )}

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setActiveId(
                                                            null
                                                        )
                                                    }
                                                    className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setActiveId(
                                                    item.id
                                                )
                                            }
                                            className="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white"
                                        >
                                            Process Request
                                        </button>
                                    )}
                                </div>
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </PanelLayout>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <div className="text-xs font-semibold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-1 text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}
JSX


echo "[7/8] Adding routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.kyc-profile.edit": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/kyc-profile',
        [\App\Http\Controllers\V2\PayoutProfileController::class, 'edit']
    )
    ->name('v2.kyc-profile.edit');
""",

    "v2.kyc-profile.update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/kyc-profile',
        [\App\Http\Controllers\V2\PayoutProfileController::class, 'update']
    )
    ->name('v2.kyc-profile.update');
""",

    "v2.withdrawals.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/withdrawals',
        [\App\Http\Controllers\V2\WithdrawalController::class, 'index']
    )
    ->name('v2.withdrawals.index');
""",

    "v2.withdrawals.store": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/withdrawals',
        [\App\Http\Controllers\V2\WithdrawalController::class, 'store']
    )
    ->name('v2.withdrawals.store');
""",

    "v2.admin.withdrawals.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/withdrawals',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'index']
    )
    ->name('v2.admin.withdrawals.index');
""",

    "v2.admin.withdrawals.approve": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/withdrawals/{withdrawal}/approve',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'approve']
    )
    ->name('v2.admin.withdrawals.approve');
""",

    "v2.admin.withdrawals.reject": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/withdrawals/{withdrawal}/reject',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'reject']
    )
    ->name('v2.admin.withdrawals.reject');
""",

    "v2.admin.withdrawals.paid": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/withdrawals/{withdrawal}/paid',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'markPaid']
    )
    ->name('v2.admin.withdrawals.paid');
""",

    "v2.admin.kyc.verify": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/kyc/{profile}/verify',
        [\App\Http\Controllers\V2\Admin\WithdrawalManagementController::class, 'verifyKyc']
    )
    ->name('v2.admin.kyc.verify');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} withdrawal/KYC routes added.")
PY


echo "[8/8] Running migrations, checks and build..."

php artisan migrate --force

php -l app/Models/Finance/PayoutProfile.php
php -l app/Models/Finance/WithdrawalRequest.php
php -l app/Services/V2/WithdrawalService.php
php -l app/Http/Controllers/V2/PayoutProfileController.php
php -l app/Http/Controllers/V2/WithdrawalController.php
php -l app/Http/Controllers/V2/Admin/WithdrawalManagementController.php
php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "WithdrawalKycEngine",\n  "installed": true,\n  "version": "4.2.0",\n  "minimum_withdrawal": 1000,\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/withdrawal-kyc-engine-installed.json

echo ""
echo "===== WITHDRAWAL & KYC ROUTES ====="

php artisan route:list | grep -E \
"v2/(withdrawals|kyc-profile|admin/withdrawals|admin/kyc)"

echo ""
echo "=================================================="
echo "WITHDRAWAL + KYC ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/withdrawal-kyc-engine-installed.json

echo ""
echo "User pages:"
echo "https://artist.mixxtune.com/v2/kyc-profile"
echo "https://artist.mixxtune.com/v2/withdrawals"

echo ""
echo "Admin page:"
echo "https://admin.mixxtune.com/v2/admin/withdrawals"

echo ""
echo "Backup:"
echo "$BACKUP"
