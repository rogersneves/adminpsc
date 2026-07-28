<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo staff↔unidade (psicólogo ou secretária lotado numa ou mais unidades).
 * Genérico sobre `users` para servir tanto psicólogos quanto secretárias — o escopo
 * de uma secretária (o que ela enxerga da agenda) é derivado destas linhas.
 *
 * Pivot puro (sem PK própria): chave composta (unit_id, user_id). Assim o `sync()` do
 * belongsToMany insere sem precisar gerar um id — uma coluna `uuid id` sem default
 * quebraria o insert no MySQL estrito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_user', function (Blueprint $table) {
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['unit_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_user');
    }
};
