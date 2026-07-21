<?php

declare(strict_types=1);

namespace Modules\Scheduling\Enums;

enum SessionModality: string
{
    case Presencial = 'presencial';
    case Online = 'online';
    case Domiciliar = 'domiciliar';
}
