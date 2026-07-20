<?php

declare(strict_types=1);

namespace Modules\Authentication\DTOs;

final readonly class LoginAttemptData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
