<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Authentication\Notifications\MfaEmailOtpNotification;
use Modules\Users\Models\User;
use Tests\TestCase;

class LoginMfaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_do_not_authenticate_the_guard_yet(): void
    {
        $user = User::factory()->create(['password' => 'Segredo-Forte-123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Segredo-Forte-123',
        ]);

        $response->assertRedirect('/mfa/challenge');
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected_without_starting_an_mfa_challenge(): void
    {
        $user = User::factory()->create(['password' => 'Segredo-Forte-123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull(session('mfa.pending_user_id'));
    }

    public function test_correct_email_otp_completes_the_login(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'Segredo-Forte-123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Segredo-Forte-123',
        ]);

        $code = null;
        Notification::assertSentTo($user, MfaEmailOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $response = $this->post('/mfa/challenge', ['code' => $code]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_email_otp_fails_and_keeps_the_user_unauthenticated(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'Segredo-Forte-123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Segredo-Forte-123',
        ]);

        $response = $this->post('/mfa/challenge', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth_failure']);
    }

    public function test_totp_enabled_user_is_challenged_with_totp_instead_of_email(): void
    {
        Notification::fake();

        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create(['password' => 'Segredo-Forte-123']);
        $user->mfa_totp_secret = $secret;
        $user->mfa_enabled_at = now();
        $user->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Segredo-Forte-123',
        ]);

        Notification::assertNothingSent();

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->post('/mfa/challenge', ['code' => $validCode]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
