<?php

declare(strict_types=1);

namespace Modules\Psychologists\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Psychologists\Http\Requests\StoreAvailabilityRequest;
use Modules\Psychologists\Models\Psychologist;
use Modules\Psychologists\Models\PsychologistAvailability;

class PsychologistAvailabilityController extends Controller
{
    public function index(Psychologist $psychologist): Response
    {
        $this->authorize('viewAny', [PsychologistAvailability::class, $psychologist]);

        return Inertia::render('Psychologists/Availability', [
            'psychologist' => ['id' => $psychologist->id, 'name' => $psychologist->user->name],
            'availabilities' => $psychologist->availabilities()
                ->orderBy('type')
                ->orderBy('weekday')
                ->orderBy('date')
                ->get()
                ->map(fn (PsychologistAvailability $a) => [
                    'id' => $a->id,
                    'type' => $a->type->value,
                    'weekday' => $a->weekday,
                    'date' => $a->date?->toDateString(),
                    'start_time' => $a->start_time,
                    'end_time' => $a->end_time,
                    'session_duration_minutes' => $a->session_duration_minutes,
                    'buffer_minutes' => $a->buffer_minutes,
                ]),
        ]);
    }

    public function store(StoreAvailabilityRequest $request, Psychologist $psychologist): RedirectResponse
    {
        $this->authorize('create', [PsychologistAvailability::class, $psychologist]);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'Disponibilidade adicionada.');
    }

    public function destroy(PsychologistAvailability $availability): RedirectResponse
    {
        $this->authorize('delete', $availability);

        $availability->delete();

        return back()->with('status', 'Disponibilidade removida.');
    }
}
