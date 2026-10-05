import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';
import api from '@/services/api';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(() => Promise.resolve({ data: {} })),
  },
}));

const ExpenditureForm = (await import('../../UserProfile/ExpenditureForm.vue')).default;

/*
 * TODO item 7a, one figure on every surface. A homeowner enters housing costs
 * against the property, so the form hides rent and utilities and leaves them
 * out of the total (ExpenditureForm essentialFields; W-0413), as the server's
 * one category sum does (UserProfileService::categorySpendingTotal). The form
 * read GET /properties as a list, but the API answers
 * { data: { properties: [...] } } (PropertyController::index), so it never knew
 * about a main residence: the Mitchell demo's web table counted £160 of
 * utilities the server does not, £1,385 against /m's £1,225.
 */
function makeStore() {
  return createStore({
    modules: {
      auth: {
        namespaced: true,
        state: () => ({ user: { id: 16, is_admin: true, is_preview_user: false }, tierFlags: { capabilities: {} }, subscriptionData: null }),
        getters: { currentUser: (s) => s.user, hasCapability: () => () => true },
      },
      aiFormFill: { namespaced: true, state: () => ({ pendingFill: null, highlightedField: null, filling: false }), actions: { beginFieldSequence: () => {} } },
      userProfile: { namespaced: true, state: () => ({}), actions: { updatePersonalInfo: () => Promise.resolve() } },
    },
  });
}

async function mountForm(extraProps = {}) {
  const wrapper = shallowMount(ExpenditureForm, {
    props: {
      initialData: { expenditure_entry_mode: 'category', rent: 0, utilities: 160, food_groceries: 225, transport_fuel: 75 },
      ...extraProps,
    },
    global: { plugins: [makeStore()], directives: { 'preview-disabled': {} } },
  });
  await flushPromises();
  return wrapper;
}

describe('ExpenditureForm for a homeowner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url) => (url === '/properties'
      ? Promise.resolve({ data: { success: true, data: { properties: [{ id: 1, property_type: 'main_residence' }] } } })
      : Promise.resolve({ data: { data: {} } })));
  });

  it('knows about the main residence from the properties response, so rent and utilities are left out', async () => {
    const wrapper = await mountForm();

    expect(wrapper.vm.totalMonthlyExpenditure).toBe(300);
  });

  it('shows the server\'s entered-spending figure while viewing, as it does the total', async () => {
    const wrapper = await mountForm({ serverTotals: { manual_monthly_total: 1225, active_monthly_total: 5191 } });

    expect(wrapper.vm.displayManualMonthly).toBe(1225);
  });
});
