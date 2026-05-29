/**
 * Fynla pricing page — interactive billing toggle
 *
 * Handles monthly/yearly switch. Updates displayed prices and
 * CTA link query strings without any framework dependency.
 *
 * FAQ accordion and carousel JS live in site.js — not re-implemented here.
 */
(function () {
  'use strict';

  /* ── Pricing data (pence → display strings) ──────────────────────
     Source: SubscriptionPlanSeeder.php
     launch_monthly / launch_yearly = discounted launch prices
     full_monthly  / full_yearly    = undiscounted rack prices
     monthly_equiv = yearly launch price expressed as per-month with saving
  ──────────────────────────────────────────────────────────────── */
  var PLANS = {
    student: {
      monthly: { full: '£4.99', launch: '£3.99', period: '/month', equiv: null },
      yearly:  { full: '£45.00', launch: '£30.00', period: '/year', equiv: '£2.50/mo — save 37%' },
    },
    standard: {
      monthly: { full: '£14.99', launch: '£10.99', period: '/month', equiv: null },
      yearly:  { full: '£135.00', launch: '£100.00', period: '/year', equiv: '£8.33/mo — save 24%' },
    },
    family: {
      monthly: { full: '£21.99', launch: '£14.99', period: '/month', equiv: null },
      yearly:  { full: '£199.00', launch: '£150.00', period: '/year', equiv: '£12.50/mo — save 17%' },
    },
    pro: {
      monthly: { full: '£29.99', launch: '£19.99', period: '/month', equiv: null },
      yearly:  { full: '£269.99', launch: '£200.00', period: '/year', equiv: '£16.67/mo — save 17%' },
    },
  };

  /* CTA base URLs — query param 'billing' is appended dynamically */
  var CTA_BASE = {
    student:  '/register?plan=student',
    standard: '/register?plan=standard',
    family:   '/register?plan=family',
    pro:      '/register?plan=pro',
  };

  var isYearly = true; /* default to yearly (matches Vue source) */

  /* ── Element references ──────────────────────────────────────── */
  var btnMonthly = document.getElementById('btn-monthly');
  var btnYearly  = document.getElementById('btn-yearly');
  var saveLabel  = document.getElementById('save-label');

  if (!btnMonthly || !btnYearly) return; /* guard — element not present */

  /* ── Apply prices for current cycle ─────────────────────────── */
  function applyPrices() {
    var cycle = isYearly ? 'yearly' : 'monthly';

    Object.keys(PLANS).forEach(function (slug) {
      var data = PLANS[slug][cycle];

      var elFull   = document.getElementById(slug + '-full');
      var elLaunch = document.getElementById(slug + '-launch');
      var elPeriod = document.getElementById(slug + '-period');
      var elEquiv  = document.getElementById(slug + '-equiv');
      var elCta    = document.getElementById('cta-' + slug);

      if (elFull)   elFull.textContent   = data.full;
      if (elLaunch) elLaunch.textContent = data.launch;
      if (elPeriod) elPeriod.textContent = data.period;
      if (elEquiv) {
        if (data.equiv) {
          elEquiv.textContent = data.equiv;
          elEquiv.hidden = false;
        } else {
          elEquiv.hidden = true;
        }
      }
      if (elCta) {
        elCta.href = CTA_BASE[slug] + '&billing=' + cycle;
      }
    });

    /* Update toggle button ARIA state */
    btnMonthly.setAttribute('aria-pressed', isYearly ? 'false' : 'true');
    btnYearly.setAttribute('aria-pressed',  isYearly ? 'true'  : 'false');

    /* Swap active class */
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

  /* ── Wire buttons ────────────────────────────────────────────── */
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

  /* ── Keyboard support for toggle buttons ─────────────────────── */
  [btnMonthly, btnYearly].forEach(function (btn) {
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        btn.click();
      }
    });
  });

  /* ── Initial render (yearly is default, already shown in HTML) ── */
  applyPrices();

}());
