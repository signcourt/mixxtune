<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label_id' => ['required', 'integer', 'exists:labels,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],

            'stage_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],

            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'bio' => ['nullable', 'string'],

            'account_status' => [
                'nullable',
                Rule::in(['active', 'inactive', 'suspended']),
            ],

            'kyc_status' => [
                'nullable',
                Rule::in(['pending', 'approved', 'rejected']),
            ],

            'can_receive_splits' => ['nullable', 'boolean'],
            'can_create_releases' => ['nullable', 'boolean'],
        ];
    }
}
