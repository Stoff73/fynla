/**
 * Fynla — faq page interactions
 *
 * FAQ accordion is wired by site.js (data-faq-item).
 * This file adds the category filter tab behaviour.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    wireFaqFilter();
  });

  /**
   * wireFaqFilter
   *
   * Reads [data-faq-filter] buttons and shows/hides [data-faq-category]
   * groups accordingly. Uses [hidden] so the global [hidden] reset in
   * global.css keeps them out of layout without needing display:none.
   */
  function wireFaqFilter() {
    var tabs   = document.querySelectorAll('[data-faq-filter]');
    var groups = document.querySelectorAll('[data-faq-category]');

    if (!tabs.length || !groups.length) return;

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var category = tab.getAttribute('data-faq-filter');

        /* Update tab active state */
        tabs.forEach(function (t) {
          t.classList.remove('is-active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');

        /* Show/hide groups */
        groups.forEach(function (group) {
          var groupCat = group.getAttribute('data-faq-category');
          if (category === 'all' || groupCat === category) {
            group.hidden = false;
          } else {
            group.hidden = true;
          }
        });
      });
    });
  }

}());
