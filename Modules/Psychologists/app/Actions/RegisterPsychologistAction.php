<?php

declare(strict_types=1);

namespace Modules\Psychologists\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Psychologists\DTOs\RegisterPsychologistData;
use Modules\Psychologists\Models\Psychologist;
use Modules\Users\Models\User;

/**
 * Cadastro de psicólogo é feito pelo Admin da Clínica (ou Super Admin), não
 * autocadastro (docs/06-Roadmap.md Fase 2, decisão de escopo #6). Em vez de senha
 * temporária, reaproveita o broker de redefinição de senha do Laravel (mesmo usado
 * em "esqueci minha senha") para o psicólogo definir a própria senha.
 */
class RegisterPsychologistAction
{
    public function __invoke(User $actor, RegisterPsychologistData $data): User
    {
        $user = DB::transaction(function () use ($actor, $data) {
            $user = User::query()->create([
                'tenant_id' => $actor->tenant_id,
                'name' => $data->name,
                'email' => $data->email,
                'password' => Str::random(40),
            ]);

            $user->assignRole('psicologo');

            Psychologist::query()->create([
                'tenant_id' => $actor->tenant_id,
                'user_id' => $user->id,
                'professional_registry_encrypted' => $data->professionalRegistry,
                'specialties' => $data->specialties,
                'default_session_duration_minutes' => $data->defaultSessionDurationMinutes,
            ]);

            return $user;
        });

        event(new Registered($user));

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }
}
