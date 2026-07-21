<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychologist_availabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('psychologist_id')->constrained('psychologists')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('session_duration_minutes')->nullable();
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->string('type');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'psychologist_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psychologist_availabilities');
    }
};
