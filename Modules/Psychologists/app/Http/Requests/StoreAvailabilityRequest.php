<?php

declare(strict_types=1);

namespace Modules\Psychologists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Psychologists\Enums\AvailabilityType;

class StoreAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', array_column(AvailabilityType::cases(), 'value'))],
            'weekday' => ['required_if:type,recorrente', 'nullable', 'integer', 'min:0', 'max:6'],
            'date' => ['required_unless:type,recorrente', 'nullable', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'session_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:240'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }
}
