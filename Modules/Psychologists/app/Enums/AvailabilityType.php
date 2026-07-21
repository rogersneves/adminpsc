<?php

declare(strict_types=1);

namespace Modules\Psychologists\Enums;

enum AvailabilityType: string
{
    case Recorrente = 'recorrente';
    case Particular = 'particular';
    case Bloqueio = 'bloqueio';
    case Ferias = 'ferias';
    case Feriado = 'feriado';

    /**
     * recorrente/particular adicionam disponibilidade; bloqueio/ferias/feriado removem.
     */
    public function addsAvailability(): bool
    {
        return match ($this) {
            self::Recorrente, self::Particular => true,
            self::Bloqueio, self::Ferias, self::Feriado => false,
        };
    }

    /**
     * recorrente usa `weekday` (repete toda semana); os demais usam `date` (pontual).
     */
    public function isRecurring(): bool
    {
        return $this === self::Recorrente;
    }
}
