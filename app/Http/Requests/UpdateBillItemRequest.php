<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:1',
            'price_per_unit' => 'sometimes|numeric|min:0',
        ];
    }
}
