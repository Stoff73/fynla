import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BalanceHistory from '../../../resources/mobile/views/BalanceHistory.vue';
import { apiGet } from '../../../resources/mobile/api.js';
import { store } from '../../../resources/mobile/store.js';

vi.mock('../../../resources/mobile/api.js', () => ({
  apiGet: vi.fn(),
  apiDownload: vi.fn(),
}));

vi.mock('../../../resources/mobile/store.js', () => ({
  store: { token: 'test-token', subscriptionStatus: null },
}));

vi.mock('../../../resources/mobile/components/MobileChrome.vue', () => ({
  default: {
    template: '<main><slot /></main>',
    props: ['title', 'subtitle', 'loading', 'loadingLabel'],
  },
}));

describe('mobile BalanceHistory', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.subscriptionStatus = null;
  });

  it('uses the shared endpoint and presents the Free visibility window', async () => {
    apiGet.mockImplementation(async (path) => {
      if (path === '/api/payment/subscription-status') {
        return { ok: true, data: { tier: 'free', payment_enabled: true } };
      }
      return {
        ok: true,
        data: {
          data: {
            visibility: { window_days: 90, label: '90 days of history' },
            totals: [{ date: '2026-07-15', assets: 12000, liabilities: 2000, net_worth: 10000 }],
            year_on_year: null,
          },
        },
      };
    });
    const wrapper = mount(BalanceHistory, {
      global: { stubs: { apexchart: true } },
    });

    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/balance-history', 'test-token');
    expect(wrapper.text()).toContain('90 days of history are included on Free.');
    expect(wrapper.text()).toContain('See plans and upgrade');
  });

  it('does not fabricate a chart when history is empty', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      data: {
        data: {
          visibility: { window_days: null, label: 'Full available history' },
          totals: [],
          year_on_year: null,
        },
      },
    });
    const wrapper = mount(BalanceHistory, {
      global: { stubs: { apexchart: true } },
    });

    await flushPromises();

    expect(wrapper.text()).toContain('Your balance history will appear after account values change.');
    expect(wrapper.find('apexchart-stub').exists()).toBe(false);
  });
});
