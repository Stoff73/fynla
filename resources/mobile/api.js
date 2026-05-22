// SP3 scaffold API client — DISPOSABLE. Bearer-token against the existing backend.
// Same-origin: VITE_API_BASE_URL defaults to '' (relative) so /api/* resolves
// against whatever host serves /m. Native Capacitor sets VITE_API_BASE_URL.
const BASE = import.meta.env.VITE_API_BASE_URL || '';

export async function apiPost(path, body, token = null) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
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
    headers: {
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}
