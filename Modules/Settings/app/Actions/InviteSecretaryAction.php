<?php

declare(strict_types=1);

namespace Modules\Settings\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Settings\Models\Unit;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;

/**
 * Convite de secretária (marco: secretárias com escopo próprio). Mesmo padrão do
 * cadastro de psicólogo (Fase 2): cria a conta com papel `secretaria` e envia link de
 * definição de senha, em vez de senha temporária. Vincula a secretária às unidades
 * informadas — o escopo dela (o que enxerga da agenda) deriva desse vínculo.
 *
 * @param  array<int, string>  $unitIds
 */
class InviteSecretaryAction
{
    public function __invoke(Tenant $tenant, string $name, string $email, array $unitIds): User
    {
        $user = DB::transaction(function () use ($tenant, $name, $email, $unitIds): User {
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40),
            ]);

            $user->assignRole('secretaria');

            // Só unidades do próprio tenant (defesa: ignora ids alheios).
            $validUnitIds = Unit::query()
                ->withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $unitIds)
                ->pluck('id');

            $user->units()->sync($validUnitIds);

            return $user;
        });

        event(new Registered($user));

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }
}
