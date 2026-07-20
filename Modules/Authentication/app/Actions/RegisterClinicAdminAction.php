<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authentication\DTOs\RegisterClinicAdminData;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;

/**
 * Onboarding de um novo cliente do SaaS: cria o Tenant e o primeiro usuário
 * (Admin da Clínica) numa única transação. O cadastro de Paciente é outro fluxo,
 * da Fase 2 (docs/06-Roadmap.md).
 *
 * Autentica o usuário imediatamente após o cadastro (sem desafio de MFA) — ele
 * acabou de provar controle da senha e da própria sessão neste exato request, o
 * caso de uso que o MFA protege é o de um LOGIN de retorno, não o instante da
 * criação da conta (mesmo padrão usado por Breeze/Fortify). A rota autenticada
 * fica atrás do middleware "verified" até o e-mail ser confirmado; o próximo
 * login de verdade (depois de logout ou expiração de sessão) passa pelo desafio
 * de MFA normalmente.
 */
class RegisterClinicAdminAction
{
    public function __invoke(RegisterClinicAdminData $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create([
                'name' => $data->tenantName,
                'slug' => $this->uniqueSlug($data->tenantName),
                'plan' => 'trial',
                'status' => 'active',
            ]);

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            $user->assignRole('admin_clinica');

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        session(['auth.login_at' => now(), 'auth.last_activity' => now()]);

        return $user;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
