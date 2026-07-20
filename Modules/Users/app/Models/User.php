<?php

declare(strict_types=1);

namespace Modules\Users\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Security\Casts\EnvelopeEncrypted;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Database\Factories\UserFactory;
use Spatie\Permission\Traits\HasRoles;

/**
 * Não usa BelongsToTenant/TenantScope de propósito: login precisa localizar um
 * usuário pelo e-mail antes de qualquer tenant estar resolvido. Ver nota em
 * docs/01-Arquitetura.md sobre essa exceção deliberada ao isolamento por tenant.
 */
#[Fillable(['name', 'email', 'password', 'tenant_id', 'preferred_locale'])]
#[Hidden(['password', 'remember_token', 'mfa_totp_secret'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuidPrimaryKey, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'password' => 'hashed',
            'mfa_totp_secret' => EnvelopeEncrypted::class.':mfa_totp_secret',
        ];
    }
}
