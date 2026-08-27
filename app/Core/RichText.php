<?php
declare(strict_types=1);

namespace CMS\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RichText
{
    private const ALLOWED_TAGS = [
        'p','br','strong','b','em','i','u','s','strike','h2','h3','h4',
        'ul','ol','li','blockquote','pre','code','a'
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><strike><h2><h3><h4><ul><ol><li><blockquote><pre><code><a>');
            $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;
            $html = preg_replace('/javascript\s*:/iu', '', $html) ?? $html;
            return trim($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="pcms-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('pcms-root');
        if (!$root) {
            return '';
        }

        self::cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public static function fromPlain(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/\R{2,}/', $text) ?: [$text];
        return implode('', array_map(
            static fn(string $part): string => '<p>' . nl2br(e(trim($part))) . '</p>',
            $parts
        ));
    }

    public static function plainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/(p|h2|h3|h4|li|blockquote|pre)>/i', "\n", $text) ?? $text;
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($child);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var DOMElement $child */
            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            $attributes = [];
            foreach ($child->attributes as $attribute) {
                $attributes[] = $attribute->name;
            }

            foreach ($attributes as $name) {
                $lowerName = strtolower($name);
                if ($tag === 'a' && in_array($lowerName, ['href','title','target','rel'], true)) {
                    continue;
                }
                if ($lowerName === 'class' && in_array($tag, ['p','h2','h3','h4','blockquote','pre','li'], true)) {
                    $classes = preg_split('/\s+/', trim($child->getAttribute('class'))) ?: [];
                    $classes = array_values(array_intersect($classes, ['rt-align-left','rt-align-center','rt-align-right']));
                    if ($classes) {
                        $child->setAttribute('class', implode(' ', $classes));
                        continue;
                    }
                }
                $child->removeAttribute($name);
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!self::safeHref($href)) {
                    $child->removeAttribute('href');
                }

                if ($child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('target');
                    $child->removeAttribute('rel');
                }
            }

            self::cleanChildren($child);
        }
    }

    private static function safeHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = strtolower((string)parse_url($href, PHP_URL_SCHEME));
        return in_array($scheme, ['http','https','mailto'], true);
    }

    private function __construct()
    {
    }
}
