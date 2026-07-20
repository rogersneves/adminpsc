<?php

declare(strict_types=1);

namespace Modules\Psychologists\DTOs;

final readonly class RegisterPsychologistData
{
    /**
     * @param  string[]  $specialties
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $professionalRegistry,
        public array $specialties,
        public int $defaultSessionDurationMinutes,
    ) {}
}
