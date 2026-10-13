/**
 * Turn a Fyn message's list lines into lists, for the web (AiMessageContent)
 * and /m (fynText) renderers alike, so a list reads the same on both
 * (walk R28).
 *
 * - "- " and "* " lines become one <ul>; "1. " lines become one <ol>. The web
 *   renderer used to turn numbered lines into bare <li>s with no list around
 *   them, so Fyn's numbered working showed markers outside the bubble with
 *   line breaks between the steps, and /m showed them as plain text.
 * - Items separated only by blank lines are one list: Fyn often leaves a blank
 *   line between bullets, and each item became its own list.
 * - The newlines a list consumes are dropped so they do not become <br>s.
 *
 * @param {string} html - Escaped message text
 * @param {{ ul?: string, ol?: string }} attributes - Attributes for each list tag, e.g. ' class="list-disc"'
 * @returns {string}
 */
export function wrapListItems(html, attributes = {}) {
  // Both kinds are marked from their lines before either is wrapped, so a
  // list's consumed newline never joins the next list's first line to it.
  const marked = html
    .replace(/^[-*]\s+(.+)$/gm, '<bli>$1</bli>')
    .replace(/^\d+\.\s+(.+)$/gm, '<nli>$1</nli>');
  const wrapped = wrap(wrap(marked, 'bli', `<ul${attributes.ul ?? ''}>`, '</ul>'), 'nli', `<ol${attributes.ol ?? ''}>`, '</ol>');

  return wrapped.replace(/<(\/?)(?:bli|nli)>/g, '<$1li>');
}

function wrap(html, item, open, close) {
  return html
    .replace(new RegExp(`</${item}>\\n+(?=<${item}>)`, 'g'), `</${item}>`)
    .replace(new RegExp(`(?:<${item}>.*?</${item}>)+\\n?`, 'g'), m => open + m.replace(/\n/g, '') + close);
}
