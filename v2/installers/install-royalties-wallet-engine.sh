#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/royalties-wallet-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Models/Finance \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Royalties \
    resources/js/Pages/V2/Wallet \
    resources/js/Pages/V2/Admin/Finance \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING ROYALTIES + WALLET ENGINE"
echo "=============================================="

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Config/panelRoutes.js
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/9] Creating finance migrations..."

MIGRATION="database/migrations/2026_08_01_000010_create_v2_royalty_wallet_tables.php"

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
        if (!Schema::hasTable('royalty_statements')) {
            Schema::create(
                'royalty_statements',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'artist_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'label_id'
                    )->nullable();

                    $table->string(
                        'statement_month',
                        10
                    );

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->decimal(
                        'gross_earnings',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'commission_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'other_deductions',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'net_payable',
                        20,
                        8
                    )->default(0);

                    $table->string(
                        'status',
                        30
                    )->default('pending');

                    $table->timestamp(
                        'approved_at'
                    )->nullable();

                    $table->timestamp(
                        'available_at'
                    )->nullable();

                    $table->timestamp(
                        'paid_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'approved_by'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->timestamps();

                    $table->unique(
                        [
                            'artist_id',
                            'label_id',
                            'statement_month',
                            'currency',
                        ],
                        'royalty_statement_owner_month_unique'
                    );

                    $table->index([
                        'status',
                        'statement_month',
                    ]);

                    $table->index('artist_id');
                    $table->index('label_id');
                }
            );
        }

        if (!Schema::hasTable('wallet_accounts')) {
            Schema::create(
                'wallet_accounts',
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
                        'currency',
                        10
                    )->default('INR');

                    $table->decimal(
                        'pending_balance',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'available_balance',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'withdrawn_balance',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'lifetime_earnings',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'hold_balance',
                        20,
                        8
                    )->default(0);

                    $table->timestamps();

                    $table->index('currency');

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create(
                'wallet_transactions',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'wallet_account_id'
                    );

                    $table->string(
                        'type',
                        30
                    );

                    $table->string(
                        'category',
                        50
                    );

                    $table->decimal(
                        'amount',
                        20,
                        8
                    );

                    $table->decimal(
                        'balance_before',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'balance_after',
                        20,
                        8
                    )->default(0);

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->string(
                        'reference_type',
                        100
                    )->nullable();

                    $table->unsignedBigInteger(
                        'reference_id'
                    )->nullable();

                    $table->string(
                        'reference_code',
                        100
                    )->nullable();

                    $table->text(
                        'description'
                    )->nullable();

                    $table->json(
                        'meta'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'wallet_account_id',
                        'created_at',
                    ]);

                    $table->index([
                        'reference_type',
                        'reference_id',
                    ]);

                    $table->foreign(
                        'wallet_account_id'
                    )
                        ->references('id')
                        ->on('wallet_accounts')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('royalty_allocations')) {
            Schema::create(
                'royalty_allocations',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'royalty_statement_id'
                    );

                    $table->unsignedBigInteger(
                        'report_row_id'
                    );

                    $table->unsignedBigInteger(
                        'release_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'track_id'
                    )->nullable();

                    $table->decimal(
                        'gross_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'net_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'share_percentage',
                        8,
                        4
                    )->default(100);

                    $table->timestamps();

                    $table->unique(
                        [
                            'royalty_statement_id',
                            'report_row_id',
                        ],
                        'royalty_statement_report_row_unique'
                    );

                    $table->index('release_id');
                    $table->index('track_id');

                    $table->foreign(
                        'royalty_statement_id'
                    )
                        ->references('id')
                        ->on('royalty_statements')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'report_row_id'
                    )
                        ->references('id')
                        ->on('report_rows')
                        ->cascadeOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial records must not be deleted
         * automatically in production.
         */
    }
};
PHP
fi


echo "[2/9] Creating finance models..."

