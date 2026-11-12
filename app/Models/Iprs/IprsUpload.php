<?php

namespace App\Models\Iprs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IprsUpload extends Model
{
    protected $fillable = [
        'public_id',
        'file_name',
        'file_hash',
        'stored_path',
        'uploaded_by',
        'member_internal_no',
        'member_name',
        'ipi_name_no',
        'ipi_base_no',
        'file_total',
        'parsed_total',
        'total_lines',
        'total_works',
        'status',
        'royalty_type',
        'period_label',
        'metadata',
    ];

    protected $casts = [
        'file_total' => 'decimal:4',
        'parsed_total' => 'decimal:4',
        'total_lines' => 'integer',
        'total_works' => 'integer',
        'metadata' => 'array',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(IprsRoyaltyLine::class, 'iprs_upload_id');
    }
}
