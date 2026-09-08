<?php

namespace App\Http\Requests\V3\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecoupmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role
            === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'label_id' => [
                'nullable',
                'integer',
                'exists:labels,id',
            ],

            'artist_id' => [
                'nullable',
                'integer',
                'exists:artists,id',
            ],

            'title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'base_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'recovery_uplift_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'lte:base_percentage',
            ],

            'maximum_recovery_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'lte:base_percentage',
            ],

            'recovery_method' => [
                'nullable',
                'in:percentage',
            ],

            'starts_on' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'initial_amount' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'initial_category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'initial_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'initial_description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'expense_date' => [
                'nullable',
                'date',
            ],
        ];
    }
}
