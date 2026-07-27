<?php

declare(strict_types=1);

namespace Modules\CMS\Actions;

use Illuminate\Support\Facades\DB;
use Modules\CMS\Actions\Concerns\NormalizesPageInput;
use Modules\CMS\DTOs\PageData;
use Modules\CMS\Models\Page;
use Modules\CMS\Services\HtmlSanitizer;

class UpdatePageAction
{
    use NormalizesPageInput;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function __invoke(Page $page, PageData $data): Page
    {
        return DB::transaction(function () use ($page, $data): Page {
            $page->update($this->attributesFor($this->sanitizer, $page->tenant_id, $data, $page));

            $this->enforceSingleHome($page->tenant_id, $page);

            return $page->refresh();
        });
    }
}
