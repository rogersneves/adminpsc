<?php

declare(strict_types=1);

namespace Modules\Financial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'installment_total' => ['nullable', 'integer', 'min:1', 'max:60'],
            'session_id' => ['nullable', 'uuid'],
        ];
    }
}
