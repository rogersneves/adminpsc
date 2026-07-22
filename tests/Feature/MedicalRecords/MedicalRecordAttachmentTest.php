<?php

declare(strict_types=1);

namespace Tests\Feature\MedicalRecords;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\MedicalRecords\Models\MedicalRecordAttachment;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class MedicalRecordAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTreatingPair(): array
    {
        $tenant = Tenant::factory()->create();

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Realizada,
        ]);

        return compact('tenant', 'psychologistUser', 'psychologist', 'patientUser', 'patient');
    }

    public function test_uploading_an_attachment_encrypts_its_content_on_disk(): void
    {
        Storage::fake('local');

        ['psychologistUser' => $psychologistUser, 'patient' => $patient] = $this->makeTreatingPair();

        $file = UploadedFile::fake()->createWithContent('laudo.txt', 'Conteúdo sensível do laudo clínico.');

        $response = $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Sessão com laudo anexado',
            'attachment' => $file,
        ]);

        $response->assertRedirect();

        $attachment = MedicalRecordAttachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('laudo.txt', $attachment->original_filename_encrypted);

        $rawRow = DB::table('medical_record_attachments')->first();
        $this->assertStringNotContainsString('laudo.txt', $rawRow->original_filename_encrypted);

        $rawFileContent = Storage::disk('local')->get($attachment->file_path_encrypted);
        $this->assertStringNotContainsString('Conteúdo sensível do laudo clínico.', $rawFileContent);
    }

    public function test_downloading_an_attachment_returns_the_original_decrypted_content(): void
    {
        Storage::fake('local');

        ['psychologistUser' => $psychologistUser, 'patient' => $patient] = $this->makeTreatingPair();

        $file = UploadedFile::fake()->createWithContent('laudo.txt', 'Conteúdo original do arquivo.');

        $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Nota',
            'attachment' => $file,
        ]);

        $attachment = MedicalRecordAttachment::query()->first();

        $response = $this->actingAs($psychologistUser)->get("/prontuario/anexos/{$attachment->id}/download");

        $response->assertOk();
        $response->assertSee('Conteúdo original do arquivo.', false);
        $response->assertHeader('Content-Disposition', 'attachment; filename="laudo.txt"');
    }

    public function test_psychologist_without_access_to_the_patient_cannot_download_the_attachment(): void
    {
        Storage::fake('local');

        ['psychologistUser' => $owner, 'patient' => $patient, 'tenant' => $tenant] = $this->makeTreatingPair();

        $file = UploadedFile::fake()->createWithContent('laudo.txt', 'Sigiloso.');
        $this->actingAs($owner)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Nota',
            'attachment' => $file,
        ]);
        $attachment = MedicalRecordAttachment::query()->first();

        $otherPsychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherPsychologistUser->assignRole('psicologo');
        Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $otherPsychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/11111',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $response = $this->actingAs($otherPsychologistUser)->get("/prontuario/anexos/{$attachment->id}/download");

        $response->assertForbidden();
    }
}
