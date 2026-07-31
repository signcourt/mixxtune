<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Foundation\Http\FormRequest;

class SaveTrackSplitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'split_type' => [
                'required',
                'in:master,publishing,mechanical,performance,youtube',
            ],

            'activate' => [
                'sometimes',
                'boolean',
            ],

            'splits' => [
                'required',
                'array',
                'min:1',
            ],

            'splits.*.contributor_id' => [
                'required',
                'integer',
                'distinct',
                'exists:contributors,id',
            ],

            'splits.*.percentage' => [
                'required',
                'numeric',
                'min:0.01',
                'max:100',
            ],

            'splits.*.is_recoupable' => [
                'nullable',
                'boolean',
            ],

            'splits.*.effective_from' => [
                'nullable',
                'date',
            ],

            'splits.*.effective_to' => [
                'nullable',
                'date',
                'after_or_equal:splits.*.effective_from',
            ],
        ];
    }
}
