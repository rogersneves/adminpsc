<?php

declare(strict_types=1);

namespace Modules\Security\DTOs;

final readonly class DecryptedKey
{
    public function __construct(
        public string $dek,
        public int $version,
    ) {}
}
