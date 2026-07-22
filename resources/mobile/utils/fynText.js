// Render a Fyn chat message: escape HTML, then turn **bold** into <strong>,
// "- " lines into a <ul> list, and remaining newlines into <br>. Mirrors the
// web AiMessageContent renderer so onboarding bubbles read identically on both
// surfaces. Fyn message text is server-authored (onboarding prompts / acks) —
// no raw user HTML reaches here — but we escape first so v-html stays XSS-safe.
export function renderFynText(text) {
  let html = String(text == null ? '' : text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

  // Markdown "- " bullets → <ul><li>; strip the newlines the list consumed so
  // they don't become stray <br> inside the list.
  html = html
    .replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>')
    .replace(/(?:<li>.*<\/li>\n?)+/g, m => '<ul>' + m.replace(/\n/g, '') + '</ul>');

  return html.replace(/\n/g, '<br>');
}
