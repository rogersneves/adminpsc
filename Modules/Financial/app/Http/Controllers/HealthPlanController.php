<?php

declare(strict_types=1);

namespace Modules\Financial\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financial\Models\HealthPlan;
use Modules\Tenant\Support\CurrentTenant;

/**
 * CRUD dos convênios aceitos pela clínica (marco: convênios). Faturamento →
 * restrito a `manage-financial`.
 */
class HealthPlanController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(): Response
    {
        $plans = HealthPlan::query()
            ->orderBy('name')
            ->get()
            ->map(fn (HealthPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'is_active' => $plan->is_active,
            ]);

        return Inertia::render('Financial/HealthPlans', ['plans' => $plans]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        HealthPlan::query()->create([
            'tenant_id' => $this->currentTenant->id(),
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('health-plans.index')->with('status', 'Convênio criado.');
    }

    public function update(Request $request, HealthPlan $healthPlan): RedirectResponse
    {
        $this->currentTenant->ownsOrFail($healthPlan);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $healthPlan->update($validated);

        return redirect()->route('health-plans.index')->with('status', 'Convênio atualizado.');
    }

    public function destroy(HealthPlan $healthPlan): RedirectResponse
    {
        $this->currentTenant->ownsOrFail($healthPlan);

        $healthPlan->delete();

        return redirect()->route('health-plans.index')->with('status', 'Convênio removido.');
    }
}
