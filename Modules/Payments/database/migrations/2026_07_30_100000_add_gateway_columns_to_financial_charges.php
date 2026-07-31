<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados da cobrança no gateway (marco: gateways reais + PIX). Nullable — cobranças
 * particulares/manuais não passam por gateway. Migration no módulo Payments (dono da
 * integração de pagamento) alterando a tabela de Financial, mesmo precedente de
 * Notifications/Security/Settings alterando tabelas de outros módulos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_charges', function (Blueprint $table) {
            $table->string('gateway')->nullable()->after('health_plan_id');
            $table->string('gateway_charge_id')->nullable()->after('gateway');
            $table->string('gateway_status')->nullable()->after('gateway_charge_id');
            $table->string('payment_url', 1000)->nullable()->after('gateway_status');
            $table->text('pix_payload')->nullable()->after('payment_url');

            $table->index('gateway_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_charges', function (Blueprint $table) {
            $table->dropIndex(['gateway_charge_id']);
            $table->dropColumn(['gateway', 'gateway_charge_id', 'gateway_status', 'payment_url', 'pix_payload']);
        });
    }
};
