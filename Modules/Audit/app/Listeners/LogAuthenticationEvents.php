<?php

declare(strict_types=1);

namespace Modules\Audit\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Modules\Audit\Services\AuditLogger;

/**
 * Reaproveita os eventos nativos que Auth::attempt()/login()/logout() já disparam,
 * em vez de inventar eventos próprios para o que o framework já cobre.
 */
class LogAuthenticationEvents
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function handleLogin(Login $event): void
    {
        $this->logger->record('login', actor: $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->logger->record('logout', actor: $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->logger->record('auth_failure', changes: [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function handleRegistered(Registered $event): void
    {
        $this->logger->record('registered', actor: $event->user, subject: $event->user);
    }
}
