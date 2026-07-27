<?php

declare(strict_types=1);

namespace Modules\Audit\Listeners;

use Modules\Audit\Services\AuditLogger;
use Modules\Financial\Events\ChargeWasCreated;
use Modules\Payments\Events\PaymentWasRecorded;
use Modules\Payments\Events\PaymentWasReversed;
use Modules\Scheduling\Events\SessionWasCancelled;
use Modules\Scheduling\Events\SessionWasRescheduled;
use Modules\Users\Models\User;

/**
 * Cobertura de auditoria das ações obrigatórias de negócio (docs/04-Seguranca.md:
 * alteração de agenda, alteração financeira). Reaproveita os Events de domínio que
 * as Actions já disparam desde a Fase 7 — Audit é consumidor cross-cutting desses
 * eventos, mesma direção de acoplamento que Notifications (os módulos de origem não
 * sabem que Audit existe).
 *
 * Síncrono de propósito (não ShouldQueue): o AuditLogger captura IP/User-Agent de
 * Request::ip()/header() e o ator de auth()->user(), que só existem no contexto da
 * requisição — enfileirar perderia esse contexto.
 */
class RecordDomainAuditEvents
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function handleSessionCancelled(SessionWasCancelled $event): void
    {
        $this->logger->record(
            'session.cancelled',
            actor: $this->actor(),
            subject: $event->session,
            tenantId: $event->session->tenant_id,
        );
    }

    public function handleSessionRescheduled(SessionWasRescheduled $event): void
    {
        $this->logger->record(
            'session.rescheduled',
            actor: $this->actor(),
            subject: $event->newSession,
            changes: ['rescheduled_from_id' => $event->oldSession->id],
            tenantId: $event->newSession->tenant_id,
        );
    }

    public function handleChargeCreated(ChargeWasCreated $event): void
    {
        $this->logger->record(
            'charge.created',
            actor: $this->actor(),
            subject: $event->charge,
            tenantId: $event->charge->tenant_id,
        );
    }

    public function handlePaymentRecorded(PaymentWasRecorded $event): void
    {
        $this->logger->record(
            'payment.recorded',
            actor: $this->actor(),
            subject: $event->payment,
            tenantId: $event->payment->tenant_id,
        );
    }

    public function handlePaymentReversed(PaymentWasReversed $event): void
    {
        $this->logger->record(
            'payment.reversed',
            actor: $this->actor(),
            subject: $event->payment,
            tenantId: $event->payment->tenant_id,
        );
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
