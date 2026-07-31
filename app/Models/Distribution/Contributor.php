<?php

namespace App\Models\Distribution;

use App\Models\Core\Artist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contributor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'user_id',
        'artist_id',
        'name',
        'legal_name',
        'email',
        'phone',
        'country',
        'ipi_number',
        'isni',
        'primary_role',
        'can_receive_splits',
        'has_dashboard_access',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'can_receive_splits' => 'boolean',
            'has_dashboard_access' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contributor $contributor): void {
            $contributor->public_id ??= (string) Str::ulid();
            $contributor->status ??= 'active';
            $contributor->can_receive_splits ??= true;
            $contributor->has_dashboard_access ??= false;
        });
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
