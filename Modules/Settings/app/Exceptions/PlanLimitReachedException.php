<?php

declare(strict_types=1);

namespace Modules\Settings\Exceptions;

use RuntimeException;

/**
 * Limite do plano atingido (ex.: nº de psicólogos do plano). Exceção de domínio —
 * o Controller a converte numa mensagem de validação amigável.
 */
class PlanLimitReachedException extends RuntimeException {}
