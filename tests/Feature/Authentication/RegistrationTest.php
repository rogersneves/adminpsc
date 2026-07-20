<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_creates_a_tenant_and_a_clinic_admin_user(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'tenant_name' => 'Clínica Bem-Estar',
            'name' => 'Ana Psicóloga',
            'email' => 'ana@example.com',
            'password' => 'Segredo-Forte-123',
            'password_confirmation' => 'Segredo-Forte-123',
        ]);

        $response->assertRedirect('/verify-email');

        $tenant = Tenant::query()->where('slug', 'clinica-bem-estar')->firstOrFail();
        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('admin_clinica'));
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);

        // Registro autentica o usuário imediatamente (ver ADR em RegisterClinicAdminAction),
        // mas ele ainda não passou pelo desafio de MFA de um login de verdade.
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'tenant_name' => 'Clínica X',
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => 'Segredo-Forte-123',
            'password_confirmation' => 'nao-bate',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'fulano@example.com']);
    }

    public function test_two_tenants_with_the_same_name_get_distinct_slugs(): void
    {
        $this->post('/register', [
            'tenant_name' => 'Clínica Popular',
            'name' => 'Primeiro',
            'email' => 'primeiro@example.com',
            'password' => 'Segredo-Forte-123',
            'password_confirmation' => 'Segredo-Forte-123',
        ]);

        $this->post('/logout');

        $this->post('/register', [
            'tenant_name' => 'Clínica Popular',
            'name' => 'Segundo',
            'email' => 'segundo@example.com',
            'password' => 'Segredo-Forte-123',
            'password_confirmation' => 'Segredo-Forte-123',
        ]);

        $this->assertSame(2, Tenant::query()->where('name', 'Clínica Popular')->count());
        $this->assertDatabaseHas('tenants', ['slug' => 'clinica-popular']);
        $this->assertDatabaseHas('tenants', ['slug' => 'clinica-popular-1']);
    }
}
