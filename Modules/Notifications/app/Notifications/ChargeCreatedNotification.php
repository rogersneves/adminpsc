<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Financial\Models\FinancialCharge;

class ChargeCreatedNotification extends TenantNotification
{
    public function __construct(public readonly FinancialCharge $charge) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->charge->amount, 2, ',', '.');
        $dueDate = $this->charge->due_date->format('d/m/Y');
        $installmentInfo = $this->charge->installment_total > 1
            ? " (parcela {$this->charge->installment_number}/{$this->charge->installment_total})"
            : '';

        return (new MailMessage)
            ->subject('Nova cobrança gerada')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Uma nova cobrança de R$ {$amount}{$installmentInfo} foi gerada, com vencimento em {$dueDate}.")
            ->action('Ver situação financeira', url("/pacientes/{$this->charge->patient_id}/financeiro"));
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format((float) $this->charge->amount, 2, ',', '.');

        return [
            'title' => 'Nova cobrança',
            'message' => "Uma nova cobrança de R$ {$amount} foi gerada, com vencimento em ".$this->charge->due_date->format('d/m/Y').'.',
            'url' => "/pacientes/{$this->charge->patient_id}/financeiro",
        ];
    }
}
