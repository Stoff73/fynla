/**
 * compare-moneyhelper.js
 * Page-specific JS for /compare/fynla-vs-moneyhelper
 *
 * This page is fully static — no API fetches, no Vue reactivity to replicate.
 * The only interactive feature from the Vue source was the CTA button's
 * @click.prevent="$router.push({ query: { demo: 'true' } })" which opened the
 * demo modal. In the PHP page that button is replaced with a plain anchor link
 * to /register?from=demo, so no JS polyfill is needed for that interaction.
 *
 * This file is intentionally minimal — site.js handles the nav/mobile menu.
 */

(function () {
  'use strict';

  // Guard: only run if we are on this page
  if (!document.querySelector('.compare-content')) return;

  // No page-specific interactions required.
  // site.js provides: mega menu, mobile accordion, active nav link highlighting.
}());
