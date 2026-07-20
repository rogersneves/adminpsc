<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('name');
            $table->text('document_number_encrypted')->nullable();
            $table->string('document_number_hash', 64)->nullable()->index();
            $table->string('email')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->text('address_encrypted')->nullable();
            $table->string('relationship');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
