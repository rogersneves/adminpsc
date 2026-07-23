<?php

declare(strict_types=1);

namespace Modules\Payments\Enums;

enum PaymentMethod: string
{
    case Dinheiro = 'dinheiro';
    case Cartao = 'cartao';
    case Transferencia = 'transferencia';
    case Pix = 'pix';
}
