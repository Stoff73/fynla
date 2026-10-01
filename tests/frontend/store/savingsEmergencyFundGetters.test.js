import { describe, expect, it, vi } from 'vitest';

// The service pulls in the API client, which reaches the root store and trips a
// circular import at collection time. Only getters are exercised here, so the
// service is stubbed away exactly as `savingsIsaAllowance.test.js` does.
vi.mock('@/services/savingsService', () => ({ default: {} }));

import savings from '@/store/modules/savings';

/**
 * One figure, every surface (CSJ 2026-10-01; audit
 * `docs/audits/2026-10-01-one-figure-every-surface.md` items 21-23).
 *
 * The cash total, the emergency fund, its runway and the spending it is
 * measured against are computed ONCE, by `SavingsAgent`, and published in
 * `GET /api/savings` as `analysis`. These getters carry those fields to the
 * web components as sent. They used to add the accounts up, apply the share
 * arithmetic and divide by the raw spending column in the browser — the W-0274
 * history: the primary owner's percentage charged to the co-owner, and a
 * `/savings` tab showing "0.0 months" while every other surface read the
 * server's 25.3.
 *
 * Every case here is built so that a getter working the figure out from the
 * accounts would give a DIFFERENT number from the server's: the accounts
 * fixture holds £81,280 of full balances while the server says £31,280.
 */

/** Sarah's view of a 75/25 joint account plus her own: £31,280 is hers. */
const accounts = [
  { id: 3, current_balance: 6280, full_balance: 6280, user_share: 6280, ownership_type: 'individual', ownership_percentage: 100 },
  { id: 2, current_balance: 100000, full_balance: 100000, user_share: 25000, ownership_type: 'joint', ownership_percentage: 75 },
];

const analysis = {
  summary: {
    total_savings: 31280,
    monthly_expenditure: 1234.5,
    expenditure_source: 'user_monthly',
    cash_groups: [
      { key: 'current_accounts', total: 6280, account_ids: [3] },
      { key: 'savings_accounts', total: 25000, account_ids: [2] },
      { key: 'isas', total: 0, account_ids: [] },
      { key: 'nsi', total: 0, account_ids: [] },
    ],
  },
  emergency_fund: {
    runway_months: 25.34,
    target_months: 9,
    current_amount: 31280,
    percent_of_target: 281.6,
    shortfall: 0,
    target: { target_months: 9, target_amount: 11110.5, rationale: 'Self-employed income can be irregular.' },
    target_amount_by_months: { 3: 3703.5, 9: 11110.5, 12: 14814 },
  },
};

describe('the cash total is the server\'s', () => {
  it('reads analysis.summary.total_savings as sent', () => {
    expect(savings.getters.totalSavings({ accounts, analysis })).toBe(31280);
  });

  it('does not move when the account list moves', () => {
    // A getter adding up the accounts would follow the list. The figure is the
    // server's, so only a fresh payload can change it.
    const more = [...accounts, { id: 9, current_balance: 50000, full_balance: 50000, user_share: 50000 }];

    expect(savings.getters.totalSavings({ accounts: more, analysis })).toBe(31280);
  });

  it('is null, not a client sum, when the server did not answer', () => {
    expect(savings.getters.totalSavings({ accounts, analysis: null })).toBeNull();
  });

  it('carries the per-group totals the cash page shows', () => {
    expect(savings.getters.cashGroups({ analysis }).map((g) => g.total)).toEqual([6280, 25000, 0, 0]);
    expect(savings.getters.cashGroups({ analysis: null })).toEqual([]);
  });
});

describe('the emergency fund is the server\'s', () => {
  it('reads the cash it is measured on from the server', () => {
    expect(savings.getters.emergencyFundTotal({ accounts, analysis })).toBe(31280);
  });

  it('carries the whole block, target and what-if targets included', () => {
    const ef = savings.getters.emergencyFund({ analysis });

    expect(ef.target.target_amount).toBe(11110.5);
    expect(ef.target_months).toBe(9);
    expect(ef.percent_of_target).toBe(281.6);
    expect(ef.target_amount_by_months['12']).toBe(14814);
  });

  it('reads the runway as sent rather than dividing on the client', () => {
    // 31,280 / 1,000 (the raw profile column) would be 31.28.
    const state = { accounts, analysis, expenditureProfile: { total_monthly_expenditure: 1000 } };

    expect(savings.getters.emergencyFundRunway(state)).toBe(25.34);
  });

  it('cannot state a runway the server could not work out', () => {
    // W-0495: null, never 0 and never a client division.
    const state = {
      accounts,
      analysis: { ...analysis, emergency_fund: { ...analysis.emergency_fund, runway_months: null } },
      expenditureProfile: { total_monthly_expenditure: 1000 },
    };

    expect(savings.getters.emergencyFundRunway(state)).toBeNull();
    expect(savings.getters.emergencyFundRunway({ accounts, analysis: null })).toBeNull();
  });

  it('shows the RESOLVED monthly spending the target was built from, not the raw column', () => {
    const state = { analysis, expenditureProfile: { total_monthly_expenditure: 1000 } };

    expect(savings.getters.monthlyExpenditure(state)).toBe(1234.5);
  });
});
