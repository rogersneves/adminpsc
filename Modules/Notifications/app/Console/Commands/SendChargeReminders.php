<?php

declare(strict_types=1);

namespace Modules\Notifications\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Notifications\Notifications\ChargeDueSoonNotification;
use Modules\Notifications\Notifications\ChargeOverdueNotification;

/**
 * Dois lembretes distintos, cada um enviado uma única vez por cobrança (colunas
 * `due_soon_reminder_sent_at`/`overdue_reminder_sent_at`, ver migration do módulo
 * Notifications): "a vencer" quando due_date cai dentro da janela configurada e a
 * cobrança ainda está em aberto; "vencida" quando `financial:apply-late-fees`
 * (Fase 5) já recalculou o status para `vencido`. Não repete o aviso de vencida
 * diariamente — é um alerta único, não um cobrança recorrente.
 */
class SendChargeReminders extends Command
{
    protected $signature = 'notifications:send-charge-reminders';

    protected $description = 'Avisa pacientes sobre cobranças a vencer e cobranças vencidas, uma única vez por cobrança';

    public function handle(): int
    {
        $daysBeforeDue = (int) config('notifications.charge_reminder.days_before_due');

        $dueSoonCharges = FinancialCharge::query()
            ->where('status', ChargeStatus::EmAberto->value)
            ->whereNull('due_soon_reminder_sent_at')
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays($daysBeforeDue))
            ->with('patient.user')
            ->get();

        foreach ($dueSoonCharges as $charge) {
            $charge->patient->user?->notify(new ChargeDueSoonNotification($charge));
            $charge->update(['due_soon_reminder_sent_at' => now()]);
        }

        $overdueCharges = FinancialCharge::query()
            ->where('status', ChargeStatus::Vencido->value)
            ->whereNull('overdue_reminder_sent_at')
            ->with('patient.user')
            ->get();

        foreach ($overdueCharges as $charge) {
            $charge->patient->user?->notify(new ChargeOverdueNotification($charge));
            $charge->update(['overdue_reminder_sent_at' => now()]);
        }

        $this->info("Lembretes de cobrança a vencer: {$dueSoonCharges->count()}; vencidas: {$overdueCharges->count()}");

        return self::SUCCESS;
    }
}
