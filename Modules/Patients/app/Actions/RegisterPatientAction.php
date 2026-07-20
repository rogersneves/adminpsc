<?php

declare(strict_types=1);

namespace Modules\Patients\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Patients\DTOs\RegisterPatientData;
use Modules\Patients\Models\Patient;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;

/**
 * Autocadastro do paciente sob uma clínica específica (Tenant já resolvido pela
 * rota via route-model-binding em `/c/{tenant:slug}/paciente/registro`). Mesmo
 * padrão de autenticação imediata sem MFA do `RegisterClinicAdminAction` (Fase 1)
 * — ver a nota lá para o raciocínio completo.
 */
class RegisterPatientAction
{
    public function __invoke(Tenant $tenant, RegisterPatientData $data): User
    {
        $user = DB::transaction(function () use ($tenant, $data) {
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data->displayName,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            $user->assignRole('paciente');

            Patient::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'display_name' => $data->displayName,
                'email' => $data->email,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        session(['auth.login_at' => now(), 'auth.last_activity' => now()]);

        return $user;
    }
}
