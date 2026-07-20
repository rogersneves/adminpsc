<?php

declare(strict_types=1);

namespace Modules\Authentication\DTOs;

final readonly class TotpSetup
{
    public function __construct(
        public string $secret,
        public string $otpauthUri,
    ) {}
}
