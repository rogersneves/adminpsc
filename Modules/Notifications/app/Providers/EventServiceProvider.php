<?php

namespace Modules\Notifications\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Financial\Events\ChargeWasCreated;
use Modules\Notifications\Listeners\SendChargeCreatedNotification;
use Modules\Notifications\Listeners\SendPaymentConfirmedNotification;
use Modules\Notifications\Listeners\SendPaymentReversedNotification;
use Modules\Notifications\Listeners\SendSessionCancelledNotification;
use Modules\Notifications\Listeners\SendSessionRescheduledNotification;
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
        SessionWasCancelled::class => [SendSessionCancelledNotification::class],
        SessionWasRescheduled::class => [SendSessionRescheduledNotification::class],
        ChargeWasCreated::class => [SendChargeCreatedNotification::class],
        PaymentWasRecorded::class => [SendPaymentConfirmedNotification::class],
        PaymentWasReversed::class => [SendPaymentReversedNotification::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
