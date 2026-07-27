<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Services\EncryptionService;
use Tests\TestCase;

class EncryptionKeyRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_retires_the_active_key_and_activates_the_next_version(): void
    {
        $service = app(EncryptionService::class);
        $service->encrypt('algo', 'rot-context'); // cria a v1 ativa

        $new = $service->rotate('rot-context');

        $this->assertSame(2, $new->version);
        $this->assertSame('active', $new->status);

        $v1 = EncryptionKey::query()->where('context', 'rot-context')->where('version', 1)->first();
        $this->assertSame('retired', $v1->status);
        $this->assertNotNull($v1->retired_at);

        $this->assertSame(1, EncryptionKey::query()
            ->where('context', 'rot-context')->where('status', 'active')->count());
    }

    public function test_data_encrypted_before_rotation_still_decrypts_after(): void
    {
        $service = app(EncryptionService::class);

        $bundle = $service->encrypt('segredo-antigo', 'rot-context');
        $this->assertStringEndsWith('.1', $bundle);

        $service->rotate('rot-context');

        $this->assertSame('segredo-antigo', $service->decrypt($bundle, 'rot-context'));
    }

    public function test_new_encryption_after_rotation_uses_the_new_version(): void
    {
        $service = app(EncryptionService::class);
        $service->encrypt('v1', 'rot-context');

        $service->rotate('rot-context');

        $bundle = $service->encrypt('v2', 'rot-context');
        $this->assertStringEndsWith('.2', $bundle);
        $this->assertSame('v2', $service->decrypt($bundle, 'rot-context'));
    }

    public function test_rotating_a_context_with_no_key_yet_just_creates_version_one(): void
    {
        $service = app(EncryptionService::class);

        $key = $service->rotate('never-used-context');

        $this->assertSame(1, $key->version);
        $this->assertSame('active', $key->status);
    }
}
