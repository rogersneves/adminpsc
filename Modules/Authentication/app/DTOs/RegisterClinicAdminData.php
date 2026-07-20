<?php

declare(strict_types=1);

namespace Modules\Authentication\DTOs;

final readonly class RegisterClinicAdminData
{
    public function __construct(
        public string $tenantName,
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
