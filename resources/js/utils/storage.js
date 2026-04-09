/**
 * Storage utility — wraps localStorage/sessionStorage with error handling.
 *
 * Use this instead of direct localStorage/sessionStorage access in components.
 * Handles cases where storage is unavailable (private browsing, quota exceeded).
 *
 * SECURITY: The default get/set/remove methods use localStorage which persists
 * beyond the browser session. ONLY use for UI preferences (collapsed states,
 * dismissed banners, menu positions). NEVER store financial data, PII, or
 * auth tokens here — use tokenStorage.js for auth, and the Vuex store for
 * financial data. Use storage.session.* for data that should not persist.
 */
const storage = {
  get(key, fallback = null) {
    try {
      const value = localStorage.getItem(key);
      return value !== null ? value : fallback;
    } catch {
      return fallback;
    }
  },

  set(key, value) {
    try {
      localStorage.setItem(key, String(value));
    } catch {
      // Storage full or unavailable
    }
  },

  remove(key) {
    try {
      localStorage.removeItem(key);
    } catch {
      // Ignore
    }
  },

  session: {
    get(key, fallback = null) {
      try {
        const value = sessionStorage.getItem(key);
        return value !== null ? value : fallback;
      } catch {
        return fallback;
      }
    },

    set(key, value) {
      try {
        sessionStorage.setItem(key, String(value));
      } catch {
        // Ignore
      }
    },
  },
};

export default storage;
