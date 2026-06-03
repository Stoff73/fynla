// SP3 scaffold API client — DISPOSABLE. Bearer-token against the existing backend.
//
// Two surfaces, one bundle:
// - Capacitor iOS (origin = `capacitor://localhost`): can't be same-origin, must
//   use the absolute URL baked at build time (`VITE_API_BASE_URL=https://fynla.org`
//   via deploy/mobile/build-ios.sh) so requests reach the production API.
// - Web (origin = whatever host serves /m, e.g. localhost:8000, csjones.co,
//   fynla.org): same-origin. On subdirectory deploys (csjones serves the whole
//   app at /fynla/) a bare relative `/api/*` resolves to the DOMAIN ROOT
//   (csjones.co/api/*) and 404s, so the web base must carry the subdirectory
//   prefix. Derive it from VITE_ROUTER_BASE — the same var router.js uses for
//   MOBILE_ROUTER_BASE. '/fynla/' -> '/fynla'; '/' or unset -> '' (root deploys
//   and localhost keep the existing same-origin relative behaviour). Stays
//   CSP `'self'`-compliant (same-origin path, not an absolute URL).
//
// Runtime detection picks the right base regardless of how the bundle was built.
// `window.Capacitor.isNativePlatform()` is auto-injected by the Capacitor runtime
// inside the WebView; absent in any browser.
const isNative = typeof window !== 'undefined' && !!window.Capacitor?.isNativePlatform?.();
const WEB_BASE = (import.meta.env.VITE_ROUTER_BASE || '/').replace(/\/$/, '');
const BASE = isNative ? (import.meta.env.VITE_API_BASE_URL || 'https://fynla.org') : WEB_BASE;

export async function apiPost(path, body, token = null) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    // Bearer-only: never send cookies. The mobile SPA authenticates purely by
    // token, so a stale same-origin web-app session cookie must not override the
    // Bearer (Sanctum's stateful guard would otherwise authenticate the cookie's
    // user). Also required for Capacitor cross-origin (WKWebView).
    credentials: 'omit',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}

export async function apiGet(path, token) {
  const res = await fetch(`${BASE}${path}`, {
    credentials: 'omit', // Bearer-only — see apiPost.
    headers: {
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}

/**
 * POST and consume a Server-Sent-Events (SSE) response. The Fyn chat backend
 * (/api/ai-chat/conversations/{id}/messages) always streams `data: {json}\n\n`
 * chunks; each chunk has a `type`. Text pieces arrive in delta|content|text and
 * the stream ends on `type: 'done'`. onDelta(piece) fires as text arrives;
 * resolves with the full accumulated text. Falls back to a one-shot read when
 * the platform has no streaming body (older WebViews).
 */
export async function apiStream(path, body, token, onDelta, onEvent) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    credentials: 'omit', // Bearer-only — see apiPost.
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'text/event-stream',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });

  if (!res.ok) {
    return { ok: false, status: res.status, text: '' };
  }

  const consumeLine = (line, state) => {
    if (!line.startsWith('data: ')) return false;
    let data;
    try { data = JSON.parse(line.slice(6)); } catch (e) { return false; }
    // Surface the full parsed event so callers can handle non-text turns
    // (onboarding quick_replies + bubbles, conversation_created, etc.).
    if (onEvent) onEvent(data);
    const t = data.type;
    if (t === 'content' || t === 'token' || t === 'content_block_delta' || t === 'text') {
      const piece = data.delta ?? data.content ?? data.text ?? '';
      if (piece) { state.acc += piece; if (onDelta) onDelta(piece); }
    }
    if (t === 'done') { state.done = true; }
    if (t === 'error') { state.error = data.message ?? 'error'; state.done = true; }
    return state.done;
  };

  // Fallback: no streaming body available.
  if (!res.body || !res.body.getReader) {
    const raw = await res.text();
    const state = { acc: '', done: false, error: null };
    raw.split('\n').forEach((l) => consumeLine(l, state));
    return { ok: !state.error, status: res.status, text: state.acc };
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  const state = { acc: '', done: false, error: null };
  while (!state.done) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split('\n');
    buffer = lines.pop() || '';
    for (const line of lines) {
      if (consumeLine(line, state)) break;
    }
  }
  return { ok: !state.error, status: res.status, text: state.acc };
}
