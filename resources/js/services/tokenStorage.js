/**
 * Token Storage Abstraction Layer
 *
 * sessionStorage, wrapped in Promises. The async shape is kept because every
 * caller awaits it; the Capacitor Preferences backend it once switched to went
 * with the Capacitor target on 2026-09-08 (the native app is SwiftUI, own auth).
 */

const AUTH_TOKEN_KEY = 'auth_token';

export async function getToken() {
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

export async function setToken(token) {
  sessionStorage.setItem(AUTH_TOKEN_KEY, token);
}

export async function removeToken() {
  sessionStorage.removeItem(AUTH_TOKEN_KEY);
}

export async function getItem(key) {
  return sessionStorage.getItem(key);
}

export async function setItem(key, value) {
  sessionStorage.setItem(key, value);
}

export async function removeItem(key) {
  sessionStorage.removeItem(key);
}

export async function clear() {
  sessionStorage.clear();
}

export function getTokenSync() {
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

export default {
  AUTH_TOKEN_KEY,
  getToken,
  setToken,
  removeToken,
  getItem,
  setItem,
  removeItem,
  clear,
  getTokenSync,
};
