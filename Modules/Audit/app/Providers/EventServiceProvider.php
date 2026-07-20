<?php

namespace Modules\Audit\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Audit\Listeners\LogAuthenticationEvents;

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
