<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
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
                'required',
                Rule::in(['label', 'sub_label']),
            ],

            'name' => [
                'required',
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
                'required',
                'string',
                'max:100',
            ],

            'timezone' => [
                'required',
                'timezone',
            ],

            'currency' => [
                'required',
                'string',
                'size:3',
            ],

            'payout_cycle' => [
                'required',
                Rule::in(['monthly', 'quarterly']),
            ],

            'minimum_withdrawal_amount' => [
                'nullable',
                'numeric',
                'min:5000',
            ],

            'royalty_share_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'parent_commission_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'can_access_catalogue' => ['required', 'boolean'],
            'can_access_royalties' => ['required', 'boolean'],
            'can_access_reports' => ['required', 'boolean'],
            'can_access_wallet' => ['required', 'boolean'],
            'can_withdraw' => ['required', 'boolean'],

            'status' => [
                'required',
                Rule::in(['active', 'inactive', 'suspended']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'minimum_withdrawal_amount.min' =>
                'Minimum withdrawal amount cannot be less than ₹5,000.',
        ];
    }
}
