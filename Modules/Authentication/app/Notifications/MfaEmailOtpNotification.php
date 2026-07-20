<?php

declare(strict_types=1);

namespace Modules\Authentication\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MfaEmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code, private readonly int $ttlMinutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de verificação AdminPSC')
            ->greeting('Olá!')
            ->line("Seu código de verificação é: {$this->code}")
            ->line("Ele expira em {$this->ttlMinutes} minutos.")
            ->line('Se você não tentou entrar na sua conta, ignore este e-mail.');
    }
}
