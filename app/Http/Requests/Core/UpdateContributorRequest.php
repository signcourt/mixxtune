<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContributorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'artist_id' => [
                'nullable',
                'integer',
                'exists:artists,id',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'legal_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'ipi_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'isni' => [
                'nullable',
                'string',
                'max:50',
            ],

            'primary_role' => [
                'sometimes',
                Rule::in([
                    'composer',
                    'lyricist',
                    'producer',
                    'publisher',
                    'performer',
                    'featured_artist',
                    'other',
                ]),
            ],

            'can_receive_splits' => [
                'nullable',
                'boolean',
            ],

            'has_dashboard_access' => [
                'nullable',
                'boolean',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],
        ];
    }
}
