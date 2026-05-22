/**
 * Fynla — help page interactions
 *
 * Provides:
 *   - TOC scroll-spy: highlights the active TOC link as the user scrolls
 *     through the help sections.
 *   - Smooth scroll: clicking a TOC link scrolls the target section into view
 *     with offset compensation for the sticky nav (4rem = 64px).
 *
 * The FAQ accordion in #help-faqs is wired by site.js (data-faq-item).
 * This file does NOT re-implement the accordion.
 */
(function () {
  'use strict';

  var NAV_HEIGHT = 80; /* px — sticky nav (64px) + small buffer */

  document.addEventListener('DOMContentLoaded', function () {
    wireTocSmoothScroll();
    wireTocScrollSpy();
  });

  /* ------------------------------------------------------------------
     Smooth scroll with nav offset
     ------------------------------------------------------------------ */
  function wireTocSmoothScroll() {
    var links = document.querySelectorAll('[data-help-toc]');
    links.forEach(function (link) {
      link.addEventListener('click', function (e) {
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) !== '#') return;
        var target = document.querySelector(href);
        if (!target) return;
        e.preventDefault();
        var top = target.getBoundingClientRect().top + window.pageYOffset - NAV_HEIGHT;
        window.scrollTo({ top: top, behavior: 'smooth' });
        /* Update active state immediately on click */
        setActiveLink(link);
      });
    });
  }

  /* ------------------------------------------------------------------
     Scroll-spy using IntersectionObserver
     ------------------------------------------------------------------ */
  function wireTocScrollSpy() {
    var links = document.querySelectorAll('[data-help-toc]');
    if (!links.length) return;

    /* Build a map: section-id → toc-link */
    var linkMap = {};
    links.forEach(function (link) {
      var href = link.getAttribute('href');
      if (href && href.charAt(0) === '#') {
        linkMap[href.slice(1)] = link;
      }
    });

    var sectionIds = Object.keys(linkMap);
    if (!sectionIds.length) return;

    /* Track which sections are currently intersecting */
    var visibleSections = {};

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          visibleSections[entry.target.id] = entry.isIntersecting;
        });

        /* Highlight the first visible section (top-most in DOM order) */
        for (var i = 0; i < sectionIds.length; i++) {
          if (visibleSections[sectionIds[i]]) {
            setActiveLink(linkMap[sectionIds[i]]);
            return;
          }
        }
      },
      {
        rootMargin: '-' + NAV_HEIGHT + 'px 0px -40% 0px',
        threshold: 0,
      }
    );

    sectionIds.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) observer.observe(el);
    });
  }

  /* ------------------------------------------------------------------
     Set active TOC link
     ------------------------------------------------------------------ */
  function setActiveLink(activeLink) {
    var links = document.querySelectorAll('[data-help-toc]');
    links.forEach(function (link) {
      link.classList.remove('is-active');
    });
    if (activeLink) activeLink.classList.add('is-active');
  }

}());
