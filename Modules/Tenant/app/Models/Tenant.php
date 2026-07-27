<?php

declare(strict_types=1);

namespace Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Tenant\Database\Factories\TenantFactory;

class Tenant extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'status',
        'trial_ends_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Configuração do plano atual (config/plans.php), com fallback para o plano
     * default se o identificador guardado não existir mais no catálogo.
     */
    public function planConfig(): array
    {
        $plans = config('plans.plans');

        return $plans[$this->plan] ?? $plans[config('plans.default')];
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function trialExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }
}
