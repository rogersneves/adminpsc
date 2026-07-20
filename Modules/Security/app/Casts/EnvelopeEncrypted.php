<?php

declare(strict_types=1);

namespace Modules\Security\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\Security\Services\EncryptionService;

/**
 * Cast reutilizável para qualquer campo que precise de envelope encryption
 * (AES-256-GCM). Uso: protected function casts(): array {
 *     return ['mfa_totp_secret' => EnvelopeEncrypted::class.':mfa_totp_secret'];
 * }
 *
 * O primeiro argumento é o "contexto" (nome lógico usado para escolher/gerar a DEK).
 * Reaproveitado pelos campos de PII a partir da Fase 2.
 */
class EnvelopeEncrypted implements CastsAttributes
{
    public function __construct(private readonly string $context) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return app(EncryptionService::class)->decrypt($value, $this->context);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return app(EncryptionService::class)->encrypt($value, $this->context);
    }
}
