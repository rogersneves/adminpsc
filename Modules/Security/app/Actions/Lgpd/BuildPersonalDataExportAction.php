<?php

declare(strict_types=1);

namespace Modules\Security\Actions\Lgpd;

use Modules\Financial\Models\FinancialCharge;
use Modules\Guardians\Models\Guardian;
use Modules\Patients\Models\Patient;
use Modules\Scheduling\Models\Session;
use Modules\Security\Models\Consent;
use Modules\Users\Models\User;

/**
 * Monta o pacote de dados pessoais do próprio titular (LGPD Art. 18, direito de acesso/
 * portabilidade). Fecha a pendência da Fase 4 (acesso do paciente ao próprio dado como
 * processo formal, não autoatendimento ad-hoc). Só o próprio dado do usuário: a
 * autorização é do Controller (ator === titular); esta Action apenas agrega e decifra.
 */
class BuildPersonalDataExportAction
{
    public function __invoke(User $user): array
    {
        $data = [
            'gerado_em' => now()->toIso8601String(),
            'conta' => [
                'nome' => $user->name,
                'email' => $user->email,
                'papeis' => $user->getRoleNames()->all(),
                'criado_em' => $user->created_at?->toIso8601String(),
            ],
            'consentimentos' => Consent::query()
                ->where('user_id', $user->id)
                ->orderBy('accepted_at')
                ->get()
                ->map(fn (Consent $c) => [
                    'documento' => $c->document_type->label(),
                    'versao' => $c->document_version,
                    'aceito_em' => $c->accepted_at?->toIso8601String(),
                ])->all(),
        ];

        $patient = Patient::query()->where('user_id', $user->id)->first();

        if ($patient === null) {
            return $data;
        }

        $data['perfil'] = [
            'nome' => $patient->display_name,
            'email' => $patient->email,
            'documento' => $patient->document_number_encrypted,
            'nascimento' => $patient->birth_date_encrypted,
            'telefones' => $patient->phones_encrypted,
            'contatos_emergencia' => $patient->emergency_contacts_encrypted,
            'endereco' => $patient->address_encrypted,
        ];

        $data['responsaveis'] = Guardian::query()
            ->where('patient_id', $patient->id)
            ->get()
            ->map(fn (Guardian $g) => [
                'nome' => $g->name,
                'parentesco' => $g->relationship,
                'documento' => $g->document_number_encrypted,
                'telefone' => $g->phone_encrypted,
            ])->all();

        $data['sessoes'] = Session::query()
            ->where('patient_id', $patient->id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Session $s) => [
                'agendada_para' => $s->scheduled_at?->toIso8601String(),
                'status' => $s->status->value,
                'modalidade' => $s->modality->value,
            ])->all();

        $data['cobrancas'] = FinancialCharge::query()
            ->where('patient_id', $patient->id)
            ->orderBy('due_date')
            ->get()
            ->map(fn (FinancialCharge $c) => [
                'valor' => $c->amount,
                'vencimento' => $c->due_date?->toDateString(),
                'status' => $c->status->value,
            ])->all();

        return $data;
    }
}
