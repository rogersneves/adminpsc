<?php

declare(strict_types=1);

namespace Modules\CMS\Actions;

use Illuminate\Support\Facades\DB;
use Modules\CMS\Actions\Concerns\NormalizesPageInput;
use Modules\CMS\DTOs\PageData;
use Modules\CMS\Models\Page;
use Modules\CMS\Services\HtmlSanitizer;
use Modules\Tenant\Models\Tenant;

class CreatePageAction
{
    use NormalizesPageInput;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function __invoke(Tenant $tenant, PageData $data): Page
    {
        return DB::transaction(function () use ($tenant, $data): Page {
            $page = Page::query()->create([
                'tenant_id' => $tenant->id,
                ...$this->attributesFor($this->sanitizer, $tenant->id, $data),
            ]);

            $this->enforceSingleHome($tenant->id, $page);

            return $page;
        });
    }
}
