/**
 * Glossary page — page-specific interactions.
 * No framework. Vanilla JS only.
 *
 * Features:
 *  - Smooth scroll to letter sections when alphabet nav is clicked
 *    (native scroll-behavior:smooth handles this; this file is a hook
 *     for any future enhancements).
 *  - No dynamic content injection — all glossary terms are static PHP.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    highlightActiveLetterOnScroll();
  });

  /**
   * Adds a visual indicator to the alphabet nav as the user scrolls
   * through letter sections.
   */
  function highlightActiveLetterOnScroll() {
    var nav = document.querySelector('.glossary-alpha-nav');
    if (!nav) return;

    var sections = document.querySelectorAll('.glossary-letter-group[id]');
    if (!sections.length) return;

    var links = nav.querySelectorAll('.glossary-alpha-nav__letter:not(.glossary-alpha-nav__letter--inactive)');

    function getActiveLetter() {
      var scrollY = window.scrollY + 120; // offset for sticky nav height
      var active = null;
      sections.forEach(function (section) {
        if (section.offsetTop <= scrollY) {
          active = section.id; // id is e.g. "letter-A"
        }
      });
      return active;
    }

    function onScroll() {
      var activeId = getActiveLetter();
      links.forEach(function (link) {
        var href = link.getAttribute('href'); // e.g. "#letter-A"
        if (href === '#' + activeId) {
          link.setAttribute('aria-current', 'location');
          link.style.background = 'var(--light-pink-100)';
          link.style.color = 'var(--raspberry-500)';
        } else {
          link.removeAttribute('aria-current');
          link.style.background = '';
          link.style.color = '';
        }
      });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // run once on load
  }
}());
