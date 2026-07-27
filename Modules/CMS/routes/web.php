<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\PageController;
use Modules\CMS\Http\Controllers\PublicPageController;

/*
 * Gestão administrativa das páginas (auth + e-mail verificado + tenant resolvido).
 * `manage-cms` é exigido pela PagePolicy dentro do Controller, não como middleware,
 * para manter a mensagem de negação consistente com o resto do app.
 */
Route::middleware(['auth', 'verified', 'resolve.tenant'])
    ->prefix('cms/paginas')
    ->name('cms.pages.')
    ->group(function () {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('/criar', [PageController::class, 'create'])->name('create');
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('/{page}/editar', [PageController::class, 'edit'])->name('edit');
        Route::put('/{page}', [PageController::class, 'update'])->name('update');
        Route::delete('/{page}', [PageController::class, 'destroy'])->name('destroy');
    });

/*
 * Páginas públicas (convidado). O tenant vem do binding `{tenant:slug}`; a Page é
 * resolvida à mão no Controller (sem binding implícito) porque é BelongsToTenant e
 * não há CurrentTenant em contexto de convidado — ver PublicPageController.
 */
Route::get('/c/{tenant:slug}', [PublicPageController::class, 'home'])->name('cms.public.home');
Route::get('/c/{tenant:slug}/p/{pageSlug}', [PublicPageController::class, 'show'])->name('cms.public.show');
