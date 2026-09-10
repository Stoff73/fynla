/**
 * The cookie banner's decline copy — one home (W-0546).
 *
 * Declining consent switches off Google Analytics and the affiliate tag and
 * nothing else: registration and sign-in run on the strictly necessary session
 * cookie (PECR reg 6(4)) and were never gated (W-0050). The old copy said
 * "some features including registration will be unavailable", which was
 * untrue and overstated the cost of refusing — the shape the ICO treats as a
 * consent dark pattern.
 *
 * `public/pages/js/cookie-consent.js` is a plain script on the server-rendered
 * pages and cannot import this module; `constants/__tests__/cookieCopy.spec.js`
 * pins that it carries the same sentences.
 */
export const COOKIE_DECLINE_TITLE = 'Without optional cookies';
export const COOKIE_DECLINE_TEXT = 'Declining switches off Google Analytics and our affiliate tracking, and nothing else. Registration, signing in and every feature work as normal.';
