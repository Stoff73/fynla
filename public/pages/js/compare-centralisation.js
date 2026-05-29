/**
 * compare-centralisation.js
 *
 * Page-specific JS for /compare/fynla-vs-financial-centralisation-platform
 *
 * Vue features converted to vanilla JS:
 *   - @click.prevent="$router.push({ query: { demo: 'true' } })" on the CTA button
 *     → replaced by the cta-band partial's plain <a href="/register"> link.
 *     No JS polyfill needed; the Vue handler only appended a demo query param
 *     which the PHP register route does not require.
 *
 * No dynamic data fetching is required on this page.
 * All interactive behaviour (nav, mobile accordion, mega menus, review carousel)
 * is handled by site.js which is loaded on every page.
 *
 * This file is intentionally minimal — it exists to satisfy the multi-file
 * architecture (one JS file per page) and to provide a hook for any future
 * page-specific enhancements.
 */

(function () {
  'use strict';

  // Guard: only run on the compare centralisation page
  if (!document.querySelector('.compare-content')) { return; }

  // No page-specific interactions required at this time.
  // The nav mega menus, mobile accordion, and CTA band are wired by site.js.

}());
