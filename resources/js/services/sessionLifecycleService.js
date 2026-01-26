/**
 * Session Lifecycle Service
 *
 * Handles automatic session termination on:
 * - User inactivity timeout (15 minutes)
 * - Manual logout (via Navbar)
 *
 * Session PERSISTS through:
 * - Page refresh (F5, browser refresh button)
 * - Tab switching
 * - Window switching
 *
 * Session ENDS on:
 * - Browser/tab close (sessionStorage automatically clears)
 * - 15 minutes of inactivity
 * - Manual logout
 *
 * SECURITY: Token stored in sessionStorage automatically clears when
 * browser/tab closes. No pagehide/beforeunload handlers needed - this
 * avoids false logouts on page refresh while maintaining security.
 */

const INACTIVITY_TIMEOUT = 15 * 60 * 1000; // 15 minutes in milliseconds
let inactivityTimer = null;
let isInitialized = false;

/**
 * Initialize session lifecycle management
 * @param {Object} store - Vuex store instance
 * @param {Object} router - Vue Router instance
 */
export function initSessionLifecycle(store, router) {
  if (isInitialized) {
    return;
  }
  isInitialized = true;

  // NOTE: We intentionally do NOT use pagehide/beforeunload events.
  // These fire on page refresh, which would incorrectly log users out.
  // Instead, we rely on:
  // 1. sessionStorage - automatically clears on browser/tab close
  // 2. Inactivity timeout - handles abandoned sessions
  // 3. Server-side token TTL - handles any orphaned tokens

  // Inactivity timer - reset on user activity
  const resetInactivityTimer = () => {
    if (inactivityTimer) {
      clearTimeout(inactivityTimer);
    }

    const token = sessionStorage.getItem('auth_token');
    if (token) {
      inactivityTimer = setTimeout(() => {
        handleInactivityLogout(store, router);
      }, INACTIVITY_TIMEOUT);
    }
  };

  // Activity events to reset timer
  const activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart', 'mousemove'];
  activityEvents.forEach(event => {
    document.addEventListener(event, resetInactivityTimer, { passive: true });
  });

  // Start timer on init if user is authenticated
  resetInactivityTimer();

  // Also reset timer when token changes (login/logout)
  const originalSetItem = sessionStorage.setItem.bind(sessionStorage);
  sessionStorage.setItem = function(key, value) {
    originalSetItem(key, value);
    if (key === 'auth_token') {
      resetInactivityTimer();
    }
  };
}

/**
 * Handle logout due to inactivity
 * @param {Object} store - Vuex store instance
 * @param {Object} router - Vue Router instance
 */
async function handleInactivityLogout(store, router) {
  try {
    // Dispatch the logout action
    await store.dispatch('auth/logout');
  } catch (error) {
    // Ensure cleanup happens even if API call fails
    sessionStorage.removeItem('auth_token');
    localStorage.removeItem('auth_token');
  }

  // Redirect to login with inactivity reason
  const basePath = window.location.pathname.includes('/fps/') ? '/fps' : '';
  router.push({ path: `${basePath}/login`, query: { reason: 'inactivity' } });
}

/**
 * Stop the inactivity timer
 * Call this on logout to prevent timer from firing after logout
 */
export function stopInactivityTimer() {
  if (inactivityTimer) {
    clearTimeout(inactivityTimer);
    inactivityTimer = null;
  }
}

/**
 * Get the current inactivity timeout value (for testing)
 * @returns {number} Timeout in milliseconds
 */
export function getInactivityTimeout() {
  return INACTIVITY_TIMEOUT;
}

export default {
  initSessionLifecycle,
  stopInactivityTimer,
  getInactivityTimeout,
};
