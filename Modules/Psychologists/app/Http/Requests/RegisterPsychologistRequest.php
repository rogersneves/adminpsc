<?php

declare(strict_types=1);

namespace Modules\Psychologists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Psychologists\DTOs\RegisterPsychologistData;
use Modules\Psychologists\Models\Psychologist;

class RegisterPsychologistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Psychologist::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'professional_registry' => ['required', 'string', 'max:32'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['string', 'max:100'],
            'default_session_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:240'],
        ];
    }

    public function toDto(): RegisterPsychologistData
    {
        return new RegisterPsychologistData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            professionalRegistry: $this->string('professional_registry')->toString(),
            specialties: $this->input('specialties', []),
            defaultSessionDurationMinutes: (int) $this->input('default_session_duration_minutes', 50),
        );
    }
}
