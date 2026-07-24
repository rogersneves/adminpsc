<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Payments\Models\Payment;

class PaymentConfirmedNotification extends TenantNotification
{
    public function __construct(public readonly Payment $payment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->payment->amount, 2, ',', '.');

        return (new MailMessage)
            ->subject('Pagamento confirmado')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Recebemos seu pagamento de R$ {$amount}.")
            ->action('Baixar recibo', url("/financeiro/pagamentos/{$this->payment->id}/recibo"));
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format((float) $this->payment->amount, 2, ',', '.');

        return [
            'title' => 'Pagamento confirmado',
            'message' => "Recebemos seu pagamento de R$ {$amount}.",
            'url' => "/financeiro/pagamentos/{$this->payment->id}/recibo",
        ];
    }
}
