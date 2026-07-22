<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\DTOs;

final readonly class MedicalRecordContentData
{
    public function __construct(
        public ?string $notes,
        public ?string $therapeuticObjectives,
        public ?string $therapeuticPlan,
    ) {}
}