cat > app/Models/Finance/RoyaltyStatement.php <<'PHP'
<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class RoyaltyStatement extends Model
{
    protected $fillable = [
        'public_id',
        'artist_id',
        'label_id',
        'statement_month',
        'currency',
        'gross_earnings',
        'commission_amount',
        'tax_amount',
        'other_deductions',
        'net_payable',
        'status',
        'approved_at',
        'available_at',
        'paid_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'gross_earnings' => 'decimal:8',
        'commission_amount' => 'decimal:8',
        'tax_amount' => 'decimal:8',
        'other_deductions' => 'decimal:8',
        'net_payable' => 'decimal:8',
        'approved_at' => 'datetime',
        'available_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function allocations()
    {
        return $this->hasMany(
            RoyaltyAllocation::class
        );
    }
}
PHP

cat > app/Models/Finance/RoyaltyAllocation.php <<'PHP'
<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class RoyaltyAllocation extends Model
{
    protected $fillable = [
        'public_id',
        'royalty_statement_id',
        'report_row_id',
        'release_id',
        'track_id',
        'gross_amount',
        'net_amount',
        'share_percentage',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'share_percentage' => 'decimal:4',
    ];

    public function statement()
    {
        return $this->belongsTo(
            RoyaltyStatement::class,
            'royalty_statement_id'
        );
    }
}
PHP

cat > app/Models/Finance/WalletAccount.php <<'PHP'
<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WalletAccount extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'currency',
        'pending_balance',
        'available_balance',
        'withdrawn_balance',
        'lifetime_earnings',
        'hold_balance',
    ];

    protected $casts = [
        'pending_balance' => 'decimal:8',
        'available_balance' => 'decimal:8',
        'withdrawn_balance' => 'decimal:8',
        'lifetime_earnings' => 'decimal:8',
        'hold_balance' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function transactions()
    {
        return $this->hasMany(
            WalletTransaction::class
        );
    }
}
PHP

cat > app/Models/Finance/WalletTransaction.php <<'PHP'
<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'public_id',
        'wallet_account_id',
        'type',
        'category',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'reference_type',
        'reference_id',
        'reference_code',
        'description',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'meta' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(
            WalletAccount::class,
            'wallet_account_id'
        );
    }
}
PHP


echo "[3/9] Creating Wallet Service..."

