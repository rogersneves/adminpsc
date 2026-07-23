<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('clinical_sessions')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->decimal('interest_amount', 10, 2)->default(0);
            $table->date('due_date');
            $table->string('status')->default('em_aberto');
            $table->unsignedSmallInteger('installment_number')->default(1);
            $table->unsignedSmallInteger('installment_total')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_charges');
    }
};
