<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Actions\JoinWaitingListAction;
use Modules\Scheduling\Http\Requests\JoinWaitingListRequest;
use Modules\Scheduling\Models\WaitingListEntry;
use Modules\Tenant\Support\CurrentTenant;

class WaitingListController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', WaitingListEntry::class);

        $entries = WaitingListEntry::query()
            ->with(['patient:id,display_name', 'psychologist.user:id,name'])
            ->where('status', 'aberto')
            ->latest()
            ->get()
            ->map(fn (WaitingListEntry $entry) => [
                'id' => $entry->id,
                'patient_name' => $entry->patient->display_name,
                'psychologist_name' => $entry->psychologist->user->name,
                'desired_period' => $entry->desired_period,
            ]);

        return Inertia::render('Scheduling/WaitingList', ['entries' => $entries]);
    }

    public function store(
        JoinWaitingListRequest $request,
        Psychologist $psychologist,
        JoinWaitingListAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $this->authorize('create', WaitingListEntry::class);
        $currentTenant->ownsOrFail($psychologist);

        $patient = Patient::query()->where('user_id', $request->user()->id)->firstOrFail();

        $action($patient, $psychologist, $request->desiredPeriod());

        return back()->with('status', 'Você entrou na lista de espera.');
    }
}
