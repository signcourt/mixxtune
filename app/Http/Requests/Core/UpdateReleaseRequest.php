<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $release = $this->route('release');

        return [
            'artist_id' => ['sometimes', 'required', 'integer', 'exists:artists,id'],
            'label_id' => ['sometimes', 'required', 'integer', 'exists:labels,id'],
            'catalog_number' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('releases', 'catalog_number')->ignore($release?->id)],

            'release_type' => ['sometimes', 'required', 'string', 'max:30'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],

            'primary_artist_name' => ['sometimes', 'required', 'string', 'max:255'],
            'featuring_artist_name' => ['nullable', 'string', 'max:255'],

            'language' => ['nullable', 'string', 'max:100'],
            'primary_genre' => ['nullable', 'string', 'max:100'],
            'sub_genre' => ['nullable', 'string', 'max:100'],

            'upc' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('releases', 'upc')->ignore($release?->id),
            ],

            'upc_is_auto_generated' => ['nullable', 'boolean'],
            'original_release_date' => ['nullable', 'date'],
            'digital_release_date' => ['nullable', 'date'],

            'copyright_owner' => ['nullable', 'string', 'max:255'],
            'copyright_year' => ['nullable', 'digits:4'],
            'phonographic_owner' => ['nullable', 'string', 'max:255'],
            'phonographic_year' => ['nullable', 'digits:4'],

            'artwork_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:40'],
            'wizard_step' => ['nullable', 'integer', 'min:1'],
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
