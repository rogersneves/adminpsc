<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Patients\Models\Patient;
use Modules\Scheduling\Actions\CancelSessionAction;
use Modules\Scheduling\Actions\MarkSessionStatusAction;
use Modules\Scheduling\Actions\RescheduleSessionAction;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Exceptions\InsufficientNoticeException;
use Modules\Scheduling\Exceptions\SlotNoLongerAvailableException;
use Modules\Scheduling\Http\Requests\RescheduleSessionRequest;
use Modules\Scheduling\Models\Session;

class SessionController extends Controller
{
    public function mine(Request $request): Response
    {
        $patient = Patient::query()->where('user_id', $request->user()->id)->firstOrFail();

        $sessions = Session::query()
            ->where('patient_id', $patient->id)
            ->with('psychologist.user:id,name')
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(fn (Session $session) => $this->toArray($session));

        return Inertia::render('Scheduling/MySessions', ['sessions' => $sessions]);
    }

    public function cancel(Session $session, CancelSessionAction $action): RedirectResponse
    {
        $this->authorize('cancel', $session);

        try {
            $action($session);
        } catch (InsufficientNoticeException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sessão cancelada.');
    }

    public function reschedule(
        RescheduleSessionRequest $request,
        Session $session,
        RescheduleSessionAction $action,
    ): RedirectResponse {
        $this->authorize('reschedule', $session);

        try {
            $action(
                $session,
                CarbonImmutable::parse($request->string('scheduled_at')->toString()),
                (int) $request->input('duration_minutes'),
            );
        } catch (InsufficientNoticeException|SlotNoLongerAvailableException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sessão reagendada.');
    }

    public function markRealizada(Session $session, MarkSessionStatusAction $action): RedirectResponse
    {
        $this->authorize('markStatus', $session);
        $action($session, SessionStatus::Realizada);

        return back()->with('status', 'Sessão marcada como realizada.');
    }

    public function markNaoCompareceu(Session $session, MarkSessionStatusAction $action): RedirectResponse
    {
        $this->authorize('markStatus', $session);
        $action($session, SessionStatus::NaoCompareceu);

        return back()->with('status', 'Sessão marcada como não compareceu.');
    }

    private function toArray(Session $session): array
    {
        return [
            'id' => $session->id,
            'psychologist_name' => $session->psychologist->user->name,
            'scheduled_at' => $session->scheduled_at->toIso8601String(),
            'duration_minutes' => $session->duration_minutes,
            'modality' => $session->modality->value,
            'status' => $session->status->value,
        ];
    }
}
