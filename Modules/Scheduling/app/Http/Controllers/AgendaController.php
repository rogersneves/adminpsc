<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Actions\BookSessionAction;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Exceptions\SlotNoLongerAvailableException;
use Modules\Scheduling\Http\Requests\BookSessionRequest;
use Modules\Scheduling\Services\AvailabilityCalculator;
use Modules\Tenant\Support\CurrentTenant;

class AgendaController extends Controller
{
    public function index(): Response
    {
        $psychologists = Psychologist::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Psychologist $psychologist) => [
                'id' => $psychologist->id,
                'name' => $psychologist->user->name,
                'specialties' => $psychologist->specialties,
            ]);

        return Inertia::render('Scheduling/ChoosePsychologist', ['psychologists' => $psychologists]);
    }

    public function show(Psychologist $psychologist, AvailabilityCalculator $calculator, CurrentTenant $currentTenant): Response
    {
        $currentTenant->ownsOrFail($psychologist);

        $from = CarbonImmutable::now();
        $to = $from->addDays((int) config('scheduling.booking_horizon_days'));

        $slotsByDate = $calculator->availableSlots($psychologist, $from, $to);

        $slots = collect($slotsByDate)->map(
            fn (array $daySlots) => collect($daySlots)->map(fn (array $slot) => [
                'starts_at' => $slot['starts_at']->toIso8601String(),
                'ends_at' => $slot['ends_at']->toIso8601String(),
                'duration_minutes' => $slot['starts_at']->diffInMinutes($slot['ends_at']),
            ])->values(),
        );

        return Inertia::render('Scheduling/Book', [
            'psychologist' => ['id' => $psychologist->id, 'name' => $psychologist->user->name],
            'slotsByDate' => $slots,
            'modalities' => array_column(SessionModality::cases(), 'value'),
        ]);
    }

    public function store(
        BookSessionRequest $request,
        Psychologist $psychologist,
        BookSessionAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($psychologist);

        $patient = Patient::query()->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $action(
                $patient,
                $psychologist,
                CarbonImmutable::parse($request->string('scheduled_at')->toString()),
                (int) $request->input('duration_minutes'),
                SessionModality::from($request->string('modality')->toString()),
            );
        } catch (SlotNoLongerAvailableException $exception) {
            return back()->withErrors(['scheduled_at' => $exception->getMessage()]);
        }

        return redirect()->route('scheduling.my-sessions')->with('status', 'Sessão reservada.');
    }
}
