<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Modules\Authentication\DTOs\LoginAttemptData;
use Modules\Authentication\Enums\MfaMethod;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Notifications\MfaEmailOtpNotification;
use Modules\Users\Models\User;

/**
 * Valida a senha mas NÃO autentica o guard ainda — todo login exige o segundo
 * fator antes de Auth::login() (docs/04-Seguranca.md: "todo novo login exige MFA,
 * sem exceção"). O estado "aguardando MFA" fica na sessão, não no guard.
 */
class AttemptLoginAction
{
    public function __invoke(LoginAttemptData $data): MfaMethod
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            event(new Failed('web', $user, ['email' => $data->email]));

            throw new InvalidCredentialsException('Credenciais inválidas.');
        }

        session(['mfa.pending_user_id' => $user->id]);

        $method = $user->mfa_enabled_at !== null ? MfaMethod::Totp : MfaMethod::EmailOtp;

        session(['mfa.method' => $method->value]);

        if ($method === MfaMethod::EmailOtp) {
            $this->sendEmailOtp($user);
        }

        return $method;
    }

    public function sendEmailOtp(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('security.mfa.email_otp_ttl_minutes');

        Cache::put(
            $this->cacheKey($user->id),
            ['hash' => Hash::make($code), 'attempts' => 0],
            now()->addMinutes($ttl),
        );

        $user->notify(new MfaEmailOtpNotification($code, $ttl));
    }

    public function cacheKey(string $userId): string
    {
        return "mfa-otp:{$userId}";
    }
}
