<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Payments\Models\Payment;

class PaymentReversedNotification extends TenantNotification
{
    public function __construct(public readonly Payment $payment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->payment->amount, 2, ',', '.');

        return (new MailMessage)
            ->subject('Pagamento estornado')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Seu pagamento de R$ {$amount} foi estornado.")
            ->action('Ver situação financeira', url("/pacientes/{$this->payment->charge->patient_id}/financeiro"));
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format((float) $this->payment->amount, 2, ',', '.');

        return [
            'title' => 'Pagamento estornado',
            'message' => "Seu pagamento de R$ {$amount} foi estornado.",
            'url' => "/pacientes/{$this->payment->charge->patient_id}/financeiro",
        ];
    }
}
