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
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
