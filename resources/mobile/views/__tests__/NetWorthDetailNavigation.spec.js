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
import NetWorthCategory from '../modules/NetWorthCategory.vue';
import PropertyDetail from '../modules/PropertyDetail.vue';
import MortgageDetail from '../modules/MortgageDetail.vue';
import LiabilityDetail from '../modules/LiabilityDetail.vue';

const detailed = {
  property: {
    count: 1,
    total_value: 400000,
    items: [{
      id: 41,
      name: '12 Example Road',
      type: 'main_residence',
      value: 400000,
      outstanding_mortgage: 180000,
      ownership_type: 'individual',
    }],
  },
  liabilities: {
    count: 2,
    total_value: 192000,
    items: [
      { id: 71, kind: 'mortgage', name: 'Example Bank', value: 180000, property_id: 41 },
      { id: 72, kind: 'liability', name: 'Personal loan', value: 12000, liability_type: 'loan' },
    ],
  },
};

describe('Net Worth canonical detail navigation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true };
  });

  it('removes category edit and routes property, mortgage and liability records', async () => {
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: detailed } });
    const propertyPush = vi.fn();
    const property = mount(NetWorthCategory, {
      global: { mocks: {
        $route: { path: '/net-worth/property', params: { category: 'property' }, query: {} },
        $router: { push: propertyPush },
      } },
    });
    await flushPromises();

    expect(property.findAll('button').some((button) => button.text() === 'Edit details')).toBe(false);
    expect(property.get('[data-destination="property_detail:41"]').text()).toContain('Mortgage £180,000');
    expect(property.get('.mnwc-item__mortgage').classes()).toContain('mnwc-item__mortgage--debt');
    await property.get('[data-destination="property_detail:41"]').trigger('click');
    expect(propertyPush).toHaveBeenCalledWith({ name: 'm-property', params: { id: 41 } });

    const debtPush = vi.fn();
    const debts = mount(NetWorthCategory, {
      global: { mocks: {
        $route: { path: '/net-worth/liabilities', params: { category: 'liabilities' }, query: {} },
        $router: { push: debtPush },
      } },
    });
    await flushPromises();

    await debts.get('[data-destination="mortgage_detail:71"]').trigger('click');
    await debts.get('[data-destination="liability_detail:72"]').trigger('click');
    expect(debtPush).toHaveBeenNthCalledWith(1, { name: 'm-mortgage', params: { id: 71 } });
    expect(debtPush).toHaveBeenNthCalledWith(2, { name: 'm-liability', params: { id: 72 } });
  });

  it.each([
    [PropertyDetail, '/api/properties/41', { id: '41' }, '12 Example Road', 'property_detail', 'property', 41],
    [MortgageDetail, '/api/mortgages/71', { id: '71' }, 'Example Bank', 'mortgage_detail', 'mortgage', 71],
    [LiabilityDetail, '/api/estate/liabilities/72', { id: '72' }, 'Personal loan', 'liability_detail', 'liability', 72],
  ])('renders canonical detail and identifier-only Edit for %s', async (
    component,
    endpoint,
    params,
    heading,
    screen,
    resourceType,
    resourceID,
  ) => {
    const key = resourceType === 'property' ? 'property' : resourceType;
    const record = resourceType === 'property'
      ? { id: 41, address_line_1: heading, current_value: 400000, outstanding_mortgage: 180000, mortgages: [{ id: 71, lender_name: 'Example Bank', outstanding_balance: 180000 }], ownership_type: 'individual', is_primary_owner: true }
      : resourceType === 'mortgage'
        ? { id: 71, lender_name: heading, outstanding_balance: 180000, monthly_payment: 1100, interest_rate: 4.2, remaining_term_months: 240, property: { id: 41, address_line_1: '12 Example Road' }, ownership_type: 'individual', is_primary_owner: true }
        : { id: 72, liability_name: heading, current_balance: 12000, monthly_payment: 350, interest_rate: 5.9, maturity_date: '2029-06-01', ownership_type: 'individual', is_primary_owner: true };
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: { [key]: record } } });
    const wrapper = mount(component, {
      global: { mocks: {
        $route: { path: `/detail/${params.id}`, params, query: {} },
        $router: { push: vi.fn() },
      } },
    });
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith(endpoint, 'live-token');
    expect(wrapper.get('h1').text()).toBe(heading);
    expect(wrapper.text()).toMatch(/£400,000|£180,000|£12,000/);
    expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit details')).toHaveLength(1);
    expect(wrapper.vm.contextualRequest.resource_type).toBe(resourceType);
    expect(wrapper.vm.contextualRequest.resource_id).toBe(resourceID);
    expect(wrapper.vm.contextualRequest.current_destination.screen).toBe(screen);
    expect(JSON.stringify(wrapper.vm.contextualRequest)).not.toMatch(/400000|180000|12000|1100|350|5\.9|4\.2/);
  });
});
