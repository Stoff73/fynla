import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
}));

import { apiGet } from '../../../api.js';
import Savings from '../Savings.vue';
import SavingsAccount from '../SavingsAccount.vue';

/**
 * T68 (release ac prod walk, 2026-10-13): Premium Bonds read "Not recorded"
 * for an interest rate. They pay prizes from a prize fund, not interest
 * (NS&I, Premium Bonds), and the savings form never asks for a rate, so no
 * rate or interest line is shown for them. A savings account keeps its line.
 */
const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

const premiumBonds = { id: 7, institution: 'NS&I', provider: 'NS&I', account_type: 'premium_bonds', is_isa: false, current_balance: 10000, interest_rate: null, annual_interest: null, monthly_interest: null };
const saver = { id: 8, institution: 'Marcus', provider: 'Marcus', account_type: 'savings_account', is_isa: false, current_balance: 6500, interest_rate: null };

const listPayload = {
  data: {
    accounts: [premiumBonds, saver],
    account_count: 2,
    account_limit: 2,
    position: { total_cash: 16500, group_totals: { current_accounts: 0, savings_accounts: 6500, isas: 0, nsi: 10000, junior_isas: 0 } },
  },
};

function mountWith(component, path, account) {
  apiGet.mockImplementation((p) => {
    if (p === '/api/savings') return Promise.resolve({ ok: true, status: 200, data: listPayload });
    if (account && p.startsWith('/api/savings/accounts/')) return Promise.resolve({ ok: true, status: 200, data: { success: true, data: account } });
    return Promise.resolve({ ok: true, status: 200, data: { success: true, data: [] } });
  });
  return mount(component, {
    global: {
      mocks: { $router: { push: vi.fn(), back: vi.fn() }, $route: { path, params: { id: String(account?.id ?? '') }, query: {} } },
      stubs: { MobileChrome: MobileChromeStub, ISAContributionHistory: true },
    },
  });
}

beforeEach(() => {
  apiGet.mockReset();
});

describe('/m Premium Bonds carry no interest rate', () => {
  it('lists Premium Bonds without a rate, and a savings account with "Not recorded"', async () => {
    const wrapper = mountWith(Savings, '/savings');
    await flushPromises();

    const rows = wrapper.findAll('.ms-acct');
    const bonds = rows.find((r) => r.text().includes('NS&I'));
    const marcus = rows.find((r) => r.text().includes('Marcus'));
    expect(bonds.text()).not.toContain('Not recorded');
    expect(bonds.find('.ms-acct__rate').exists()).toBe(false);
    expect(marcus.text()).toContain('Not recorded');
  });

  it('shows no interest rate or interest lines on the Premium Bonds screen', async () => {
    const wrapper = mountWith(SavingsAccount, '/savings/accounts/7', premiumBonds);
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain('Full balance');
    // Found on the T68 walk: the section was still headed "Balance & interest".
    expect(text).not.toContain('Balance & interest');
    expect(text).not.toContain('Interest rate');
    expect(text).not.toContain('Monthly interest');
    expect(text).not.toContain('Annual interest');
  });
});
