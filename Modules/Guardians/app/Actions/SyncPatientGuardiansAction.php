<?php

declare(strict_types=1);

namespace Modules\Guardians\Actions;

use Modules\Guardians\Models\Guardian;
use Modules\Patients\Models\Patient;
use Modules\Security\Services\EncryptionService;

/**
 * Escopo desta fase: só adiciona responsáveis novos (a tela de perfil do paciente é
 * um formulário de "completar cadastro", não um CRUD completo de responsáveis).
 * Editar/remover um responsável já cadastrado fica para quando houver uma tela
 * dedicada de gestão de paciente.
 */
class SyncPatientGuardiansAction
{
    public function __construct(private readonly EncryptionService $encryption) {}

    public function __invoke(Patient $patient, array $guardiansData): void
    {
        foreach ($guardiansData as $guardianData) {
            $documentNumber = $this->normalizeDocumentNumber($guardianData['document_number'] ?? null);

            Guardian::query()->create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'name' => $guardianData['name'],
                'document_number_encrypted' => $documentNumber,
                'document_number_hash' => $documentNumber
                    ? $this->encryption->searchHash($documentNumber, 'guardian_document_number')
                    : null,
                'email' => $guardianData['email'] ?? null,
                'phone_encrypted' => $guardianData['phone'] ?? null,
                'address_encrypted' => $guardianData['address'] ?? null,
                'relationship' => $guardianData['relationship'],
            ]);
        }
    }

    private function normalizeDocumentNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/\D/', '', $value);
    }
}
