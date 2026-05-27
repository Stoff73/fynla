// SP3 scaffold API client — DISPOSABLE. Bearer-token against the existing backend.
//
// Two surfaces, one bundle:
// - Capacitor iOS (origin = `capacitor://localhost`): can't be same-origin, must
//   use the absolute URL baked at build time (`VITE_API_BASE_URL=https://fynla.org`
//   via deploy/mobile/build-ios.sh) so requests reach the production API.
// - Web (origin = whatever host serves /m, e.g. localhost:8000, csjones.co,
//   fynla.org): same-origin, relative `/api/*` works and satisfies CSP `'self'`.
//
// Runtime detection picks the right base regardless of how the bundle was built.
// `window.Capacitor.isNativePlatform()` is auto-injected by the Capacitor runtime
// inside the WebView; absent in any browser.
const isNative = typeof window !== 'undefined' && !!window.Capacitor?.isNativePlatform?.();
const BASE = isNative ? (import.meta.env.VITE_API_BASE_URL || 'https://fynla.org') : '';

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