cat > app/Services/V2/WalletService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Finance\WalletAccount;
use App\Models\Finance\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function account(
        User $user,
        string $currency = 'INR'
    ): WalletAccount {
        return WalletAccount::query()
            ->firstOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'currency' =>
                        strtoupper($currency),
                ]
            );
    }

    public function creditPending(
        User $user,
        float $amount,
        string $category,
        array $reference = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Credit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $category,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                $wallet->refresh();

                $before =
                    (float) $wallet
                        ->pending_balance;

                $after =
                    $before + $amount;

                $wallet->update([
                    'pending_balance' =>
                        $after,

                    'lifetime_earnings' =>
                        (float) $wallet
                            ->lifetime_earnings
                        + $amount,
                ]);

                return $this->transaction(
                    $wallet,
                    'credit',
                    $category,
                    $amount,
                    $before,
                    $after,
                    $reference
                );
            }
        );
    }

    public function releasePending(
        User $user,
        float $amount,
        array $reference = []
    ): array {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Release amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                $wallet->refresh();

                $pendingBefore =
                    (float) $wallet
                        ->pending_balance;

                if ($pendingBefore < $amount) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Pending balance is insufficient.',
                    ]);
                }

                $availableBefore =
                    (float) $wallet
                        ->available_balance;

                $wallet->update([
                    'pending_balance' =>
                        $pendingBefore - $amount,

                    'available_balance' =>
                        $availableBefore + $amount,
                ]);

                $pendingTransaction =
                    $this->transaction(
                        $wallet,
                        'debit',
                        'pending_release',
                        $amount,
                        $pendingBefore,
                        $pendingBefore - $amount,
                        $reference
                    );

                $availableTransaction =
                    $this->transaction(
                        $wallet,
                        'credit',
                        'available_credit',
                        $amount,
                        $availableBefore,
                        $availableBefore + $amount,
                        $reference
                    );

                return [
                    'wallet' =>
                        $wallet->fresh(),

                    'pending_transaction' =>
                        $pendingTransaction,

                    'available_transaction' =>
                        $availableTransaction,
                ];
            }
        );
    }

    public function debitAvailable(
        User $user,
        float $amount,
        string $category,
        array $reference = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Debit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $category,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                $wallet->refresh();

                $before =
                    (float) $wallet
                        ->available_balance;

                if ($before < $amount) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Available wallet balance is insufficient.',
                    ]);
                }

                $after =
                    $before - $amount;

                $wallet->update([
                    'available_balance' =>
                        $after,

                    'withdrawn_balance' =>
                        (float) $wallet
                            ->withdrawn_balance
                        + $amount,
                ]);

                return $this->transaction(
                    $wallet,
                    'debit',
                    $category,
                    $amount,
                    $before,
                    $after,
                    $reference
                );
            }
        );
    }

    private function transaction(
        WalletAccount $wallet,
        string $type,
        string $category,
        float $amount,
        float $before,
        float $after,
        array $reference
    ): WalletTransaction {
        $referenceType =
            $reference['reference_type']
            ?? null;

        $referenceId =
            $reference['reference_id']
            ?? null;

        $referenceCode =
            $reference['reference_code']
            ?? null;

        if (
            $referenceType
            && $referenceId
            && WalletTransaction::query()
                ->where(
                    'wallet_account_id',
                    $wallet->id
                )
                ->where(
                    'category',
                    $category
                )
                ->where(
                    'reference_type',
                    $referenceType
                )
                ->where(
                    'reference_id',
                    $referenceId
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'reference' =>
                    'This wallet transaction has already been recorded.',
            ]);
        }

        return WalletTransaction::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'wallet_account_id' =>
                $wallet->id,

            'type' =>
                $type,

            'category' =>
                $category,

            'amount' =>
                $amount,

            'balance_before' =>
                $before,

            'balance_after' =>
                $after,

            'currency' =>
                $wallet->currency,

            'reference_type' =>
                $referenceType,

            'reference_id' =>
                $referenceId,

            'reference_code' =>
                $referenceCode,

            'description' =>
                $reference['description']
                ?? null,

            'meta' =>
                $reference['meta']
                ?? [],

            'created_by' =>
                $reference['created_by']
                ?? auth()->id(),
        ]);
    }
}
PHP


echo "[4/9] Creating Royalty Service..."

