<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link da teleconsulta (marco: teleconsulta). Só faz sentido para sessões `online`;
 * o psicólogo define o link (Meet/Zoom/Whereby etc.) e o paciente o vê. Nullable —
 * sessões presenciais/sem link ficam sem. Não há integração com provedor de vídeo:
 * o link é informado manualmente, mesmo espírito do PIX "registro manual" da Fase 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_sessions', function (Blueprint $table) {
            $table->string('meeting_url', 500)->nullable()->after('modality');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_sessions', function (Blueprint $table) {
            $table->dropColumn('meeting_url');
        });
    }
};
