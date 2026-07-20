<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Modules\Authentication\DTOs\TotpSetup;
use Modules\Users\Models\User;

/**
 * Gera um novo secret TOTP e o guarda cifrado, mas NÃO ativa o MFA por TOTP ainda —
 * só depois que o usuário confirmar um código válido (ConfirmTotpAction), para não
 * travar a conta com um secret que o usuário não conseguiu configurar direito.
 */
class EnableTotpAction
{
    public function __invoke(User $user): TotpSetup
    {
        $google2fa = app('pragmarx.google2fa');

        $secret = $google2fa->generateSecretKey();

        $user->mfa_totp_secret = $secret;
        $user->save();

        $uri = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return new TotpSetup(secret: $secret, otpauthUri: $uri);
    }
}
