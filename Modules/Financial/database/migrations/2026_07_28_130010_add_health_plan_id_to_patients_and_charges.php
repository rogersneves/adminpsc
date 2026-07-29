<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convênio do paciente e da cobrança (marco: convênios). Nullable = particular
 * (sem convênio). Migration no módulo Financial porque convênio é conceito de
 * faturamento; altera a tabela `patients` (de Patients) pelo mesmo motivo que
 * Notifications/Security alteram tabelas de outros módulos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignUuid('health_plan_id')->nullable()->after('user_id')
                ->constrained('health_plans')->nullOnDelete();
        });

        Schema::table('financial_charges', function (Blueprint $table) {
            $table->foreignUuid('health_plan_id')->nullable()->after('patient_id')
                ->constrained('health_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_plan_id');
        });
        Schema::table('financial_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_plan_id');
        });
    }
};
