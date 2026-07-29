<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convênios (planos de saúde) aceitos por uma clínica (marco: convênios). Catálogo
 * por tenant; pacientes e cobranças podem referenciar um convênio. A cobrança em si
 * ao convênio (guias, faturamento TISS) é evolução futura — aqui é o vínculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_plans');
    }
};
