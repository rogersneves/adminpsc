<?php

declare(strict_types=1);

namespace Modules\Psychologists\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Psychologists\Actions\RegisterPsychologistAction;
use Modules\Psychologists\Http\Requests\RegisterPsychologistRequest;
use Modules\Psychologists\Models\Psychologist;

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

        return Inertia::render('Psychologists/Create');
    }

    public function store(RegisterPsychologistRequest $request, RegisterPsychologistAction $action): RedirectResponse
    {
        $action($request->user(), $request->toDto());

        return redirect()->route('psychologists.index')->with('status', 'Psicólogo cadastrado. Um link para definir a senha foi enviado por e-mail.');
    }
}
