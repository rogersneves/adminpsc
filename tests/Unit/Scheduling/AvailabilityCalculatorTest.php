<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Scheduling\Services\AvailabilityCalculator;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class AvailabilityCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makePsychologist(int $defaultDuration = 50): Psychologist
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => $defaultDuration,
        ]);
    }

    private function makePatient(string $tenantId): \Modules\Patients\Models\Patient
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);

        return \Modules\Patients\Models\Patient::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function test_recurring_rule_generates_slots_on_matching_weekday(): void
    {
        $psychologist = $this->makePsychologist();

        $nextMonday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => 1, // Carbon: 1 = Monday
            'start_time' => '09:00',
            'end_time' => '10:00',
            'buffer_minutes' => 0,
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $nextMonday, $nextMonday);

        $this->assertArrayHasKey($nextMonday->toDateString(), $slots);
        $this->assertCount(1, $slots[$nextMonday->toDateString()]);
        $this->assertSame('09:00', $slots[$nextMonday->toDateString()][0]['starts_at']->format('H:i'));
    }

    public function test_particular_rule_adds_a_one_off_day(): void
    {
        $psychologist = $this->makePsychologist();
        $specificDate = CarbonImmutable::now()->addDays(10);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'particular',
            'date' => $specificDate->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'buffer_minutes' => 0,
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $specificDate, $specificDate);

        $this->assertArrayHasKey($specificDate->toDateString(), $slots);
    }

    public function test_bloqueio_removes_part_of_a_recurring_day(): void
    {
        $psychologist = $this->makePsychologist(60);
        $nextMonday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'buffer_minutes' => 0,
        ]);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'bloqueio',
            'date' => $nextMonday->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $nextMonday, $nextMonday);
        $starts = collect($slots[$nextMonday->toDateString()])->map(fn ($s) => $s['starts_at']->format('H:i'))->all();

        $this->assertSame(['09:00', '11:00'], $starts);
    }

    public function test_feriado_removes_the_entire_day(): void
    {
        $psychologist = $this->makePsychologist();
        $nextMonday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'buffer_minutes' => 0,
        ]);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'feriado',
            'date' => $nextMonday->toDateString(),
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $nextMonday, $nextMonday);

        $this->assertArrayNotHasKey($nextMonday->toDateString(), $slots);
    }

    public function test_buffer_minutes_spaces_out_consecutive_slots(): void
    {
        $psychologist = $this->makePsychologist(50);
        $nextMonday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'buffer_minutes' => 10,
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $nextMonday, $nextMonday);
        $starts = collect($slots[$nextMonday->toDateString()])->map(fn ($s) => $s['starts_at']->format('H:i'))->all();

        // 09:00-09:50, buffer 10 -> next starts 10:00-10:50; 11:00 would need 50min ending at 11:50 > 11:00 window end
        $this->assertSame(['09:00', '10:00'], $starts);
    }

    public function test_booked_session_removes_that_slot(): void
    {
        $psychologist = $this->makePsychologist(60);
        $nextMonday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'buffer_minutes' => 0,
        ]);

        $patient = $this->makePatient($psychologist->tenant_id);

        Session::query()->create([
            'tenant_id' => $psychologist->tenant_id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => $nextMonday->setTime(9, 0),
            'duration_minutes' => 60,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Agendada,
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $nextMonday, $nextMonday);
        $starts = collect($slots[$nextMonday->toDateString()])->map(fn ($s) => $s['starts_at']->format('H:i'))->all();

        $this->assertSame(['10:00'], $starts);
    }

    public function test_cancelled_session_does_not_block_the_slot(): void
    {
        $psychologist = $this->makePsychologist(60);
        $nextMonday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'buffer_minutes' => 0,
        ]);

        $patient = $this->makePatient($psychologist->tenant_id);

        Session::query()->create([
            'tenant_id' => $psychologist->tenant_id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => $nextMonday->setTime(9, 0),
            'duration_minutes' => 60,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Cancelada,
        ]);

        $slots = (new AvailabilityCalculator)->availableSlots($psychologist, $nextMonday, $nextMonday);

        $this->assertCount(1, $slots[$nextMonday->toDateString()]);
    }
}
