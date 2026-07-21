<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
        ];
    }
}
