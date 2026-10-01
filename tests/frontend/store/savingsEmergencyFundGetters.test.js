import { describe, expect, it, vi } from 'vitest';

// The service pulls in the API client, which reaches the root store and trips a
// circular import at collection time. Only getters are exercised here.
vi.mock('@/services/savingsService', () => ({ default: {} }));

import savings from '@/store/modules/savings';

/**
 * One figure, every surface (CSJ 2026-10-01). The savings figures are worked
 * out once on the server (SavingsPosition via GET /api/savings): the user's
 * share of all their cash (W-0274: never the ticked subset, never the primary
 * owner's share applied to the co-owner), the runway from resolved spending,
 * the ISA allowance. The getters transport that block; /m and iOS read the same
 * one. The share and runway rules are tested where they live
 * (tests/Unit/Services/Savings/SavingsPositionTest.php and the store parity
 * tests), not re-derived here.
 */

/** Vuex hands getters the resolved getter map; build it the same way. */
const gettersFor = (state) => {
  const g = {};
  for (const [name, fn] of Object.entries(savings.getters)) {
    Object.defineProperty(g, name, { get: () => fn(state, g), enumerable: true });
  }
  return g;
};

const position = {
  total_cash: 31280,
  monthly_expenditure: 1225,
  group_totals: { current_accounts: 6280, savings_accounts: 0, isas: 25000, nsi: 0 },
  emergency_fund: { runway_months: 25.53, runway_label: '26 months from cash savings', target_months: 6, target_amount: 7350, covered_percent: 100, status: 'on_track' },
  isa: { total_allowance: 20000, used: 5000, remaining: 15000, percent_used: 25 },
};

/** A co-owner's view: the joint account's full balance and the primary's 75%. */
const accounts = [
  { id: 1, current_balance: 6280, user_share: 6280, ownership_percentage: 100 },
  { id: 2, current_balance: 100000, user_share: 25000, ownership_type: 'joint', ownership_percentage: 75, is_primary_owner: false },
];

describe('savings getters transport the server figures', () => {
  it('reads the total and the emergency fund from the server, not the accounts', () => {
    const g = gettersFor({ accounts, position });

    expect(g.totalSavings).toBe(31280);
    expect(g.emergencyFundTotal).toBe(31280);
    expect(g.totalISABalance).toBe(25000);
  });

  it('reads the runway and the spending it was worked out from', () => {
    const g = gettersFor({ accounts, position });

    expect(g.emergencyFundRunway).toBe(25.53);
    expect(g.monthlyExpenditure).toBe(1225);
  });

  it('keeps "no runway" as null, never 0 months (W-0495)', () => {
    const g = gettersFor({ accounts, position: { ...position, emergency_fund: { runway_months: null } } });

    expect(g.emergencyFundRunway).toBeNull();
  });

  it('reads the ISA allowance remaining and used as the tracker sent them', () => {
    const g = gettersFor({ accounts, position });

    expect(g.isaAllowanceRemaining).toBe(15000);
    expect(g.isaUsagePercent).toBe(25);
  });

  it('shows nothing rather than a made-up figure before the server answers', () => {
    const g = gettersFor({ accounts, position: null });

    expect(g.totalSavings).toBe(0);
    expect(g.emergencyFundRunway).toBeNull();
  });
});
