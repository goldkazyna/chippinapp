<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:equal,custom',
            'splits' => 'required_if:type,custom|array|min:1',
            'splits.*.participant_id' => 'required_with:splits|integer|exists:participants,id',
            'splits.*.quantity' => 'required_with:splits|numeric|min:0.01',
        ];
    }
}
