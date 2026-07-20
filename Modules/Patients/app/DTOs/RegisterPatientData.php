<?php

declare(strict_types=1);

namespace Modules\Patients\DTOs;

final readonly class RegisterPatientData
{
    public function __construct(
        public string $displayName,
        public string $email,
        public string $password,
    ) {}
}
