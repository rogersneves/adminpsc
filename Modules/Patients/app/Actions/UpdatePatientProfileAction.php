<?php

declare(strict_types=1);

namespace Modules\Patients\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Actions\SyncPatientGuardiansAction;
use Modules\Patients\Models\Patient;
use Modules\Security\Services\EncryptionService;

/**
 * Campos opcionais pós-primeiro-acesso (docs/06-Roadmap.md Fase 2). A validação da
 * regra "responsável obrigatório se menor de 16" já rodou no FormRequest antes desta
 * Action ser chamada — aqui só persiste.
 */
class UpdatePatientProfileAction
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly SyncPatientGuardiansAction $syncGuardians,
    ) {}

    public function __invoke(Patient $patient, array $data): Patient
    {
        DB::transaction(function () use ($patient, $data) {
            $documentNumber = $this->normalizeDocumentNumber($data['document_number'] ?? null);

            $patient->fill([
                'document_number_encrypted' => $documentNumber ?? $patient->document_number_encrypted,
                'document_number_hash' => $documentNumber
                    ? $this->encryption->searchHash($documentNumber, 'patient_document_number')
                    : $patient->document_number_hash,
                'birth_date_encrypted' => $data['birth_date'] ?? $patient->birth_date_encrypted,
                'phones_encrypted' => $data['phones'] ?? $patient->phones_encrypted,
                'emergency_contacts_encrypted' => $data['emergency_contacts'] ?? $patient->emergency_contacts_encrypted,
                'address_encrypted' => $data['address'] ?? $patient->address_encrypted,
            ]);

            $patient->save();

            if (! empty($data['guardians'])) {
                ($this->syncGuardians)($patient, $data['guardians']);
            }
        });

        return $patient->fresh();
    }

    private function normalizeDocumentNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/\D/', '', $value);
    }
}
