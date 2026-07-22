<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\MedicalRecords\DTOs\MedicalRecordContentData;
use Modules\MedicalRecords\Models\MedicalRecordAttachment;
use Modules\MedicalRecords\Models\MedicalRecordEntry;
use Modules\MedicalRecords\Services\AttachmentStorage;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;

/**
 * Sempre cria uma nova versão — nunca atualiza uma existente (docs/06-Roadmap.md
 * Fase 4). Campos de conteúdo não enviados herdam o valor da versão anterior, mesmo
 * padrão de fallback já usado em UpdatePatientProfileAction (Fase 2).
 */
class CreateMedicalRecordEntryAction
{
    public function __construct(private readonly AttachmentStorage $attachments) {}

    public function __invoke(
        Patient $patient,
        Psychologist $psychologist,
        MedicalRecordContentData $data,
        ?string $sessionId,
        ?UploadedFile $attachment,
    ): MedicalRecordEntry {
        return DB::transaction(function () use ($patient, $psychologist, $data, $sessionId, $attachment) {
            $previous = MedicalRecordEntry::query()
                ->where('patient_id', $patient->id)
                ->orderByDesc('version')
                ->first();

            $previousContent = $previous?->content_encrypted ?? [];

            $entry = MedicalRecordEntry::query()->create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'psychologist_id' => $psychologist->id,
                'session_id' => $sessionId,
                'version' => ($previous?->version ?? 0) + 1,
                'previous_version_id' => $previous?->id,
                'content_encrypted' => [
                    'notes' => $data->notes ?? ($previousContent['notes'] ?? null),
                    'therapeutic_objectives' => $data->therapeuticObjectives ?? ($previousContent['therapeutic_objectives'] ?? null),
                    'therapeutic_plan' => $data->therapeuticPlan ?? ($previousContent['therapeutic_plan'] ?? null),
                ],
                'created_at' => now(),
            ]);

            if ($attachment !== null) {
                $stored = $this->attachments->store($attachment);

                MedicalRecordAttachment::query()->create([
                    'tenant_id' => $patient->tenant_id,
                    'medical_record_entry_id' => $entry->id,
                    'file_path_encrypted' => $stored['path'],
                    'original_filename_encrypted' => $stored['original_name'],
                    'mime_type' => $stored['mime_type'],
                    'size_bytes' => $stored['size'],
                    'created_at' => now(),
                ]);
            }

            return $entry;
        });
    }
}
