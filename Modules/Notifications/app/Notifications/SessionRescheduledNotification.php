<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Scheduling\Models\Session;

class SessionRescheduledNotification extends TenantNotification
{
    public function __construct(
        public readonly Session $oldSession,
        public readonly Session $newSession,
        public readonly string $otherPartyName,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sessão reagendada')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua sessão com {$this->otherPartyName}, antes marcada para ".$this->oldSession->scheduled_at->format('d/m/Y \à\s H:i').', foi reagendada para '.$this->newSession->scheduled_at->format('d/m/Y \à\s H:i').'.')
            ->action('Ver minhas sessões', url('/minhas-sessoes'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Sessão reagendada',
            'message' => "Sua sessão com {$this->otherPartyName} foi reagendada para ".$this->newSession->scheduled_at->format('d/m/Y H:i').'.',
            'url' => '/minhas-sessoes',
        ];
    }
}
