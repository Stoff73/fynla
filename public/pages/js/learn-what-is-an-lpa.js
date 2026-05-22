/**
 * learn-what-is-an-lpa.js
 *
 * Polyfills the GuideNav Vue component's category-tab filter.
 * Vue feature replaced: v-for (tabs), @click (category filter), v-if/computed (filteredGuides).
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    wireGuideTabs();
  });

  /**
   * Guide category tab filter
   * Reads data-category on each .guide-link and shows/hides links
   * based on the active tab. Maps Vue's filteredGuides computed property.
   */
  function wireGuideTabs() {
    var panel = document.getElementById('guide-links-panel');
    if (!panel) return;

    var tabs  = document.querySelectorAll('.guide-tab');
    var links = panel.querySelectorAll('.guide-link');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var category = tab.getAttribute('data-category');

        // Update tab aria-selected + active class
        tabs.forEach(function (t) {
          t.setAttribute('aria-selected', 'false');
          t.classList.remove('is-active');
        });
        tab.setAttribute('aria-selected', 'true');
        tab.classList.add('is-active');

        // Filter links
        links.forEach(function (link) {
          if (category === 'all' || link.getAttribute('data-category') === category) {
            link.hidden = false;
          } else {
            link.hidden = true;
          }
        });
      });
    });
  }
}());
