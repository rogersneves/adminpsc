<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CMS\Services\HtmlSanitizer;
use Tests\TestCase;

/**
 * Cobre diretamente o serviço de sanitização — a barreira de defense-in-depth contra
 * stored XSS na renderização pública das páginas do CMS.
 */
class HtmlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_removes_script_tags_entirely(): void
    {
        $out = $this->sanitizer->sanitizeHtml('<div>ok</div><script>alert(1)</script>');

        $this->assertStringNotContainsString('script', $out);
        $this->assertStringContainsString('<div>ok</div>', $out);
    }

    public function test_removes_inline_event_handlers(): void
    {
        $out = $this->sanitizer->sanitizeHtml('<button onclick="steal()">x</button>');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('<button', $out);
    }

    public function test_strips_javascript_urls_but_keeps_safe_ones(): void
    {
        $out = $this->sanitizer->sanitizeHtml(
            '<a href="javascript:alert(1)">a</a><a href="https://ok.com">b</a>'
        );

        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringContainsString('https://ok.com', $out);
    }

    public function test_preserves_classes_ids_and_inline_styles(): void
    {
        $html = '<section class="hero" id="top" style="padding:20px;color:#111">Oi</section>';
        $out = $this->sanitizer->sanitizeHtml($html);

        $this->assertStringContainsString('class="hero"', $out);
        $this->assertStringContainsString('id="top"', $out);
        $this->assertStringContainsString('padding:20px', $out);
    }

    public function test_unwraps_unknown_tags_but_keeps_their_text(): void
    {
        $out = $this->sanitizer->sanitizeHtml('<blink>importante</blink>');

        $this->assertStringNotContainsString('blink', $out);
        $this->assertStringContainsString('importante', $out);
    }

    public function test_removes_iframes(): void
    {
        $out = $this->sanitizer->sanitizeHtml('<iframe src="https://evil"></iframe><p>ok</p>');

        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringContainsString('<p>ok</p>', $out);
    }

    public function test_keeps_data_image_uris_but_drops_data_html(): void
    {
        $keep = $this->sanitizer->sanitizeHtml('<img src="data:image/png;base64,AAAA">');
        $this->assertStringContainsString('data:image/png', $keep);

        $drop = $this->sanitizer->sanitizeHtml('<a href="data:text/html,<script>">x</a>');
        $this->assertStringNotContainsString('data:text/html', $drop);
    }

    public function test_css_strips_import_and_expression_and_javascript(): void
    {
        $out = $this->sanitizer->sanitizeCss(
            '@import url(evil.css); body{color:red} a{background:expression(alert(1))} b{color:javascript:x}'
        );

        $this->assertStringNotContainsString('@import', $out);
        $this->assertStringNotContainsString('expression(', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringContainsString('color:red', $out);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer->sanitizeHtml(null));
        $this->assertSame('', $this->sanitizer->sanitizeCss(''));
    }
}
