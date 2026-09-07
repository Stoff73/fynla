import { describe, expect, it, vi } from 'vitest';

// The service pulls in the API client, which reaches the root store and trips a
// circular import at collection time. Only getters are exercised here, so the
// service is stubbed away exactly as `savingsIsaAllowance.test.js` does.
vi.mock('@/services/savingsService', () => ({ default: {} }));

import savings from '@/store/modules/savings';

/**
 * W-0274 — the fourth answer to "how much emergency fund does this household
 * have", the one a person could see.
 *
 * Minutes apart, on one login, `/dashboard`, `/m` and `/risk-profile` all read
 * the backend's figure and showed Sarah Jones 25.3 months against £31,030, while
 * `/savings` → Emergency Fund said **"Months Runway 0.0"**, **"Current Fund £0"**
 * and *"Priority: Build your emergency fund"*. Six service-level measurements and
 * fifteen feature tests reported the backend consolidation complete — and it was.
 * The surviving implementation was in JavaScript, in these getters.
 *
 * Two independent defects lived in them:
 *
 *   1. **A retired definition.** `filter(a => a.is_emergency_fund)` — the flag is
 *      a DESIGNATION ("which account has the user nominated"), not a definition of
 *      what the fund contains. A household with £130,780 of cash and no ticked
 *      boxes does not have a £0 emergency fund.
 *   2. **The share applied from the wrong side.** `ownership_percentage` is the
 *      PRIMARY owner's share, and it was applied whoever was looking, so the
 *      co-owner of a joint account was charged the primary owner's fraction.
 *
 * **Every fixture here is asymmetric — 75/25 and 70/30, never 50/50.** At 50/50
 * the primary owner's share and the co-owner's are the same number, and a getter
 * that always returns the primary's share is correct for both parties; no
 * assertion could tell the two hypotheses apart (`tests/CLAUDE.md` §4, Collision).
 * That symmetry is exactly why this survived earlier sweeps.
 */

/** The API sends `user_share` on every account; these fixtures carry it. */
const account = ({ id, balance, share, ownership = 'individual', percentage = 100, primary = true, isa = false, flagged = false }) => ({
  id,
  current_balance: balance,
  full_balance: balance,
  user_share: share,
  ownership_type: ownership,
  ownership_percentage: percentage,
  is_primary_owner: primary,
  is_shared: ownership !== 'individual',
  is_isa: isa,
  is_emergency_fund: flagged,
});

/** David's view: he records the joint account and holds 75% of it. */
const primaryOwnerAccounts = [
  account({ id: 1, balance: 25000, share: 25000 }),
  account({ id: 2, balance: 100000, share: 75000, ownership: 'joint', percentage: 75, primary: true }),
];

/** Sarah's view of the same household: the same joint record, her 25%. */
const coOwnerAccounts = [
  account({ id: 3, balance: 6280, share: 6280 }),
  account({ id: 2, balance: 100000, share: 25000, ownership: 'joint', percentage: 75, primary: false }),
];

describe('savings totals apply the share from the viewer\'s own side', () => {
  it('gives the primary owner their stated share', () => {
    expect(savings.getters.totalSavings({ accounts: primaryOwnerAccounts })).toBe(100000);
  });

  it('gives the co-owner the complement, not the primary owner\'s share', () => {
    // The defect returned 6,280 + 75,000 = 81,280: Sarah charged with David's
    // three quarters of an account that is one quarter hers.
    expect(savings.getters.totalSavings({ accounts: coOwnerAccounts })).toBe(31280);
  });

  it('moves when the split moves, on both sides of the record', () => {
    // The countermeasure of last resort: assert the answer MOVES with the input,
    // so no fixed expectation can be satisfied by a getter that ignores it.
    const at70 = [account({ id: 4, balance: 10000, share: 7000, ownership: 'joint', percentage: 70, primary: true })];
    const at30 = [account({ id: 4, balance: 10000, share: 3000, ownership: 'joint', percentage: 70, primary: false })];

    expect(savings.getters.totalSavings({ accounts: at70 })).toBe(7000);
    expect(savings.getters.totalSavings({ accounts: at30 })).toBe(3000);
  });

  it('applies the same rule to ISA balances', () => {
    // The third copy of the arithmetic. A joint ISA does not exist in UK law, so
    // this branch should never fire in production — but a rule with three
    // implementations has three chances to be edited into disagreement.
    const accounts = [
      account({ id: 5, balance: 22500, share: 22500, isa: true }),
      account({ id: 6, balance: 100000, share: 25000, ownership: 'joint', percentage: 75, primary: false, isa: true }),
      account({ id: 7, balance: 50000, share: 50000 }),
    ];

    expect(savings.getters.totalISABalance({ accounts })).toBe(47500);
  });
});

