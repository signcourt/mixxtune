<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DistributionStore extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'is_active',
        'default_selected',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_selected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
