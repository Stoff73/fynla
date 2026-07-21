/**
 * Inheritance Tax Thresholds page — page-specific interactions.
 * GuideNav tab switching and link highlighting.
 */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.guide-nav-tabs')) return;
    wireGuideNav('/learn/tax/iht-thresholds');
  });
  function wireGuideNav(currentPath) {
    var tabs  = document.querySelectorAll('.guide-nav-tabs__tab');
    var links = document.querySelectorAll('.guide-nav-link');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');
        var category = tab.getAttribute('data-category');
        links.forEach(function (link) {
          var linkCat = link.getAttribute('data-category');
          if (category === 'all' || linkCat === category) {
            link.removeAttribute('hidden');
          } else {
            link.setAttribute('hidden', '');
          }
        });
      });
    });
  }
}());
