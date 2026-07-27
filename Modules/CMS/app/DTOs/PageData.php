<?php

declare(strict_types=1);

namespace Modules\CMS\DTOs;

use Modules\CMS\Enums\PageStatus;

/**
 * Dados de uma página vindos do editor, transportados do Controller para a Action
 * sem carregar o Request adentro da camada de negócio.
 */
readonly class PageData
{
    public function __construct(
        public string $title,
        public ?string $slug,
        public PageStatus $status,
        public bool $isHome,
        public ?string $html,
        public ?string $css,
        public ?array $projectData,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {}
}
