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
  // The server's total at the viewer's share (GET /api/investment `summary`),
  // the figure net worth and the dashboard read on every surface (CSJ
  // 2026-10-01). W-0015's share rule lives in CrossModuleAssetAggregator now;
  // the getter transports the figure and never adds up accounts.
  it('reads the server total, not the accounts', () => {
    const state = {
      summary: { total_value: 132500 },
      accounts: [
        { id: 13, ownership_type: 'individual', current_value: 85000, user_share: 85000 },
        { id: 14, ownership_type: 'joint', ownership_percentage: 50, current_value: 95000, user_share: 47500 },
      ],
    };

    expect(investment.getters.totalPortfolioValue(state)).toBe(132500);
  });

  it('shows nothing rather than a sum of its own before the server answers', () => {
    const state = { summary: null, accounts: [{ id: 14, current_value: 95000, user_share: 47500 }] };

    expect(investment.getters.totalPortfolioValue(state)).toBe(0);
  });
});
