/**
 * Wrap a Fyn message's "<li>" lines in lists, for the web (AiMessageContent)
 * and /m (fynText) renderers alike, so a list reads the same on both.
 *
 * Fyn often leaves a blank line between bullets. Each item then became its
 * own <ul>, with a paragraph break between them: wide gaps, and markers on the
 * bubble's edge (walk R28). Items separated only by blank lines are one list.
 *
 * @param {string} html - Escaped message text with bullet lines already turned into <li>…</li>
 * @param {string} ulAttributes - Attributes for the <ul> tag, e.g. ' class="list-disc"'
 * @returns {string}
 */
export function wrapListItems(html, ulAttributes = '') {
  return html
    .replace(/<\/li>\n+(?=<li>)/g, '</li>')
    .replace(/(?:<li>.*<\/li>\n?)+/g, m => `<ul${ulAttributes}>` + m.replace(/\n/g, '') + '</ul>');
}
