<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers\Lgpd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Services\AuditLogger;
use Modules\Security\Actions\Lgpd\BuildPersonalDataExportAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Direito de acesso/portabilidade (LGPD Art. 18): o titular vê e baixa os próprios
 * dados. Sempre o dado do usuário autenticado — não há como pedir o de outro.
 */
class PersonalDataController extends Controller
{
    public function show(Request $request, BuildPersonalDataExportAction $build): Response
    {
        return Inertia::render('Lgpd/MyData', [
            'data' => $build($request->user()),
        ]);
    }

    public function download(Request $request, BuildPersonalDataExportAction $build, AuditLogger $audit): StreamedResponse
    {
        $user = $request->user();
        $payload = $build($user);

        // O download é uma "exportação" auditável (docs/04-Seguranca.md).
        $audit->record('lgpd.data_exported', actor: $user, tenantId: $user->tenant_id);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn () => print ($json),
            'meus-dados-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }
}
