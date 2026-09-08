<?php

namespace App\Http\Requests\V3\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecoupmentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role
            === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                Rule::in([
                    'signed_agreement',
                    'advance_agreement',
                    'invoice',
                    'payment_receipt',
                    'bank_proof',
                    'utr_proof',
                    'addendum',
                    'other',
                ]),
            ],

            'recoupment_expense_id' => [
                'nullable',
                'integer',
                'exists:recoupment_expenses,id',
            ],

            'document' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp',
            ],
        ];
    }
}
