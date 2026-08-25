/* =============================================================================
 * cookie-consent.js — server-rendered public-page cookie banner.
 *
 * The public landing + funnel pages are server-rendered (index.php, savetax.php,
 * savetax-plan.php) and do NOT mount the Vue SPA, so the SPA's CookieBanner.vue
 * never shows there — historically the prompt only appeared once the journey
 * reached the first SPA page (registration). This vanilla banner restores the
 * prompt at the landing/funnel.
 *
 * The decision is NOT stored here. It is POSTed to /api/cookie-consent, which
 * records it against the visitor in user_consents and sets the
 * `fyn_cookie_consent` cookie — the same cookie the SPA banner and the
 * server-side affiliate middleware read (W-0049). One decision, one record,
 * one place that writes it, whichever surface asked. Mirror the SPA banner's
 * copy and two-step decline flow so the experience is identical across
 * surfaces.
 * ========================================================================== */
(function () {
  'use strict';

  var STATUS_COOKIE = 'fyn_cookie_consent';

  function getConsent() {
    try {
      var match = document.cookie.match(new RegExp('(?:^|; )' + STATUS_COOKIE + '=([^;]*)'));
      var value = match ? decodeURIComponent(match[1]) : null;
      return (value === 'accepted' || value === 'declined') ? value : null;
    } catch (e) { return null; }
  }

  // The Awin click reference the visitor is still carrying, if any. An
  // affiliate landing is a single request and this banner is answered after
  // it, so the reference has to travel with the decision or it is lost.
  function clickReference() {
    try {
      return new URLSearchParams(window.location.search).get('awc');
    } catch (e) { return null; }
  }

  function setConsent(value) {
    // Cookie first so the visitor is never held behind the banner by a slow or
    // failed request; the server's response re-sets the same host-only cookie
    // and adds the subject token that ties the record to this browser.
    writeStatusCookie(value);

    var body = { status: value };
    var awc = clickReference();
    if (awc) body.awc = awc;

    try {
      fetch((window.FYNLA_BASE || '') + '/api/cookie-consent', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body)
      })['catch'](function () { /* choice already applied locally */ });
    } catch (e) { /* choice already applied locally */ }
  }

  function writeStatusCookie(value) {
    try {
      var secure = window.location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = STATUS_COOKIE + '=' + value + '; path=/; max-age=31536000; SameSite=Lax' + secure;
    } catch (e) { /* cookies unavailable */ }
  }

  // Already chosen (this visit or a prior one) — never re-ask.
  if (getConsent() !== null) return;

  var ICON_COOKIE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
  var ICON_WARN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';

  function injectStyles() {
    if (document.getElementById('cc-styles')) return;
    var css =
      '.cc-overlay{position:fixed;inset:0;z-index:2000;display:flex;align-items:flex-end;justify-content:center;padding:0 16px 32px}' +
      '.cc-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.30)}' +
      '.cc-card{position:relative;background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(31,42,68,.30);max-width:32rem;width:100%;padding:24px;font-family:"Segoe UI",Inter,system-ui,sans-serif}' +
      '.cc-row{display:flex;align-items:flex-start;gap:12px}' +
      '.cc-icon{width:24px;height:24px;flex:0 0 auto;margin-top:2px;color:var(--violet-500,#7C6BD6)}' +
      '.cc-title{font-size:.875rem;font-weight:700;color:var(--horizon-500,#1F2A44);margin:0}' +
      '.cc-text{font-size:.875rem;color:var(--neutral-500,#717171);margin:4px 0 0}' +
      '.cc-text a{color:var(--raspberry-500,#E83E6D);text-decoration:none}' +
      '.cc-text a:hover{text-decoration:underline}' +
      '.cc-actions{display:flex;gap:12px;margin-top:16px}' +
      '.cc-btn{flex:1;padding:10px 16px;border-radius:8px;font-size:.875rem;font-weight:500;cursor:pointer;border:1px solid transparent;transition:background .15s ease,border-color .15s ease}' +
      '.cc-btn--accept{background:var(--raspberry-500,#E83E6D);color:#fff}' +
      '.cc-btn--accept:hover{background:var(--raspberry-600,#DB2777)}' +
      '.cc-btn--decline{background:#fff;color:var(--neutral-500,#717171);border-color:var(--light-gray,#EEEEEE)}' +
      '.cc-btn--decline:hover{background:var(--eggshell-500,#F7F6F4)}';
    var el = document.createElement('style');
    el.id = 'cc-styles';
    el.textContent = css;
    document.head.appendChild(el);
  }

  var overlay;

  function close() {
    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
  }

  function renderInitial() {
    overlay.querySelector('.cc-card').innerHTML =
      '<div class="cc-row"><span class="cc-icon">' + ICON_COOKIE + '</span>' +
      '<div><h3 class="cc-title">Cookie Preferences</h3>' +
      '<p class="cc-text">We use cookies to help analyse how you use our site. You can accept or decline. ' +
      '<a href="/privacy">Privacy Policy</a></p></div></div>' +
      '<div class="cc-actions">' +
      '<button type="button" class="cc-btn cc-btn--accept" data-cc="accept">Accept Cookies</button>' +
      '<button type="button" class="cc-btn cc-btn--decline" data-cc="warn">Decline Cookies</button>' +
      '</div>';
  }

  function renderWarning() {
    overlay.querySelector('.cc-card').innerHTML =
      '<div class="cc-row"><span class="cc-icon">' + ICON_WARN + '</span>' +
      '<div><h3 class="cc-title">Limited Functionality</h3>' +
      '<p class="cc-text">Without cookies, some features including registration will be unavailable. ' +
      'Google Analytics has been disabled.</p></div></div>' +
      '<div class="cc-actions">' +
      '<button type="button" class="cc-btn cc-btn--accept" data-cc="accept">Accept Cookies</button>' +
      '<button type="button" class="cc-btn cc-btn--decline" data-cc="decline">Continue Without Cookies</button>' +
      '</div>';
  }

  function onClick(e) {
    var btn = e.target.closest('[data-cc]');
    if (!btn) return;
    var action = btn.getAttribute('data-cc');
    if (action === 'accept') { setConsent('accepted'); close(); }
    else if (action === 'decline') { setConsent('declined'); close(); }
    else if (action === 'warn') { renderWarning(); }
  }

  function show() {
    injectStyles();
    overlay = document.createElement('div');
    overlay.className = 'cc-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'Cookie preferences');
    overlay.innerHTML = '<div class="cc-backdrop"></div><div class="cc-card"></div>';
    renderInitial();
    overlay.addEventListener('click', onClick);
    document.body.appendChild(overlay);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', show);
  } else {
    show();
  }
})();
