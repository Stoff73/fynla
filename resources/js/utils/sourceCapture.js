/**
 * Marketing-channel attribution.
 *
 * On first page load Fynla's URL may carry a `utm_source` parameter
 * (e.g. https://fynla.org/savetax?utm_source=linkedin). We stash the
 * value in sessionStorage so the registration form can submit it
 * alongside the new account, even if the user navigates around the
 * marketing site before signing up.
 *
 * SessionStorage (rather than a cookie) is deliberate — it scopes the
 * attribution to the current browsing session without raising any
 * GDPR consent questions.
 *
 * Allowlist enforced both client-side here and server-side in the
 * registration controller. Adding a new platform requires editing
 * BOTH lists.
 */

const STORAGE_KEY = 'fynla.signup_source';

const ALLOWED_SOURCES = Object.freeze([
  'linkedin',
  'facebook',
  'instagram',
  'tiktok',
  'x',
  'youtube',
]);

function readUtmSourceFromUrl() {
  if (typeof window === 'undefined' || !window.location) return null;
  try {
    const params = new URLSearchParams(window.location.search);
    const raw = params.get('utm_source');
    if (typeof raw !== 'string') return null;
    const normalised = raw.trim().toLowerCase();
    return ALLOWED_SOURCES.includes(normalised) ? normalised : null;
  } catch {
    return null;
  }
}

function safeSessionStorage() {
  try {
    if (typeof window === 'undefined' || !window.sessionStorage) return null;
    return window.sessionStorage;
  } catch {
    return null;
  }
}

/**
 * Read utm_source from the current URL (if present and on the
 * allowlist) and persist it to sessionStorage. Existing values are
 * NOT overwritten — first-touch attribution wins.
 */
export function captureSourceFromUrl() {
  const candidate = readUtmSourceFromUrl();
  if (!candidate) return null;

  const storage = safeSessionStorage();
  if (!storage) return candidate;

  const existing = storage.getItem(STORAGE_KEY);
  if (existing && ALLOWED_SOURCES.includes(existing)) return existing;

  storage.setItem(STORAGE_KEY, candidate);
  return candidate;
}

/**
 * Returns the captured signup source or null. Safe to call from any
 * component — never throws.
 */
export function getCapturedSource() {
  const storage = safeSessionStorage();
  if (!storage) return null;
  const value = storage.getItem(STORAGE_KEY);
  return value && ALLOWED_SOURCES.includes(value) ? value : null;
}

/**
 * Clears the captured source. Called by the registration flow after
 * the value has been persisted to the user record so a subsequent
 * registration in the same browser session does not inherit it.
 */
export function clearCapturedSource() {
  const storage = safeSessionStorage();
  if (storage) storage.removeItem(STORAGE_KEY);
}

export const ALLOWED_SIGNUP_SOURCES = ALLOWED_SOURCES;
