<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Dados do dono do token (API v1). Ponto de partida de qualquer cliente da API.
 */
class MeController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'tenant' => $tenant->get() ? [
                    'id' => $tenant->id(),
                    'name' => $tenant->get()->name,
                ] : null,
            ],
        ]);
    }
}
