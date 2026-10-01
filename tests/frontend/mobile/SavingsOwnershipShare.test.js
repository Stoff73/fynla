import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Savings from '../../../resources/mobile/views/modules/Savings.vue';
import { apiGet } from '../../../resources/mobile/api.js';

/**
 * W-0274, `/m` half, and the 2026-10-01 one-figure audit (items 21-23).
 *
 * The `/m` bank-accounts screen summed `full_balance` across every account it
 * could see, then summed shares with a client helper, divided by the raw
 * spending column and worked out "% of target" itself. Every one of those
 * figures is now `SavingsAgent`'s, published in `analysis`, and this screen
 * shows them as sent — so it cannot disagree with web, iOS or the dashboard.
 *
 * The fixtures make a client computation give a DIFFERENT answer from the
 * server: full balances add to £26,280, raw spending is £1,000 a month, the
 * stated target is £6,000 — while the server says £12,280 of cash, a 9.82-month
 * runway against resolved spending of £1,250, and 68.2% of a £18,000 target.
 * 70/30, never 50/50 (Collision).
 */
vi.mock('../../../resources/mobile/api.js', () => ({ apiGet: vi.fn() }));

vi.mock('../../../resources/mobile/store.js', () => ({
  store: { token: 'test-token', screenRefreshTick: 0 },
}));

vi.mock('../../../resources/mobile/components/MobileChrome.vue', () => ({
  default: {
    template: '<main><slot /></main>',
    props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  },
}));

vi.mock('../../../resources/mobile/components/ISAContributionHistory.vue', () => ({
  default: { template: '<section />', props: ['status'] },
}));

/** Sarah's view: she is the JOINT owner of the £20,000 account, holding 30%. */
const coOwnerPayload = {
  accounts: [
    {
      id: 1,
      institution: 'Barclays',
      account_type: 'current_account',
      current_balance: 6280,
      full_balance: 6280,
      user_share: 6280,
      user_share_percent: 100,
      ownership_type: 'individual',
      ownership_percentage: 100,
      is_primary_owner: true,
      is_shared: false,
    },
    {
      id: 2,
      institution: 'Nationwide',
      account_type: 'current_account',
      current_balance: 20000,
      full_balance: 20000,
      user_share: 6000,
      user_share_percent: 30,
      ownership_type: 'joint',
      ownership_percentage: 70,
      is_primary_owner: false,
      is_shared: true,
    },
  ],
  expenditure_profile: { total_monthly_expenditure: 1000 },
  // The deprecated alias carries the old controller figure here so a screen
  // still reading it would show the wrong target and fail.
  emergency_fund_target: { target_months: 6, target_amount: 6000 },
  analysis: {
    summary: { total_savings: 12280, monthly_expenditure: 1250 },
    emergency_fund: {
      runway_months: 9.82,
      target_months: 9,
      current_amount: 12280,
      percent_of_target: 68.2,
      shortfall: 5720,
      target: { target_months: 9, target_amount: 18000, rationale: 'Self-employed income can be irregular.' },
    },
  },
  isa_allowance: null,
};

function mountSavings() {
  return mount(Savings, { global: { mocks: { $router: { push: vi.fn() } } } });
}

describe('mobile bank accounts', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: coOwnerPayload } });
  });

  it('shows the server\'s total cash, not a sum of the rows', async () => {
    const wrapper = mountSavings();
    await flushPromises();

    expect(wrapper.vm.totalCash).toBe(12280);
    expect(wrapper.text()).toContain('£12,280');
  });

  it('shows the server\'s runway, target and percentage of target', async () => {
    const wrapper = mountSavings();
    await flushPromises();

    // 12,280 / 1,000 would be 12.28 months and "100% of target" against £6,000.
    expect(wrapper.vm.runwayMonths).toBe(9.82);
    expect(wrapper.text()).toContain('9.8 months from cash savings');
    expect(wrapper.text()).toContain('Target (9 months)');
    expect(wrapper.text()).toContain('£18,000');
    expect(wrapper.vm.runwayCovered).toBe('68% of target');
    expect(wrapper.vm.runwayStatus).toBe('violet');
    expect(wrapper.text()).toContain('Self-employed income can be irregular.');
  });

  it('names the viewer\'s share on the row from the server\'s percentage', async () => {
    const wrapper = mountSavings();
    await flushPromises();

    // `ownership_percentage` is 70 (the primary owner's); Sarah's is 30.
    expect(wrapper.text()).toContain('Your 30.00% of £20,000');
    expect(wrapper.text()).toContain('£6,000');
  });

  it('gives the primary owner the server\'s complementary share of the same record', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        data: {
          ...coOwnerPayload,
          accounts: [{ ...coOwnerPayload.accounts[1], user_share: 14000, user_share_percent: 70, is_primary_owner: true }],
          analysis: { ...coOwnerPayload.analysis, summary: { total_savings: 14000, monthly_expenditure: 1250 } },
        },
      },
    });

    const wrapper = mountSavings();
    await flushPromises();

    expect(wrapper.vm.totalCash).toBe(14000);
    expect(wrapper.text()).toContain('Your 70.00% of');
  });
});
