<?php

declare(strict_types=1);

namespace Tests\Feature\Units;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Settings\Models\Unit;
use Modules\Settings\Services\UnitScope;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class SecretaryScopeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin_clinica');

        return $user;
    }

    public function test_admin_invites_a_secretary_bound_to_units(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'name' => 'Centro', 'is_active' => true]);

        $this->actingAs($this->admin($tenant))->post('/secretarias', [
            'name' => 'Sec Ana',
            'email' => 'ana@example.com',
            'unit_ids' => [$unit->id],
        ])->assertRedirect();

        $secretary = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($secretary);
        $this->assertTrue($secretary->hasRole('secretaria'));
        $this->assertTrue($secretary->units->contains($unit->id));
    }

    public function test_unit_scope_is_null_for_admin_and_limited_for_secretary(): void
    {
        $tenant = Tenant::factory()->create();
        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'name' => 'Centro', 'is_active' => true]);

        $admin = $this->admin($tenant);
        $this->assertNull(app(UnitScope::class)->unitIdsFor($admin));

        $secretary = User::factory()->create(['tenant_id' => $tenant->id]);
        $secretary->assignRole('secretaria');
        $secretary->units()->sync([$unit->id]);

        $this->assertSame([$unit->id], app(UnitScope::class)->unitIdsFor($secretary->fresh()));
    }

    public function test_secretary_only_sees_sessions_from_own_units(): void
    {
        $tenant = Tenant::factory()->create();
        $unitA = Unit::query()->create(['tenant_id' => $tenant->id, 'name' => 'A', 'is_active' => true]);
        $unitB = Unit::query()->create(['tenant_id' => $tenant->id, 'name' => 'B', 'is_active' => true]);

        $psyUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $psyUser->id,
            'professional_registry_encrypted' => 'CRP', 'specialties' => [], 'default_session_duration_minutes' => 50,
        ]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'display_name' => 'P', 'email' => 'p@example.com',
        ]);

        $makeSession = fn (Unit $unit) => Session::query()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'psychologist_id' => $psychologist->id,
            'unit_id' => $unit->id, 'scheduled_at' => CarbonImmutable::now()->addDay(),
            'duration_minutes' => 50, 'modality' => SessionModality::Online, 'status' => SessionStatus::Agendada,
        ]);
        $sessionA = $makeSession($unitA);
        $makeSession($unitB);

        $secretary = User::factory()->create(['tenant_id' => $tenant->id]);
        $secretary->assignRole('secretaria');
        $secretary->units()->sync([$unitA->id]);

        $this->actingAs($secretary)
            ->get('/agenda-unidade')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scheduling/UnitAgenda')
                ->where('scoped', true)
                ->has('sessions', 1)
                ->where('sessions.0.id', $sessionA->id));
    }

    public function test_admin_sees_all_units_in_unit_agenda(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs($this->admin($tenant))
            ->get('/agenda-unidade')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('scoped', false));
    }

    public function test_secretary_invite_requires_manage_users(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');

        $this->actingAs($psychologist)->get('/secretarias')->assertForbidden();
    }

    public function test_booking_stamps_the_psychologist_unit_on_the_session(): void
    {
        $tenant = Tenant::factory()->create();
        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'name' => 'Centro', 'is_active' => true]);

        $psyUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psyUser->assignRole('psicologo');
        $psyUser->units()->sync([$unit->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $psyUser->id,
            'professional_registry_encrypted' => 'CRP', 'specialties' => [], 'default_session_duration_minutes' => 50,
        ]);

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $patientUser->id,
            'display_name' => $patientUser->name, 'email' => $patientUser->email,
        ]);

        $psychologist->availabilities()->create([
            'tenant_id' => $tenant->id, 'type' => 'recorrente',
            'weekday' => CarbonImmutable::now()->next()->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:00', 'buffer_minutes' => 0,
        ]);
        $slotStart = CarbonImmutable::now()->next()->setTime(9, 0);

        $this->actingAs($patientUser)->post("/agenda/{$psychologist->id}/reservar", [
            'scheduled_at' => $slotStart->toIso8601String(),
            'duration_minutes' => 50,
            'modality' => 'online',
        ])->assertRedirect('/minhas-sessoes');

        $this->assertDatabaseHas('clinical_sessions', [
            'psychologist_id' => $psychologist->id,
            'unit_id' => $unit->id,
        ]);
    }
}
