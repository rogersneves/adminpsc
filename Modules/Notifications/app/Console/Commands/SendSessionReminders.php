<?php

declare(strict_types=1);

namespace Modules\Notifications\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Notifications\SessionReminderNotification;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;

/**
 * `reminder_sent_at` (coluna adicionada pelo próprio módulo Notifications, ver
 * migration) garante idempotência: rodar o comando mais de uma vez na mesma janela
 * não duplica o lembrete, mesmo em execução horária.
 */
class SendSessionReminders extends Command
{
    protected $signature = 'notifications:send-session-reminders';

    protected $description = 'Envia lembrete de sessão às sessões agendadas dentro da janela configurada, uma única vez por sessão';

    public function handle(): int
    {
        $hoursBefore = (int) config('notifications.session_reminder.hours_before');

        $sessions = Session::query()
            ->whereIn('status', [SessionStatus::Agendada->value, SessionStatus::Confirmada->value])
            ->whereNull('reminder_sent_at')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addHours($hoursBefore))
            ->with('patient.user')
            ->get();

        foreach ($sessions as $session) {
            $session->patient->user?->notify(new SessionReminderNotification($session));
            $session->update(['reminder_sent_at' => now()]);
        }

        $this->info("Lembretes de sessão enviados: {$sessions->count()}");

        return self::SUCCESS;
    }
}
