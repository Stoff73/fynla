import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BalanceHistory from '@/views/NetWorth/BalanceHistory.vue';
import netWorthService from '@/services/netWorthService';

vi.mock('@/services/netWorthService', () => ({
  default: {
    getBalanceHistory: vi.fn(),
  },
}));

const payload = (windowDays = 90) => ({
  data: {
    visibility: {
      window_days: windowDays,
      label: windowDays === null ? 'Full available history' : '90 days of history',
    },
    effective_range: { from: '2026-04-16', to: '2026-07-15' },
    totals: [
      { date: '2025-07-15', assets: 10000, liabilities: 2000, net_worth: 8000 },
      { date: '2026-07-15', assets: 12500, liabilities: 2500, net_worth: 10000 },
    ],
    series: [],
    year_on_year: {
      net_worth_change: 2000,
      percentage_change: 25,
      compared_with: '2025-07-15',
    },
  },
});

describe('BalanceHistory', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows the Free 90-day window and one shared Premium action', async () => {
    netWorthService.getBalanceHistory.mockResolvedValue(payload(90));
    const push = vi.fn();
    const wrapper = mount(BalanceHistory, {
      global: {
        mocks: { $router: { push } },
        stubs: { apexchart: true },
      },
    });

    await flushPromises();

    expect(wrapper.text()).toContain('Balance history');
    expect(wrapper.text()).toContain('90 days of history are included on Free.');
    expect(wrapper.text()).toContain('See plans and upgrade');
    expect(wrapper.text()).toContain('£2,000');

    await wrapper.get('[data-testid="history-upgrade"]').trigger('click');
    expect(push).toHaveBeenCalledWith('/settings?tab=subscription');
  });

  it('shows full available history for Premium without an upgrade action', async () => {
    netWorthService.getBalanceHistory.mockResolvedValue(payload(null));
    const wrapper = mount(BalanceHistory, {
      global: { stubs: { apexchart: true } },
    });

    await flushPromises();

    expect(wrapper.text()).toContain('Full available history');
    expect(wrapper.find('[data-testid="history-upgrade"]').exists()).toBe(false);
  });

  it('renders an honest empty state when no snapshots exist', async () => {
    netWorthService.getBalanceHistory.mockResolvedValue({
      data: {
        visibility: { window_days: null, label: 'Full available history' },
        effective_range: { from: '2019-07-17', to: '2026-07-15' },
        totals: [],
        series: [],
        year_on_year: null,
      },
    });
    const wrapper = mount(BalanceHistory, {
      global: { stubs: { apexchart: true } },
    });

    await flushPromises();

    expect(wrapper.text()).toContain('Your balance history will appear after account values change.');
    expect(wrapper.text()).not.toContain('Year-on-year change');
  });
});
