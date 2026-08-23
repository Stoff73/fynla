import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Savings from '../../../resources/mobile/views/modules/Savings.vue';
import { apiGet } from '../../../resources/mobile/api.js';

/**
 * W-0274, `/m` half (Rule 19 — done means web AND `/m`).
 *
 * The `/m` bank-accounts screen summed `full_balance` across every account it
 * could see, so a joint account was counted WHOLE against both spouses, and the
 * runway, the progress bar and "% of target" beneath it all inherited that. The
 * account DETAIL screen one tap away has always shown the viewer's share, so `/m`
 * contradicted itself within a single journey.
 *
 * `/m` is an isolated bundle with its own store and API client, so the desktop
 * store fix does not reach it — but it does reach the same `ownership.js`, by
 * relative path, exactly as the `/m` investment, property and savings-account
 * screens already do. One home, three surfaces (Rule 20).
 *
 * **70/30, never 50/50** (`tests/CLAUDE.md` §4, Collision): at 50/50 the full
 * balance, the primary's share and the co-owner's share collapse toward one
 * another and a screen that shows the wrong one still reads correctly.
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
      ownership_type: 'joint',
      ownership_percentage: 70,
      is_primary_owner: false,
      is_shared: true,
    },
  ],
  expenditure_profile: { total_monthly_expenditure: 1000 },
  emergency_fund_target: { target_months: 6, target_amount: 6000 },
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

  it('counts the co-owner\'s share of a joint account, not the whole balance', async () => {
    const wrapper = mountSavings();
    await flushPromises();

    // £6,280 + £6,000. The defect summed £6,280 + £20,000 = £26,280 — £14,000 of
    // it her husband's.
    expect(wrapper.vm.totalCash).toBe(12280);
  });

  it('carries that share into the runway, the bar and the target coverage', async () => {
    const wrapper = mountSavings();
    await flushPromises();

    // 12,280 / 1,000. Under the defect this read 26 months of cover — and the
    // bar and "% of target" were wrong by the same money.
    // The label rounds to whole months above ten, so 12.28 renders as "12".
    expect(wrapper.vm.runwayMonths).toBeCloseTo(12.28, 2);
    expect(wrapper.text()).toContain('12 months of cover');
    expect(wrapper.vm.runwayCovered).toBe('100% of target');
  });

  it('names the share on the row rather than showing the full balance alone', async () => {
    const wrapper = mountSavings();
    await flushPromises();

    // Mirrors the `/m` investment list: the headline is what the viewer owns and
    // the line beneath gives the full balance it came out of.
    expect(wrapper.text()).toContain('Your 30.00% of');
  });

  it('gives the primary owner the complementary share of the same record', async () => {
    // The same account, seen from the other side. The two views must not both be
    // the same number — which is exactly what a 50/50 fixture would allow.
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        data: {
          ...coOwnerPayload,
          accounts: [{ ...coOwnerPayload.accounts[1], user_share: 14000, is_primary_owner: true }],
        },
      },
    });

    const wrapper = mountSavings();
    await flushPromises();

    expect(wrapper.vm.totalCash).toBe(14000);
    expect(wrapper.text()).toContain('Your 70.00% of');
  });
});
