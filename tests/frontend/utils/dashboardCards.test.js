import { describe, it, expect } from 'vitest';
import { dashboardFigures } from '@/utils/dashboardCards';

/**
 * The point of this util is that the two dashboards cannot answer the same
 * question differently (W-0245). So these cases pin the DERIVATION, and the
 * per-surface presentation — labels, routes, visualisation — is deliberately not
 * tested here because it is allowed to differ.
 *
 * The payload shape is the live one from `GET /api/v1/mobile/dashboard`, taken from
 * the peak_earners persona on 2026-08-26.
 */
const PAYLOAD = {
  net_worth: {
    total: 1464500,
    breakdown: {
      total_assets: 1635000,
      total_liabilities: 170500,
      assets: {
        property: 755500, savings: 74750, investments: 172500,
        pensions: 500000, business: 0, chattels: 132250, cash: 0,
      },
    },
    trend: 0,
  },
  modules: {
    protection: { status: 'active', total_coverage: 700000, policy_count: 3 },
    savings: { total_savings: 74750, emergency_fund_months: 59.8, emergency_fund_target_months: 6, emergency_fund_percent_of_target: 996.7 },
    retirement: { pot_value: 500000, projected_income: 35000, target_income: 50000 },
    investment: { portfolio_value: 172500, accounts_count: 3, holdings_count: 6 },
  },
};

describe('dashboardFigures', () => {
  it('derives every card from the live payload shape', () => {
    const f = dashboardFigures(PAYLOAD);

    expect(f.netWorth.total).toBe(1464500);
    expect(f.netWorth.totalAssets).toBe(1635000);
    expect(f.netWorth.equityPct).toBe(90);

    expect(f.protection.value).toBe(700000);
    expect(f.protection.covered).toBe(true);
    expect(f.protection.caption).toBe('Cover in place');

    expect(f.savings.value).toBe(74750);
    expect(f.savings.months).toBe(59.8);
    expect(f.savings.caption).toBe('Emergency fund on track');

    expect(f.retirement.value).toBe(500000);
    expect(f.retirement.pct).toBe(70);

    expect(f.investment.value).toBe(172500);
    expect(f.investment.holdingsCount).toBe(6);
    expect(f.investment.caption).toBe('6 holdings');
  });

  it('takes the emergency-fund target and fill from the server, not a typed-in 6 months', () => {
    // A self-employed household: 4.5 months held against a 9-month target is
    // 50%. The old derivation divided by 6 (75%), said "/ 6 months", and
    // called anything at 6 months "on track" (2026-10-01 audit items 23, 35).
    const f = dashboardFigures({
      modules: { savings: { total_savings: 9000, emergency_fund_months: 4.5, emergency_fund_target_months: 9, emergency_fund_percent_of_target: 50 } },
    });

    expect(f.savings.targetMonths).toBe(9);
    expect(f.savings.barFill).toBe(50);
    expect(f.savings.barUnit).toBe('/ 9 months');
    expect(f.savings.caption).toBe('Building your fund');

    const retired = dashboardFigures({
      modules: { savings: { total_savings: 6000, emergency_fund_months: 3, emergency_fund_target_months: 3, emergency_fund_percent_of_target: 100 } },
    });
    expect(retired.savings.caption).toBe('Emergency fund on track');
    expect(retired.savings.barFill).toBe(100);
  });

  it('reads one field per figure, with no fallback to another key', () => {
    // `value` is not a key the aggregator sends; reading it as a fallback is a
    // second path to the same number.
    const f = dashboardFigures({
      modules: { protection: { value: 999, total_coverage: 340000 }, savings: { value: 1, total_savings: 5000 } },
    });

    expect(f.protection.value).toBe(340000);
    expect(f.savings.value).toBe(5000);
  });

  it('reads total assets from the flat field, matching the sum of the class map', () => {
    // Web read `breakdown.total_assets`; /m summed `breakdown.assets` under a comment
    // saying no flat field existed. Both gave 1,635,000, so this was duplicate work
    // rather than divergent answers — but two reads can part company and one cannot.
    const f = dashboardFigures(PAYLOAD);
    const summed = Object.values(PAYLOAD.net_worth.breakdown.assets).reduce((a, b) => a + b, 0);

    expect(f.netWorth.totalAssets).toBe(summed);
  });

  it('accepts modules as an array as well as a map', () => {
    const asArray = {
      ...PAYLOAD,
      modules: Object.entries(PAYLOAD.modules).map(([key, m]) => ({ key, ...m })),
    };

    expect(dashboardFigures(asArray).protection.value).toBe(700000);
    expect(dashboardFigures(asArray).investment.holdingsCount).toBe(6);
  });

  it('survives an empty payload without dividing by zero', () => {
    const f = dashboardFigures({});

    expect(f.netWorth.total).toBe(0);
    expect(f.netWorth.equityPct).toBe(0);
    expect(f.protection.covered).toBe(false);
    expect(f.protection.caption).toBe('Add your cover');
    expect(f.savings.caption).toBe('Start your emergency fund');
    expect(f.investment.caption).toBe('Add your investments');
    expect(f.investment.sharePct).toBe(0);
  });

  it('calls a portfolio the whole of the assets when nothing else is recorded', () => {
    const f = dashboardFigures({
      net_worth: { total: 0, breakdown: {} },
      modules: { investment: { portfolio_value: 5000, accounts_count: 1, holdings_count: 1 } },
    });

    expect(f.investment.sharePct).toBe(100);
    expect(f.investment.vizCap).toBe('Account');
    expect(f.investment.caption).toBe('1 holding');
  });

  it('clamps a percentage that would otherwise exceed the ring', () => {
    // Liabilities exceeding assets, or a projection past target, must not produce a
    // fill above 100 or below 0 — both surfaces render these straight into a ring.
    const over = dashboardFigures({
      net_worth: { total: 200, breakdown: { total_assets: 100 } },
      modules: { retirement: { pot_value: 1, projected_income: 90000, target_income: 50000 } },
    });

    expect(over.netWorth.equityPct).toBe(100);
    expect(over.retirement.pct).toBe(100);

    const under = dashboardFigures({
      net_worth: { total: -50000, breakdown: { total_assets: 100000 } },
    });

    expect(under.netWorth.equityPct).toBe(0);
  });
});