cat > app/Services/V2/RoyaltyService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\RoyaltyAllocation;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoyaltyService
{
    public function __construct(
        private readonly WalletService $wallet
    ) {
    }

    public function generateMonthlyStatements(
        string $month,
        float $commissionPercent = 0,
        string $currency = 'INR'
    ): array {
        $rows = ReportRow::query()
            ->where(
                'sale_month',
                $month
            )
            ->whereNotNull('artist_id')
            ->get()
            ->groupBy('artist_id');

        $created = 0;
        $updated = 0;
        $failed = [];

        foreach ($rows as $artistId => $artistRows) {
            try {
                $statement =
                    $this->generateArtistStatement(
                        (int) $artistId,
                        $month,
                        $artistRows,
                        $commissionPercent,
                        $currency
                    );

                $statement->wasRecentlyCreated
                    ? $created++
                    : $updated++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'artist_id' =>
                        $artistId,

                    'message' =>
                        $exception->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed_count' =>
                count($failed),
            'failed' => $failed,
        ];
    }

    public function approve(
        RoyaltyStatement $statement,
        User $admin
    ): RoyaltyStatement {
        abort_unless(
            in_array(
                $statement->status,
                ['pending', 'generated'],
                true
            ),
            422,
            'Only pending statements can be approved.'
        );

        $owner = $this->ownerUser(
            $statement
        );

        abort_unless(
            $owner,
            422,
            'Statement owner user is missing.'
        );

        DB::transaction(
            function () use (
                $statement,
                $admin,
                $owner
            ) {
                $statement->update([
                    'status' =>
                        'approved',

                    'approved_at' =>
                        now(),

                    'approved_by' =>
                        $admin->id,
                ]);

                $this->wallet->creditPending(
                    $owner,
                    (float) $statement
                        ->net_payable,
                    'royalty_statement',
                    [
                        'currency' =>
                            $statement->currency,

                        'reference_type' =>
                            RoyaltyStatement::class,

                        'reference_id' =>
                            $statement->id,

                        'reference_code' =>
                            $statement->public_id,

                        'description' =>
                            "Royalty statement {$statement->statement_month}",

                        'created_by' =>
                            $admin->id,
                    ]
                );
            }
        );

        return $statement->fresh();
    }

    public function makeAvailable(
        RoyaltyStatement $statement,
        User $admin
    ): RoyaltyStatement {
        abort_unless(
            $statement->status ===
                'approved',
            422,
            'Statement must be approved first.'
        );

        $owner = $this->ownerUser(
            $statement
        );

        abort_unless(
            $owner,
            422,
            'Statement owner user is missing.'
        );

        DB::transaction(
            function () use (
                $statement,
                $owner,
                $admin
            ) {
                $this->wallet->releasePending(
                    $owner,
                    (float) $statement
                        ->net_payable,
                    [
                        'currency' =>
                            $statement->currency,

                        'reference_type' =>
                            RoyaltyStatement::class,

                        'reference_id' =>
                            $statement->id,

                        'reference_code' =>
                            $statement->public_id,

                        'description' =>
                            "Royalty available for {$statement->statement_month}",

                        'created_by' =>
                            $admin->id,
                    ]
                );

                $statement->update([
                    'status' =>
                        'available',

                    'available_at' =>
                        now(),
                ]);
            }
        );

        return $statement->fresh();
    }

    private function generateArtistStatement(
        int $artistId,
        string $month,
        $rows,
        float $commissionPercent,
        string $currency
    ): RoyaltyStatement {
        return DB::transaction(
            function () use (
                $artistId,
                $month,
                $rows,
                $commissionPercent,
                $currency
            ) {
                $gross = round(
                    (float) $rows->sum(
                        'earnings'
                    ),
                    8
                );

                $commission = round(
                    $gross
                    * (
                        $commissionPercent
                        / 100
                    ),
                    8
                );

                $net = round(
                    $gross - $commission,
                    8
                );

                $statement =
                    RoyaltyStatement::query()
                        ->updateOrCreate(
                            [
                                'artist_id' =>
                                    $artistId,

                                'label_id' =>
                                    null,

                                'statement_month' =>
                                    $month,

                                'currency' =>
                                    strtoupper($currency),
                            ],
                            [
                                'public_id' =>
                                    RoyaltyStatement::query()
                                        ->where(
                                            'artist_id',
                                            $artistId
                                        )
                                        ->where(
                                            'statement_month',
                                            $month
                                        )
                                        ->value(
                                            'public_id'
                                        )
                                    ?: (string) Str::ulid(),

                                'gross_earnings' =>
                                    $gross,

                                'commission_amount' =>
                                    $commission,

                                'net_payable' =>
                                    $net,

                                'status' =>
                                    'pending',
                            ]
                        );

                foreach ($rows as $row) {
                    RoyaltyAllocation::query()
                        ->updateOrCreate(
                            [
                                'royalty_statement_id' =>
                                    $statement->id,

                                'report_row_id' =>
                                    $row->id,
                            ],
                            [
                                'public_id' =>
                                    (string) Str::ulid(),

                                'release_id' =>
                                    $row->release_id,

                                'track_id' =>
                                    $row->track_id,

                                'gross_amount' =>
                                    $row->earnings,

                                'net_amount' =>
                                    $row->earnings
                                    * (
                                        1
                                        - (
                                            $commissionPercent
                                            / 100
                                        )
                                    ),

                                'share_percentage' =>
                                    100,
                            ]
                        );
                }

                return $statement;
            }
        );
    }

    private function ownerUser(
        RoyaltyStatement $statement
    ): ?User {
        if ($statement->artist_id) {
            $artist = Artist::query()
                ->find(
                    $statement->artist_id
                );

            return $artist?->user_id
                ? User::query()->find(
                    $artist->user_id
                )
                : null;
        }

        if ($statement->label_id) {
            $label = Label::query()
                ->find(
                    $statement->label_id
                );

            return $label?->user_id
                ? User::query()->find(
                    $label->user_id
                )
                : null;
        }

        return null;
    }
}
PHP


echo "[5/9] Creating Royalties and Wallet Controllers..."

cat > app/Http/Controllers/V2/RoyaltyController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoyaltyController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'royalties.view'
        );

        $role = $permissions->role(
            $request->user()
        );

        $query =
            RoyaltyStatement::query();

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'artist_id',
                $artistId ?: 0
            );
        } elseif ($role === 'label') {
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'label_id',
                $labelId ?: 0
            );
        } elseif ($role === 'admin') {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $request->user()->id
                )
                ->pluck('id');

            $query->whereIn(
                'artist_id',
                $artistIds
            );
        }

        $summary = [
            'gross' =>
                (float) (
                    (clone $query)
                        ->sum(
                            'gross_earnings'
                        )
                ),

            'pending' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'pending'
                        )
                        ->sum(
                            'net_payable'
                        )
                ),

            'approved' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'approved'
                        )
                        ->sum(
                            'net_payable'
                        )
                ),

            'available' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'available'
                        )
                        ->sum(
                            'net_payable'
                        )
                ),

            'paid' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'paid'
                        )
                        ->sum(
                            'net_payable'
                        )
                ),
        ];

        return Inertia::render(
            'V2/Royalties/Index',
            [
                'role' =>
                    $role,

                'summary' =>
                    $summary,

                'statements' =>
                    $query
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->paginate(25),
            ]
        );
    }
}
PHP

