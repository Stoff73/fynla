// SP3 scaffold API client — DISPOSABLE. Bearer-token against the existing backend.
//
// Origin = whatever host serves /m (localhost:8000, csjones.co, fynla.org):
// same-origin. On subdirectory deploys (csjones serves the whole
//   app at /fynla/) a bare relative `/api/*` resolves to the DOMAIN ROOT
//   (csjones.co/api/*) and 404s, so the web base must carry the subdirectory
//   prefix. Derive it from VITE_ROUTER_BASE — the same var router.js uses for
//   MOBILE_ROUTER_BASE. '/fynla/' -> '/fynla'; '/' or unset -> '' (root deploys
//   and localhost keep the existing same-origin relative behaviour). Stays
//   CSP `'self'`-compliant (same-origin path, not an absolute URL).
import { parseFynEvents, readFynEvents } from './utils/fynStream.js';

const BASE = (import.meta.env.VITE_ROUTER_BASE || '/').replace(/\/$/, '');

export async function apiPost(path, body, token = null) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    // Bearer-only: never send cookies. The mobile SPA authenticates purely by
    // token, so a stale same-origin web-app session cookie must not override the
    // Bearer (Sanctum's stateful guard would otherwise authenticate the cookie's
    // user).
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

export async function apiPut(path, body, token = null) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'PUT',
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

export async function apiDelete(path, token = null) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'DELETE',
    credentials: 'omit',
    headers: {
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}

export async function apiDownload(path, token) {
  const res = await fetch(`${BASE}${path}`, {
    credentials: 'omit',
    headers: {
      'Accept': 'application/pdf',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    return { ok: false, status: res.status, data };
  }

  return {
    ok: true,
    status: res.status,
    blob: await res.blob(),
    disposition: res.headers.get('content-disposition') || '',
  };
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
      'X-Fynla-Forms': '1',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });

  if (!res.ok) {
    return { ok: false, status: res.status, text: '' };
  }

  // 202 = the conversation already has a turn in flight; the message was
  // QUEUED server-side (JSON body, not SSE). Without this branch the JSON
  // parses as zero SSE lines and the caller shows a failure bubble while the
  // message silently waits in the queue. Surface it so the caller can stream
  // the queued reply once the in-flight turn finishes.
  if (res.status === 202) {
    const data = await res.json().catch(() => ({}));
    return { ok: true, status: 202, queued: true, data, text: '' };
  }

  // A retried turn the server has already taken answers in JSON, not SSE:
  // 'answered' (the reply is stored) or 'in_progress' (still running).
  if ((res.headers.get('Content-Type') || '').includes('application/json')) {
    const data = await res.json().catch(() => ({}));
    if (data.status === 'answered' || data.status === 'in_progress') {
      return { ok: true, status: res.status, turnTaken: data.status, text: '' };
    }
  }

  // Surface the full parsed event so callers can handle non-text turns,
  // including user-visible failures and capture confirmations. The mixin is
  // the presentation boundary; this transport never collapses typed events
  // into generic errors. ALL typed frames pass through to onEvent — including
  // the gamification `level_up` frame the backend emits strictly AFTER `done`.
  const state = { acc: '', error: null };
  const onFrame = (data) => {
    if (onEvent) onEvent(data);
    const t = data.type;
    if (t === 'content' || t === 'token' || t === 'content_block_delta' || t === 'text') {
      const piece = data.delta ?? data.content ?? data.text ?? '';
      if (piece) { state.acc += piece; if (onDelta) onDelta(piece); }
    }
    if (t === 'error') { state.error = data.message ?? 'error'; }
  };

  // Fallback: no streaming body available (older WebViews).
  const { terminal } = (!res.body || !res.body.getReader)
    ? await parseFynEvents(await res.text(), onFrame)
    : await readFynEvents(res.body.getReader(), onFrame);

  // `interrupted`: the stream closed without the terminal frame every turn
  // ends with, so whatever text arrived is not a finished answer.
  return { ok: !state.error, status: res.status, text: state.acc, interrupted: terminal === null };
}
