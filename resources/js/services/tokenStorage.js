/**
 * Token Storage Abstraction Layer
 *
 * Provides a unified async interface for auth token storage.
 * Web implementation uses sessionStorage (sync, wrapped in Promises).
 * Mobile/Capacitor (Phase 2) will swap to secure native storage
 * (iOS Keychain / Android Keystore) without changing consuming code.
 *
 * All methods return Promises to support both sync (web) and async (native) backends.
 */

const AUTH_TOKEN_KEY = 'auth_token';

/**
 * Check if running on a native platform (Capacitor/Cordova).
 * Returns false for now (web-only implementation).
 * Phase 2 will detect Capacitor environment.
 * @returns {boolean}
 */
export function isNativePlatform() {
  // Phase 2: return typeof window !== 'undefined' && window.Capacitor?.isNativePlatform() === true;
  return false;
}

// =============================================
// Auth Token Methods (primary use case)
// =============================================

/**
 * Get the auth token from storage.
 * @returns {Promise<string|null>}
 */
export async function getToken() {
  if (isNativePlatform()) {
    // Phase 2: Secure native storage (Capacitor Preferences / Keychain)
    // return await SecureStorage.get({ key: AUTH_TOKEN_KEY });
  }
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

/**
 * Store the auth token.
 * @param {string} token
 * @returns {Promise<void>}
 */
export async function setToken(token) {
  if (isNativePlatform()) {
    // Phase 2: Secure native storage
    // return await SecureStorage.set({ key: AUTH_TOKEN_KEY, value: token });
  }
  sessionStorage.setItem(AUTH_TOKEN_KEY, token);
}

/**
 * Remove the auth token from storage.
 * @returns {Promise<void>}
 */
export async function removeToken() {
  if (isNativePlatform()) {
    // Phase 2: Secure native storage
    // return await SecureStorage.remove({ key: AUTH_TOKEN_KEY });
  }
  sessionStorage.removeItem(AUTH_TOKEN_KEY);
}

// =============================================
// Generic Key-Value Methods (for other session data)
// =============================================

/**
 * Get an item from storage by key.
 * @param {string} key
 * @returns {Promise<string|null>}
 */
export async function getItem(key) {
  if (isNativePlatform()) {
    // Phase 2: Capacitor Preferences
    // const { value } = await Preferences.get({ key });
    // return value;
  }
  return sessionStorage.getItem(key);
}

/**
 * Set an item in storage.
 * @param {string} key
 * @param {string} value
 * @returns {Promise<void>}
 */
export async function setItem(key, value) {
  if (isNativePlatform()) {
    // Phase 2: Capacitor Preferences
    // return await Preferences.set({ key, value });
  }
  sessionStorage.setItem(key, value);
}

/**
 * Remove an item from storage by key.
 * @param {string} key
 * @returns {Promise<void>}
 */
export async function removeItem(key) {
  if (isNativePlatform()) {
    // Phase 2: Capacitor Preferences
    // return await Preferences.remove({ key });
  }
  sessionStorage.removeItem(key);
}

/**
 * Clear all items from storage.
 * @returns {Promise<void>}
 */
export async function clear() {
  if (isNativePlatform()) {
    // Phase 2: Capacitor Preferences
    // return await Preferences.clear();
  }
  sessionStorage.clear();
}

// =============================================
// Synchronous Getters (for performance-critical paths)
// =============================================

/**
 * Synchronously get the auth token. Web-only convenience method.
 * On native platforms (Phase 2), this will return a cached value
 * that may be stale — prefer getToken() for guaranteed accuracy.
 * @returns {string|null}
 */
export function getTokenSync() {
  if (isNativePlatform()) {
    // Phase 2: Return cached value from memory
    // return _cachedToken;
    return null;
  }
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

export default {
  AUTH_TOKEN_KEY,
  isNativePlatform,
  getToken,
  setToken,
  removeToken,
  getItem,
  setItem,
  removeItem,
  clear,
  getTokenSync,
};
