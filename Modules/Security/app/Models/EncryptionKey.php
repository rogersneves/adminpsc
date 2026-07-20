<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUuidPrimaryKey;

class EncryptionKey extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'context',
        'version',
        'wrapped_dek',
        'status',
        'activated_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
