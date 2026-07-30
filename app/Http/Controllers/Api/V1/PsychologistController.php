<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Psychologists\Models\Psychologist;

/**
 * Lista os psicólogos do tenant (API v1). Escopo por tenant vem da TenantScope
 * (resolve.tenant já resolveu o tenant do dono do token).
 */
class PsychologistController extends Controller
{
    public function index(): JsonResponse
    {
        $psychologists = Psychologist::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Psychologist $p) => [
                'id' => $p->id,
                'name' => $p->user?->name,
                'specialties' => $p->specialties ?? [],
                'default_session_duration_minutes' => $p->default_session_duration_minutes,
            ]);

        return response()->json(['data' => $psychologists]);
    }
}