describe('the emergency fund is the household\'s cash, not the ticked subset', () => {
  it('does not report £0 because no account carries the flag', () => {
    // Precisely the screen the tester photographed: real money, nothing flagged.
    const accounts = [
      account({ id: 8, balance: 6280, share: 6280 }),
      account({ id: 9, balance: 22500, share: 22500 }),
      account({ id: 10, balance: 4500, share: 2250, ownership: 'joint', percentage: 50, primary: true }),
    ];

    expect(accounts.every((a) => a.is_emergency_fund === false)).toBe(true);
    expect(savings.getters.emergencyFundTotal({ accounts })).toBe(31030);
  });

  it('does not shrink to the flagged subset when one account is nominated', () => {
    // The flag still answers a real question — "has the user nominated an
    // account" — and answering it must not change what the fund is worth.
    const accounts = [
      account({ id: 11, balance: 6280, share: 6280, flagged: true }),
      account({ id: 12, balance: 22500, share: 22500 }),
    ];

    expect(savings.getters.emergencyFundTotal({ accounts })).toBe(28780);
  });

  it('agrees with the total savings figure beside it', () => {
    // Two getters, one household, one number. They were free to disagree, and did.
    const state = { accounts: coOwnerAccounts };

    expect(savings.getters.emergencyFundTotal(state)).toBe(savings.getters.totalSavings(state));
  });
});

describe('months of runway', () => {
  const stateFor = (accounts, analysis = null, monthly = 1225) => ({
    accounts,
    analysis,
    expenditureProfile: { total_monthly_expenditure: monthly },
  });

  /** Vuex hands getters the resolved getter map; build it the same way. */
  const runwayFor = (state) => {
    const resolved = {
      monthlyExpenditure: savings.getters.monthlyExpenditure(state),
      emergencyFundTotal: savings.getters.emergencyFundTotal(state),
    };

    return savings.getters.emergencyFundRunway(state, resolved);
  };

  it('prefers the backend figure, which resolves expenditure the browser cannot', () => {
    // `SavingsAgent` divides `calculateCashTotal()` by RESOLVED monthly
    // expenditure — a priority chain, not one column. The persona proves the
    // chain is live: David's resolves from `expenditure_profile` and Sarah's from
    // `user_monthly`. Where the two disagree the backend's answer wins.
    const state = stateFor(coOwnerAccounts, { emergency_fund: { runway_months: 25.33 } }, 9999);

    expect(runwayFor(state)).toBe(25.33);
  });

  it('divides the fund by expenditure when the payload carries no analysis', () => {
    // 31,280 / 1,225. Not the 0.0 the tab showed, and not 81,280 / 1,225 either.
    expect(runwayFor(stateFor(coOwnerAccounts))).toBeCloseTo(25.53, 2);
  });

  it('cannot state a runway when there is no expenditure to run through', () => {
    // W-0495. This asserted 0, which is the defect as a contract: dividing by
    // nothing is not "no runway", it is "no answer". Zero is a claim, and a
    // household holding £31,280 in cash was told it had none — the alarming
    // direction, and enough to raise a false "build your emergency fund".
    expect(runwayFor(stateFor(coOwnerAccounts, null, 0))).toBeNull();
  });
});
