<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesma razão da migration irmã em clinical_sessions: colunas de rastreio que só
 * existem para o Notifications module não reenviar o mesmo lembrete de cobrança
 * (a caminho de vencer / já vencida) mais de uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_charges', function (Blueprint $table) {
            $table->timestamp('due_soon_reminder_sent_at')->nullable()->after('status');
            $table->timestamp('overdue_reminder_sent_at')->nullable()->after('due_soon_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('financial_charges', function (Blueprint $table) {
            $table->dropColumn(['due_soon_reminder_sent_at', 'overdue_reminder_sent_at']);
        });
    }
};
