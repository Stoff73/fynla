/**
 * stage/starting-out page — page-specific JS
 *
 * The "View demo" and "Try Starting out demo" links in the Vue source called
 * this.$store.dispatch('preview/loadPersona', 'student') then redirected to
 * /dashboard. On public pages, authenticated users with a session will reach
 * /dashboard directly; unauthenticated visitors are sent to /register.
 * No Vuex or SPA routing is available here — the demo links simply point to
 * /register, which is the correct public-page behaviour.
 *
 * All FAQ, carousel, and nav interactivity is handled by site.js.
 * This file is intentionally minimal.
 */
(function () {
  'use strict';
  /* Guard: only run on this page */
  if (!document.getElementById('hero')) return;
  /* No page-specific interactions required beyond what site.js provides. */
}());
