<?php

declare(strict_types=1);

namespace Modules\Security\Actions\Lgpd;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Audit\Services\AuditLogger;
use Modules\Guardians\Models\Guardian;
use Modules\Patients\Models\Patient;
use Modules\Users\Models\User;

/**
 * Anonimização irreversível de um paciente (LGPD, docs/04-Seguranca.md). Substitui a
 * PII por marcadores e apaga os campos cifrados/hashes — mantém a linha (soft-deleted)
 * para preservar as obrigações de retenção legal (vínculo com sessões/cobranças), mas
 * sem dado pessoal identificável. Cascata para responsáveis e para a conta de usuário
 * vinculada. Registra a operação na auditoria. Idempotente: pular se já anonimizado.
 */
class AnonymizePatientAction
{
    private const MARKER = '[titular anonimizado]';

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Patient $patient, ?User $actor = null): Patient
    {
        if ($patient->anonymized_at !== null) {
            return $patient;
        }

        return DB::transaction(function () use ($patient, $actor): Patient {
            $this->scrubGuardians($patient);
            $this->scrubUser($patient);

            $patient->forceFill([
                'display_name' => self::MARKER,
                'email' => 'anon-'.$patient->id.'@anonimizado.invalid',
                'document_number_encrypted' => null,
                'document_number_hash' => null,
                'birth_date_encrypted' => null,
                'phones_encrypted' => null,
                'emergency_contacts_encrypted' => null,
                'address_encrypted' => null,
                'anonymized_at' => now(),
            ])->save();

            $patient->delete(); // soft delete

            $this->audit->record(
                'lgpd.patient_anonymized',
                actor: $actor,
                subject: $patient,
                tenantId: $patient->tenant_id,
            );

            return $patient;
        });
    }

    private function scrubGuardians(Patient $patient): void
    {
        Guardian::query()
            ->withoutTenantScope()
            ->where('patient_id', $patient->id)
            ->get()
            ->each(function (Guardian $guardian): void {
                $guardian->forceFill([
                    'name' => self::MARKER,
                    'email' => null,
                    'document_number_encrypted' => null,
                    'document_number_hash' => null,
                    'phone_encrypted' => null,
                    'address_encrypted' => null,
                ])->save();

                $guardian->delete();
            });
    }

    private function scrubUser(Patient $patient): void
    {
        if ($patient->user_id === null) {
            return;
        }

        $user = User::query()->find($patient->user_id);

        if ($user === null) {
            return;
        }

        // Scrub da PII de login + inutiliza a senha (conta não é mais acessível).
        $user->forceFill([
            'name' => self::MARKER,
            'email' => 'anon-'.$user->id.'@anonimizado.invalid',
            'password' => bcrypt(Str::random(64)),
            'mfa_totp_secret' => null,
        ])->save();
    }
}
