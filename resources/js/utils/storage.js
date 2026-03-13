/**
 * Storage utility — wraps localStorage/sessionStorage with error handling.
 *
 * Use this instead of direct localStorage/sessionStorage access in components.
 * Handles cases where storage is unavailable (private browsing, quota exceeded).
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
