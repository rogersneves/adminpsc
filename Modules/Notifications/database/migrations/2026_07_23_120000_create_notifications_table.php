<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema padrão do Laravel para o canal `database` de Notification, com uma
 * mudança deliberada: `notifiable_id` precisa ser UUID, não o `unsignedBigInteger`
 * que `$table->morphs('notifiable')` geraria — `User` (o único notifiable do
 * projeto) tem PK UUID. Mesma classe de ajuste já feita para
 * `model_has_roles`/`model_has_permissions` na Fase 1 (ver CLAUDE.md).
 *
 * Sem `tenant_id`: o acesso a este recurso é sempre por relação
 * `$user->notifications()`, nunca por listagem administrativa entre tenants —
 * o isolamento vem da própria FK para `users`, mesmo raciocínio que já vale para
 * a tabela `users` não usar `BelongsToTenant`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
