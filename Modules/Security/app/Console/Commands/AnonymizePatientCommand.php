<?php

declare(strict_types=1);

namespace Modules\Security\Console\Commands;

use Illuminate\Console\Command;
use Modules\Patients\Models\Patient;
use Modules\Security\Actions\Lgpd\AnonymizePatientAction;

/**
 * Anonimização irreversível de um paciente sob solicitação/obrigação LGPD
 * (docs/04-Seguranca.md). Processo deliberadamente manual e explícito — não é
 * automático. Requer --force para confirmar a operação irreversível.
 */
class AnonymizePatientCommand extends Command
{
    protected $signature = 'lgpd:anonymize-patient {patient : ID do paciente} {--force : Confirma a operação irreversível}';

    protected $description = 'Anonimiza irreversivelmente a PII de um paciente (LGPD).';

    public function handle(AnonymizePatientAction $anonymize): int
    {
        $patient = Patient::query()->withoutTenantScope()->withTrashed()->find($this->argument('patient'));

        if ($patient === null) {
            $this->error('Paciente não encontrado.');

            return self::FAILURE;
        }

        if ($patient->anonymized_at !== null) {
            $this->info('Paciente já está anonimizado. Nada a fazer.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error('Operação irreversível. Reexecute com --force para confirmar.');

            return self::FAILURE;
        }

        $anonymize($patient);

        $this->info("Paciente [{$patient->id}] anonimizado.");

        return self::SUCCESS;
    }
}
