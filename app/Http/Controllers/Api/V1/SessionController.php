<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Actions\BookSessionAction;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Exceptions\SlotNoLongerAvailableException;
use Modules\Scheduling\Models\Session;

/**
 * Sessões do dono do token (API v1). A reserva reusa exatamente a mesma
 * BookSessionAction do fluxo web (Fase 3) — mesma trava transacional contra dupla
 * reserva, mesma regra de disponibilidade. Zero regra de negócio duplicada aqui.
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = Session::query()
            ->with(['psychologist.user:id,name', 'patient:id,display_name'])
            ->where(function ($q) use ($user) {
                $q->whereHas('patient', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhereHas('psychologist', fn ($p) => $p->where('user_id', $user->id));
            })
            ->orderByDesc('scheduled_at')
            ->limit(200)
            ->get()
            ->map(fn (Session $s) => $this->toArray($s));

        return response()->json(['data' => $sessions]);
    }

    public function store(Request $request, BookSessionAction $book): JsonResponse
    {
        $validated = $request->validate([
            'psychologist_id' => ['required', 'string'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'modality' => ['required', Rule::enum(SessionModality::class)],
        ]);

        $patient = Patient::query()->where('user_id', $request->user()->id)->first();

        if ($patient === null) {
            return response()->json(['message' => 'Apenas pacientes podem reservar sessões.'], 403);
        }

        $psychologist = Psychologist::query()->find($validated['psychologist_id']);

        if ($psychologist === null) {
            return response()->json(['message' => 'Psicólogo não encontrado.'], 404);
        }

        try {
            $session = $book(
                $patient,
                $psychologist,
                CarbonImmutable::parse($validated['scheduled_at']),
                $validated['duration_minutes'],
                SessionModality::from($validated['modality']),
            );
        } catch (SlotNoLongerAvailableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->toArray($session->load(['psychologist.user', 'patient']))], 201);
    }

    private function toArray(Session $session): array
    {
        return [
            'id' => $session->id,
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'duration_minutes' => $session->duration_minutes,
            'modality' => $session->modality->value,
            'status' => $session->status->value,
            'meeting_url' => $session->meeting_url,
            'patient_name' => $session->patient?->display_name,
            'psychologist_name' => $session->psychologist?->user?->name,
        ];
    }
}
