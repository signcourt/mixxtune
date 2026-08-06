<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class RevenueAgreement extends Model
{
    protected $table =
        'revenue_agreements';

    protected $fillable = [
        'public_id',
        'subject_type',
        'subject_id',
        'agreement_name',
        'beneficiary_percentage',
        'company_retention_percentage',
        'parent_commission_percentage',
        'currency',
        'effective_from',
        'effective_to',
        'status',
        'priority',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'subject_id' =>
            'integer',

        'beneficiary_percentage' =>
            'decimal:4',

        'company_retention_percentage' =>
            'decimal:4',

        'parent_commission_percentage' =>
            'decimal:4',

        'effective_from' =>
            'date',

        'effective_to' =>
            'date',

        'priority' =>
            'integer',

        'metadata' =>
            'array',
    ];
}
