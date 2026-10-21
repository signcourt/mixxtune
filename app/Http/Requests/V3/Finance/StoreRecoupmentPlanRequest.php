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

    /* MIXXTUNE_RECOUPMENT_V2: the old form sent initial_advance_amount */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('initial_amount') && $this->filled('initial_advance_amount')) {
            $this->merge(['initial_amount' => $this->input('initial_advance_amount')]);
        }

        foreach (['label_id', 'artist_id', 'maximum_recovery_percentage', 'initial_amount'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function attributes(): array
    {
        return [
            'base_percentage' => 'owner share',
            'recovery_uplift_percentage' => 'recovery %',
            'maximum_recovery_percentage' => 'maximum recovery %',
            'initial_amount' => 'advance amount',
        ];
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
            'notify_owner' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
