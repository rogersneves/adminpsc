<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Modules\Security\Http\Middleware\EnsureSessionIsValid;
use Modules\Tenant\Http\Middleware\ResolveTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureSessionIsValid::class,
        ]);

        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
        ]);

        // Sem isso, o binding implícito de rota (ex.: {psychologist} em
        // /psicologos/{psychologist}/disponibilidade) roda ANTES de resolve.tenant,
        // não importa a ordem no array de middleware da rota — Laravel ordena por uma
        // lista de prioridade interna, e SubstituteBindings tem prioridade mais alta
        // que qualquer middleware customizado por padrão. Sem isso, toda rota que faz
        // binding implícito de um Model com BelongsToTenant lança
        // UnresolvedTenantException pra usuários legítimos (não é só uma brecha de
        // isolamento entre tenants — quebra a rota inteira). Ver gotcha no CLAUDE.md.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
