<?php

declare(strict_types=1);

namespace Modules\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Modules\Audit\Models\AuditLog;
use Modules\Users\Models\User;

class AuditLogger
{
    public function record(
        string $action,
        ?User $actor = null,
        ?Model $subject = null,
        array $changes = [],
        ?string $tenantId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'tenant_id' => $tenantId ?? $actor?->tenant_id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'created_at' => now(),
        ]);
    }
}
