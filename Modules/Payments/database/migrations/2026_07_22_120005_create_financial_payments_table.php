<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sem softDeletes(): reversão de pagamento nunca apaga a linha, só marca
 * reversed_at (ver Modules\Payments\Models\Payment / ReversePaymentAction) —
 * preserva a trilha de que este pagamento existiu e foi estornado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('charge_id')->constrained('financial_charges')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->dateTime('paid_at');
            $table->string('method');
            $table->string('gateway_reference')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'charge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_payments');
    }
};
