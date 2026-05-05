/**
 * Strip HTML tags from a string. Mirrors PHP's strip_tags() so the
 * frontend optimistic-display path (e.g. the user's chat-message bubble
 * shown before the API round-trip) matches what `SanitizeInput`
 * middleware writes to the database.
 *
 * Why a regex rather than DOMParser: DOMParser's body.textContent
 * concatenates the textContent of stripped elements (so
 * "<script>alert(1)</script> hello" → "alert(1) hello"), which DOES
 * match strip_tags. But it also parses partially-malformed input as
 * HTML, which can fold whitespace and decode entities — diverging from
 * strip_tags. The narrow tag-shaped regex below preserves cases like
 * "<3" or "< 5 > 3" exactly the way strip_tags does.
 */
export function stripTags(value) {
    if (typeof value !== 'string') return '';
    return value.replace(/<\/?[a-zA-Z][^>]*>/g, '').trim();
}
