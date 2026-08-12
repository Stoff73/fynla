import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiStream: vi.fn(),
}));

vi.mock('../../navigation/webHandoff.js', () => ({ issueWebHandoff: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import Income from '../Income.vue';
import IncomeDetail from '../IncomeDetail.vue';
import Expenditure from '../Expenditure.vue';

const incomeSummary = {
  user: {
    total: 72000,
    sources: [{
      key: 'employment',
      label: 'Employment',
      amount: 72000,
      frequency: 'annual',
      ownership: 'user',
      ownership_label: 'You',
      detail: 'Northstar Ltd · Product designer',
      tax_position: 'Taxable earned income',
    }],
    tax_position: {
      total_income: 72000,
      adjusted_net_income: 71000,
      personal_allowance: 12570,
      personal_allowance_label: 'Standard personal allowance',
      pension_annual_allowance: 60000,
      pension_annual_allowance_label: 'Standard pension annual allowance',
    },
  },
  spouse: null,
};

function profile(data) {
  return { ok: true, status: 200, data: { data } };
}

function mounting(route, router = { push: vi.fn(), back: vi.fn() }) {
  return { global: { mocks: { $route: route, $router: router } } };
}

describe('Income and expenditure canonical presentation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true };
  });

  it('routes an income source card to its canonical detail', async () => {
    apiGet.mockResolvedValue(profile({ income_summary: incomeSummary }));
    const router = { push: vi.fn(), back: vi.fn() };
    const wrapper = mount(Income, mounting({ path: '/income', params: {}, query: {} }, router));
    await flushPromises();

    await wrapper.get('[data-destination="income_detail:user:employment"]').trigger('click');
    expect(router.push).toHaveBeenCalledWith({
      name: 'm-income-detail',
      params: { owner: 'user', source: 'employment' },
    });
  });

  it('shows source, amount, frequency, ownership and server-owned tax position', async () => {
    apiGet.mockResolvedValue(profile({ income_summary: incomeSummary }));
    const wrapper = mount(IncomeDetail, mounting({
      path: '/income/user/employment',
      params: { owner: 'user', source: 'employment' },
      query: {},
    }));
    await flushPromises();

    expect(wrapper.text()).toContain('Employment');
    expect(wrapper.text()).toContain('£72,000');
    expect(wrapper.text()).toContain('Annual');
    expect(wrapper.text()).toContain('You');
    expect(wrapper.text()).toContain('Taxable earned income');
    expect(wrapper.text()).toContain('Standard personal allowance');
    expect(wrapper.vm.contextualRequest.current_destination.params).toEqual({
      income_owner: 'user',
      income_source: 'employment',
    });
    expect(JSON.stringify(wrapper.vm.contextualRequest)).not.toMatch(/72000|71000|12570|Northstar/);
  });

  it('uses the server active total and explains summary-only expenditure', async () => {
    apiGet.mockResolvedValue(profile({
      expenditure: {
        monthly_expenditure: 9999,
        annual_expenditure: 119988,
        presentation: {
          entry_mode: 'summary',
          entry_mode_label: 'Monthly summary',
          active_monthly_total: 1800,
          active_annual_total: 21600,
          total_basis: 'Monthly summary plus financial commitments',
          detail_available: false,
          reconciles: true,
          summary_only_reason: 'Only a monthly summary has been entered. Add category details to improve your insights.',
        },
      },
    }));
    const wrapper = mount(Expenditure, mounting({ path: '/expenditure', params: {}, query: {} }));
    await flushPromises();

    expect(wrapper.text()).toContain('£1,800');
    expect(wrapper.text()).not.toContain('£9,999');
    expect(wrapper.text()).toContain('Monthly summary');
    expect(wrapper.text()).toContain('Only a monthly summary has been entered');
    expect(wrapper.vm.contextualRequest.resource_type).toBe('expenditure');
    expect(JSON.stringify(wrapper.vm.contextualRequest)).not.toMatch(/1800|21600|9999/);
  });

  it('labels category mode while keeping the reconciled server total', async () => {
    apiGet.mockResolvedValue(profile({
      expenditure: {
        monthly_expenditure: 9999,
        annual_expenditure: 119988,
        categories: { food_groceries: 600, transport_fuel: 200, other_expenditure: 100 },
        presentation: {
          entry_mode: 'category',
          entry_mode_label: 'Category detail',
          active_monthly_total: 900,
          active_annual_total: 10800,
          total_basis: 'Category entries plus financial commitments',
          detail_available: true,
          reconciles: true,
          summary_only_reason: null,
        },
      },
    }));
    const wrapper = mount(Expenditure, mounting({ path: '/expenditure', params: {}, query: {} }));
    await flushPromises();

    expect(wrapper.text()).toContain('£900');
    expect(wrapper.text()).not.toContain('£9,999');
    expect(wrapper.text()).toContain('Category detail');
    expect(wrapper.text()).toContain('Food & groceries');
  });
});
