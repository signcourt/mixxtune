<?php

namespace App\Http\Requests\V3\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReverseFinancialAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role
            === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'notify_owner' => ['nullable', 'boolean'], /* MIXXTUNE_ADJUSTMENTS_V2 */

            'reason' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}
