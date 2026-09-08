<?php

namespace App\Http\Requests\V3\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role
            === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'required',
                'string',
                'max:191',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'direction' => [
                'required',
                Rule::in([
                    'credit',
                    'debit',
                ]),
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'reason' => [
                'required',
                'string',
                'max:5000',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'internal_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'effective_date' => [
                'nullable',
                'date',
            ],

            'type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
        ];
    }
}