cat > app/Http/Controllers/V2/WalletController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use App\Services\V2\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        WalletService $walletService
    ): Response {
        $permissions->authorize(
            $request->user(),
            'wallet.view'
        );

        $wallet = $walletService->account(
            $request->user()
        );

        return Inertia::render(
            'V2/Wallet/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'wallet' =>
                    $wallet,

                'transactions' =>
                    $wallet
                        ->transactions()
                        ->orderByDesc('id')
                        ->paginate(30),
            ]
        );
    }
}
PHP


echo "[6/9] Creating Admin Finance Controller..."

cat > app/Http/Controllers/V2/Admin/FinanceController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\PermissionService;
use App\Services\V2\RoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
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

        return Inertia::render(
            'V2/Admin/Finance/Index',
            [
                'role' =>
                    $role,

                'statements' =>
                    RoyaltyStatement::query()
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->orderByDesc('id')
                        ->paginate(30),
            ]
        );
    }

    public function generate(
        Request $request,
        PermissionService $permissions,
        RoyaltyService $royalties
    ): RedirectResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            $role === 'super_admin',
            403,
            'Super Admin access required.'
        );

        $validated = $request->validate([
            'month' => [
                'required',
                'date_format:Y-m',
            ],

            'commission_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $result =
            $royalties
                ->generateMonthlyStatements(
                    $validated['month'],
                    (float) (
                        $validated[
                            'commission_percent'
                        ] ?? 0
                    )
                );

        return back()->with(
            'success',
            "Statements generated. Created: {$result['created']}, updated: {$result['updated']}, failed: {$result['failed_count']}."
        );
    }

    public function approve(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions,
        RoyaltyService $royalties
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403
        );

        $royalties->approve(
            $statement,
            $request->user()
        );

        return back()->with(
            'success',
            'Royalty statement approved.'
        );
    }

    public function makeAvailable(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions,
        RoyaltyService $royalties
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403
        );

        $royalties->makeAvailable(
            $statement,
            $request->user()
        );

        return back()->with(
            'success',
            'Royalty amount moved to available wallet balance.'
        );
    }
}
PHP


