<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Label extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_label_id',
        'label_type',
        'public_id',
        'name',
        'slug',
        'legal_name',
        'email',
        'phone',
        'website',
        'logo_path',
        'country',
        'timezone',
        'currency',
        'payout_cycle',
        'minimum_withdrawal_amount',
        'royalty_share_percentage',
        'parent_commission_percentage',
        'status',
        'can_access_catalogue',
        'can_access_royalties',
        'can_access_reports',
        'can_access_wallet',
        'can_withdraw',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_withdrawal_amount' => 'decimal:2',
            'royalty_share_percentage' => 'decimal:2',
            'parent_commission_percentage' => 'decimal:2',
            'can_access_catalogue' => 'boolean',
            'can_access_royalties' => 'boolean',
            'can_access_reports' => 'boolean',
            'can_access_wallet' => 'boolean',
            'can_withdraw' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_label_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_label_id');
    }

    public function artists(): HasMany
    {
        return $this->hasMany(Artist::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isMonthlyPayout(): bool
    {
        return $this->payout_cycle === 'monthly';
    }

    public function isQuarterlyPayout(): bool
    {
        return $this->payout_cycle === 'quarterly';
    }

    public function isSubLabel(): bool
    {
        return $this->parent_label_id !== null;
    }

    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'admin_label_assignments',
            'label_id',
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