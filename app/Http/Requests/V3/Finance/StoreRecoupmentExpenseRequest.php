<?php

namespace App\Http\Requests\V3\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecoupmentExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role
            === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'category' => [
                'required',
                Rule::in([
                    'advance',
                    'youtube_promotion',
                    'meta_ads',
                    'google_ads',
                    'music_video_promotion',
                    'influencer_promotion',
                    'pr_media',
                    'artwork_production',
                    'marketing',
                    'distribution_expense',
                    'legal_expense',
                    'other',
                ]),
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'is_recoverable' => [
                'required',
                'boolean',
            ],

            'expense_date' => [
                'nullable',
                'date',
            ],
        ];
    }
}
