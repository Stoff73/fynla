/**
 * Fynla pricing page — interactive billing toggle.
 *
 * Prices are the paid tiers (Tier 1/2/3) and come from the live tier store
 * via the public GET /api/pricing-config endpoint — the same single source of
 * truth as the in-app upgrade modal and the admin Tier Configuration screen.
 * Seeded values are kept inline as a fallback so the page still shows sensible
 * pricing if the request fails. There are no trials and no launch discounts.
 *
 * FAQ accordion and carousel JS live in site.js — not re-implemented here.
 */
(function () {
  'use strict';

  var btnMonthly = document.getElementById('btn-monthly');
  var btnYearly  = document.getElementById('btn-yearly');
  var saveLabel  = document.getElementById('save-label');

  if (!btnMonthly || !btnYearly) return; /* guard — element not present */

  var isYearly = true; /* yearly is the default */

  /* Paid-tier prices in pence. Fallback values mirror tier_configurations;
     overwritten by the live /api/pricing-config response when it arrives. */
  var TIERS = {
    tier1: { monthly: 499, yearly: 4990 },
    tier2: { monthly: 1499, yearly: 14990 },
    tier3: { monthly: 2999, yearly: 29990 },
  };

  /* The app is served under /fynla on dev (csjones) and at the root on prod. */
  var BASE = location.pathname.indexOf('/fynla') === 0 ? '/fynla' : '';

  function fmt(pence) {
    if (pence === null || pence === undefined) return '…';
    return '£' + (pence / 100).toFixed(2);
  }

  function applyPrices() {
    var cycle = isYearly ? 'yearly' : 'monthly';

    ['tier1', 'tier2', 'tier3'].forEach(function (key) {
      var t = TIERS[key];
      if (!t) return;
      var elPrice  = document.getElementById(key + '-price');
      var elPeriod = document.getElementById(key + '-period');
      var elEquiv  = document.getElementById(key + '-equiv');
      var elCta    = document.getElementById('cta-' + key);

      if (elPrice)  elPrice.textContent  = fmt(isYearly ? t.yearly : t.monthly);
      if (elPeriod) elPeriod.textContent = isYearly ? '/year' : '/month';
      if (elEquiv) {
        elEquiv.textContent = (isYearly && t.yearly)
          ? fmt(Math.round(t.yearly / 12)) + '/mo billed yearly'
          : '';
      }
      if (elCta) elCta.href = '/register?plan=' + key + '&billing=' + cycle;
    });

    btnMonthly.setAttribute('aria-pressed', isYearly ? 'false' : 'true');
    btnYearly.setAttribute('aria-pressed',  isYearly ? 'true'  : 'false');

    if (isYearly) {
      btnYearly.classList.add('billing-toggle__btn--active');
      btnMonthly.classList.remove('billing-toggle__btn--active');
      if (saveLabel) saveLabel.hidden = false;
    } else {
      btnMonthly.classList.add('billing-toggle__btn--active');
      btnYearly.classList.remove('billing-toggle__btn--active');
      if (saveLabel) saveLabel.hidden = true;
    }
  }

  btnMonthly.addEventListener('click', function () {
    if (!isYearly) return;
    isYearly = false;
    applyPrices();
  });

  btnYearly.addEventListener('click', function () {
    if (isYearly) return;
    isYearly = true;
    applyPrices();
  });

  [btnMonthly, btnYearly].forEach(function (btn) {
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        btn.click();
      }
    });
  });

  /* Pull live tier pricing from the single source of truth. */
  fetch(BASE + '/api/pricing-config', { headers: { Accept: 'application/json' } })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (json) {
      var rows = (json && json.data) || [];
      rows.forEach(function (t) {
        if (TIERS[t.tier]) {
          TIERS[t.tier] = { monthly: t.price_monthly_pence, yearly: t.price_annual_pence };
        }
      });
      applyPrices();
    })
    .catch(function () { /* fallback values already applied */ });

  applyPrices();

}());
