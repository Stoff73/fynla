<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Mews\Purifier\Facades\Purifier;

class HTMLBodySanitiser
{
    public function sanitise(string $html): string
    {
        // Pre-pass: hoist <img data-pending-image="..."> tags out of the HTML before
        // HTMLPurifier sees them. Purifier's HTML 4.01 doctype requires <img src>, so
        // any <img> without a src is stripped. We replace placeholder tags with text
        // sentinels that pass through Purifier intact, then restore them after.
        //
        // The sentinel embeds a per-call random nonce so user-authored text can never
        // collide with it (e.g. a docx body that literally contains "FYNLA_PENDING_IMAGE_0"
        // would otherwise be rewritten as a tag — a content-integrity bug).
        $nonce = bin2hex(random_bytes(8));
        $placeholderToken = static fn (int $index): string => "FYNLA_PENDING_IMAGE_{$nonce}_{$index}";

        $placeholders = [];
        $html = preg_replace_callback(
            '/<img\b([^>]*\bdata-pending-image\b[^>]*)>/i',
            function (array $m) use (&$placeholders, $placeholderToken): string {
                $index = count($placeholders);
                $placeholders[] = '<img' . $m[1] . '>';
                return $placeholderToken($index);
            },
            $html
        );

        // Pass 1: HTMLPurifier with our profile.
        $clean = Purifier::clean($html, 'document_article');

        // Pass 2: enforce that <img src> starts with /storage/document-articles/.
        // Purifier's URI filter is host-based; we want a path-prefix rule, easier
        // to apply with a follow-up regex than to wire a custom URIFilter.
        $clean = preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function (array $m): string {
                $attrs = $m[1];
                if (preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $attrs, $srcMatch)) {
                    if (! str_starts_with($srcMatch[1], '/storage/document-articles/')) {
                        return ''; // strip the whole tag
                    }
                }
                // No src attribute — keep (placeholder may rely on data-pending-image).
                return '<img'.$attrs.'>';
            },
            $clean
        );

        // Post-pass: restore the original <img data-pending-image="..."> tags from
        // their sentinels. The nonce guarantees no collision with body text.
        foreach ($placeholders as $index => $tag) {
            $clean = str_replace($placeholderToken($index), $tag, $clean);
        }

        return $clean;
    }
}
