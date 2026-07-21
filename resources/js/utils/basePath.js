/**
 * Prefix a root-relative path with the app's router base so links resolve under
 * a subdirectory deployment (csjones.co/fynla) as well as at the domain root
 * (fynla.org).
 *
 * Use this for any full-page navigation that bypasses Vue Router — a raw
 * `<a href>`, `window.open`, or `window.location` — to a server-rendered route
 * (e.g. /savetax) or an SPA route opened outside the router. `router.push()` and
 * `<router-link>` already apply VITE_ROUTER_BASE and must NOT be wrapped.
 *
 * On fynla.org VITE_ROUTER_BASE is '/', so this is a no-op. On csjones it is
 * '/fynla/', so withBase('/savetax') === '/fynla/savetax'.
 */
export function withBase(path) {
  const base = (import.meta.env.VITE_ROUTER_BASE || '/').replace(/\/$/, '');
  const suffix = String(path).startsWith('/') ? path : `/${path}`;
  return `${base}${suffix}`;
}
