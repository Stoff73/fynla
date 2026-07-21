import DOMPurify from 'dompurify';

/**
 * HTML sanitiser for v-html content.
 *
 * Wraps DOMPurify (a DOM-based sanitiser) rather than regex string-replacement.
 * Regex HTML sanitisers are bypassable (e.g. malformed/nested tags, entity
 * encoding, attribute splitting), so this is both safer and simpler. DOMPurify's
 * default profile preserves safe formatting/structural HTML while stripping
 * scripts, event handlers, javascript: URIs and other XSS vectors — matching the
 * permissive intent of the previous regex sanitiser used for server-generated
 * structured HTML (LPA/Will documents) and AI message content.
 */
export function sanitizeHtml(html) {
    if (!html || typeof html !== 'string') return '';
    return DOMPurify.sanitize(html);
}
