/**
 * Fynla pricing page — canonical Free/Premium rendering.
 *
 * The page contains a complete server-rendered fallback. Live values replace
 * those defaults only when both canonical tier rows are returned by the public
 * pricing endpoint.
 */
(function () {
  'use strict';

  function annualSavingPercent(monthlyPence, annualPence) {
    if (!Number.isFinite(monthlyPence) || !Number.isFinite(annualPence) || monthlyPence <= 0 || annualPence <= 0) {
      return null;
    }

    var monthlyAnnualised = monthlyPence * 12;
    if (annualPence >= monthlyAnnualised) return null;

    return Math.round((1 - annualPence / monthlyAnnualised) * 100);
  }

  window.FynlaPricing = { annualSavingPercent: annualSavingPercent };

  var btnMonthly = document.getElementById('btn-monthly');
  var btnYearly = document.getElementById('btn-yearly');
  var saveLabel = document.getElementById('save-label');

  if (!btnMonthly || !btnYearly) return;

  var BASE = location.pathname.indexOf('/fynla') === 0 ? '/fynla' : '';
  var isYearly = true;
  var premium = { monthly: 699, yearly: 5999 };
  var yearlySavingPercent = annualSavingPercent(premium.monthly, premium.yearly);

  function formatMoney(pence) {
    return '£' + (pence / 100).toFixed(2);
  }

  function formatNumber(value) {
    return new Intl.NumberFormat('en-GB').format(value);
  }

  function formatStorage(value) {
    var amount = Number(value);
    if (!Number.isFinite(amount) || amount <= 0) return 'Not included';

    return amount.toLocaleString('en-GB', { maximumFractionDigits: 2 }) + ' GB document storage';
  }

  function isIncluded(value) {
    return value && value !== 'none';
  }

  function includedLabel(value) {
    return isIncluded(value) ? 'Included' : 'Not included';
  }

  function capacityLabel(tier, key, singular, plural) {
    var matrix = tier.capability_matrix || {};
    var caps = tier.count_caps || {};

    if (!isIncluded(matrix[key])) return 'Not included';
    if (caps[key] === null || caps[key] === undefined) return 'Unlimited ' + plural;

    var count = Number(caps[key]);
    return count + ' ' + (count === 1 ? singular : plural);
  }

  function goalCapacityLabel(tier, key, capKey, singular, plural) {
    var matrix = tier.capability_matrix || {};
    var caps = tier.count_caps || {};

    if (!isIncluded(matrix[key])) return 'Not included';
    if (caps[capKey] === null || caps[capKey] === undefined) return 'Unlimited ' + plural;

    var count = Number(caps[capKey]);
    return count + ' ' + (count === 1 ? singular : plural);
  }

  function estateLabel(value) {
    if (value === 'full') return 'Full planning';
    if (value === 'teaser') return 'Preview';
    return 'Not included';
  }

  function historyLabel(days) {
    if (days === null || days === undefined) return 'Full available history';
    return Number(days) + ' days';
  }

  function fynLabel(tokens) {
    if (!Number.isFinite(Number(tokens)) || Number(tokens) <= 0) return 'Not included';
    return formatNumber(Number(tokens)) + ' Fyn tokens each week';
  }

  function setText(id, value) {
    var element = document.getElementById(id);
    if (element) element.textContent = value;
  }

  function makeFeature(label) {
    var item = document.createElement('li');
    item.className = 'plan-card__feature';
    item.textContent = label;
    return item;
  }

  function replaceHighlights(tierKey, labels) {
    var list = document.getElementById(tierKey + '-features');
    if (!list) return;

    list.replaceChildren.apply(list, labels.map(makeFeature));
  }

  function applyPrices() {
    var price = document.getElementById('premium-price');
    var period = document.getElementById('premium-period');
    var equivalent = document.getElementById('premium-equiv');
    var cta = document.getElementById('cta-premium');
    var cycle = isYearly ? 'yearly' : 'monthly';

    if (isYearly) {
      if (price) price.textContent = formatMoney(premium.yearly);
      if (period) period.textContent = '/year';
      if (equivalent) equivalent.textContent = formatMoney(Math.round(premium.yearly / 12)) + ' per month equivalent';
    } else {
      if (price) price.textContent = formatMoney(premium.monthly);
      if (period) period.textContent = '/month';
      if (equivalent) equivalent.textContent = 'Billed monthly';
    }

    if (cta) cta.href = BASE + '/register?plan=premium&billing=' + cycle;

    btnMonthly.setAttribute('aria-pressed', isYearly ? 'false' : 'true');
    btnYearly.setAttribute('aria-pressed', isYearly ? 'true' : 'false');
    btnMonthly.classList.toggle('billing-toggle__btn--active', !isYearly);
    btnYearly.classList.toggle('billing-toggle__btn--active', isYearly);

    if (saveLabel) {
      saveLabel.textContent = yearlySavingPercent === null ? '' : 'Save ' + yearlySavingPercent + '%';
      saveLabel.hidden = !isYearly || yearlySavingPercent === null;
    }
  }

  function applyComparison(freeTier, premiumTier) {
    var tiers = { free: freeTier, premium: premiumTier };

    Object.keys(tiers).forEach(function (key) {
      var tier = tiers[key];
      var matrix = tier.capability_matrix || {};

      setText('comparison-' + key + '-cash-accounts', capacityLabel(tier, 'savings_account', 'cash or savings account', 'cash or savings accounts'));
      setText('comparison-' + key + '-investments', capacityLabel(tier, 'investment', 'investment', 'investments'));
      setText('comparison-' + key + '-property', capacityLabel(tier, 'property', 'property', 'properties'));
      setText('comparison-' + key + '-pensions', capacityLabel(tier, 'pension_account', 'pension', 'pensions'));
      setText('comparison-' + key + '-personal-valuables', includedLabel(matrix.chattels));
      setText('comparison-' + key + '-net-worth', includedLabel(matrix.dashboard));
      setText('comparison-' + key + '-combined-household', includedLabel(matrix.joint_household_view));
      setText('comparison-' + key + '-projections', includedLabel(matrix.future_value_projections));
      setText('comparison-' + key + '-save-tax', includedLabel(matrix.tax_strategy));
      setText('comparison-' + key + '-inheritance-tax', estateLabel(matrix.estate));
      setText('comparison-' + key + '-risk', includedLabel(matrix.risk_profile));
      setText('comparison-' + key + '-goals', goalCapacityLabel(tier, 'goals', 'goal', 'Goal', 'Goals'));
      setText('comparison-' + key + '-life-events', goalCapacityLabel(tier, 'life_events', 'life_event', 'Life Event', 'Life Events'));
      setText('comparison-' + key + '-fyn', fynLabel(tier.fyn_weekly_token_budget));
      setText('comparison-' + key + '-balance-history', historyLabel(tier.snapshot_surfacing_window_days));
      setText('comparison-' + key + '-basic-expenditure', includedLabel(matrix.expenditure));
      setText('comparison-' + key + '-detailed-expenditure', includedLabel(matrix.expenditure_detailed));
      setText('comparison-' + key + '-document-storage', formatStorage(tier.document_storage_gb));
      setText('comparison-' + key + '-letter-to-spouse', includedLabel(matrix.letter_to_spouse));
      setText('comparison-' + key + '-retirement-planning', includedLabel(matrix.retirement_decumulation));
      setText('comparison-' + key + '-what-if', includedLabel(matrix.what_if));
      setText('comparison-' + key + '-plan-export', includedLabel(matrix.advisor_export));
      setText('comparison-' + key + '-statement-upload', includedLabel(matrix.statement_upload));
      setText('comparison-' + key + '-investment-fee-analysis', includedLabel(matrix.investment_cost_analysis));
    });

    replaceHighlights('free', [
      capacityLabel(freeTier, 'savings_account', 'cash or savings account', 'cash or savings accounts'),
      capacityLabel(freeTier, 'investment', 'investment', 'investments'),
      capacityLabel(freeTier, 'pension_account', 'pension', 'pensions'),
      capacityLabel(freeTier, 'property', 'property', 'properties'),
      goalCapacityLabel(freeTier, 'goals', 'goal', 'Goal', 'Goals') + ' and ' + goalCapacityLabel(freeTier, 'life_events', 'life_event', 'Life Event', 'Life Events'),
      fynLabel(freeTier.fyn_weekly_token_budget),
      historyLabel(freeTier.snapshot_surfacing_window_days) + ' of balance history',
    ]);

    replaceHighlights('premium', [
      'Unlimited cash or savings accounts, investments, pensions and properties',
      goalCapacityLabel(premiumTier, 'goals', 'goal', 'Goal', 'Goals') + ' and ' + goalCapacityLabel(premiumTier, 'life_events', 'life_event', 'Life Event', 'Life Events'),
      fynLabel(premiumTier.fyn_weekly_token_budget),
      formatStorage(premiumTier.document_storage_gb),
      historyLabel(premiumTier.snapshot_surfacing_window_days),
      'Detailed planning, projections and exports',
    ]);
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

  fetch(BASE + '/api/pricing-config', { headers: { Accept: 'application/json' } })
    .then(function (response) { return response.ok ? response.json() : null; })
    .then(function (json) {
      var rows = (json && json.data) || [];
      var byKey = {};

      rows.forEach(function (tier) {
        if (tier.tier === 'free' || tier.tier === 'premium') byKey[tier.tier] = tier;
      });

      if (!byKey.free || !byKey.premium) return;

      premium = {
        monthly: Number(byKey.premium.price_monthly_pence),
        yearly: Number(byKey.premium.price_annual_pence),
      };

      var yearlyAvailable = premium.monthly > 0 && premium.yearly > 0;
      btnYearly.disabled = !yearlyAvailable;
      btnYearly.setAttribute('aria-disabled', yearlyAvailable ? 'false' : 'true');
      if (!yearlyAvailable) isYearly = false;

      yearlySavingPercent = annualSavingPercent(premium.monthly, premium.yearly);
      applyPrices();
      applyComparison(byKey.free, byKey.premium);
    })
    .catch(function () {
      // The complete server-rendered contract remains visible.
    });

  applyPrices();
}());
