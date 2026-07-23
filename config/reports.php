<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Janela de inatividade do paciente
    |--------------------------------------------------------------------------
    |
    | Quantos dias sem nenhuma sessão (passada dentro dessa janela ou futura já
    | agendada) fazem um paciente contar como "inativo" nos indicadores do
    | dashboard do psicólogo. Não configurável por tenant ainda (depende do
    | módulo Settings). @see docs/06-Roadmap.md Fase 6
    */
    'inactive_patient_days' => (int) env('REPORTS_INACTIVE_PATIENT_DAYS', 60),
];
