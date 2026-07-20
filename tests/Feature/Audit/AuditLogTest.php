<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Audit\Models\AuditLog;
use Modules\Users\Models\User;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_attempt_is_recorded_in_the_audit_log(): void
    {
        $user = User::factory()->create(['email' => 'auditee@example.com']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth_failure',
        ]);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = AuditLog::query()->create(['action' => 'login', 'created_at' => now()]);

        $this->expectException(LogicException::class);

        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = AuditLog::query()->create(['action' => 'login', 'created_at' => now()]);

        $this->expectException(LogicException::class);

        $log->delete();
    }
}
