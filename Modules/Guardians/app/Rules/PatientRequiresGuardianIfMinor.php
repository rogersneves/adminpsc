<?php

declare(strict_types=1);

namespace Modules\Guardians\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida o campo `birth_date`: se a idade calculada for menor que 16 anos, exige que
 * haja ao menos um responsável legal (já cadastrado ou enviado no mesmo request —
 * a contagem final é calculada por quem constrói a regra, não aqui).
 *
 * @see docs/02-Banco-de-Dados.md
 */
class PatientRequiresGuardianIfMinor implements ValidationRule
{
    public function __construct(private readonly int $guardianCountAfterSave) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $age = Carbon::parse($value)->age;

        if ($age < 16 && $this->guardianCountAfterSave === 0) {
            $fail('Pacientes menores de 16 anos precisam de ao menos um responsável legal cadastrado.');
        }
    }
}
