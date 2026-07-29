<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Contracts;

/**
 * Assinatura eletrônica de documentos (marco: assinatura eletrônica). Contrato apenas
 * — sem implementação nem binding, como `PaymentGatewayInterface`/`InvoiceIssuerInterface`:
 * depende de um provedor de assinatura contratado (ex.: ICP-Brasil via provedor,
 * Clicksign, D4Sign, DocuSign) e do fluxo jurídico de consentimento, fora deste escopo.
 *
 * Casos de uso previstos: assinatura de documentos do prontuário, contratos de
 * prestação de serviço e termos LGPD (Fase 10). Quando houver provedor, registra-se a
 * implementação e um Job dispara a solicitação de assinatura, carregando só o ID do
 * documento (nunca o conteúdo sensível no payload da fila — docs/04-Seguranca.md).
 */
interface SignatureProviderInterface
{
    /**
     * Solicita a assinatura de um documento (identificado por tipo + id) por um
     * signatário e devolve o identificador do processo de assinatura no provedor.
     */
    public function requestSignature(string $documentType, string $documentId, string $signerEmail): string;
}
