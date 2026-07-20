<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\Services\AuditLogger;
use Modules\Authentication\Enums\MfaMethod;
use Modules\Authentication\Exceptions\InvalidMfaChallengeException;
use Modules\Users\Models\User;

class VerifyMfaChallengeAction
{
    public function __construct(
        private readonly AttemptLoginAction $attemptLogin,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function __invoke(User $user, MfaMethod $method, string $code): void
    {
        try {
            match ($method) {
                MfaMethod::Totp => $this->verifyTotp($user, $code),
                MfaMethod::EmailOtp => $this->verifyEmailOtp($user, $code),
            };
        } catch (InvalidMfaChallengeException $exception) {
            $this->auditLogger->record('auth_failure', actor: $user, changes: [
                'reason' => 'invalid_mfa_code',
                'method' => $method->value,
            ]);

            throw $exception;
        }

        Auth::login($user);

        request()->session()->regenerate();
        request()->session()->forget(['mfa.pending_user_id', 'mfa.method']);
        request()->session()->put('auth.login_at', now());
        request()->session()->put('auth.last_activity', now());
    }

    private function verifyTotp(User $user, string $code): void
    {
        $google2fa = app('pragmarx.google2fa');

        if ($user->mfa_totp_secret === null || $google2fa->verifyKey($user->mfa_totp_secret, $code) === false) {
            throw new InvalidMfaChallengeException('Código TOTP inválido.');
        }
    }

    private function verifyEmailOtp(User $user, string $code): void
    {
        $key = $this->attemptLogin->cacheKey($user->id);
        $challenge = Cache::get($key);

        if ($challenge === null) {
            throw new InvalidMfaChallengeException('Código expirado. Solicite um novo.');
        }

        if (! Hash::check($code, $challenge['hash'])) {
            Cache::put($key, [...$challenge, 'attempts' => $challenge['attempts'] + 1], now()->addMinutes(10));

            throw new InvalidMfaChallengeException('Código inválido.');
        }

        Cache::forget($key);
    }
}
