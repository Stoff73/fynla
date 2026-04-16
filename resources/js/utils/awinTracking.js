/**
 * Awin affiliate attribution — browser-side tracking.
 *
 * Responsibilities:
 *   1. Inject the Awin MasterTag script into <head> after cookie consent.
 *   2. Set window.AWIN.Tracking.Sale on the confirmation page and inject the
 *      fallback conversion pixel so attribution still fires if the MasterTag
 *      is blocked by an ad-blocker.
 *   3. Respect route exclusions — the MasterTag must not load on checkout
 *      pages that show payment widgets (Awin's own guidance).
 *
 * Mirrors the pattern in services/analyticsService.js and the loadGoogleAnalytics()
 * helper in utils/cookieConsent.js — all network I/O is guarded so tracking
 * failures can never break the app.
 *
 * Environment variables (Vite):
 *   - VITE_AWIN_ENABLED         - master switch ('true' to enable)
 *   - VITE_AWIN_MERCHANT_ID     - Fynla's Awin merchant ID (default 126105)
 *   - VITE_AWIN_MASTER_TAG_URL  - MasterTag script URL
 *   - VITE_AWIN_FALLBACK_PIXEL  - fallback pixel base URL
 */

const MERCHANT_ID = import.meta.env.VITE_AWIN_MERCHANT_ID || '126105';
const ENABLED = String(import.meta.env.VITE_AWIN_ENABLED || '').toLowerCase() === 'true';
const MASTER_TAG_URL = import.meta.env.VITE_AWIN_MASTER_TAG_URL || `https://www.dwin1.com/${MERCHANT_ID}.js`;
const FALLBACK_PIXEL_BASE = import.meta.env.VITE_AWIN_FALLBACK_PIXEL || 'https://www.awin1.com/sread.img';

const MASTER_TAG_ID = 'awin-master-tag';
const FALLBACK_PIXEL_ID = 'awin-fallback-pixel';

// Vue route names where the MasterTag must NOT load. Primary reason: the
// Revolut checkout widget displays card inputs — Awin's own docs require
// tags be removed from pages that process payment info.
const EXCLUDED_ROUTE_NAMES = new Set([
  'Checkout',
  'checkout',
  'auth.checkout',
  'payment.confirm',
]);

let masterTagLoaded = false;

export function isEnabled() {
  return ENABLED;
}

/**
 * Whether the MasterTag should be loaded for the given route.
 * Returns false on sensitive routes or when Awin is disabled.
 */
export function shouldLoadAwin(routeName) {
  if (!ENABLED) return false;
  if (routeName && EXCLUDED_ROUTE_NAMES.has(routeName)) return false;
  return true;
}

/**
 * Inject the Awin MasterTag into <head>. Safe to call multiple times — the
 * guard on `masterTagLoaded` and the DOM id check mean repeated calls are
 * no-ops.
 */
export function loadMasterTag() {
  if (!ENABLED) return;
  if (typeof document === 'undefined') return;
  if (masterTagLoaded) return;
  if (document.getElementById(MASTER_TAG_ID)) {
    masterTagLoaded = true;
    return;
  }

  try {
    const script = document.createElement('script');
    script.id = MASTER_TAG_ID;
    script.src = MASTER_TAG_URL;
    script.defer = true;
    document.body.appendChild(script);
    masterTagLoaded = true;
  } catch {
    // Tracking must never break the app
  }
}

/**
 * Remove the MasterTag from the DOM. Called when the user declines cookies
 * or when navigating onto an excluded route.
 */
export function unloadMasterTag() {
  if (typeof document === 'undefined') return;
  const el = document.getElementById(MASTER_TAG_ID);
  if (el && el.parentNode) {
    el.parentNode.removeChild(el);
  }
  masterTagLoaded = false;

  // Also scrub any Sale object left behind so a later MasterTag reload
  // cannot re-fire against stale data.
  if (typeof window !== 'undefined' && window.AWIN && window.AWIN.Tracking) {
    try {
      delete window.AWIN.Tracking.Sale;
    } catch {
      /* noop */
    }
  }
}

/**
 * Fire an Awin browser-side conversion. Sets the AWIN.Tracking.Sale object
 * (consumed by the MasterTag) AND injects a fallback <img> pixel so the sale
 * is still recorded even if the MasterTag is blocked.
 *
 * Expected params shape:
 *   {
 *     order_ref: 'FYN-PAY-123',
 *     amount: '10.99',               // decimal GBP string
 *     currency: 'GBP',
 *     voucher_code: 'LAUNCH50' | '',
 *     customer_acquisition: 'new' | 'existing',
 *     commission_group: 'SUB',
 *   }
 */
export function fireConversion(params) {
  if (!ENABLED) return;
  if (typeof window === 'undefined' || !params) return;

  try {
    // Populate the Sale object the MasterTag polls for.
    window.AWIN = window.AWIN || {};
    window.AWIN.Tracking = window.AWIN.Tracking || {};
    window.AWIN.Tracking.Sale = {
      amount: params.amount,
      orderRef: params.order_ref,
      parts: `${params.commission_group || 'SUB'}:${params.amount}`,
      currency: params.currency || 'GBP',
      voucher: params.voucher_code || '',
      test: '0',
      channel: 'aw',
      customerAcquisition: params.customer_acquisition || 'existing',
    };

    // Make sure the MasterTag is present so it can read the Sale object.
    loadMasterTag();

    // Inject the fallback pixel as a safety net (replaces any prior pixel
    // in case of multiple calls — unlikely but cheap to guard).
    const prior = document.getElementById(FALLBACK_PIXEL_ID);
    if (prior && prior.parentNode) {
      prior.parentNode.removeChild(prior);
    }

    const query = new URLSearchParams({
      tt: 'ns',
      tv: '2',
      merchant: MERCHANT_ID,
      amount: params.amount,
      ch: 'aw',
      cr: params.currency || 'GBP',
      ref: params.order_ref,
      parts: `${params.commission_group || 'SUB'}:${params.amount}`,
      vc: params.voucher_code || '',
      customeracquisition: params.customer_acquisition || 'existing',
    });

    const img = document.createElement('img');
    img.id = FALLBACK_PIXEL_ID;
    img.src = `${FALLBACK_PIXEL_BASE}?${query.toString()}`;
    img.width = 0;
    img.height = 0;
    img.border = 0;
    img.style.position = 'absolute';
    img.style.left = '-9999px';
    img.setAttribute('aria-hidden', 'true');
    document.body.appendChild(img);
  } catch {
    // Tracking must never break the app
  }
}

/**
 * Reset the module-level loaded flag. Used in tests.
 */
export function _resetForTests() {
  masterTagLoaded = false;
}
