<?php

declare(strict_types=1);

namespace Modules\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Timeout absoluto + timeout por inatividade (docs/04-Seguranca.md). Só age sobre
 * requisições já autenticadas — o desafio de MFA roda antes de Auth::login(), então
 * não é afetado por este middleware.
 */
class EnsureSessionIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $now = now();
        $loginAt = $request->session()->get('auth.login_at');
        $lastActivity = $request->session()->get('auth.last_activity');

        $absoluteMinutes = (int) config('security.session.absolute_minutes');
        $inactivityMinutes = (int) config('security.session.inactivity_minutes');

        $absoluteExpired = $loginAt !== null
            && $now->diffInMinutes($loginAt, absolute: true) > $absoluteMinutes;

        $inactivityExpired = $lastActivity !== null
            && $now->diffInMinutes($lastActivity, absolute: true) > $inactivityMinutes;

        if ($absoluteExpired || $inactivityExpired) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Sua sessão expirou. Faça login novamente.');
        }

        $request->session()->put('auth.last_activity', $now);

        return $next($request);
    }
}
