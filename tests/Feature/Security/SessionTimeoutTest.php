<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Models\User;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_past_the_absolute_timeout_is_logged_out(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'auth.login_at' => now()->subMinutes((int) config('security.session.absolute_minutes') + 1),
                'auth.last_activity' => now(),
            ])
            ->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_session_past_the_inactivity_timeout_is_logged_out(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'auth.login_at' => now(),
                'auth.last_activity' => now()->subMinutes((int) config('security.session.inactivity_minutes') + 1),
            ])
            ->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_session_within_limits_stays_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'auth.login_at' => now()->subMinutes(5),
                'auth.last_activity' => now()->subMinutes(1),
            ])
            ->get('/dashboard');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
