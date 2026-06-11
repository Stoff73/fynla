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
  var cancelLabel = document.getElementById('cancel-label');

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

  var CHECK_SVG = '<svg class="plan-card__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';

  /* A capability is "on" when full, teaser or limited; 'none'/missing = off. */
  function isOn(cap) { return cap === 'full' || cap === 'teaser' || cap === 'limited'; }

  /* Combined accounts label — savings accounts, investments and pensions in one
     bullet. When all three are uncapped (full) it reads "Unlimited savings
     accounts, investments and pensions"; when capped it shows the "up to N" caps
     for each, e.g. "Up to 3 savings accounts, 2 investments and 5 pensions". */
  function accountsLabel(m, c) {
    var sOn = isOn(m.savings_account), iOn = isOn(m.investment), pOn = isOn(m.pension_account);
    if (!sOn && !iOn && !pOn) return null;
    var sLim = m.savings_account === 'limited';
    var iLim = m.investment === 'limited';
    var pLim = m.pension_account === 'limited';

    if (!sLim && !iLim && !pLim) return 'Unlimited savings accounts, investments and pensions';

    var parts = [];
    if (sOn) parts.push((sLim && c.savings_account != null ? 'up to ' + c.savings_account + ' ' : '') + 'savings accounts');
    if (iOn) parts.push((iLim && c.investment != null ? c.investment + ' ' : '') + 'investments');
    if (pOn) parts.push((pLim && c.pension_account != null ? c.pension_account + ' ' : '') + 'pensions');

    var joined = parts.length <= 1
      ? (parts[0] || '')
      : parts.slice(0, -1).join(', ') + ' and ' + parts[parts.length - 1];
    return joined.charAt(0).toUpperCase() + joined.slice(1);
  }

  /* Property label — capped reads "Up to N properties"; uncapped "Unlimited
     properties". The cap comes from the live count_caps (single source of
     truth), so it stays in sync with what the tier actually grants. */
  function propertyLabel(m, c) {
    if (!isOn(m.property)) return null;
    if (c.property != null) return 'Up to ' + c.property + ' properties';
    return 'Unlimited properties';
  }

  /* Build the curated feature set for a tier as {id, label} entries (included
     items only — crossed-out capabilities are omitted). The id is stable across
     tiers so paid cards can show only the DELTA vs the tier below; the label can
     change between tiers (e.g. capped → unlimited) which counts as an upgrade. */
  function buildFeatures(tier) {
    var m = tier.capability_matrix || {};
    var c = tier.count_caps || {};
    var out = [];
    var add = function (id, label) { if (label) out.push({ id: id, label: label }); };

    if (isOn(m.dashboard)) add('dashboard', 'Financial dashboard');
    if (isOn(m.income) || isOn(m.expenditure) || isOn(m.liabilities)) add('tracking', 'Income, expenditure and liabilities tracking');
    if (isOn(m.protection)) add('protection', 'Protection module');
    add('accounts', accountsLabel(m, c));
    if (isOn(m.investments_exotic)) add('investments_exotic', 'Alternative investments');
    if (isOn(m.retirement_decumulation)) add('retirement', 'Retirement planning');
    add('property', propertyLabel(m, c));
    if (isOn(m.chattels)) add('chattels', 'Unlimited personal valuables');
    if (isOn(m.goals)) add('goals', 'Goals and life events');
    if (isOn(m.family_module) || isOn(m.benefits_child)) add('family', 'Family module inc benefit modelling and protection');
    if (m.estate === 'full') add('estate', 'Full estate planning');
    else if (m.estate === 'teaser') add('estate', 'Estate planning preview');
    if (isOn(m.letter_to_spouse)) add('letter_to_spouse', 'Letter to spouse and expression of wishes');

    return out;
  }

  /* Render a card's feature list. Free (no prevTier) shows its full list; paid
     tiers show only what they ADD over the previous tier (new ids, or ids whose
     label changed — i.e. a genuine upgrade), keeping the cards short. */
  function renderFeatures(key, tier, prevName, prevTier) {
    var listEl = document.getElementById(key + '-features');
    var plusEl = document.getElementById(key + '-plus');
    if (!listEl) return;

    var features = buildFeatures(tier);

    if (prevTier) {
      var prevById = {};
      buildFeatures(prevTier).forEach(function (f) { prevById[f.id] = f.label; });
      features = features.filter(function (f) {
        return !(f.id in prevById) || prevById[f.id] !== f.label;
      });
    }

    var html = features.map(function (f) {
      return '<li class="plan-card__feature">' + CHECK_SVG + f.label + '</li>';
    }).join('');
    if (html) listEl.innerHTML = html;

    if (plusEl) {
      if (prevName) {
        plusEl.textContent = 'Everything in ' + prevName + ', plus:';
        plusEl.hidden = false;
      } else {
        plusEl.hidden = true;
      }
    }
  }

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
      var elCtaTop = document.getElementById('cta-' + key + '-top');

      // Yearly: show the monthly-equivalent as the big price, with the total
      // billed annually underneath. Monthly: show the monthly price directly,
      // with a green "Cancel anytime" reassurance line in the equiv slot.
      if (isYearly && t.yearly) {
        if (elPrice)  elPrice.textContent  = fmt(Math.round(t.yearly / 12));
        if (elPeriod) elPeriod.textContent = '/month';
        if (elEquiv)  elEquiv.textContent  = fmt(t.yearly) + ' billed yearly';
      } else {
        if (elPrice)  elPrice.textContent  = fmt(t.monthly);
        if (elPeriod) elPeriod.textContent = '/month';
        if (elEquiv)  elEquiv.textContent  = 'Cancel anytime';
      }
      var href = BASE + '/register?plan=' + key + '&billing=' + cycle;
      if (elCta)    elCta.href    = href;
      if (elCtaTop) elCtaTop.href = href;
    });

    btnMonthly.setAttribute('aria-pressed', isYearly ? 'false' : 'true');
    btnYearly.setAttribute('aria-pressed',  isYearly ? 'true'  : 'false');

    if (isYearly) {
      btnYearly.classList.add('billing-toggle__btn--active');
      btnMonthly.classList.remove('billing-toggle__btn--active');
      if (saveLabel) saveLabel.hidden = false;
      if (cancelLabel) cancelLabel.hidden = true;
    } else {
      btnMonthly.classList.add('billing-toggle__btn--active');
      btnYearly.classList.remove('billing-toggle__btn--active');
      if (saveLabel) saveLabel.hidden = true;
      if (cancelLabel) cancelLabel.hidden = false;
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

  /* Pull live tier config (price + capability matrix) from the single source of
     truth, then render each card's feature list to match the in-app pricing. */
  fetch(BASE + '/api/pricing-config', { headers: { Accept: 'application/json' } })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (json) {
      var rows = (json && json.data) || [];

      // Update prices.
      rows.forEach(function (t) {
        if (TIERS[t.tier]) {
          TIERS[t.tier] = { monthly: t.price_monthly_pence, yearly: t.price_annual_pence };
        }
      });
      applyPrices();

      // Render capability-matrix feature lists. "Everything in <prev>, plus:"
      // uses the on-page card names (Bronze/Silver/Gold), read from the headings.
      var nameOf = function (key) {
        var h = document.getElementById('plan-' + key + '-heading');
        return h ? h.textContent.trim() : null;
      };
      var byKey = {};
      rows.forEach(function (t) { byKey[t.tier] = t; });

      // Free card — its full feature list.
      var freeTier = byKey.free || {};
      renderFeatures('free', freeTier, null, null);

      // Premium card (tier1 element) — the page now shows a single paid plan, so
      // it lists the UNION of all paid tiers' capabilities (Bronze + Silver +
      // Gold), shown as the delta over Free ("Everything in Free, plus:").
      var freeById = {};
      buildFeatures(freeTier).forEach(function (f) { freeById[f.id] = f.label; });
      var merged = {}, ordered = [];
      ['tier1', 'tier2', 'tier3'].forEach(function (k) {
        var t = byKey[k];
        if (!t) return;
        buildFeatures(t).forEach(function (f) {
          if (!(f.id in merged)) ordered.push(f.id);
          merged[f.id] = f.label;   // a higher tier may upgrade the label
        });
      });
      var premiumLabels = ordered
        .filter(function (id) { return !(id in freeById) || freeById[id] !== merged[id]; })
        .map(function (id) { return merged[id]; });
      // Gold's storage differentiator isn't a capability-matrix item — append it.
      if (byKey.tier3) premiumLabels.push('More document uploads and storage');

      var pListEl = document.getElementById('tier1-features');
      var pPlusEl = document.getElementById('tier1-plus');
      if (pListEl && premiumLabels.length) {
        pListEl.innerHTML = premiumLabels.map(function (l) {
          return '<li class="plan-card__feature">' + CHECK_SVG + l + '</li>';
        }).join('');
      }
      if (pPlusEl) { pPlusEl.textContent = 'Everything in Free, plus:'; pPlusEl.hidden = false; }
    })
    .catch(function () { /* fallback static features already in the DOM */ });

  applyPrices();

}());
