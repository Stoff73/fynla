import { removeToken } from '@/services/tokenStorage';

// The token the server last confirmed on this page load. A guest-only or
// public route asks at most once per token, so an authenticated user pays one
// /api/auth/user call, not one per navigation.
let confirmedToken = null;

/**
 * Is the stored desktop token still a live session?
 *
 * `auth/isAuthenticated` is only `!!state.token`, and the token lives in
 * sessionStorage, which outlives a sign-out done elsewhere in the same tab —
 * notably the /m app, which shares the tab with this SPA when /m frames it
 * (mobile-host.blade.php). A dead token would bounce a guest off /register
 * (and, framed, hand them to a logged-out /m app), so guest-only and public
 * routes confirm the token with the server before redirecting on it.
 *
 * Only a 401/419 counts as dead: the dead token is dropped and false is
 * returned. A network error or 5xx keeps the old behaviour (true), so a
 * blip never signs a real user out.
 */
export async function storedSessionIsLive(store) {
  const token = store.state.auth.token;
  if (!token) return false;
  if (token === confirmedToken) return true;

  try {
    await store.dispatch('auth/fetchUser');
    confirmedToken = token;
    return true;
  } catch (error) {
    if (error?.status !== 401 && error?.status !== 419) return true;
    await removeToken();
    // The store's one sign-out clear: token, user, role and tier, and the
    // persisted snapshot follows.
    store.commit('auth/clearAuth');
    return false;
  }
}

export function resetStoredSessionCheck() {
  confirmedToken = null;
}
