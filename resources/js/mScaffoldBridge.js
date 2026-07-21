/**
 * /m → desktop auth bridge.
 *
 * The web app and the `/m` mobile app are two same-origin SPAs that keep the
 * Sanctum bearer token in different places:
 *   - desktop SPA: sessionStorage('auth_token') — read SYNCHRONOUSLY when the
 *     Vuex auth store module is first evaluated (state.token = getTokenSync()).
 *   - `/m` mobile SPA: localStorage('m_scaffold_token').
 *
 * When a user taps "Admin Panel" in the `/m` drawer (the admin panel has no
 * mobile screen) the top window navigates to /admin and the desktop SPA boots
 * with an empty sessionStorage, so isAuthenticated is false and the router
 * bounces to login. Seeding sessionStorage from the mobile frame fails on iOS
 * (cross-context sessionStorage is partitioned), but localStorage is reliably
 * shared same-origin — so adopt the mobile token here.
 *
 * MUST be imported before the Vuex store (which reads the token on module eval),
 * hence this is the very first import in app.js. Copy (not move) so returning to
 * /m still finds m_scaffold_token; clearAuth() removes it on logout.
 */
try {
  if (!sessionStorage.getItem('auth_token')) {
    const mToken = localStorage.getItem('m_scaffold_token');
    if (mToken) {
      sessionStorage.setItem('auth_token', mToken);
    }
  }
} catch (e) {
  /* private mode / storage disabled — nothing to bridge */
}
