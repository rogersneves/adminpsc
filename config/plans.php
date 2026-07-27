<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Planos do SaaS (Fase 11)
|--------------------------------------------------------------------------
|
| Planos definidos em config (não tabela) — são catálogo da plataforma, não dado
| de tenant. O tenant guarda só o identificador do plano (`tenants.plan`) e o fim
| do trial (`tenants.trial_ends_at`). Limites `null` = ilimitado. Cobrança real de
| gateway é marco futuro (docs/06-Roadmap.md); aqui o "billing" é estado de
| assinatura + trial + aplicação de limites, sem integração de pagamento.
|
| @see docs/06-Roadmap.md Fase 11
*/

return [

    'default' => 'trial',

    'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),

    'plans' => [
        'trial' => [
            'label' => 'Avaliação',
            'max_psychologists' => 2,
            'max_patients' => 50,
        ],
        'basico' => [
            'label' => 'Básico',
            'max_psychologists' => 5,
            'max_patients' => 300,
        ],
        'profissional' => [
            'label' => 'Profissional',
            'max_psychologists' => null,
            'max_patients' => null,
        ],
    ],
];
