<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Events\ChargeWasCreated;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Payments\Events\PaymentWasRecorded;
use Modules\Payments\Events\PaymentWasReversed;
use Modules\Payments\Models\Payment;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Events\SessionWasCancelled;
use Modules\Scheduling\Events\SessionWasRescheduled;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Cobertura de auditoria das ações obrigatórias de agenda/financeiro (Fase 9),
 * verificando que os Events de domínio já existentes geram uma linha em audit_logs
 * com ação, ator e sujeito corretos.
 */
class DomainAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        // Notificações (também escutam esses eventos) não devem realmente sair aqui.
        Notification::fake();

        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actor->assignRole('admin_clinica');
        $this->actingAs($this->actor);
    }

    private function makePatient(): Patient
    {
        return Patient::query()->create([
            'tenant_id' => $this->tenant->id,
            'display_name' => 'Paciente',
            'email' => 'p'.uniqid().'@example.com',
        ]);
    }

    private function makeSession(): Session
    {
        $psychologistUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        return Session::query()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->makePatient()->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => CarbonImmutable::now()->addDays(2),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Agendada,
        ]);
    }

    private function makeCharge(): FinancialCharge
    {
        return FinancialCharge::query()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->makePatient()->id,
            'amount' => 100,
            'due_date' => CarbonImmutable::now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);
    }

    private function makePayment(FinancialCharge $charge): Payment
    {
        return Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'charge_id' => $charge->id,
            'amount' => 50,
            'paid_at' => CarbonImmutable::now(),
            'method' => 'dinheiro',
        ]);
    }

    public function test_session_cancelled_is_audited(): void
    {
        $session = $this->makeSession();

        SessionWasCancelled::dispatch($session);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session.cancelled',
            'actor_user_id' => $this->actor->id,
            'auditable_id' => $session->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_session_rescheduled_is_audited(): void
    {
        $old = $this->makeSession();
        $new = $this->makeSession();

        SessionWasRescheduled::dispatch($old, $new);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'session.rescheduled',
            'auditable_id' => $new->id,
        ]);
    }

    public function test_charge_created_is_audited(): void
    {
        $charge = $this->makeCharge();

        ChargeWasCreated::dispatch($charge);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'charge.created',
            'auditable_id' => $charge->id,
            'actor_user_id' => $this->actor->id,
        ]);
    }

    public function test_payment_recorded_and_reversed_are_audited(): void
    {
        $payment = $this->makePayment($this->makeCharge());

        PaymentWasRecorded::dispatch($payment);
        PaymentWasReversed::dispatch($payment);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.recorded', 'auditable_id' => $payment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.reversed', 'auditable_id' => $payment->id]);
    }
}
