import { describe, it, expect, vi } from 'vitest';

vi.mock('@/services/estateService', () => ({
  default: {
    getLiabilities: vi.fn(async () => ({
      success: true,
      data: {
        liabilities: [{ id: 'mortgage_1', liability_type: 'mortgage', user_share: 180000 }],
        liability_totals: { all: { balance: 180000, monthly_payments: 0 } },
      },
    })),
  },
}));

import estate from '@/store/modules/estate';
import LiabilitiesList from '@/components/NetWorth/LiabilitiesList.vue';

// T28 (F12): the Liabilities page read the Estate index, which gives Free users
// a teaser, so their mortgages and debts never showed. It reads its own list.
describe('Liabilities page data', () => {
  it('fills the list and totals from the liabilities route', async () => {
    const commits = {};
    await estate.actions.fetchLiabilities({ commit: (type, value) => { commits[type] = value; } });

    expect(commits.setLiabilities).toEqual([{ id: 'mortgage_1', liability_type: 'mortgage', user_share: 180000 }]);
    expect(commits.setLiabilityTotals.all.balance).toBe(180000);
  });

  it('loads the page through that action, not the Estate index', async () => {
    const fetchLiabilities = vi.fn();
    const fetchEstateData = vi.fn();
    await LiabilitiesList.methods.fetchData.call({ fetchLiabilities, fetchEstateData });

    expect(fetchLiabilities).toHaveBeenCalled();
    expect(fetchEstateData).not.toHaveBeenCalled();
  });
});
