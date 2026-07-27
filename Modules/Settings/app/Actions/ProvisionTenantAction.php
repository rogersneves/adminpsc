<?php

declare(strict_types=1);

namespace Modules\Settings\Actions;

use Illuminate\Support\Str;
use Modules\Tenant\Models\Tenant;

/**
 * Provisionamento de um novo tenant do SaaS (Fase 11): plano inicial, janela de
 * trial e slug único. Fonte única de "como nasce um tenant" — usado tanto pelo
 * auto-cadastro do Admin da Clínica (RegisterClinicAdminAction) quanto pela criação
 * manual por um Super Admin, para os dois caírem exatamente no mesmo estado inicial.
 */
class ProvisionTenantAction
{
    public function __invoke(string $name, ?string $plan = null): Tenant
    {
        $plan ??= config('plans.default');
        $trialDays = (int) config('plans.trial_days');

        return Tenant::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'plan' => $plan,
            'status' => 'active',
            'trial_ends_at' => now()->addDays($trialDays),
            'settings' => [],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'clinica';
        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
