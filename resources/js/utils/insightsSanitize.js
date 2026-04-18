import DOMPurify from 'dompurify';

// Classes the admin editor is allowed to emit via the font-size / text-colour
// toolbar presets. The backend also enforces this via the span allow-list in
// StoreInsightArticleRequest; this is defence in depth so any hand-crafted
// or legacy payload rendered through DOMPurify still gets scrubbed down to
// the palette before hitting the DOM.
const ALLOWED_SPAN_CLASSES = new Set([
  'text-sm',
  'text-lg',
  'text-raspberry-500',
  'text-horizon-500',
  'text-spring-500',
  'text-violet-500',
  'text-neutral-500',
]);

let hookInstalled = false;
function ensureClassFilter() {
  if (hookInstalled) return;
  DOMPurify.addHook('uponSanitizeAttribute', (node, data) => {
    if (data.attrName !== 'class') return;
    const kept = (data.attrValue || '')
      .split(/\s+/)
      .filter((c) => ALLOWED_SPAN_CLASSES.has(c));
    if (!kept.length) {
      data.keepAttr = false;
      data.attrValue = '';
      return;
    }
    data.attrValue = kept.join(' ');
  });
  hookInstalled = true;
}

export function sanitiseInline(html) {
  ensureClassFilter();
  return DOMPurify.sanitize(html || '', {
    ALLOWED_TAGS: ['strong', 'em', 'u', 'a', 'br', 'span'],
    ALLOWED_ATTR: ['href', 'target', 'rel', 'class'],
  });
}

export function sanitiseBlock(html) {
  ensureClassFilter();
  return DOMPurify.sanitize(html || '', {
    ALLOWED_TAGS: ['p', 'strong', 'em', 'u', 'a', 'br', 'ul', 'ol', 'li', 'span'],
    ALLOWED_ATTR: ['href', 'target', 'rel', 'class'],
  });
}
