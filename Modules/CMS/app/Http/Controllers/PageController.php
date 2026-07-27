<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Actions\CreatePageAction;
use Modules\CMS\Actions\UpdatePageAction;
use Modules\CMS\Http\Requests\SavePageRequest;
use Modules\CMS\Models\Page;
use Modules\Tenant\Support\CurrentTenant;

/**
 * CRUD administrativo das páginas do CMS. Só orquestra: valida (SavePageRequest),
 * autoriza (PagePolicy), chama a Action e devolve Inertia/redirect. Toda rota passa
 * por `resolve.tenant`; edit/update/destroy reforçam `ownsOrFail` no Page recebido
 * por binding (disciplina do gotcha da Fase 3).
 */
class PageController extends Controller
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Page::class);

        $pages = Page::query()
            ->orderByDesc('is_home')
            ->orderBy('title')
            ->get()
            ->map(fn (Page $page) => $this->summary($page));

        return Inertia::render('CMS/Index', [
            'pages' => $pages,
            'tenantSlug' => $this->currentTenant->get()?->slug,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Page::class);

        return Inertia::render('CMS/Editor', [
            'page' => null,
        ]);
    }

    public function store(SavePageRequest $request, CreatePageAction $action): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $page = $action($this->currentTenant->get(), $request->toDto());

        return redirect()
            ->route('cms.pages.edit', $page)
            ->with('status', 'Página criada.');
    }

    public function edit(Page $page): Response
    {
        $this->currentTenant->ownsOrFail($page);
        $this->authorize('update', $page);

        return Inertia::render('CMS/Editor', [
            'page' => [
                ...$this->summary($page),
                'project_data' => $page->project_data,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ],
        ]);
    }

    public function update(SavePageRequest $request, Page $page, UpdatePageAction $action): RedirectResponse
    {
        $this->currentTenant->ownsOrFail($page);
        $this->authorize('update', $page);

        $action($page, $request->toDto());

        return redirect()
            ->route('cms.pages.edit', $page)
            ->with('status', 'Página salva.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->currentTenant->ownsOrFail($page);
        $this->authorize('delete', $page);

        $page->delete();

        return redirect()
            ->route('cms.pages.index')
            ->with('status', 'Página removida.');
    }

    private function summary(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status->value,
            'status_label' => $page->status->label(),
            'is_home' => $page->is_home,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }
}
