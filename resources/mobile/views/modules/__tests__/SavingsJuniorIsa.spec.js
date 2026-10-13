import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
}));

import { apiGet } from '../../../api.js';
import Savings from '../Savings.vue';

/**
 * T65 (release ac prod walk, 2026-10-13): Bea's Vanguard Junior ISA for Arjun
 * sat under "Cash ISA Accounts" and in "Across 3 accounts". A Junior ISA is
 * the child's money (GOV.UK); CSJ 2026-10-13: out of every parent total, on
 * its own card naming the child.
 */
const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

const payload = {
  data: {
    accounts: [
      { id: 1, institution: 'Monzo', account_type: 'current_account', is_isa: false, current_balance: 2400 },
      { id: 2, institution: 'Marcus', account_type: 'savings_account', is_isa: false, current_balance: 6500, interest_rate: 4.3 },
      { id: 3, institution: 'Vanguard', account_type: 'junior_isa', is_isa: true, isa_type: 'junior', current_balance: 3100, beneficiary_name: 'Arjun' },
    ],
    account_count: 2,
    account_limit: 2,
    position: { total_cash: 8900, group_totals: { current_accounts: 2400, savings_accounts: 6500, isas: 0, nsi: 0, junior_isas: 3100 } },
  },
};

beforeEach(() => {
  apiGet.mockReset();
  apiGet.mockImplementation((path) => Promise.resolve(path === '/api/savings'
    ? { ok: true, status: 200, data: payload }
    : { ok: true, status: 200, data: { success: true, data: [] } }));
});

describe('/m Bank Accounts — a child\'s Junior ISA', () => {
  it('shows it on its own card naming the child, outside the Cash ISAs and the count', async () => {
    const wrapper = mount(Savings, {
      global: {
        mocks: { $router: { push: vi.fn() }, $route: { path: '/savings', query: {} } },
        stubs: { MobileChrome: MobileChromeStub, ISAContributionHistory: true },
      },
    });
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain("Junior ISAs (your children's)");
    expect(text).toContain('Arjun · Vanguard');
    expect(text).not.toContain('Cash ISA Accounts');
    expect(text).toContain('Across 2 accounts.');
    expect(text).toContain('£8,900');
  });
});
