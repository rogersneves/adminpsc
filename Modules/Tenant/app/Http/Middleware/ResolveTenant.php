<?php

declare(strict_types=1);

namespace Modules\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Support\CurrentTenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda depois de 'auth'. Resolve o tenant do usuário autenticado para o contexto
 * da requisição. Super Admin (tenant_id null) fica deliberadamente sem tenant
 * resolvido — ele opera no nível de plataforma, não de um tenant específico.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        $tenant = $user?->tenant_id
            ? Tenant::query()->find($user->tenant_id)
            : null;

        app(CurrentTenant::class)->set($tenant);

        return $next($request);
    }
}
