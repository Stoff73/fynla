/**
 * Fynla — advisors page interactions
 *
 * Nav, footer, FAQ, and carousel JS all live in site.js.
 * This file handles only advisors-page-specific behaviour.
 *
 * Currently no page-specific interactions are required beyond what
 * site.js provides. This file is included for future extensibility
 * and to establish the cache-busting ?v= versioning contract.
 */
(function () {
  'use strict';

  // Smooth scroll for any in-page anchor links on this page
  document.addEventListener('DOMContentLoaded', function () {
    var anchors = document.querySelectorAll('a[href^="#"]');
    if (!anchors.length) return;

    anchors.forEach(function (anchor) {
      anchor.addEventListener('click', function (e) {
        var targetId = anchor.getAttribute('href').slice(1);
        var target   = document.getElementById(targetId);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  });

}());
