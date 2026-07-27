<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos legais versionados por tenant (política de privacidade, termos de uso).
 * Nova versão nunca sobrescreve a anterior (docs/04-Seguranca.md): cada publicação é
 * uma linha nova com `version` incrementado; só uma fica `is_current` por (tenant, tipo).
 * O histórico é preservado para provar qual texto o titular aceitou e quando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_current')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'type', 'version']);
            $table->index(['tenant_id', 'type', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
