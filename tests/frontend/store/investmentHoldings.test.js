import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/investmentService', () => ({
  default: {
    updateHolding: vi.fn(),
    createHolding: vi.fn(),
  },
}));

vi.mock('@/utils/poller', () => ({ pollMonteCarloJob: vi.fn() }));

import investmentService from '@/services/investmentService';
import investment from '@/store/modules/investment';

describe('investment/updateHolding', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    investmentService.updateHolding.mockResolvedValue({ data: { id: 32 } });
  });

  it('forwards the payload its callers actually send', async () => {
    // AccountForm.vue, InvestmentHoldings.vue and InvestmentProjections.vue all
    // dispatch { id, data }. The action destructured `holdingData`, so the
    // payload was undefined, axios sent an empty body, and every holding edit
    // was silently discarded behind a 200 OK (W-0009).
    const holding = {
      id: 32,
      ticker: 'VGLS80',
      isin: 'GB00B4PQW151',
      sub_type: 'mixed_fund',
      purchase_price: 225,
      current_price: 255,
      ocf_percent: 0.22,
    };

    const commit = vi.fn();
    const dispatch = vi.fn();

    await investment.actions.updateHolding({ commit, dispatch }, { id: 32, data: holding });

    expect(investmentService.updateHolding).toHaveBeenCalledTimes(1);
    expect(investmentService.updateHolding).toHaveBeenCalledWith(32, holding);

    const [, payload] = investmentService.updateHolding.mock.calls[0];
    expect(payload).not.toBeUndefined();
    expect(payload.ticker).toBe('VGLS80');
  });

  it('rethrows so the caller can keep the modal open and show the failure', async () => {
    investmentService.updateHolding.mockRejectedValue(new Error('boom'));

    const commit = vi.fn();
    const dispatch = vi.fn();

    await expect(
      investment.actions.updateHolding({ commit, dispatch }, { id: 32, data: { ticker: 'X' } }),
    ).rejects.toThrow('boom');

    expect(commit).toHaveBeenCalledWith('setError', 'boom');
  });
});

describe('investment/totalPortfolioValue', () => {
  it('counts only the viewer’s share of a joint account', () => {
    // /net-worth/investments used to total the FULL value of a joint account
    // while the wealth summary counted half — the same session, the same
    // account, £47,500 apart (W-0015).
    const state = {
      accounts: [
        { id: 13, ownership_type: 'individual', current_value: 85000, user_share: 85000 },
        {
          id: 14,
          ownership_type: 'joint',
          ownership_percentage: 50,
          current_value: 95000,
          user_share: 47500,
          is_primary_owner: true,
        },
      ],
    };

    expect(investment.getters.totalPortfolioValue(state)).toBe(132500);
  });

  it('gives the joint owner the complementary share, not the full value', () => {
    const state = {
      accounts: [{
        id: 14,
        ownership_type: 'joint',
        ownership_percentage: 50,
        current_value: 95000,
        user_share: 47500,
        is_primary_owner: false,
      }],
    };

    expect(investment.getters.totalPortfolioValue(state)).toBe(47500);
  });
});
