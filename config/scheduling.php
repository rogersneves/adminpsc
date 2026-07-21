<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Horizonte de reserva
    |--------------------------------------------------------------------------
    |
    | Quantos dias à frente o paciente consegue ver/reservar horários.
    */
    'booking_horizon_days' => (int) env('SCHEDULING_BOOKING_HORIZON_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Antecedência mínima para cancelar/reagendar
    |--------------------------------------------------------------------------
    |
    | Não configurável por tenant ainda (depende do módulo Settings).
    | @see docs/06-Roadmap.md Fase 3
    */
    'minimum_reschedule_notice_hours' => (int) env('SCHEDULING_MINIMUM_RESCHEDULE_NOTICE_HOURS', 24),
];
