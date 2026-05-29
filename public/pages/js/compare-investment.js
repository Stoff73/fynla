/**
 * compare-investment.js
 * Page-specific JS for /compare/fynla-vs-financial-investment-platform
 *
 * This page has no dynamic API fetches and no interactive components
 * beyond the shared nav/mobile-menu (handled by site.js).
 *
 * The only Vue feature that needed conversion:
 *   @click.prevent="$router.push({ query: { demo: 'true' } })"
 *   — replaced with a plain href="/register?from=demo" on the CTA button.
 *   No JS polyfill required.
 *
 * Guard: all selectors are null-checked before use so this file is
 * a safe no-op on any page that doesn't include this markup.
 */

(function () {
  'use strict';

  // Nothing to initialise on this page beyond what site.js already handles.
  // This file is intentionally minimal — it exists as a placeholder so the
  // <script defer src="/pages/js/compare-investment.js"> tag is valid and
  // future page-specific enhancements can be added here without touching
  // site.js or the PHP file.

})();
