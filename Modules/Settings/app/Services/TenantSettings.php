<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Illuminate\Support\Arr;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Configuração por tenant (Fase 11). Lê de `tenants.settings` (JSON) com fallback
 * para o config global — um tenant que nunca mexeu numa chave herda o default do
 * `config/*`. Fecha pendências antigas ("timeout de sessão / antecedência mínima
 * configurável por tenant", Fases 1/3) sem duplicar os valores no banco quando o
 * tenant usa o padrão.
 */
class TenantSettings
{
    /**
     * Chaves configuráveis: dot-key => [config de fallback, cast, grupo, label].
     * `null` de fallback resolve para um default fixo tratado em resolveDefault().
     */
    public const REGISTRY = [
        'scheduling.booking_horizon_days' => ['scheduling.booking_horizon_days', 'int', 'agenda', 'Horizonte de reserva (dias)'],
        'scheduling.minimum_reschedule_notice_hours' => ['scheduling.minimum_reschedule_notice_hours', 'int', 'agenda', 'Antecedência mínima p/ cancelar/reagendar (h)'],
        'branding.display_name' => [null, 'string', 'marca', 'Nome de exibição'],
        'branding.primary_color' => [null, 'string', 'marca', 'Cor primária'],
    ];

    public function get(Tenant $tenant, string $key): mixed
    {
        if (! array_key_exists($key, self::REGISTRY)) {
            return null;
        }

        $stored = Arr::get($tenant->settings ?? [], $key);
        $value = $stored ?? $this->resolveDefault($tenant, $key);

        return $this->cast($value, self::REGISTRY[$key][1]);
    }

    /**
     * Valor de uma chave para o tenant da requisição atual (conveniência para
     * consumidores em contexto web com resolve.tenant). Fora de tenant resolvido,
     * cai no default global.
     */
    public function current(string $key): mixed
    {
        $tenant = app(CurrentTenant::class)->get();

        if ($tenant === null) {
            return $this->cast($this->resolveDefault(null, $key), self::REGISTRY[$key][1] ?? 'string');
        }

        return $this->get($tenant, $key);
    }

    /**
     * Todas as chaves resolvidas (para a tela de configuração), agrupadas.
     *
     * @return array<string, mixed>
     */
    public function all(Tenant $tenant): array
    {
        $out = [];
        foreach (array_keys(self::REGISTRY) as $key) {
            $out[$key] = $this->get($tenant, $key);
        }

        return $out;
    }

    /**
     * Grava um subconjunto de chaves conhecidas no JSON do tenant (ignora chaves
     * fora do registro). Preserva as demais chaves de settings.
     */
    public function set(Tenant $tenant, array $values): void
    {
        $settings = $tenant->settings ?? [];

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::REGISTRY)) {
                continue;
            }

            Arr::set($settings, $key, $this->cast($value, self::REGISTRY[$key][1]));
        }

        $tenant->update(['settings' => $settings]);
    }

    private function resolveDefault(?Tenant $tenant, string $key): mixed
    {
        $configKey = self::REGISTRY[$key][0] ?? null;

        if ($configKey !== null) {
            return config($configKey);
        }

        return match ($key) {
            'branding.display_name' => $tenant?->name ?? config('app.name'),
            // Petróleo da identidade AdminPSC (docs/05); o tenant pode sobrescrever.
            'branding.primary_color' => '#2d5b7a',
            default => null,
        };
    }

    private function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            default => (string) $value,
        };
    }
}
