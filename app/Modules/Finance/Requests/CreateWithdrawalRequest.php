<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'string', 'max:255'],
            'provider_method' => ['sometimes', 'nullable', 'string', 'max:80'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
