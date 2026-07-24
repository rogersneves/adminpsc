<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Financial\Models\FinancialCharge;

class ChargeDueSoonNotification extends TenantNotification
{
    public function __construct(public readonly FinancialCharge $charge) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->charge->amount, 2, ',', '.');
        $dueDate = $this->charge->due_date->format('d/m/Y');

        return (new MailMessage)
            ->subject('Cobrança a vencer')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua cobrança de R$ {$amount} vence em {$dueDate}.")
            ->action('Ver situação financeira', url("/pacientes/{$this->charge->patient_id}/financeiro"));
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format((float) $this->charge->amount, 2, ',', '.');

        return [
            'title' => 'Cobrança a vencer',
            'message' => "Sua cobrança de R$ {$amount} vence em ".$this->charge->due_date->format('d/m/Y').'.',
            'url' => "/pacientes/{$this->charge->patient_id}/financeiro",
        ];
    }
}
