<?php

declare(strict_types=1);

namespace Modules\Psychologists\DTOs;

final readonly class RegisterPsychologistData
{
    /**
     * @param  string[]  $specialties
     * @param  string[]  $unitIds
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $professionalRegistry,
        public array $specialties,
        public int $defaultSessionDurationMinutes,
        public array $unitIds = [],
    ) {}
}
