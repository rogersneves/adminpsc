<?php

declare(strict_types=1);

namespace Modules\Psychologists\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Psychologists\Actions\RegisterPsychologistAction;
use Modules\Psychologists\Http\Requests\RegisterPsychologistRequest;
use Modules\Psychologists\Models\Psychologist;
use Modules\Settings\Exceptions\PlanLimitReachedException;
use Modules\Settings\Models\Unit;

class PsychologistController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Psychologist::class);

        $psychologists = Psychologist::query()
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (Psychologist $psychologist) => [
                'id' => $psychologist->id,
                'name' => $psychologist->user->name,
                'email' => $psychologist->user->email,
                'specialties' => $psychologist->specialties,
                'default_session_duration_minutes' => $psychologist->default_session_duration_minutes,
            ]);

        return Inertia::render('Psychologists/Index', ['psychologists' => $psychologists]);
    }

    public function create(): Response
    {
        $this->authorize('create', Psychologist::class);

        // Unidades ativas do tenant, para vincular o psicólogo (marco: múltiplas unidades).
        $units = Unit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

        return Inertia::render('Psychologists/Create', ['units' => $units]);
    }

    public function store(RegisterPsychologistRequest $request, RegisterPsychologistAction $action): RedirectResponse
    {
        try {
            $action($request->user(), $request->toDto());
        } catch (PlanLimitReachedException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('psychologists.index')->with('status', 'Psicólogo cadastrado. Um link para definir a senha foi enviado por e-mail.');
    }
}
