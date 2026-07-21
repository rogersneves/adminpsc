<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela chamada "clinical_sessions", não "sessions" — "sessions" já é a tabela de
 * sessão HTTP do próprio Laravel (SESSION_DRIVER=database, ver
 * database/migrations/0001_01_01_000000_create_users_table.php). Ver nota em
 * docs/02-Banco-de-Dados.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('psychologist_id')->constrained('psychologists')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('modality');
            $table->string('status')->default('agendada');
            $table->foreignUuid('rescheduled_from_id')->nullable()->constrained('clinical_sessions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'psychologist_id', 'scheduled_at']);
            $table->index(['tenant_id', 'patient_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_sessions');
    }
};
