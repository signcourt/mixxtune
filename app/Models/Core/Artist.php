<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Models\Distribution\Release;
use App\Models\Finance\RoyaltyLedger;
use App\Models\Finance\WalletTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'user_id',
        'label_id',
        'stage_name',
        'legal_name',
        'slug',
        'email',
        'phone',
        'country',
        'timezone',
        'currency',
        'revenue_share_percentage',
        'profile_image_path',
        'bio',
        'account_status',
        'kyc_status',
        'can_receive_splits',
        'can_create_releases',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'revenue_share_percentage' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function royaltyLedgers(): HasMany
    {
        return $this->hasMany(RoyaltyLedger::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'admin_artist_assignments',
            'artist_id',
            'user_id'
        )
            ->withPivot([
                'assignment_role',
                'can_view',
                'can_edit',
                'can_manage_releases',
                'can_manage_team',
                'can_manage_splits',
                'assigned_by',
            ])
            ->withTimestamps();
    }

}