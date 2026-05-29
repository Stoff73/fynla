/**
 * compare-planning.js
 * Page-specific interactions for /compare/fynla-vs-financial-planning-platform
 *
 * This page has no complex Vue-sourced interactivity beyond the demo CTA.
 * All nav/footer wiring is handled by site.js.
 * No inline event handlers — all wiring happens here on DOMContentLoaded.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    highlightActiveComparePage();
  });

  /**
   * Mark this compare page as active in the nav (the nav active-link logic
   * in site.js matches on exact path or prefix; /compare/* pages sit under
   * no top-level nav entry, so no additional active state is needed here.
   * This stub is retained for future extensibility).
   */
  function highlightActiveComparePage() {
    // No additional active-state logic required — site.js handles nav links.
  }

}());
