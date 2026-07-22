<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\MedicalRecords\DTOs\MedicalRecordContentData;

class StoreMedicalRecordEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:10000'],
            'therapeutic_objectives' => ['nullable', 'string', 'max:10000'],
            'therapeutic_plan' => ['nullable', 'string', 'max:10000'],
            'session_id' => ['nullable', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:10240'], // 10MB, em KB pra validação do Laravel
        ];
    }

    public function contentData(): MedicalRecordContentData
    {
        return new MedicalRecordContentData(
            notes: $this->input('notes'),
            therapeuticObjectives: $this->input('therapeutic_objectives'),
            therapeuticPlan: $this->input('therapeutic_plan'),
        );
    }
}
