<?php

declare(strict_types=1);

namespace Tests\Feature\Psychologists;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Modules\Psychologists\Models\Psychologist;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PsychologistRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_clinica_can_register_a_psychologist(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $response = $this->actingAs($admin)->post('/psicologos', [
            'name' => 'Dr. João',
            'email' => 'joao@example.com',
            'professional_registry' => 'CRP 06/12345',
            'specialties' => ['TCC', 'Terapia de casal'],
            'default_session_duration_minutes' => 45,
        ]);

        $response->assertRedirect('/psicologos');

        $user = User::query()->where('email', 'joao@example.com')->firstOrFail();
        $psychologist = Psychologist::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('psicologo'));
        $this->assertSame(45, $psychologist->default_session_duration_minutes);
        $this->assertSame('CRP 06/12345', $psychologist->professional_registry_encrypted);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_without_manage_users_permission_cannot_register_a_psychologist(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');

        $response = $this->actingAs($psychologistUser)->post('/psicologos', [
            'name' => 'Dr. Outro',
            'email' => 'outro@example.com',
            'professional_registry' => 'CRP 06/54321',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'outro@example.com']);
    }
}
