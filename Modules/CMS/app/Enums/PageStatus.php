<?php

declare(strict_types=1);

namespace Modules\CMS\Enums;

/**
 * Estado de publicação de uma página do CMS. `rascunho` nunca aparece nas rotas
 * públicas (`/c/{tenant:slug}`); só `publicada` é servida ao visitante.
 */
enum PageStatus: string
{
    case Draft = 'rascunho';
    case Published = 'publicada';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicada',
        };
    }
}
