<?php

declare(strict_types=1);

namespace Modules\Patients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Guardians\Rules\PatientRequiresGuardianIfMinor;
use Modules\Patients\Models\Patient;

class UpdatePatientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_number' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date', 'before:today', new PatientRequiresGuardianIfMinor($this->guardianCountAfterSave())],
            'phones' => ['nullable', 'array'],
            'phones.*' => ['string', 'max:32'],
            'emergency_contacts' => ['nullable', 'array'],
            'emergency_contacts.*.name' => ['required_with:emergency_contacts', 'string'],
            'emergency_contacts.*.phone' => ['required_with:emergency_contacts', 'string'],
            'address' => ['nullable', 'string', 'max:500'],
            'guardians' => ['nullable', 'array'],
            'guardians.*.name' => ['required_with:guardians', 'string', 'max:255'],
            'guardians.*.relationship' => ['required_with:guardians', 'string', 'max:32'],
            'guardians.*.document_number' => ['nullable', 'string', 'max:32'],
            'guardians.*.email' => ['nullable', 'email'],
            'guardians.*.phone' => ['nullable', 'string', 'max:32'],
            'guardians.*.address' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function guardianCountAfterSave(): int
    {
        $patient = Patient::query()->where('user_id', $this->user()->id)->first();

        $existing = $patient?->guardians()->count() ?? 0;
        $incoming = count($this->input('guardians', []));

        return $existing + $incoming;
    }
}
