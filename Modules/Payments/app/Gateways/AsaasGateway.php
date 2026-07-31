<?php

declare(strict_types=1);

namespace Modules\Payments\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\DTOs\GatewayChargeResult;
use Modules\Payments\DTOs\GatewayWebhookEvent;
use Modules\Payments\Enums\PaymentMethod;
use RuntimeException;

/**
 * Adapter de referência para o Asaas (BR: PIX, boleto, cartão). Integração real via
 * HTTP client do Laravel; requer `ASAAS_API_KEY` (config/payments.php). É um exemplo
 * concreto do contrato — outro provedor (Mercado Pago, Pagar.me) é outro driver.
 *
 * Fluxo de createCharge: garante um customer no Asaas (a partir do paciente) → cria a
 * cobrança (billingType conforme o método) → para PIX, busca o copia-e-cola. As
 * chamadas são mockáveis com Http::fake nos testes.
 */
class AsaasGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'asaas';
    }

    public function createCharge(FinancialCharge $charge, PaymentMethod $method): GatewayChargeResult
    {
        $customerId = $this->ensureCustomer($charge);

        $payment = $this->request()->post('/payments', [
            'customer' => $customerId,
            'billingType' => $this->billingType($method),
            'value' => (float) $charge->totalDue(),
            'dueDate' => $charge->due_date?->toDateString(),
            'externalReference' => $charge->id,
        ])->throw()->json();

        $pixPayload = null;
        if ($method === PaymentMethod::Pix) {
            $pixPayload = $this->request()->get("/payments/{$payment['id']}/pixQrCode")
                ->throw()->json('payload');
        }

        return new GatewayChargeResult(
            gatewayChargeId: $payment['id'],
            status: $payment['status'] ?? 'PENDING',
            paymentUrl: $payment['invoiceUrl'] ?? null,
            pixPayload: $pixPayload,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $expected = $this->config['webhook_token'] ?? null;

        // Sem token configurado, aceita (sandbox/dev). Com token, exige match exato.
        if ($expected === null || $expected === '') {
            return true;
        }

        return hash_equals($expected, (string) $request->header('asaas-access-token'));
    }

    public function parseWebhook(Request $request): ?GatewayWebhookEvent
    {
        $event = $request->input('event');
        $payment = $request->input('payment');

        if (! is_array($payment) || ! in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            return null;
        }

        return new GatewayWebhookEvent(
            type: 'paid',
            gatewayChargeId: (string) $payment['id'],
            externalId: (string) $payment['id'],
            amount: (float) ($payment['value'] ?? 0),
            method: $this->methodFromBillingType($payment['billingType'] ?? null),
        );
    }

    private function ensureCustomer(FinancialCharge $charge): string
    {
        $patient = $charge->patient;

        $response = $this->request()->post('/customers', [
            'name' => $patient?->display_name ?? 'Paciente',
            'email' => $patient?->email,
            'externalReference' => $patient?->id,
        ])->throw()->json();

        return $response['id'] ?? throw new RuntimeException('Asaas não retornou o id do customer.');
    }

    private function request()
    {
        return Http::baseUrl($this->config['base_url'])
            ->withHeaders(['access_token' => (string) $this->config['api_key']])
            ->acceptJson()
            ->asJson();
    }

    private function billingType(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Pix => 'PIX',
            PaymentMethod::Cartao => 'CREDIT_CARD',
            default => 'BOLETO',
        };
    }

    private function methodFromBillingType(?string $billingType): PaymentMethod
    {
        return match ($billingType) {
            'PIX' => PaymentMethod::Pix,
            'CREDIT_CARD' => PaymentMethod::Cartao,
            default => PaymentMethod::Transferencia,
        };
    }
}
