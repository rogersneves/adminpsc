<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encryption_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('context')->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('wrapped_dek');
            $table->string('status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'context', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encryption_keys');
    }
};
