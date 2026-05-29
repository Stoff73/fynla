/**
 * Fynla — Compare: Fynla vs Spreadsheets
 * Page-specific JS. No framework — vanilla only.
 *
 * This page has no dynamic data fetches and no interactive widgets
 * beyond what site.js already handles (nav, mobile menu, mega menus).
 * This file is a deliberate no-op shell so the <script defer> tag
 * in the PHP file has a real target and the cache-busting version
 * string works correctly.
 *
 * If interactive features are added in future (e.g. a comparison
 * table toggle or a tab panel), add initialisers here wrapped in
 * null-checks so the file degrades gracefully when those elements
 * are absent.
 */

(function () {
  'use strict';

  // Guard: only run after the DOM is ready. Because this script is
  // loaded with defer it executes after DOMContentLoaded, but wrap
  // in a readyState check for robustness in case of future inlining.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    // No page-specific interactions required at launch.
    // site.js handles: nav dropdowns, mega menus, mobile accordion,
    // FAQ accordion, review carousel, active nav-link highlighting.
  }
}());
