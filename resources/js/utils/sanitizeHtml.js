/**
 * Simple HTML sanitiser for v-html content.
 * Strips dangerous elements (script, iframe, object, embed, form)
 * and event handler attributes (on*).
 *
 * For server-generated HTML from structured data, this provides
 * defence-in-depth against potential XSS.
 */
export function sanitizeHtml(html) {
    if (!html || typeof html !== 'string') return '';

    // Remove script tags and their content
    let clean = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');

    // Remove dangerous elements
    clean = clean.replace(/<(iframe|object|embed|form|meta|link)\b[^>]*>/gi, '');
    clean = clean.replace(/<\/(iframe|object|embed|form|meta|link)>/gi, '');

    // Remove event handlers (on*)
    clean = clean.replace(/\s+on\w+\s*=\s*["'][^"']*["']/gi, '');
    clean = clean.replace(/\s+on\w+\s*=\s*[^\s>]*/gi, '');

    // Remove javascript: protocol
    clean = clean.replace(/javascript\s*:/gi, '');

    return clean;
}
