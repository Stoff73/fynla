import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { data: [] } })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
  },
}));

const ExpenditureForm = (await import('../../UserProfile/ExpenditureForm.vue')).default;

/**
 * W-0011. handleSave appended all 22 detailed category keys unconditionally, so a
 * Simple View save posted them as zeros. The backend gate fired on the presence
 * of those keys, so a free-tier user could not record expenditure by any route —
 * and the failure was invisible: the panel closed as though it had saved.
 */
function makeStore({ capabilities = {} } = {}) {
  return createStore({
    modules: {
      auth: {
        namespaced: true,
        state: () => ({
          user: { id: 16, is_admin: false, is_preview_user: false },
          tierFlags: { capabilities },
          subscriptionData: null,
        }),
        getters: {
          currentUser: (s) => s.user,
          hasCapability: (s) => (key) => s.user?.is_admin === true
            || s.user?.is_preview_user === true
            || s.tierFlags?.capabilities?.[key] === 'full',
        },
      },
      aiFormFill: {
        namespaced: true,
        state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
        actions: { beginFieldSequence: () => {} },
      },
      userProfile: {
        namespaced: true,
        state: () => ({}),
        actions: { updatePersonalInfo: () => Promise.resolve() },
      },
    },
  });
}

async function mountForm({ capabilities, initialData = {}, extraProps = {} } = {}) {
  const wrapper = shallowMount(ExpenditureForm, {
    props: { initialData, startInEditMode: true, ...extraProps },
    global: {
      plugins: [makeStore({ capabilities })],
      directives: { 'preview-disabled': {} },
    },
  });
  await flushPromises();
  return wrapper;
}

const FREE = { expenditure: 'full', expenditure_detailed: 'none' };
const PREMIUM = { expenditure: 'full', expenditure_detailed: 'full' };

const DETAILED_KEYS = [
  'food_groceries', 'transport_fuel', 'healthcare_medical', 'insurance',
  'mobile_phones', 'internet_tv', 'subscriptions', 'clothing_personal_care',
  'entertainment_dining', 'holidays_travel', 'pets', 'childcare',
  'other_expenditure',
];

describe('ExpenditureForm simple entry', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('sends no detailed category keys from Simple View', async () => {
    const wrapper = await mountForm({ capabilities: FREE });

    wrapper.vm.simpleMonthlyExpenditure = 2500;
    wrapper.vm.handleSave();

    const payload = wrapper.emitted('save')[0][0];

    expect(payload.monthly_expenditure).toBe(2500);
    expect(payload.annual_expenditure).toBe(30000);
    expect(payload.expenditure_entry_mode).toBe('simple');
    DETAILED_KEYS.forEach((key) => expect(payload).not.toHaveProperty(key));
  });

  it('still sends the categories from Detailed View', async () => {
    const wrapper = await mountForm({ capabilities: PREMIUM });

    wrapper.vm.useSimpleEntry = false;
    wrapper.vm.handleSave();

    const payload = wrapper.emitted('save')[0][0];

    expect(payload.expenditure_entry_mode).toBe('category');
    expect(payload).toHaveProperty('food_groceries');
  });

  // W-0412. A joint household used to emit `{ userData, spouseData }` here, the
  // same figures twice. On the profile page that made the spouse's half depend
  // on a SECOND HTTP request the backend never required — when it did not
  // arrive the household total silently inherited the difference. In onboarding
  // it was worse: OnboardingService::processExpenditureInfo routes on the
  // presence of those two keys, so a JOINT household took its SEPARATE branch
  // and had the full household figure written whole to BOTH accounts.
  //
  // FIXTURE NOTE: every other case in this file mounts an unmarried user, so
  // none of them could see either shape.
  it('sends ONE household payload when the household spends jointly', async () => {
    const wrapper = await mountForm({
      capabilities: PREMIUM,
      extraProps: { isMarried: true, spouseName: 'Sarah' },
    });

    wrapper.vm.useSimpleEntry = false;
    wrapper.vm.useSeparateExpenditure = false;
    wrapper.vm.handleSave();

    const payload = wrapper.emitted('save')[0][0];

    expect(payload).not.toHaveProperty('userData');
    expect(payload).not.toHaveProperty('spouseData');
    expect(payload.expenditure_entry_mode).toBe('category');
    expect(payload.use_separate_expenditure).toBe(false);
  });

  it('still sends both sides when the household spends separately', async () => {
    const wrapper = await mountForm({
      capabilities: PREMIUM,
      extraProps: { isMarried: true, spouseName: 'Sarah' },
    });

    wrapper.vm.useSimpleEntry = false;
    wrapper.vm.useSeparateExpenditure = true;
    wrapper.vm.handleSave();

    const payload = wrapper.emitted('save')[0][0];

    expect(payload).toHaveProperty('userData');
    expect(payload).toHaveProperty('spouseData');
  });

  it('does not offer the Detailed View toggle without the capability', async () => {
    const free = await mountForm({ capabilities: FREE });
    const premium = await mountForm({ capabilities: PREMIUM });

    expect(free.vm.canUseDetailedExpenditure).toBe(false);
    expect(free.text()).not.toContain('Detailed View');

    expect(premium.vm.canUseDetailedExpenditure).toBe(true);
    expect(premium.text()).toContain('Detailed View');
  });

  it('keeps a free-tier user in Simple View even when the stored mode is category', async () => {
    const wrapper = await mountForm({
      capabilities: FREE,
      initialData: { expenditure_entry_mode: 'category', monthly_expenditure: 2500 },
    });

    expect(wrapper.vm.useSimpleEntry).toBe(true);
  });

  it('honours a stored category mode for a user who has the capability', async () => {
    const wrapper = await mountForm({
      capabilities: PREMIUM,
      initialData: { expenditure_entry_mode: 'category', monthly_expenditure: 2500 },
    });

    expect(wrapper.vm.useSimpleEntry).toBe(false);
  });
});

