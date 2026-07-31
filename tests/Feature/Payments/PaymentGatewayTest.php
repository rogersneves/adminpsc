<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Payments\Actions\RequestGatewayChargeAction;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin_clinica');

        return $user;
    }

    private function charge(Tenant $tenant, array $overrides = []): FinancialCharge
    {
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'display_name' => 'Paciente', 'email' => 'p'.uniqid().'@ex.com',
        ]);

        return FinancialCharge::query()->create(array_merge([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id,
            'amount' => 100, 'due_date' => CarbonImmutable::now()->addDays(5), 'status' => ChargeStatus::EmAberto,
        ], $overrides));
    }

    public function test_null_driver_marks_the_charge_without_calling_out(): void
    {
        config(['payments.default' => 'null']);
        $tenant = Tenant::factory()->create();
        $charge = $this->charge($tenant);

        app(RequestGatewayChargeAction::class)($charge, PaymentMethod::Pix);

        $fresh = $charge->fresh();
        $this->assertSame('null', $fresh->gateway);
        $this->assertSame('null_'.$charge->id, $fresh->gateway_charge_id);
        $this->assertNull($fresh->payment_url);
    }

    public function test_staff_can_issue_a_gateway_charge_and_others_cannot(): void
    {
        config(['payments.default' => 'null']);
        $tenant = Tenant::factory()->create();
        $charge = $this->charge($tenant);

        $this->actingAs($this->admin($tenant))
            ->post("/financeiro/cobrancas/{$charge->id}/gateway", ['method' => 'pix'])
            ->assertRedirect();
        $this->assertNotNull($charge->fresh()->gateway_charge_id);

        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');
        $this->actingAs($psychologist)
            ->post("/financeiro/cobrancas/{$charge->id}/gateway", ['method' => 'pix'])
            ->assertForbidden();
    }

    public function test_asaas_driver_creates_a_pix_charge_with_the_right_request(): void
    {
        config([
            'payments.default' => 'asaas',
            'payments.gateways.asaas.base_url' => 'https://sandbox.asaas.com/api/v3',
            'payments.gateways.asaas.api_key' => 'key_123',
        ]);
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_1'], 200),
            '*/payments/pay_1/pixQrCode' => Http::response(['payload' => '00020126-PIX'], 200),
            '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://asaas/i/pay_1'], 200),
        ]);

        $tenant = Tenant::factory()->create();
        $charge = $this->charge($tenant);

        app(RequestGatewayChargeAction::class)($charge, PaymentMethod::Pix);

        $fresh = $charge->fresh();
        $this->assertSame('asaas', $fresh->gateway);
        $this->assertSame('pay_1', $fresh->gateway_charge_id);
        $this->assertSame('https://asaas/i/pay_1', $fresh->payment_url);
        $this->assertSame('00020126-PIX', $fresh->pix_payload);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments')
            && $request['billingType'] === 'PIX'
            && $request['externalReference'] === $charge->id);
    }

    public function test_webhook_reconciles_a_payment_idempotently(): void
    {
        Notification::fake();
        config([
            'payments.default' => 'asaas',
            'payments.gateways.asaas.webhook_token' => null,
        ]);
        $tenant = Tenant::factory()->create();
        $charge = $this->charge($tenant, ['gateway' => 'asaas', 'gateway_charge_id' => 'pay_1']);

        $payload = [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_1', 'value' => 100, 'externalReference' => $charge->id, 'billingType' => 'PIX'],
        ];

        $this->postJson('/webhooks/payments/asaas', $payload)->assertOk();
        $this->postJson('/webhooks/payments/asaas', $payload)->assertOk(); // replay

        $this->assertSame(1, $charge->payments()->count());
        $this->assertSame(ChargeStatus::Pago, $charge->fresh()->status);
    }

    public function test_webhook_with_invalid_token_is_rejected(): void
    {
        config([
            'payments.default' => 'asaas',
            'payments.gateways.asaas.webhook_token' => 'secret-token',
        ]);
        $tenant = Tenant::factory()->create();
        $charge = $this->charge($tenant, ['gateway' => 'asaas', 'gateway_charge_id' => 'pay_1']);

        $this->postJson('/webhooks/payments/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_1', 'value' => 100, 'externalReference' => $charge->id, 'billingType' => 'PIX'],
        ], ['asaas-access-token' => 'wrong'])->assertForbidden();

        $this->assertSame(0, $charge->payments()->count());
    }

    public function test_webhook_for_unknown_driver_is_404(): void
    {
        $this->postJson('/webhooks/payments/does-not-exist', [])->assertNotFound();
    }
}
