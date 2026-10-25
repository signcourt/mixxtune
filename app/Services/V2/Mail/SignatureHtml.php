<?php

namespace App\Services\V2\Mail;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/*
 * W102 MIXXTUNE_HTML_SIGNATURE
 * A signature pasted as HTML source code (from a signature generator).
 * clean(): keeps the design (tables, inline styles, images, links) and removes
 * anything that could run code. text(): the same signature as plain lines,
 * for the text version of the mail.
 */
class SignatureHtml
{
    private const DROP = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'meta', 'link', 'base', 'style', 'title', 'head', 'svg', 'math', 'noscript', 'template', 'video', 'audio', 'source'];

    public static function clean(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $old = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="mxsig">'.$html.'</div></body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($old);

        $root = $doc->getElementById('mxsig');
        if (!$root) {
            return null;
        }
        $xp = new DOMXPath($doc);
        foreach (iterator_to_array($xp->query('.//comment()', $root)) as $c) {
            $c->parentNode?->removeChild($c);
        }
        foreach (self::DROP as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $el) {
                $el->parentNode?->removeChild($el);
            }
        }
        foreach (iterator_to_array($xp->query('.//*', $root)) as $el) {
            /** @var DOMElement $el */
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                $value = trim($attr->nodeValue ?? '');
                if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction', 'action', 'xmlns'], true)) {
                    $el->removeAttribute($attr->nodeName);
                } elseif (in_array($name, ['href', 'src', 'background'], true) && !self::safeUrl($value, $name)) {
                    $el->removeAttribute($attr->nodeName);
                } elseif ($name === 'style' && preg_match('/expression\s*\(|javascript:|behavior\s*:|-moz-binding/i', $value)) {
                    $el->removeAttribute($attr->nodeName);
                }
            }
            if (strtolower($el->nodeName) === 'a' && $el->hasAttribute('href')) {
                $el->setAttribute('target', '_blank');
            }
        }

        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $doc->saveHTML($n);
        }
        $out = trim($out);

        return $out === '' ? null : $out;
    }

    private static function safeUrl(string $url, string $attr): bool
    {
        $u = strtolower(preg_replace('/[\x00-\x20]+/', '', $url));
        if ($u === '' || str_starts_with($u, '#')) {
            return $attr === 'href';
        }
        if (preg_match('#^(https?:|//)#', $u)) {
            return true;
        }
        if ($attr === 'href' && preg_match('#^(mailto:|tel:)#', $u)) {
            return true;
        }

        /* small pictures put inside the code (data:image/png;base64,...) - not svg */
        return $attr === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $u) === 1;
    }

    public static function text(?string $html): string
    {
        $s = (string) $html;
        $s = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6]|/table)\b[^>]*>#i', "\n", $s);
        $s = preg_replace('#</t[dh]>#i', "  ", $s);
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\u{00A0}", ' ', $s);
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), preg_split('/\r\n|\r|\n/', $s));

        return trim(implode("\n", array_filter($lines, fn ($l) => $l !== '')));
    }
}
