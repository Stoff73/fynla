import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/savingsService', () => ({
  default: {
    getISAAllowance: vi.fn(),
  },
}));

import savingsService from '@/services/savingsService';
import savings from '@/store/modules/savings';

const allowance = {
  tax_year: '2026/27',
  total_allowance: 20000,
  cash_isa_used: 10000,
  stocks_shares_isa_used: 0,
  total_used: 10000,
  remaining: 10000,
};

describe('savings/ensureISAAllowance', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    savingsService.getISAAllowance.mockResolvedValue({ success: true, data: allowance });
  });

  it('loads the allowance when the store has none', async () => {
    // The allowance only ever arrived with the big /api/savings payload, so the
    // investment account modal read cash_isa_used: 0 from a null state and
    // withheld the guard on the £20,000 statutory limit (W-0007).
    const commit = vi.fn();
    const state = { isaAllowance: null };

    const result = await savings.actions.ensureISAAllowance({ commit, state });

    expect(savingsService.getISAAllowance).toHaveBeenCalledTimes(1);
    expect(commit).toHaveBeenCalledWith('setISAAllowance', allowance);
    expect(result).toEqual(allowance);
  });

  it('does not refetch when the allowance is already loaded', async () => {
    const commit = vi.fn();
    const state = { isaAllowance: allowance };

    await savings.actions.ensureISAAllowance({ commit, state });

    expect(savingsService.getISAAllowance).not.toHaveBeenCalled();
    expect(commit).not.toHaveBeenCalled();
  });

  it('refetches when the caller forces it', async () => {
    const commit = vi.fn();
    const state = { isaAllowance: allowance };

    await savings.actions.ensureISAAllowance({ commit, state }, { force: true });

    expect(savingsService.getISAAllowance).toHaveBeenCalledTimes(1);
  });

  it('does not break the screen when the allowance cannot be loaded', async () => {
    savingsService.getISAAllowance.mockRejectedValue(new Error('offline'));
    const commit = vi.fn();
    const state = { isaAllowance: null };

    await expect(savings.actions.ensureISAAllowance({ commit, state })).resolves.toBeNull();
  });
});

describe('savings/currentYearISASubscription', () => {
  it('reports the Cash ISA used once the allowance is loaded', () => {
    expect(savings.getters.currentYearISASubscription({ isaAllowance: allowance })).toBe(10000);
  });

  it('reports zero while the allowance is unloaded — which is why it must be loaded', () => {
    expect(savings.getters.currentYearISASubscription({ isaAllowance: null })).toBe(0);
  });
});
