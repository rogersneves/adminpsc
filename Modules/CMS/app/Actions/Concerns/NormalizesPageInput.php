<?php

declare(strict_types=1);

namespace Modules\CMS\Actions\Concerns;

use Illuminate\Support\Str;
use Modules\CMS\DTOs\PageData;
use Modules\CMS\Enums\PageStatus;
use Modules\CMS\Models\Page;
use Modules\CMS\Services\HtmlSanitizer;

/**
 * Lógica compartilhada por CreatePageAction/UpdatePageAction: sanitização do
 * html/css, slug único por tenant, exclusividade de página inicial, carimbo de
 * `published_at`.
 */
trait NormalizesPageInput
{
    /**
     * Monta o array de atributos gravável a partir do DTO, já sanitizado.
     * `$existing` é a própria página no update (para preservar o slug atual e
     * ignorá-la na checagem de unicidade).
     */
    protected function attributesFor(
        HtmlSanitizer $sanitizer,
        string $tenantId,
        PageData $data,
        ?Page $existing = null,
    ): array {
        $slug = $this->resolveSlug($tenantId, $data, $existing);

        $publishedAt = match (true) {
            $data->status === PageStatus::Published && $existing?->published_at !== null => $existing->published_at,
            $data->status === PageStatus::Published => now(),
            default => null,
        };

        return [
            'title' => $data->title,
            'slug' => $slug,
            'status' => $data->status,
            'is_home' => $data->isHome,
            'html' => $sanitizer->sanitizeHtml($data->html),
            'css' => $sanitizer->sanitizeCss($data->css),
            'project_data' => $data->projectData,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'published_at' => $publishedAt,
        ];
    }

    private function resolveSlug(string $tenantId, PageData $data, ?Page $existing): string
    {
        $base = Str::slug($data->slug ?: $data->title) ?: 'pagina';
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($tenantId, $slug, $existing?->id)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $tenantId, string $slug, ?string $ignoreId): bool
    {
        return Page::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->withTrashed()
            ->exists();
    }

    /**
     * Só uma página inicial por tenant: ao marcar esta como home, desmarca as demais.
     */
    protected function enforceSingleHome(string $tenantId, Page $page): void
    {
        if (! $page->is_home) {
            return;
        }

        Page::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $page->id)
            ->where('is_home', true)
            ->update(['is_home' => false]);
    }
}
