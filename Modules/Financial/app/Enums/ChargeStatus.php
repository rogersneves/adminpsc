<?php

declare(strict_types=1);

namespace Modules\Financial\Enums;

enum ChargeStatus: string
{
    case EmAberto = 'em_aberto';
    case Pago = 'pago';
    case Vencido = 'vencido';
    case Parcial = 'parcial';
    case Cancelado = 'cancelado';
    case Estornado = 'estornado';
}
