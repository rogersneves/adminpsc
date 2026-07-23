<?php

declare(strict_types=1);

namespace Modules\Financial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChargeDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
