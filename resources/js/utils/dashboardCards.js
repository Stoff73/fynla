import { retirementHeadline } from './retirementHeadline';

/**
 * What the five dashboard cards SAY — the one home for that derivation.
 *
 * `resources/js/views/GamifiedDashboard.vue` and `resources/mobile/views/Dashboard.vue`
 * render the same five cards from the same endpoint and each built the figures
 * itself, in near-identical computeds that had already drifted (W-0245). W-0238
 * extracted the retirement headline into `retirementHeadline.js` after the two
 * surfaces started answering that question differently; this is the same move for
 * the other four cards, following that precedent rather than adding a second one.
 *
 * **This returns numbers and shared wording, never labels, routes or visualisation
 * choices.** Those are genuinely per-surface and must stay in each component:
 * `/m` calls the savings card "Bank Accounts" and routes to `/savings`, web calls it
 * "Savings" and routes to `/net-worth/cash`. Neither is wrong, and collapsing them
 * would be a regression dressed as consolidation.
 *
 * Captions ARE shared. "Emergency fund on track", "Cover in place", "Add your
 * investments" are the same sentence on both surfaces today, and Rule 20 says one
 * wording has one home — so a change to any of them now lands on both by
 * construction rather than by someone remembering.
 *
 * @param {object} payload - `GET /api/v1/mobile/dashboard`'s data block. Both
 *   surfaces consume this same endpoint.
 * @returns {object} derived figures, keyed by card.
 */
export function dashboardFigures(payload) {
  const d = payload || {};
  const modsRaw = d.modules || {};

  // Modules arrive as an array keyed by `.key` on some responses and as an object
  // map on others. Both surfaces already handled both shapes; that stays here.
  const find = (k) => (Array.isArray(modsRaw) ? (modsRaw.find((m) => m.key === k) || {}) : (modsRaw[k] || {}));

  const num = (v) => Number(v) || 0;
  const clampPct = (v) => Math.max(0, Math.min(100, Math.round(v)));

  // ---- Net worth -------------------------------------------------------------
  const nw = d.net_worth || {};
  const bd = nw.breakdown || {};
  const total = num(nw.total);

  // `breakdown.total_assets` and the sum of `breakdown.assets` are the same figure
  // — verified against a live payload, both £1,635,000 for peak_earners. Web read
  // the flat field, `/m` summed the map under a comment saying no flat field
  // existed. It does. Reading it once removes the chance of them parting company
  // if the backend ever adds a class to one and not the other.
  const totalAssets = num(bd.total_assets);

  // Share of assets owned outright, net of liabilities. Zero when there are no
  // assets rather than a division by zero.
  const equityPct = totalAssets > 0 ? clampPct((total / totalAssets) * 100) : 0;

  // ---- Protection ------------------------------------------------------------
  const prot = find('protection');
  const protectionValue = num(prot.value != null ? prot.value : prot.total_coverage);
  const covered = protectionValue > 0;

  // ---- Savings ---------------------------------------------------------------
  const sav = find('savings');
  const efMonths = num(sav.emergency_fund_months);
  const efTarget = 6;
  const savingsValue = num(sav.total_savings != null ? sav.total_savings : sav.value);
  const efBarFill = efTarget > 0 ? clampPct((efMonths / efTarget) * 100) : 0;

  // ---- Retirement ------------------------------------------------------------
  const ret = find('retirement');
  const projectedIncome = num(ret.projected_income);
  const targetIncome = num(ret.target_income);
  const retirementPct = targetIncome > 0 ? clampPct((projectedIncome / targetIncome) * 100) : 0;

  // ---- Investment ------------------------------------------------------------
  const inv = find('investment');
  const investmentValue = num(inv.portfolio_value != null ? inv.portfolio_value : inv.value);
  const accountsCount = num(inv.accounts_count);
  const holdingsCount = num(inv.holdings_count);

  // Share of total assets held as investments. With no assets recorded at all but a
  // portfolio present, the portfolio is by definition all of it.
  const investmentPct = totalAssets > 0
    ? clampPct((investmentValue / totalAssets) * 100)
    : (investmentValue > 0 ? 100 : 0);

  return {
    netWorth: {
      total,
      totalAssets,
      equityPct,
      trendPct: num(nw.trend),
    },
    protection: {
      value: protectionValue,
      covered,
      caption: covered ? 'Cover in place' : 'Add your cover',
      vizNum: covered ? 'Active' : 'None',
    },
    savings: {
      value: savingsValue,
      months: efMonths,
      targetMonths: efTarget,
      barFill: efBarFill,
      barValue: efMonths ? (Math.round(efMonths * 10) / 10) : '0',
      barUnit: `/ ${efTarget} months`,
      caption: efMonths >= efTarget
        ? 'Emergency fund on track'
        : (efMonths > 0 ? 'Building your fund' : 'Start your emergency fund'),
    },
    retirement: {
      ...retirementHeadline(ret),
      projectedIncome,
      targetIncome,
      pct: retirementPct,
    },
    investment: {
      value: investmentValue,
      accountsCount,
      holdingsCount,
      sharePct: investmentPct,
      vizNum: investmentValue > 0 ? String(accountsCount) : '0',
      vizCap: accountsCount === 1 ? 'Account' : 'Accounts',
      caption: investmentValue > 0
        ? `${holdingsCount} ${holdingsCount === 1 ? 'holding' : 'holdings'}`
        : 'Add your investments',
    },
  };
}
