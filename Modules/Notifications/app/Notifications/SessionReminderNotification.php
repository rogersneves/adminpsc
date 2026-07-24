<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Scheduling\Models\Session;

class SessionReminderNotification extends TenantNotification
{
    public function __construct(public readonly Session $session) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lembrete: você tem uma sessão em breve')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Este é um lembrete da sua sessão marcada para '.$this->session->scheduled_at->format('d/m/Y \à\s H:i').'.')
            ->action('Ver minhas sessões', url('/minhas-sessoes'))
            ->line('Se precisar cancelar ou reagendar, faça isso com a antecedência mínima exigida.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Lembrete de sessão',
            'message' => 'Você tem uma sessão marcada para '.$this->session->scheduled_at->format('d/m/Y \à\s H:i').'.',
            'url' => '/minhas-sessoes',
        ];
    }
}
