// Shared /m auth-expiry handler.
//
// A 401 from ANY /m data request — a chat call or a plain view load() — means
// the session token has died server-side (a dead/expired bearer, a revoked
// session, or a cache-backed session lost on a deploy cache-clear). Every
// further request on that token fails the exact same way. Log out locally and
// send the user to the /m login screen instead of stranding them behind a
// generic "could not load" / "something went wrong" message with no way
// forward. apiGet/apiPost/apiStream (api.js) all resolve `{ ok, status, ... }`
// on a non-2xx response rather than throwing, so this is checked on the
// resolved value, not via try/catch.
//
// Used by the onboardingChat mixin (chat paths, via its thin wrapper method)
// AND directly by every data-view load() that doesn't mix in onboardingChat,
// so both surfaces re-authenticate the same way. Returns true when it handled
// the response (a 401), so callers can bail out of their load path
// immediately without also rendering an error.
import { store } from './store.js';

export function handleAuthExpiry(res, router) {
  if (!res || res.status !== 401) return false;
  store.logout();
  router.push('/login');
  return true;
}
