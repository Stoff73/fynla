/**
 * stage/protecting-and-growing — page-specific JavaScript
 *
 * All shared behaviour (nav toggle, mega menu, FAQ accordion, review carousel)
 * is handled by site.js which is loaded on every page.
 *
 * Vue interactions replaced:
 *   launchDemo() — dispatched preview/loadPersona('young_family') then routed to
 *   /dashboard. On the public PHP page the "Try James & Emily's demo" CTA links
 *   directly to /register, which is the correct unauthenticated-user destination.
 *
 *   JourneyMap component — interactive stage-progress visualisation with Vue
 *   reactivity. No static HTML equivalent exists; omitted from the PHP page.
 *
 * No page-specific interactions are needed beyond site.js.
 */
