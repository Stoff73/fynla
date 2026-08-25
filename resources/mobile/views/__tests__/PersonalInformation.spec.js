import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
}));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import PersonalInformation from '../PersonalInformation.vue';

const canonicalProfile = {
  personal_info: {
    name: 'Alex Morgan',
    email: 'alex@example.com',
    date_of_birth: '1987-04-12',
    age: 39,
    marital_status: 'married',
    national_insurance_number: '***3456',
    address: {
      line_1: '14 Market Street',
      city: 'Bristol',
      postcode: 'BS1 1AA',
    },
  },
  household: { name: 'Morgan household' },
  spouse: { name: 'Sam Morgan' },
  domicile_info: {
    domicile_status: 'uk_domiciled',
    country_of_birth: 'United Kingdom',
    explanation: 'You are UK domiciled.',
  },
  income_occupation: { total_annual_income: 86000 },
  expenditure: { monthly_expenditure: 3200, annual_expenditure: 38400 },
  assets_summary: { total: 610000 },
  liabilities_summary: { total: 85000 },
  net_worth: 525000,
};

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><h1>{{ title }}</h1><button v-if="contextualRequest">Edit details</button><slot /></main>',
};

function mountView() {
  return mount(PersonalInformation, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/personal-information', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

describe('PersonalInformation.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true };
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: canonicalProfile } });
  });

  it('renders the canonical profile summary with an identifier-only contextual Edit action', async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/user/profile', 'live-token');
    expect(wrapper.text()).toContain('Alex Morgan');
    expect(wrapper.text()).toContain('Morgan household');
    expect(wrapper.text()).toContain('Sam Morgan');
    expect(wrapper.text()).toContain('You are UK domiciled.');
    expect(wrapper.text()).toContain('***3456');
    expect(wrapper.text()).toContain('£86,000');
    expect(wrapper.text()).toContain('£3,200');
    expect(wrapper.text()).toContain('£525,000');
    expect(wrapper.text()).toContain('Edit details');
    expect(wrapper.findComponent(MobileChromeStub).props('contextualRequest')).toEqual({
      action: 'edit',
      resource_type: 'personal_information',
      current_destination: { screen: 'personal_information', params: {}, fallback: 'dashboard' },
      origin: { kind: 'surface_action', recommendation_id: null },
    });
    expect(wrapper.find('[data-testid="personal-information-edit"]').exists()).toBe(false);
  });

  it('lists dependants with relationship and age, and hides the section when there are none', async () => {
    const noDependants = mountView();
    await flushPromises();
    expect(noDependants.find('[data-testid="personal-information-dependants"]').exists()).toBe(false);

    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        data: {
          ...canonicalProfile,
          family_members: [
            { id: 1, first_name: 'Sam', relationship: 'spouse', is_dependent: false },
            { id: 2, first_name: 'Rosie', relationship: 'child', age: 9, is_dependent: true },
            { id: 3, first_name: 'Maureen', relationship: 'parent', age: 78, is_dependent: true },
            { id: 4, first_name: 'Jo', relationship: 'other_dependent', is_dependent: true },
          ],
        },
      },
    });
    const wrapper = mountView();
    await flushPromises();

    const section = wrapper.get('[data-testid="personal-information-dependants"]');
    expect(section.text()).toContain('Rosie');
    expect(section.text()).toContain('Child, aged 9');
    expect(section.text()).toContain('Parent, aged 78');
    expect(section.text()).toContain('Dependant');
    expect(section.text()).not.toContain('Sam');
  });

  it('shows a clear empty state for an empty canonical envelope', async () => {
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: {} } });
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain('No personal information is available yet.');
  });

  it('shows an error and retries the canonical request', async () => {
    apiGet
      .mockResolvedValueOnce({ ok: false, status: 503, data: {} })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { data: canonicalProfile } });
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('We could not load your personal information.');
    await wrapper.get('[data-testid="personal-information-retry"]').trigger('click');
    await flushPromises();

    expect(apiGet).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).toContain('Alex Morgan');
  });
});
