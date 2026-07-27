<?php

declare(strict_types=1);

namespace Modules\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança HTTP (Fase 9, docs/04-Seguranca.md). Aplicado ao grupo
 * `web`. Toda a política é configurável em config/security.php ('headers'); um valor
 * vazio/null omite o cabeçalho correspondente. HSTS só é emitido sobre https —
 * navegadores ignoram HSTS em http e emiti-lo em dev só polui a resposta.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $config = config('security.headers', []);

        if (! ($config['enabled'] ?? true)) {
            return $response;
        }

        $this->put($response, 'Content-Security-Policy', $config['content_security_policy'] ?? null);
        $this->put($response, 'Referrer-Policy', $config['referrer_policy'] ?? null);
        $this->put($response, 'Permissions-Policy', $config['permissions_policy'] ?? null);
        $this->put($response, 'X-Frame-Options', $config['frame_options'] ?? null);

        // Sempre seguros de emitir, não dependem de configuração.
        $this->put($response, 'X-Content-Type-Options', 'nosniff');

        if ($request->isSecure()) {
            $this->put($response, 'Strict-Transport-Security', $config['hsts'] ?? null);
        }

        return $response;
    }

    private function put(Response $response, string $header, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // Não sobrescreve um cabeçalho já definido deliberadamente por uma rota.
        if (! $response->headers->has($header)) {
            $response->headers->set($header, $value);
        }
    }
}
