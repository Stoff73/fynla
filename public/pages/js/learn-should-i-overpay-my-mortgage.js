/**
 * learn-should-i-overpay-my-mortgage.js — page-specific interactions for
 * /learn/should-i-overpay-my-mortgage
 *
 * Handles: guide nav category tab filtering.
 * All structural HTML is server-rendered — JS enhances only.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    wireGuideNavTabs();
  });

  function wireGuideNavTabs() {
    var tabs  = document.querySelectorAll('[data-guide-tab]');
    var links = document.querySelectorAll('[data-guide-link]');

    if (!tabs.length || !links.length) return;

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var cat = tab.getAttribute('data-guide-tab');

        tabs.forEach(function (t) {
          t.classList.remove('is-active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');

        links.forEach(function (link) {
          var linkCat = link.getAttribute('data-guide-link');
          if (cat === 'all' || linkCat === cat) {
            link.classList.remove('guide-nav__link--hidden');
          } else {
            link.classList.add('guide-nav__link--hidden');
          }
        });
      });
    });
  }
}());
