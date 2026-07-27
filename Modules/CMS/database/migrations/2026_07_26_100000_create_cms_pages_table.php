<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->string('status', 20)->default('rascunho');
            $table->boolean('is_home')->default(false);

            // Artefatos de publicação: HTML já sanitizado + CSS, servidos ao visitante.
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();

            // Fonte de verdade do editor GrapesJS (getProjectData) para reabrir a
            // página no builder. Nunca é servido ao público — só o html/css sanitizado é.
            $table->longText('project_data')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // slug único por tenant (não global — dois tenants podem ter "/sobre").
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
