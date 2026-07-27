<?php

namespace Modules\Audit\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Audit\Listeners\LogAuthenticationEvents;
use Modules\Audit\Listeners\RecordDomainAuditEvents;
use Modules\Financial\Events\ChargeWasCreated;
use Modules\Payments\Events\PaymentWasRecorded;
use Modules\Payments\Events\PaymentWasReversed;
use Modules\Scheduling\Events\SessionWasCancelled;
use Modules\Scheduling\Events\SessionWasRescheduled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        Login::class => [LogAuthenticationEvents::class.'@handleLogin'],
        Logout::class => [LogAuthenticationEvents::class.'@handleLogout'],
        Failed::class => [LogAuthenticationEvents::class.'@handleFailed'],
        Registered::class => [LogAuthenticationEvents::class.'@handleRegistered'],

        // Ações de negócio obrigatoriamente auditadas (Fase 9), reaproveitando os
        // Events de domínio já disparados pelas Actions desde a Fase 7.
        SessionWasCancelled::class => [RecordDomainAuditEvents::class.'@handleSessionCancelled'],
        SessionWasRescheduled::class => [RecordDomainAuditEvents::class.'@handleSessionRescheduled'],
        ChargeWasCreated::class => [RecordDomainAuditEvents::class.'@handleChargeCreated'],
        PaymentWasRecorded::class => [RecordDomainAuditEvents::class.'@handlePaymentRecorded'],
        PaymentWasReversed::class => [RecordDomainAuditEvents::class.'@handlePaymentReversed'],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
