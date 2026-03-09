<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncAdjustmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustments' => 'present|array',
            'adjustments.*.type' => 'required|in:tip,service,tax,delivery,discount',
            'adjustments.*.calc_mode' => 'required|in:percent,fixed',
            'adjustments.*.value' => 'required|numeric|min:0',
            'adjustments.*.split_mode' => 'required|in:proportional,equal',
        ];
    }
}
