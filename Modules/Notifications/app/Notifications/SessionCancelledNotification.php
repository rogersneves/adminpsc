<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Scheduling\Models\Session;

class SessionCancelledNotification extends TenantNotification
{
    public function __construct(
        public readonly Session $session,
        public readonly string $otherPartyName,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sessão cancelada')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua sessão com {$this->otherPartyName}, marcada para ".$this->session->scheduled_at->format('d/m/Y \à\s H:i').', foi cancelada.')
            ->action('Ver minhas sessões', url('/minhas-sessoes'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Sessão cancelada',
            'message' => "Sua sessão com {$this->otherPartyName} em ".$this->session->scheduled_at->format('d/m/Y H:i').' foi cancelada.',
            'url' => '/minhas-sessoes',
        ];
    }
}
