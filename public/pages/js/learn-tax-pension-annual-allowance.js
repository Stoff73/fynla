/**
 * Pension Annual Allowance page — page-specific interactions.
 * GuideNav tab switching and link highlighting.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.guide-nav-tabs')) return;
    wireGuideNav('/learn/tax/pension-annual-allowance');
  });

  function wireGuideNav(currentPath) {
    var tabs   = document.querySelectorAll('.guide-nav-tabs__tab');
    var links  = document.querySelectorAll('.guide-nav-link');
    var allLinks = Array.from(links);

    // Mark active link
    allLinks.forEach(function (link) {
      if (link.getAttribute('href') === currentPath) {
        link.classList.add('guide-nav-link--active');
        var dot = link.querySelector('.guide-nav-link__dot');
        if (dot) dot.style.background = 'white';
      }
    });

    // Tab switching
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');
        var category = tab.getAttribute('data-category');

        allLinks.forEach(function (link) {
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
