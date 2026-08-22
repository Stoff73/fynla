// resources/js/utils/cookieConsent.js
//
// Cookie-banner consent for the web SPA.
//
// The decision itself is NOT owned here. It is recorded server-side by
// POST /api/cookie-consent (App\Services\Consent\CookieConsentService), which
// writes both the user_consents record and the `fyn_cookie_consent` cookie
// this module reads. That is what makes the affiliate middleware honour a
// refusal — it reads the same cookie — and what makes consent demonstrable.
// This module only asks for the decision and acts on it locally by loading or
// not loading the tags. Do not add a second store here (Rule 20).

import { loadMasterTag as loadAwinMasterTag, unloadMasterTag as unloadAwinMasterTag, shouldLoadAwin } from '@/utils/awinTracking';

const STATUS_COOKIE = 'fyn_cookie_consent';
const ACCEPTED = 'accepted';
const DECLINED = 'declined';

// No fallback measurement ID. An environment without VITE_GA_ID gets no
// analytics at all — never the production property (W-0047). The production
// build script is what supplies the real ID.
const GA_ID = import.meta.env.VITE_GA_ID || '';

// Same-origin, honouring a subdirectory deployment (csjones.co/fynla).
const ROUTER_BASE = (import.meta.env.VITE_ROUTER_BASE || '/').replace(/\/$/, '');
const CONSENT_ENDPOINT = `${ROUTER_BASE}/api/cookie-consent`;

let gaLoaded = false;

function readStatusCookie() {
  try {
    const match = document.cookie.match(new RegExp(`(?:^|; )${STATUS_COOKIE}=([^;]*)`));
    const value = match ? decodeURIComponent(match[1]) : null;
    return value === ACCEPTED || value === DECLINED ? value : null;
  } catch {
    return null;
  }
}

/**
 * The Awin click reference the visitor is currently carrying, if any.
 *
 * An affiliate landing is a single request and the banner is answered after
 * it, so the server cannot capture the reference at consent time on its own.
 */
function currentClickReference() {
  try {
    return new URLSearchParams(window.location.search).get('awc');
  } catch {
    return null;
  }
}

/**
 * Send the decision to the one endpoint that records it. Resolves true when
 * the server has recorded it and set the cookie.
 */
async function recordConsent(status) {
  const body = { status };
  const awc = currentClickReference();
  if (awc) body.awc = awc;

  try {
    const response = await fetch(CONSENT_ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    });
    return response.ok;
  } catch {
    return false;
  }
}

/**
 * Write the status cookie from the browser.
 *
 * Written first, before the request, so that a slow or failed round-trip can
 * never trap a visitor who has answered the banner behind it. The server's
 * response re-sets the same host-only cookie authoritatively and adds the
 * subject token; where the request fails, the visitor's choice still stands
 * locally and only the server-side record is missing.
 */
function writeStatusCookie(status) {
  try {
    const maxAge = 60 * 60 * 24 * 365;
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${STATUS_COOKIE}=${status}; path=/; max-age=${maxAge}; SameSite=Lax${secure}`;
  } catch {
    // Cookies unavailable — the banner will ask again next load.
  }
}

/**
 * Get current consent status.
 * @returns {'accepted'|'declined'|null}
 */
export function getConsentStatus() {
  return readStatusCookie();
}

/**
 * Whether the user has accepted cookies.
 */
export function hasConsent() {
  return getConsentStatus() === ACCEPTED;
}

/**
 * Accept cookies — record the decision server-side, then load Google
 * Analytics + the Awin MasterTag.
 */
export async function acceptCookies() {
  writeStatusCookie(ACCEPTED);
  await recordConsent(ACCEPTED);

  loadGoogleAnalytics();

  // Load Awin MasterTag if the current route is not excluded (checkout, etc.).
  // The router.afterEach hook in router/index.js handles subsequent navigation.
  const currentRouteName = window?.__appRouter?.currentRoute?.value?.name;
  if (shouldLoadAwin(currentRouteName)) {
    loadAwinMasterTag();
  }
}

/**
 * Decline cookies — record the refusal server-side (which also expires the
 * HttpOnly Awin click cookie, something this script cannot do) and scrub the
 * Awin MasterTag if it was loaded from a prior session.
 */
export async function declineCookies() {
  writeStatusCookie(DECLINED);
  await recordConsent(DECLINED);

  unloadAwinMasterTag();
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
