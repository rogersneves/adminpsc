<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        // Requisição http (padrão nos testes): HSTS não deve ser emitido.
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        // Requisição https: HSTS presente.
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_headers_can_be_disabled_via_config(): void
    {
        config(['security.headers.enabled' => false]);

        $response = $this->get('/login');

        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('X-Content-Type-Options');
    }
}
