/**
 * learn.js — page-specific interactions for /learn (LearnHubPage)
 *
 * Handles: category tab filtering of guide links.
 * All structural HTML is server-rendered — JS enhances only.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    wireCategoryTabs();
  });

  function wireCategoryTabs() {
    var tabs  = document.querySelectorAll('[data-learn-tab]');
    var links = document.querySelectorAll('[data-learn-link]');

    if (!tabs.length || !links.length) return;

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var cat = tab.getAttribute('data-learn-tab');

        /* update active tab */
        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        tabs.forEach(function (t) {
          if (t !== tab) t.setAttribute('aria-selected', 'false');
        });

        /* show/hide guide links */
        links.forEach(function (link) {
          var linkCat = link.getAttribute('data-learn-link');
          if (cat === 'all' || linkCat === cat) {
            link.classList.remove('guide-link--hidden');
          } else {
            link.classList.add('guide-link--hidden');
          }
        });
      });
    });
  }
}());
