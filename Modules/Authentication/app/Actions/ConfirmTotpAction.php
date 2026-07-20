<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Modules\Authentication\Exceptions\InvalidMfaChallengeException;
use Modules\Users\Models\User;

/**
 * Só depois de um código válido é que o TOTP vira o método de MFA do usuário
 * (mfa_enabled_at setado) — evita travar a conta com um secret mal configurado.
 */
class ConfirmTotpAction
{
    public function __invoke(User $user, string $code): void
    {
        $google2fa = app('pragmarx.google2fa');

        if ($user->mfa_totp_secret === null) {
            throw new InvalidMfaChallengeException('Nenhum secret TOTP pendente de confirmação.');
        }

        if ($google2fa->verifyKey($user->mfa_totp_secret, $code) === false) {
            throw new InvalidMfaChallengeException('Código TOTP inválido.');
        }

        $user->mfa_enabled_at = now();
        $user->save();
    }
}
