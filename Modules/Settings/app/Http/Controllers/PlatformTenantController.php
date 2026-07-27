<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Psychologists\Models\Psychologist;
use Modules\Settings\Actions\ProvisionTenantAction;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;

/**
 * Gestão de tenants pela plataforma (Super Admin, Fase 11). Primeira rota a usar a
 * permissão `platform.manage-tenants` (seedada desde a Fase 1, sem uso até aqui).
 * Não usa resolve.tenant — o Super Admin opera cross-tenant, não tem tenant próprio.
 * `Tenant` não é BelongsToTenant, então binding/consulta funcionam sem scope.
 */
class PlatformTenantController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
                'plan_label' => $tenant->planConfig()['label'],
                'status' => $tenant->status,
                'on_trial' => $tenant->onTrial(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'psychologists' => Psychologist::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->count(),
                'users' => User::query()->where('tenant_id', $tenant->id)->count(),
            ]);

        return Inertia::render('Platform/Tenants', [
            'tenants' => $tenants,
            'plans' => collect(config('plans.plans'))->map(fn ($p, $key) => [
                'value' => $key,
                'label' => $p['label'],
            ])->values(),
            'statuses' => ['active', 'suspended'],
        ]);
    }

    public function store(Request $request, ProvisionTenantAction $provision): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
        ]);

        $provision($validated['name'], $validated['plan']);

        return redirect()->route('platform.tenants.index')->with('status', 'Tenant provisionado.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $tenant->update($validated);

        return redirect()->route('platform.tenants.index')->with('status', 'Tenant atualizado.');
    }
}
