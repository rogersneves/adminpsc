<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('psychologist_id')->constrained('psychologists')->cascadeOnDelete();
            $table->json('desired_period');
            $table->string('status')->default('aberto');
            $table->timestamps();

            $table->index(['tenant_id', 'psychologist_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_entries');
    }
};