echo "[7/9] Creating frontend pages..."

cat > resources/js/Pages/V2/Royalties/Index.jsx <<'JSX'
import {
    Head,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    summary = {},
    statements = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Royalties"
            subtitle="Monthly royalty statements and payable earnings"
        >
            <Head title="Royalties" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['gross', 'Gross Earnings'],
                        ['pending', 'Pending'],
                        ['approved', 'Approved'],
                        ['available', 'Available'],
                        ['paid', 'Paid'],
                    ].map(([key, label]) => (
                        <div
                            key={key}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div className="text-sm text-slate-500">
                                {label}
                            </div>

                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                ₹
                                {Number(
                                    summary[key] ?? 0
                                ).toFixed(2)}
                            </div>
                        </div>
                    ))}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Month',
                                    'Gross',
                                    'Commission',
                                    'Tax',
                                    'Net Payable',
                                    'Status',
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
                            {(statements.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.statement_month}
                                        </Cell>

                                        <Cell>
                                            {item.gross_earnings}
                                        </Cell>

                                        <Cell>
                                            {item.commission_amount}
                                        </Cell>

                                        <Cell>
                                            {item.tax_amount}
                                        </Cell>

                                        <Cell>
                                            {item.net_payable}
                                        </Cell>

                                        <Cell>
                                            <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold capitalize text-violet-700">
                                                {item.status}
                                            </span>
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

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Wallet/Index.jsx <<'JSX'
import {
    Head,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    wallet = {},
    transactions = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Wallet"
            subtitle="Available balance and transaction history"
        >
            <Head title="Wallet" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Balance
                        label="Available"
                        value={
                            wallet.available_balance
                        }
                    />

                    <Balance
                        label="Pending"
                        value={
                            wallet.pending_balance
                        }
                    />

                    <Balance
                        label="On Hold"
                        value={
                            wallet.hold_balance
                        }
                    />

                    <Balance
                        label="Withdrawn"
                        value={
                            wallet.withdrawn_balance
                        }
                    />

                    <Balance
                        label="Lifetime Earnings"
                        value={
                            wallet.lifetime_earnings
                        }
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Date',
                                    'Type',
                                    'Category',
                                    'Description',
                                    'Amount',
                                    'Balance',
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
                            {(transactions.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.created_at}
                                        </Cell>

                                        <Cell>
                                            {item.type}
                                        </Cell>

                                        <Cell>
                                            {item.category}
                                        </Cell>

                                        <Cell>
                                            {item.description ||
                                                '—'}
                                        </Cell>

                                        <Cell>
                                            <span
                                                className={
                                                    item.type ===
                                                    'credit'
                                                        ? 'font-semibold text-emerald-600'
                                                        : 'font-semibold text-red-600'
                                                }
                                            >
                                                {item.type ===
                                                'credit'
                                                    ? '+'
                                                    : '-'}
                                                ₹{item.amount}
                                            </span>
                                        </Cell>

                                        <Cell>
                                            ₹
                                            {item.balance_after}
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

function Balance({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold text-slate-900">
                ₹
                {Number(value ?? 0).toFixed(
                    2
                )}
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

cat > resources/js/Pages/V2/Admin/Finance/Index.jsx <<'JSX'
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'super_admin',
    statements = {},
}) {
    const {
        data,
        setData,
        post,
        processing,
    } = useForm({
        month: '',
        commission_percent: 0,
    });

    const generate = (event) => {
        event.preventDefault();

        post(
            '/v2/admin/finance/statements/generate'
        );
    };

    const run = (endpoint) => {
        router.post(endpoint, {}, {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Finance & Royalties"
            subtitle="Generate and approve royalty statements"
        >
            <Head title="Finance & Royalties" />

            <div className="space-y-6">
                <form
                    onSubmit={generate}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 md:grid-cols-[180px_180px_auto]">
                        <input
                            type="month"
                            value={data.month}
                            onChange={(event) =>
                                setData(
                                    'month',
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3"
                            required
                        />

                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={
                                data.commission_percent
                            }
                            onChange={(event) =>
                                setData(
                                    'commission_percent',
                                    event.target.value
                                )
                            }
                            placeholder="Commission %"
                            className="rounded-xl border border-slate-300 px-4 py-3"
                        />

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            Generate Statements
                        </button>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Month',
                                    'Artist ID',
                                    'Gross',
                                    'Net',
                                    'Status',
                                    'Actions',
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
                            {(statements.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.statement_month}
                                        </Cell>

                                        <Cell>
                                            {item.artist_id ||
                                                '—'}
                                        </Cell>

                                        <Cell>
                                            {item.gross_earnings}
                                        </Cell>

                                        <Cell>
                                            {item.net_payable}
                                        </Cell>

                                        <Cell>
                                            {item.status}
                                        </Cell>

                                        <Cell>
                                            <div className="flex gap-2">
                                                {item.status ===
                                                    'pending' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            run(
                                                                `/v2/admin/finance/statements/${item.id}/approve`
                                                            )
                                                        }
                                                        className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white"
                                                    >
                                                        Approve
                                                    </button>
                                                )}

                                                {item.status ===
                                                    'approved' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            run(
                                                                `/v2/admin/finance/statements/${item.id}/available`
                                                            )
                                                        }
                                                        className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white"
                                                    >
                                                        Make Available
                                                    </button>
                                                )}
                                            </div>
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

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX


echo "[8/9] Adding finance routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.royalties.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/royalties',
        [\App\Http\Controllers\V2\RoyaltyController::class, 'index']
    )
    ->name('v2.royalties.index');
""",

    "v2.wallet.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/wallet',
        [\App\Http\Controllers\V2\WalletController::class, 'index']
    )
    ->name('v2.wallet.index');
""",

    "v2.admin.finance.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/finance',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'index']
    )
    ->name('v2.admin.finance.index');
""",

    "v2.admin.finance.generate": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/statements/generate',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'generate']
    )
    ->name('v2.admin.finance.generate');
""",

    "v2.admin.finance.approve": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/statements/{statement}/approve',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'approve']
    )
    ->name('v2.admin.finance.approve');
""",

    "v2.admin.finance.available": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/finance/statements/{statement}/available',
        [\App\Http\Controllers\V2\Admin\FinanceController::class, 'makeAvailable']
    )
    ->name('v2.admin.finance.available');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} finance routes added.")
PY


echo "[9/9] Running migrations and checks..."

php artisan migrate --force

php -l app/Models/Finance/RoyaltyStatement.php
php -l app/Models/Finance/RoyaltyAllocation.php
php -l app/Models/Finance/WalletAccount.php
php -l app/Models/Finance/WalletTransaction.php
php -l app/Services/V2/WalletService.php
php -l app/Services/V2/RoyaltyService.php
php -l app/Http/Controllers/V2/RoyaltyController.php
php -l app/Http/Controllers/V2/WalletController.php
php -l app/Http/Controllers/V2/Admin/FinanceController.php
php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "RoyaltiesWalletEngine",\n  "installed": true,\n  "version": "4.1.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/royalties-wallet-engine-installed.json

echo ""
echo "===== FINANCE ROUTES ====="

php artisan route:list | grep -E \
"v2/(royalties|wallet|admin/finance)"

echo ""
echo "=============================================="
echo "ROYALTIES + WALLET ENGINE INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/royalties-wallet-engine-installed.json

echo ""
echo "Pages:"
echo "https://artist.mixxtune.com/v2/royalties"
echo "https://artist.mixxtune.com/v2/wallet"
echo "https://admin.mixxtune.com/v2/admin/finance"

echo ""
echo "Backup:"
echo "$BACKUP"
