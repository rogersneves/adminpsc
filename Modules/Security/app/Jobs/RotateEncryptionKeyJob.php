<?php

declare(strict_types=1);

namespace Modules\Security\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Security\Casts\EncryptedJson;
use Modules\Security\Casts\EnvelopeEncrypted;

/**
 * Recifra em background todos os registros de um contexto para a DEK ativa mais
 * recente (docs/04-Seguranca.md). Rodar DEPOIS de `EncryptionService::rotate()`:
 * a rotação torna a nova versão ativa, este Job migra o dado antigo sem downtime
 * (dado ainda não migrado continua legível pela DEK aposentada).
 *
 * O contexto → Model vem de config('security.encryption_contexts'); os atributos
 * são descobertos via getCasts() do Model, então só recifra o(s) atributo(s) que
 * pertencem exatamente a este contexto (um Model como Patient tem vários contextos
 * independentes). Idempotente na prática: reexecutar só regera nonces.
 */
class RotateEncryptionKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $context) {}

    public function handle(): void
    {
        $modelClass = config("security.encryption_contexts.{$this->context}");

        if ($modelClass === null || ! class_exists($modelClass)) {
            return;
        }

        /** @var Model $model */
        $model = new $modelClass;

        $attributes = $this->attributesForContext($model);

        if ($attributes === []) {
            return;
        }

        $query = $modelClass::query()->withoutGlobalScopes();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        $query->chunkById(200, function ($records) use ($attributes): void {
            foreach ($records as $record) {
                $this->reencrypt($record, $attributes);
            }
        });
    }

    /**
     * Nomes de atributo cujo cast é envelope encryption PARA ESTE contexto.
     */
    private function attributesForContext(Model $model): array
    {
        $matches = [];

        foreach ($model->getCasts() as $attribute => $cast) {
            if (! is_string($cast)) {
                continue;
            }

            $isEnvelope = str_starts_with($cast, EnvelopeEncrypted::class.':')
                || str_starts_with($cast, EncryptedJson::class.':');

            if ($isEnvelope && str_ends_with($cast, ':'.$this->context)) {
                $matches[] = $attribute;
            }
        }

        return $matches;
    }

    private function reencrypt(Model $record, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $value = $record->getAttribute($attribute); // decifra (versão antiga)

            if ($value === null || $value === '') {
                continue;
            }

            $record->setAttribute($attribute, $value); // recifra (versão ativa)
        }

        if ($record->isDirty($attributes)) {
            $record->saveQuietly();
        }
    }
}
