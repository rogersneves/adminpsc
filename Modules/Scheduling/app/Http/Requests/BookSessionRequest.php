<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Models\Session;

class BookSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Session::class);
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'modality' => ['required', 'string', 'in:'.implode(',', array_column(SessionModality::cases(), 'value'))],
        ];
    }
}
