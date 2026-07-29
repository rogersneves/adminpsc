<?php

declare(strict_types=1);

namespace Modules\Payments\Contracts;

use Modules\Payments\Models\Payment;

/**
 * Emissão de nota fiscal (marco: emissão de notas fiscais). Contrato apenas — sem
 * implementação nem binding no container, exatamente como `PaymentGatewayInterface`:
 * depende de um provedor fiscal contratado (ex.: Focus NFe, eNotas, NFe.io) e das
 * credenciais/parametrização fiscal do tenant, que não existem neste escopo.
 *
 * Quando houver provedor, uma implementação concreta é registrada no container e um
 * Job (fila) chama `issue()` após a confirmação de um pagamento — o payload carrega
 * só o ID do Payment, recarregando os dados dentro do Job (docs/04-Seguranca.md).
 */
interface InvoiceIssuerInterface
{
    /**
     * Emite a nota fiscal correspondente a um pagamento e devolve o identificador/URL
     * do documento emitido pelo provedor.
     */
    public function issue(Payment $payment): string;
}
