<?php

declare(strict_types=1);

namespace Modules\Security\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\Security\Services\EncryptionService;

/**
 * Como EnvelopeEncrypted, mas para valores estruturados (arrays) — ex.: telefones,
 * contatos de recado. Serializa para JSON antes de cifrar, desserializa depois de
 * decifrar. Reaproveita o mesmo EncryptionService, sem duplicar a lógica de envelope.
 */
class EncryptedJson implements CastsAttributes
{
    public function __construct(private readonly string $context) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $json = app(EncryptionService::class)->decrypt($value, $this->context);

        return json_decode($json, associative: true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return app(EncryptionService::class)->encrypt(json_encode($value), $this->context);
    }
}
