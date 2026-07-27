<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\CMS\Enums\PageStatus;
use Modules\CMS\Models\Page;
use Modules\Tenant\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renderização pública das páginas do CMS (rota de convidado, sem `resolve.tenant`).
 *
 * O tenant vem do binding `{tenant:slug}` — `Tenant` não é tenant-scoped, então é
 * seguro resolvê-lo por binding. `Page`, por outro lado, É `BelongsToTenant`: aqui
 * não há `CurrentTenant` resolvido (contexto de convidado), então a query usa
 * `withoutTenantScope()` + `where('tenant_id', ...)` explícito, escopando à mão em
 * vez de depender da global scope — mesmo raciocínio do cadastro público de paciente.
 *
 * Só o html/css já sanitizado é servido; `project_data` (estado do editor) nunca sai.
 */
class PublicPageController extends Controller
{
    public function home(Tenant $tenant): View
    {
        abort_unless($tenant->status === 'active', 404);

        $page = $this->publishedPages($tenant)
            ->where('is_home', true)
            ->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return $this->render($tenant, $page);
    }

    public function show(Tenant $tenant, string $pageSlug): View
    {
        abort_unless($tenant->status === 'active', 404);

        $page = $this->publishedPages($tenant)
            ->where('slug', $pageSlug)
            ->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return $this->render($tenant, $page);
    }

    private function publishedPages(Tenant $tenant)
    {
        return Page::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('status', PageStatus::Published->value);
    }

    private function render(Tenant $tenant, Page $page): View
    {
        return view('cms::public.show', [
            'tenantName' => $tenant->name,
            'title' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description,
            'html' => $page->html ?? '',
            'css' => $page->css ?? '',
        ]);
    }
}
