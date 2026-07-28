<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Settings\Models\Unit;
use Modules\Tenant\Support\CurrentTenant;

/**
 * CRUD das unidades (filiais) da clínica. Estrutura organizacional do tenant →
 * restrito a `manage-clinic-settings` (mesmo escopo de /configuracoes).
 */
class UnitController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(): Response
    {
        $units = Unit::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'city' => $unit->city,
                'is_active' => $unit->is_active,
                'staff_count' => $unit->users_count,
            ]);

        return Inertia::render('Settings/Units', ['units' => $units]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        Unit::query()->create([
            'tenant_id' => $this->currentTenant->id(),
            'name' => $validated['name'],
            'city' => $validated['city'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('units.index')->with('status', 'Unidade criada.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->currentTenant->ownsOrFail($unit);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $unit->update($validated);

        return redirect()->route('units.index')->with('status', 'Unidade atualizada.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->currentTenant->ownsOrFail($unit);

        $unit->delete();

        return redirect()->route('units.index')->with('status', 'Unidade removida.');
    }
}
