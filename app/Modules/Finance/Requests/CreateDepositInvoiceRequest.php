<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDepositInvoiceRequest extends FormRequest
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
            'amount_usd' => ['required', 'numeric', 'min:0.01'],
            'coin' => ['required', 'string', 'max:40'],
            'expires_minutes' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
