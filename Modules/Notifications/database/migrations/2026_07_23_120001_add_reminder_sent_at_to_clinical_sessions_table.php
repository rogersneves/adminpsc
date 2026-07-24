<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna de rastreio pertence ao módulo Notifications (única razão de existir é
 * evitar reenviar o mesmo lembrete de sessão), não ao Scheduling — por isso a
 * migration mora aqui, alterando a tabela `clinical_sessions` da Fase 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_sessions', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_sessions', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
