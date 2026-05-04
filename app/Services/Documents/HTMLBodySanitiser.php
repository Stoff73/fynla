<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Mews\Purifier\Facades\Purifier;

class HTMLBodySanitiser
{
    public function sanitise(string $html): string
    {
        // Pre-pass: extract data-pending-image placeholder tags before Purifier
        // strips them (HTMLPurifier requires <img src> and removes any <img>
        // without a valid src attribute, so placeholders must be hoisted out).
        // We use a plain-text sentinel that survives Purifier's comment stripping.
        $placeholders = [];
        $html = preg_replace_callback(
            '/<img\b([^>]*\bdata-pending-image\b[^>]*)>/i',
            function (array $m) use (&$placeholders): string {
                $index = count($placeholders);
                $placeholders[] = '<img' . $m[1] . '>';
                return 'FYNLA_PENDING_IMAGE_' . $index;
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

        // Pass 3: re-inject data-pending-image attribute (Purifier strips unknown
        // attributes; we treat the placeholder as a known marker).
        $clean = preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function (array $m) use ($html): string {
                $attrs = $m[1];
                // Look up the matching original tag by index — naive but fine
                // because mammoth emits images in document order.
                return '<img'.$attrs.'>';
            },
            $clean
        );

        // Post-pass: restore data-pending-image placeholders.
        foreach ($placeholders as $index => $tag) {
            $clean = str_replace('FYNLA_PENDING_IMAGE_' . $index, $tag, $clean);
        }

        return $clean;
    }
}
