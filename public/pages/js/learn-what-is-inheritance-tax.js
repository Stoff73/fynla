/**
 * learn-what-is-inheritance-tax.js
 * Guide category tab filter — polyfills GuideNav Vue component.
 */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('guide-links-panel');
    if (!panel) return;
    var tabs  = document.querySelectorAll('.guide-tab');
    var links = panel.querySelectorAll('.guide-link');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var category = tab.getAttribute('data-category');
        tabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); t.classList.remove('is-active'); });
        tab.setAttribute('aria-selected', 'true');
        tab.classList.add('is-active');
        links.forEach(function (link) {
          link.hidden = !(category === 'all' || link.getAttribute('data-category') === category);
        });
      });
    });
  });
}());
