<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gateways de pagamento (marco: gateways reais + PIX)
|--------------------------------------------------------------------------
|
| Driver ativo resolvido pelo PaymentGatewayManager. `null` é o padrão: não chama
| provedor externo — a cobrança fica aguardando pagamento manual (staff registra
| via RecordPaymentAction, como sempre). `asaas` é o adapter de referência (BR:
| PIX/boleto/cartão); trocar/adicionar provedor é implementar um driver contra
| PaymentGatewayInterface e registrá-lo no Manager.
|
| Credenciais aqui são no nível da plataforma (env). Credenciais por-tenant
| (cifradas) são pendência documentada — cada clínica teria sua própria conta.
|
| @see docs/06-Roadmap.md
*/

return [

    'default' => env('PAYMENTS_GATEWAY', 'null'),

    'gateways' => [

        'null' => [
            // Sem configuração — driver local, sem chamadas externas.
        ],

        'asaas' => [
            'base_url' => env('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3'),
            'api_key' => env('ASAAS_API_KEY'),
            // Token que o Asaas envia no header `asaas-access-token` dos webhooks,
            // configurado no painel do Asaas — usado para verificar a origem.
            'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        ],
    ],
];
