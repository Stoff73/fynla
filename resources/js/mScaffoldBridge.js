/**
 * /m → desktop auth bridge.
 *
 * A consumed server-issued handoff creates a normal Laravel web session and
 * leaves a short-lived, non-secret marker cookie. The desktop Vuex store still
 * needs a synchronous truthy value while it verifies that session, so this
 * module translates only that marker into the fixed `web-session` sentinel.
 * It must never read or copy `/m`'s bearer token into the desktop SPA.
 *
 * MUST be imported before the Vuex store (which reads the token on module eval),
 * hence this is the very first import in app.js.
 */
try {
  const hasWebSessionMarker = document.cookie
    .split(';')
    .some(cookie => cookie.trim().startsWith('fynla_web_session='));

  if (hasWebSessionMarker) {
    // The server handoff may have authenticated a different user from the
    // bearer already held by this tab. Always discard that bearer so an
    // expired web session can never fall back to the previous account.
    sessionStorage.setItem('auth_token', 'web-session');
  }

  if (hasWebSessionMarker) {
    document.cookie = 'fynla_web_session=; Max-Age=0; path=/; SameSite=Lax';
  }
} catch {
  /* private mode / storage disabled — nothing to bridge */
}

export function isTransferableMobileBearer(token) {
  return typeof token === 'string' && token.length > 0 && token !== 'web-session';
}
