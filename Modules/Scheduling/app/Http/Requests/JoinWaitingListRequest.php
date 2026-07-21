<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinWaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'after_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function desiredPeriod(): array
    {
        return [
            'from' => $this->string('from')->toString(),
            'to' => $this->string('to')->toString(),
            'notes' => $this->input('notes'),
        ];
    }
}
