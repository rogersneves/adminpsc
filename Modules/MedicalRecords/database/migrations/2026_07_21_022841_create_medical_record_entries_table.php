<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only: sem updated_at (nunca é atualizado, só versionado — cada edição cria
 * uma nova linha ligada por previous_version_id). Ver Modules\MedicalRecords\Models\
 * MedicalRecordEntry, que reforça isso em código (update()/delete() lançam exceção).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('psychologist_id')->constrained('psychologists')->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('clinical_sessions')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->foreignUuid('previous_version_id')->nullable()->constrained('medical_record_entries')->nullOnDelete();
            $table->text('content_encrypted');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['tenant_id', 'patient_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_entries');
    }
};
