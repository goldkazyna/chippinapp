<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPaidByRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_id' => 'required|integer|exists:participants,id',
        ];
    }
}
