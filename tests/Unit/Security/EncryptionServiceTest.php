<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Exceptions\DecryptionException;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Services\EncryptionService;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypt_then_decrypt_round_trip_returns_original_plaintext(): void
    {
        $service = app(EncryptionService::class);

        $bundle = $service->encrypt('S3gredoDoUsuario', 'test-context');

        $this->assertNotSame('S3gredoDoUsuario', $bundle);
        $this->assertSame('S3gredoDoUsuario', $service->decrypt($bundle, 'test-context'));
    }

    public function test_encrypting_twice_reuses_the_same_dek_version(): void
    {
        $service = app(EncryptionService::class);

        $service->encrypt('primeiro', 'reuse-context');
        $service->encrypt('segundo', 'reuse-context');

        $this->assertSame(1, EncryptionKey::query()->where('context', 'reuse-context')->count());
    }

    public function test_tampering_with_ciphertext_makes_decryption_fail(): void
    {
        $service = app(EncryptionService::class);

        $bundle = $service->encrypt('dado-sensivel', 'tamper-context');

        [$nonce, $ciphertext, $tag, $version] = explode('.', $bundle);
        $raw = base64_decode($ciphertext);
        $raw[0] = chr(ord($raw[0]) ^ 0xFF);
        $tampered = base64_encode($raw);

        $this->expectException(DecryptionException::class);

        $service->decrypt(implode('.', [$nonce, $tampered, $tag, $version]), 'tamper-context');
    }

    public function test_decrypting_with_wrong_context_fails(): void
    {
        $service = app(EncryptionService::class);

        $bundle = $service->encrypt('outro-dado', 'context-a');

        $this->expectException(DecryptionException::class);

        $service->decrypt($bundle, 'context-b');
    }
}
