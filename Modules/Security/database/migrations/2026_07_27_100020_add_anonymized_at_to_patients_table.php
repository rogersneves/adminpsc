<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de anonimização LGPD no paciente. Vive numa migration do módulo Security
 * (mesmo padrão da Fase 7, em que Notifications adicionou colunas a tabelas de outros
 * módulos): a única razão desta coluna existir é o fluxo de anonimização do LGPD.
 * Distingue "anonimizado" de um soft-delete comum e evita reprocessar o mesmo titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
