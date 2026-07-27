<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitização defense-in-depth do HTML/CSS produzido pelo editor visual (GrapesJS)
 * antes de persistir/servir publicamente.
 *
 * O usuário final edita por blocos, não digita HTML cru (requisito da Fase 8), então
 * a superfície de ataque é limitada — mas conteúdo gerado que depois é servido em
 * `<div>{!! $html !!}</div>` numa página pública ainda é um vetor clássico de stored
 * XSS (admin comprometido, bug futuro no editor, import de template externo). Este
 * serviço remove o que é perigoso preservando a fidelidade do GrapesJS (classes, ids
 * e estilos inline que casam com o blob de CSS gerado à parte).
 *
 * Abordagem: allowlist de tags + allowlist de atributos via DOMDocument (mais seguro
 * que regex). Tags fora da lista são "desembrulhadas" (filhos preservados); tags
 * perigosas são removidas com todo o conteúdo.
 */
class HtmlSanitizer
{
    /** Tags permitidas no HTML de uma página pública. */
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'address', 'article', 'aside', 'b', 'blockquote', 'br', 'button',
        'caption', 'cite', 'code', 'col', 'colgroup', 'dd', 'div', 'dl', 'dt', 'em',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4',
        'h5', 'h6', 'header', 'hr', 'i', 'img', 'input', 'label', 'legend', 'li', 'main',
        'mark', 'nav', 'ol', 'optgroup', 'option', 'p', 'picture', 'pre', 'section',
        'select', 'small', 'source', 'span', 'strong', 'sub', 'sup', 'table', 'tbody',
        'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'tr', 'u', 'ul', 'video',
    ];

    /** Tags removidas com todo o conteúdo (nunca desembrulhadas). */
    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'base', 'link',
        'meta', 'noscript', 'template', 'title', 'head', 'frame', 'frameset',
    ];

    /** Atributos permitidos em qualquer elemento. */
    private const ALLOWED_ATTRS = [
        'class', 'id', 'style', 'title', 'alt', 'href', 'src', 'srcset', 'sizes',
        'width', 'height', 'target', 'rel', 'type', 'name', 'value', 'placeholder',
        'for', 'colspan', 'rowspan', 'datetime', 'aria-label', 'aria-hidden',
        'aria-describedby', 'role', 'poster', 'controls', 'loading', 'disabled',
        'readonly', 'required', 'checked', 'selected', 'rows', 'cols', 'maxlength',
        'min', 'max', 'step', 'autocomplete', 'method', 'action',
    ];

    public function sanitizeHtml(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        // Encapsula num nó raiz conhecido e força UTF-8; suprime warnings de HTML5.
        $wrapped = '<?xml encoding="UTF-8"><div id="__cms_root__">'.$html.'</div>';

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__cms_root__');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * CSS gerado pelo GrapesJS. Remove diretivas capazes de exfiltrar/executar
     * (`@import`, `expression()`, `url(javascript:...)`) e qualquer tentativa de
     * quebrar fora de um `<style>` na renderização.
     */
    public function sanitizeCss(?string $css): string
    {
        $css = trim((string) $css);

        if ($css === '') {
            return '';
        }

        $patterns = [
            '/@import\b[^;]*;?/i',
            '/expression\s*\(/i',
            '/javascript\s*:/i',
            '/<\s*\/?\s*style/i',
            '/<\s*!\s*--/i',
        ];

        $css = preg_replace($patterns, '', $css) ?? '';

        // Neutraliza url(javascript:...) que sobreviva à troca acima.
        $css = preg_replace_callback('/url\s*\(([^)]*)\)/i', function (array $m): string {
            $inner = trim($m[1], " \t\n\r\"'");
            if (preg_match('/^(javascript|vbscript|data:text\/html)/i', $inner)) {
                return 'url()';
            }

            return $m[0];
        }, $css) ?? '';

        return trim($css);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Cópia estática: vamos mutar a lista durante a iteração.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue; // texto/comentário: DOMDocument não serializa <script> como texto executável
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DANGEROUS_TAGS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            // Limpa filhos antes de decidir o destino do próprio nó.
            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->cleanAttributes($child);
        }
    }

    private function cleanAttributes(DOMElement $el): void
    {
        /** @var DOMAttr $attr */
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue ?? '';

            // Qualquer handler de evento inline.
            if (str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if (! in_array($name, self::ALLOWED_ATTRS, true)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src', 'action', 'poster', 'formaction'], true)
                && $this->isDangerousUrl($value)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if ($name === 'style' && $this->hasDangerousStyle($value)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if ($name === 'srcset' && $this->isDangerousUrl($value)) {
                $el->removeAttribute($attr->nodeName);
            }
        }
    }

    private function isDangerousUrl(string $value): bool
    {
        $normalized = strtolower(trim($value));
        // Remove espaços/quebras que possam ofuscar o esquema (ex.: "java\nscript:").
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        if ($normalized === '') {
            return false;
        }

        // data:image/* é aceitável (imagens embutidas do GrapesJS); demais data: não.
        if (str_starts_with($normalized, 'data:')) {
            return ! str_starts_with($normalized, 'data:image/');
        }

        return (bool) preg_match('/^(javascript|vbscript|file):/i', $normalized);
    }

    private function hasDangerousStyle(string $value): bool
    {
        return (bool) preg_match('/(expression\s*\(|javascript\s*:|vbscript\s*:|url\s*\(\s*[\'"]?\s*(javascript|vbscript|data:text\/html))/i', $value);
    }

    private function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;

        if ($parent === null) {
            return;
        }

        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }
}
