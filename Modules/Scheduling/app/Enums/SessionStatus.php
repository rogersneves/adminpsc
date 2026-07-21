<?php

declare(strict_types=1);

namespace Modules\Scheduling\Enums;

enum SessionStatus: string
{
    case Agendada = 'agendada';
    case Confirmada = 'confirmada';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';
    case Reagendada = 'reagendada';
    case NaoCompareceu = 'nao_compareceu';

    public function blocksSlot(): bool
    {
        return match ($this) {
            self::Cancelada, self::Reagendada => false,
            default => true,
        };
    }
}
