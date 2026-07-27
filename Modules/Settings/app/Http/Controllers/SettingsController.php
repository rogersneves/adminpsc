<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Psychologists\Models\Psychologist;
use Modules\Settings\Services\PlanLimits;
use Modules\Settings\Services\TenantSettings;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Configurações da própria clínica (Fase 11): parâmetros operacionais (agenda) e
 * marca. Restrito a `manage-clinic-settings` (permissão seedada desde a Fase 1, sem
 * uso real até aqui). Também mostra o estado da assinatura/plano e o uso vs. limite.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function edit(TenantSettings $settings, PlanLimits $limits): Response
    {
        $tenant = $this->currentTenant->get();

        return Inertia::render('Settings/Index', [
            'settings' => $settings->all($tenant),
            'fields' => $this->fields(),
            'plan' => [
                'key' => $tenant->plan,
                'label' => $tenant->planConfig()['label'],
                'max_psychologists' => $limits->psychologistLimit($tenant),
                'max_patients' => $limits->patientLimit($tenant),
                'psychologists_used' => $this->psychologistCount($tenant),
                'patients_used' => $limits->countPatients($tenant),
                'on_trial' => $tenant->onTrial(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request, TenantSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'scheduling.booking_horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'scheduling.minimum_reschedule_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'branding.display_name' => ['required', 'string', 'max:120'],
            'branding.primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        // $validated volta aninhado (regras com dot-notation); achata para dot-keys
        // que casam com o registro do TenantSettings.
        $settings->set($this->currentTenant->get(), Arr::dot($validated));

        return redirect()->route('settings.edit')->with('status', 'Configurações salvas.');
    }

    /**
     * Metadados do registro de chaves para a UI, agrupados.
     */
    private function fields(): array
    {
        $fields = [];
        foreach (TenantSettings::REGISTRY as $key => [, $type, $group, $label]) {
            $fields[] = ['key' => $key, 'type' => $type, 'group' => $group, 'label' => $label];
        }

        return $fields;
    }

    private function psychologistCount(Tenant $tenant): int
    {
        return Psychologist::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }
}
