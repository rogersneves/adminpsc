<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de aceite de documentos legais (docs/04-Seguranca.md). Append-only: cada
 * aceite é uma linha nova (tipo, versão aceita, data/hora, IP), nunca atualizada nem
 * apagada — é a prova de consentimento. Sem `updated_at` (o Model bloqueia update()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgpd_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('legal_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->string('document_type', 40);
            $table->unsignedInteger('document_version');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accepted_at')->useCurrent();

            $table->index(['user_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgpd_consents');
    }
};
