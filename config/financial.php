<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Multa por atraso
    |--------------------------------------------------------------------------
    |
    | Percentual fixo aplicado sobre o valor da cobrança quando ela vence sem
    | pagamento (convenção comum em contratos de prestação de serviço no Brasil).
    */
    'late_fine_percent' => (float) env('FINANCIAL_LATE_FINE_PERCENT', 2),

    /*
    |--------------------------------------------------------------------------
    | Juros de mora
    |--------------------------------------------------------------------------
    |
    | Percentual ao mês, aplicado pro-rata die (proporcional aos dias em atraso)
    | sobre o valor da cobrança. Não configurável por tenant ainda (depende do
    | módulo Settings). @see docs/06-Roadmap.md Fase 5
    */
    'late_interest_percent_per_month' => (float) env('FINANCIAL_LATE_INTEREST_PERCENT_PER_MONTH', 1),
];
