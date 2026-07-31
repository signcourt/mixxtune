<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_label_id' => [
                'nullable',
                'integer',
                'exists:labels,id',
            ],

            'label_type' => [
                'sometimes',
                Rule::in(['label', 'sub_label']),
            ],

            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'legal_name' => [
                'nullable',
                'string',
                'max:200',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'country' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'timezone' => [
                'sometimes',
                'timezone',
            ],

            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],

            'payout_cycle' => [
                'sometimes',
                Rule::in(['monthly', 'quarterly']),
            ],

            'minimum_withdrawal_amount' => [
                'sometimes',
                'numeric',
                'min:5000',
            ],

            'royalty_share_percentage' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],

            'parent_commission_percentage' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],

            'can_access_catalogue' => ['sometimes', 'boolean'],
            'can_access_royalties' => ['sometimes', 'boolean'],
            'can_access_reports' => ['sometimes', 'boolean'],
            'can_access_wallet' => ['sometimes', 'boolean'],
            'can_withdraw' => ['sometimes', 'boolean'],

            'status' => [
                'sometimes',
                Rule::in(['active', 'inactive', 'suspended']),
            ],
        ];
    }
}
