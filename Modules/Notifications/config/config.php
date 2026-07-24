<?php

declare(strict_types=1);

/**
 * Canais por padrão: mail + database. Arquitetura pluggable — adicionar um canal
 * futuro (sms, whatsapp) é só acrescentar o nome aqui e implementar
 * `toSms()`/`toWhatsApp()` nas classes de Notification já existentes; nenhuma
 * classe existente precisa ser refatorada (docs/06-Roadmap.md, Fase 7).
 */
return [
    'name' => 'Notifications',

    'channels' => [
        'default' => array_filter(explode(',', (string) env('NOTIFICATIONS_DEFAULT_CHANNELS', 'mail,database'))),
    ],

    'session_reminder' => [
        'hours_before' => (int) env('NOTIFICATIONS_SESSION_REMINDER_HOURS_BEFORE', 24),
    ],

    'charge_reminder' => [
        'days_before_due' => (int) env('NOTIFICATIONS_CHARGE_REMINDER_DAYS_BEFORE_DUE', 3),
    ],
];
