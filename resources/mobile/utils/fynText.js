// Render a Fyn chat message: escape HTML, then turn **bold** into <strong>.
// Fyn message text is server-authored (onboarding prompts / acks) — no raw
// user HTML reaches here — but we escape first so v-html stays XSS-safe.
export function renderFynText(text) {
  const escaped = String(text == null ? '' : text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  return escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}
