<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidade onde a sessão ocorre. Nullable — sessões antigas (e clínicas de unidade
 * única que nunca configuraram unidades) ficam sem unidade. A migration vive no
 * módulo Settings porque a única razão da coluna existir é o recurso de unidades
 * (mesmo padrão de Notifications/Security alterando tabelas de outros módulos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_sessions', function (Blueprint $table) {
            $table->foreignUuid('unit_id')->nullable()->after('psychologist_id')
                ->constrained('units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
