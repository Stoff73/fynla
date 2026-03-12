/**
 * Mobile App Lifecycle Manager
 *
 * Handles app background/foreground transitions, biometric re-auth,
 * token validation, and SSE stream management.
 */

import { platform } from '@/utils/platform';
import { getToken, setToken } from '@/services/tokenStorage';
import api from '@/services/api';

let appListenerRegistered = false;

export async function initAppLifecycle(store, router) {
  if (!platform.isNative() || appListenerRegistered) return;

  const { App } = await import('@capacitor/app');

  App.addListener('appStateChange', async ({ isActive }) => {
    if (!isActive) {
      // App going to background — abort any active SSE streams
      store.dispatch('aiChat/abortStreaming').catch(() => {});
    } else {
      // App coming to foreground — validate token and refresh data
      const token = await getToken();
      if (!token) return;

      try {
        await api.get('/auth/user');
        // Token valid — refresh dashboard
        store.dispatch('mobileDashboard/refreshDashboard').catch(() => {});
      } catch (error) {
        if (error.response?.status === 401) {
          // Token expired — redirect to login
          store.commit('auth/clearAuth');
          router.push('/m/login');
        }
      }
    }
  });

  // Handle back button (Android, but safe to register on iOS too)
  App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack) {
      router.back();
    }
  });

  appListenerRegistered = true;
}

/**
 * Attempt biometric login on app launch.
 * Returns true if successful, false if re-auth needed.
 */
export async function attemptBiometricLogin(store) {
  if (!platform.canUseBiometrics()) return false;

  try {
    const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
    const { isAvailable } = await NativeBiometric.isAvailable();
    if (!isAvailable) return false;

    // Check for stored credentials FIRST — if none exist, skip silently
    let credentials;
    try {
      credentials = await NativeBiometric.getCredentials({ server: 'fynla.org' });
    } catch {
      // No stored credentials — user has never set up biometric login
      return false;
    }
    if (!credentials?.password) return false;

    // Credentials exist — now prompt for biometric verification
    await NativeBiometric.verifyIdentity({
      reason: 'Sign in to Fynla',
      title: 'Fynla',
    });

    // Hydrate token into storage/store
    if (credentials?.password) {
      await setToken(credentials.password);
      store.commit('auth/setToken', credentials.password);
      try {
        // Validate token and fetch user data into the auth store
        await store.dispatch('auth/fetchUser');
        return true;
      } catch {
        // Token expired or invalid — clear it so user sees login screen
        store.commit('auth/clearAuth');
        return false;
      }
    }

    return false;
  } catch {
    return false;
  }
}

/**
 * Check if token needs refresh (>25 days old).
 * Called on app foreground.
 */
export async function checkTokenRefresh(store) {
  const token = await getToken();
  if (!token) return;

  try {
    // The server handles token age checking — just call refresh
    // The backend returns a new token if the current one is >25 days old
    // If not needed, it returns the current token info
    const response = await api.post('/v1/auth/refresh-token');
    if (response.data?.data?.access_token) {
      const { setToken } = await import('@/services/tokenStorage');
      await setToken(response.data.data.access_token);
      store.commit('auth/setToken', response.data.data.access_token);
    }
  } catch {
    // Token refresh failed — not critical, will retry on next launch
  }
}
