<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Settings\Actions\InviteSecretaryAction;
use Modules\Settings\Models\Unit;
use Modules\Tenant\Support\CurrentTenant;
use Modules\Users\Models\User;

/**
 * Convite e listagem de secretárias (marco: secretárias com escopo próprio). Restrito
 * a `manage-users` (mesma permissão do cadastro de psicólogo). O vínculo com unidades
 * define o escopo de agenda de cada secretária.
 */
class SecretaryController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(): Response
    {
        $secretaries = User::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->whereHas('roles', fn ($q) => $q->where('name', 'secretaria'))
            ->with('units:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'units' => $user->units->map(fn (Unit $u) => ['id' => $u->id, 'name' => $u->name]),
            ]);

        return Inertia::render('Settings/Secretaries', [
            'secretaries' => $secretaries,
            'units' => $this->tenantUnits(),
        ]);
    }

    public function store(Request $request, InviteSecretaryAction $invite): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['string'],
        ]);

        $invite($this->currentTenant->get(), $validated['name'], $validated['email'], $validated['unit_ids']);

        return redirect()->route('secretaries.index')
            ->with('status', 'Secretária convidada. Um link para definir a senha foi enviado por e-mail.');
    }

    private function tenantUnits(): array
    {
        return Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
