<?php

declare(strict_types=1);

namespace Modules\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Security\Services\ConsentChecker;
use Modules\Users\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gating de consentimento LGPD: se o tenant tem uma versão atual de um documento legal
 * obrigatório que o usuário ainda não aceitou, redireciona para a tela de aceite antes
 * de qualquer outra ação. Quando o tenant não publicou documento nenhum, é no-op
 * (recurso opt-in por clínica) — por isso não afeta fluxos/testes que não usam LGPD.
 *
 * Roda no grupo `web`. Pula requisições não autenticadas (o desafio de MFA acontece
 * antes de Auth::login()), a própria tela de aceite (evita loop) e o logout (o usuário
 * pode sair mesmo com aceite pendente).
 */
class EnsureLgpdConsent
{
    public function __construct(private readonly ConsentChecker $checker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($this->checker->hasPending($user)) {
            return redirect()->route('lgpd.consent.show');
        }

        return $next($request);
    }

    /**
     * Checagem por path (não routeIs) porque middleware de grupo pode rodar antes de a
     * rota estar totalmente resolvida — path é sempre confiável e evita loop de redirect.
     */
    private function isExempt(Request $request): bool
    {
        return $request->is('lgpd/consentimento')
            || $request->is('logout')
            || $request->is('login')
            || $request->is('email/verify*')
            || $request->is('email/verification-notification');
    }
}
