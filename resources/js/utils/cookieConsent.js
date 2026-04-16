// resources/js/utils/cookieConsent.js

import { loadMasterTag as loadAwinMasterTag, unloadMasterTag as unloadAwinMasterTag, shouldLoadAwin } from '@/utils/awinTracking';

const STORAGE_KEY = 'cookie_consent';
const GA_ID = import.meta.env.VITE_GA_ID || 'G-3Y8DL3QB09';

let gaLoaded = false;

/**
 * Get current consent status.
 * @returns {'accepted'|'declined'|null}
 */
export function getConsentStatus() {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

/**
 * Whether the user has accepted cookies.
 */
export function hasConsent() {
  return getConsentStatus() === 'accepted';
}

/**
 * Accept cookies — store preference and load Google Analytics + Awin MasterTag.
 */
export function acceptCookies() {
  try {
    localStorage.setItem(STORAGE_KEY, 'accepted');
  } catch {
    // localStorage unavailable — proceed anyway
  }
  loadGoogleAnalytics();

  // Load Awin MasterTag if the current route is not excluded (checkout, etc.).
  // The router.afterEach hook in router/index.js handles subsequent navigation.
  const currentRouteName = window?.__appRouter?.currentRoute?.value?.name;
  if (shouldLoadAwin(currentRouteName)) {
    loadAwinMasterTag();
  }
}

/**
 * Decline cookies — store preference, do not load GA, and scrub the Awin
 * MasterTag if it was loaded from a prior session.
 */
export function declineCookies() {
  try {
    localStorage.setItem(STORAGE_KEY, 'declined');
  } catch {
    // localStorage unavailable
  }
  unloadAwinMasterTag();
}

/**
 * Reset consent — removes the stored preference.
 * Banner will show again on next page load.
 */
export function resetConsent() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    // localStorage unavailable
  }
  gaLoaded = false;
}

/**
 * Dynamically inject Google Analytics gtag script.
 * Safe to call multiple times — only loads once.
 */
function loadGoogleAnalytics() {
  if (gaLoaded || !GA_ID) return;

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_ID}`;
  document.head.appendChild(script);

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', GA_ID);

  gaLoaded = true;
}

/**
 * Initialise — if user previously accepted, load GA + Awin on page load.
 * Called from App.vue on mount.
 */
export function initCookieConsent() {
  if (hasConsent()) {
    loadGoogleAnalytics();

    // Awin MasterTag — respect route exclusions (checkout, etc.). The
    // router.afterEach hook re-evaluates on every subsequent navigation.
    const currentRouteName = window?.__appRouter?.currentRoute?.value?.name;
    if (shouldLoadAwin(currentRouteName)) {
      loadAwinMasterTag();
    }
  }
}