/**
 * W-0550. The API serialises monthly_expenditure as a decimal string ("900.00").
 * The simple-entry path stored it unparsed, so the "Total Monthly Expenditure"
 * row concatenated "900.00" + 245 and showed £900 — the commitments vanished
 * from the total on web while /m and the API included them.
 */
describe('ExpenditureForm simple entry total (W-0550)', () => {
  it('adds the financial commitments to a string-typed simple monthly figure', async () => {
    const api = (await import('@/services/api')).default;
    api.get.mockImplementation((url) => url === '/user/financial-commitments'
      ? Promise.resolve({ data: { data: { totals: { total: 245, annual_lump_sum: 0 }, commitments: {} } } })
      : Promise.resolve({ data: { data: [] } }));

    const wrapper = await mountForm({
      initialData: { monthly_expenditure: '900.00', expenditure_entry_mode: 'simple' },
      extraProps: { startInEditMode: false },
    });

    expect(wrapper.vm.totalMonthlyExpenditure).toBe(900);
    expect(wrapper.vm.totalMonthlyWithCommitments).toBe(1145);
  });
});

describe('ExpenditureForm total row source (W-0550)', () => {
  it('shows the server presentation total while viewing and the live sum while editing', async () => {
    const api = (await import('@/services/api')).default;
    api.get.mockImplementation((url) => url === '/user/financial-commitments'
      ? Promise.resolve({ data: { data: { totals: { total: 245, annual_lump_sum: 0 }, commitments: {} } } })
      : Promise.resolve({ data: { data: [] } }));

    const viewing = await mountForm({
      initialData: { monthly_expenditure: '900.00', expenditure_entry_mode: 'simple' },
      extraProps: { startInEditMode: false, serverTotals: { active_monthly_total: '1145.00', active_annual_total: '13740.00' } },
    });
    expect(viewing.vm.displayMonthlyWithCommitments).toBe(1145);
    expect(viewing.vm.displayAnnualWithCommitments).toBe(13740);

    const editing = await mountForm({
      initialData: { monthly_expenditure: '900.00', expenditure_entry_mode: 'simple' },
      extraProps: { startInEditMode: true, serverTotals: { active_monthly_total: '999999', active_annual_total: '1' } },
    });
    expect(editing.vm.displayMonthlyWithCommitments).toBe(1145);
  });
});
