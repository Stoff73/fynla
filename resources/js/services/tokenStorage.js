/**
 * Token Storage Abstraction Layer
 *
 * Web: sessionStorage (sync, wrapped in Promises).
 * Native (Capacitor): @capacitor/preferences (encrypted iOS Keychain / Android Keystore).
 */

import { Capacitor } from '@capacitor/core';

const AUTH_TOKEN_KEY = 'auth_token';

let _cachedToken = null;

export function isNativePlatform() {
  return Capacitor.isNativePlatform();
}

// Lazy-load Preferences only on native to avoid bundling issues on web
async function getPreferences() {
  const { Preferences } = await import('@capacitor/preferences');
  return Preferences;
}

export async function getToken() {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    const { value } = await Preferences.get({ key: AUTH_TOKEN_KEY });
    _cachedToken = value;
    return value;
  }
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

export async function setToken(token) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.set({ key: AUTH_TOKEN_KEY, value: token });
    _cachedToken = token;
    return;
  }
  sessionStorage.setItem(AUTH_TOKEN_KEY, token);
}

export async function removeToken() {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.remove({ key: AUTH_TOKEN_KEY });
    _cachedToken = null;
    return;
  }
  sessionStorage.removeItem(AUTH_TOKEN_KEY);
}

export async function getItem(key) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    const { value } = await Preferences.get({ key });
    return value;
  }
  return sessionStorage.getItem(key);
}

export async function setItem(key, value) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.set({ key, value });
    return;
  }
  sessionStorage.setItem(key, value);
}

export async function removeItem(key) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.remove({ key });
    return;
  }
  sessionStorage.removeItem(key);
}

export async function clear() {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.clear();
    _cachedToken = null;
    return;
  }
  sessionStorage.clear();
}

export function getTokenSync() {
  if (isNativePlatform()) {
    return _cachedToken;
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
