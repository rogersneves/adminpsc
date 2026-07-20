<?php

declare(strict_types=1);

namespace Modules\Security\Services;

use Modules\Security\DTOs\DecryptedKey;
use Modules\Security\Exceptions\DecryptionException;
use Modules\Security\Models\EncryptionKey;
use RuntimeException;

/**
 * Envelope encryption: Master Key cifra/decifra Data Encryption Keys (DEK), e cada
 * DEK cifra/decifra os dados de um "contexto" (ex.: mfa_totp_secret) com AES-256-GCM.
 *
 * Escopo desta fase: uma DEK ativa por contexto (+ tenant, quando aplicável), versão 1.
 * Rotação/versionamento avançado fica para a Fase 9 — a versão já viaja no bundle
 * cifrado para que a rotação futura não exija migrar dado existente de uma vez.
 *
 * @see docs/04-Seguranca.md
 */
class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    private const NONCE_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public function encrypt(string $plaintext, string $context, ?string $tenantId = null): string
    {
        $key = $this->activeDek($context, $tenantId);

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key->dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Falha ao cifrar valor.');
        }

        return implode('.', [
            base64_encode($nonce),
            base64_encode($ciphertext),
            base64_encode($tag),
            (string) $key->version,
        ]);
    }

    public function decrypt(string $bundle, string $context, ?string $tenantId = null): string
    {
        $parts = explode('.', $bundle);

        if (count($parts) !== 4) {
            throw new DecryptionException('Formato de bundle cifrado inválido.');
        }

        [$nonceB64, $ciphertextB64, $tagB64, $version] = $parts;

        $key = $this->dekForVersion($context, (int) $version, $tenantId);

        $plaintext = openssl_decrypt(
            base64_decode($ciphertextB64),
            self::CIPHER,
            $key->dek,
            OPENSSL_RAW_DATA,
            base64_decode($nonceB64),
            base64_decode($tagB64),
        );

        if ($plaintext === false) {
            throw new DecryptionException('Falha ao decifrar valor: dado inválido ou adulterado.');
        }

        return $plaintext;
    }

    /**
     * Retorna (criando se necessário) a DEK ativa de um contexto, já desembrulhada.
     */
    private function activeDek(string $context, ?string $tenantId): DecryptedKey
    {
        $record = EncryptionKey::query()
            ->where('context', $context)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if ($record === null) {
            $record = $this->createDek($context, $tenantId);
        }

        return new DecryptedKey(
            dek: $this->unwrap($record->wrapped_dek),
            version: $record->version,
        );
    }

    private function dekForVersion(string $context, int $version, ?string $tenantId): DecryptedKey
    {
        $record = EncryptionKey::query()
            ->where('context', $context)
            ->where('tenant_id', $tenantId)
            ->where('version', $version)
            ->first();

        if ($record === null) {
            throw new DecryptionException("DEK não encontrada para o contexto [{$context}] versão [{$version}].");
        }

        return new DecryptedKey(
            dek: $this->unwrap($record->wrapped_dek),
            version: $record->version,
        );
    }

    private function createDek(string $context, ?string $tenantId): EncryptionKey
    {
        $dek = random_bytes(32);

        return EncryptionKey::query()->create([
            'tenant_id' => $tenantId,
            'context' => $context,
            'version' => 1,
            'wrapped_dek' => $this->wrap($dek),
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    private function masterKey(): string
    {
        $masterKey = config('security.master_key');

        if (! is_string($masterKey) || $masterKey === '') {
            throw new RuntimeException(
                'ENCRYPTION_MASTER_KEY não configurada. Gere uma com: php artisan security:master-key:generate'
            );
        }

        $decoded = base64_decode($masterKey, strict: true);

        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('ENCRYPTION_MASTER_KEY inválida: esperado base64 de 32 bytes.');
        }

        return $decoded;
    }

    private function wrap(string $dek): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $wrapped = openssl_encrypt(
            $dek,
            self::CIPHER,
            $this->masterKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($wrapped === false) {
            throw new RuntimeException('Falha ao envelopar DEK.');
        }

        return implode('.', [base64_encode($nonce), base64_encode($wrapped), base64_encode($tag)]);
    }

    private function unwrap(string $wrappedBundle): string
    {
        $parts = explode('.', $wrappedBundle);

        if (count($parts) !== 3) {
            throw new DecryptionException('Formato de DEK envelopada inválido.');
        }

        [$nonceB64, $wrappedB64, $tagB64] = $parts;

        $dek = openssl_decrypt(
            base64_decode($wrappedB64),
            self::CIPHER,
            $this->masterKey(),
            OPENSSL_RAW_DATA,
            base64_decode($nonceB64),
            base64_decode($tagB64),
        );

        if ($dek === false) {
            throw new DecryptionException('Falha ao desembrulhar DEK: Master Key incorreta ou dado adulterado.');
        }

        return $dek;
    }
}
