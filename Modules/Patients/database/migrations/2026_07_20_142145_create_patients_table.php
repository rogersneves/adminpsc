<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name');
            $table->string('email');
            $table->text('document_number_encrypted')->nullable();
            $table->string('document_number_hash', 64)->nullable()->index();
            $table->text('birth_date_encrypted')->nullable();
            $table->text('phones_encrypted')->nullable();
            $table->text('emergency_contacts_encrypted')->nullable();
            $table->text('address_encrypted')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
