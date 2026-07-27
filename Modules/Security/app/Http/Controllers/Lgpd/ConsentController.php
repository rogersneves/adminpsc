<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers\Lgpd;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Security\Actions\Lgpd\RecordConsentAction;
use Modules\Security\Services\ConsentChecker;

/**
 * Tela de aceite dos documentos legais pendentes (LGPD). O EnsureLgpdConsent
 * redireciona para cá enquanto houver documento atual não aceito.
 */
class ConsentController extends Controller
{
    public function show(Request $request, ConsentChecker $checker): Response|RedirectResponse
    {
        $pending = $checker->pendingFor($request->user());

        // Nada pendente (ex.: acessou a URL diretamente): volta ao painel.
        if ($pending === []) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Lgpd/Consent', [
            'documents' => array_map(fn ($doc) => [
                'id' => $doc->id,
                'type' => $doc->type->value,
                'type_label' => $doc->type->label(),
                'title' => $doc->title,
                'content' => $doc->content,
                'version' => $doc->version,
            ], $pending),
        ]);
    }

    public function store(Request $request, ConsentChecker $checker, RecordConsentAction $record): RedirectResponse
    {
        $request->validate([
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'É necessário aceitar os documentos para continuar.',
        ]);

        $user = $request->user();

        foreach ($checker->pendingFor($user) as $document) {
            $record($user, $document);
        }

        return redirect()->route('dashboard')->with('status', 'Consentimento registrado. Obrigado.');
    }
}
