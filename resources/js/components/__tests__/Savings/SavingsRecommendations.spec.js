import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

const api = (await import('@/services/api')).default;
const savings = (await import('@/store/modules/savings')).default;
const SavingsRecommendations = (await import('../../Savings/SavingsRecommendations.vue')).default;

/**
 * fyn-wiring Batch A, Task 8. The Savings module's Strategy tab read
 * `savings.recommendations` from Vuex, and nothing ever wrote to it — the tab
 * said "No recommendations" for every user since it was built, while the
 * dashboard and Fyn showed the same household several. The tab now loads the
 * ONE savings recommendations endpoint (GET /savings/recommendations, the
 * SavingsPlanService path every other consumer uses) and renders each item with
 * its decision trace, so a user can see the runway arithmetic behind a card.
 */
const items = [
  {
    priority: 1,
    category: 'Emergency Fund',
    title: 'Increase Your Emergency Fund',
    description: 'Your emergency fund covers 2.7 months — below the recommended 6 months.',
    action: 'Prioritise building your emergency fund to at least 6 months of expenses.',
    impact: 'High',
    definition_key: 'emergency_fund_low',
    estimated_impact: 14493.46,
    decision_trace: [
      {
        question: 'What is the recommended emergency fund target based on employment status?',
        data_field: 'employment_status',
        data_value: 'employed → 6 months',
        threshold: 'Employed = 6 months, self-employed/contractor = 9 months, retired = 3 months',
        passed: true,
        explanation: 'Employment status "employed" maps to a 6-month emergency fund target.',
      },
    ],
  },
  {
    priority: 2,
    category: 'Interest Rates',
    title: 'Move Everyday Saver to a better rate',
    description: 'Everyday Saver pays 1.50%, below the 4.25% easy-access benchmark.',
    action: 'Compare easy-access rates and switch.',
    impact: 'Medium',
    definition_key: 'rate_below_market',
    estimated_impact: 275,
    decision_trace: [],
  },
];

function mountTab() {
  const store = createStore({ modules: { savings } });
  return mount(SavingsRecommendations, { global: { plugins: [store] } });
}

beforeEach(() => {
  api.get.mockReset();
});

describe('SavingsRecommendations (Strategy tab)', () => {
  it('loads the savings recommendations endpoint and renders one card per item', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: items } });

    const wrapper = mountTab();
    await flushPromises();

    expect(api.get).toHaveBeenCalledWith('/savings/recommendations');
    expect(wrapper.text()).toContain('Increase Your Emergency Fund');
    expect(wrapper.text()).toContain('Move Everyday Saver to a better rate');
    expect(wrapper.text()).toContain('1.50%');
    expect(wrapper.text()).not.toContain('No recommendations');
  });

  it('reveals the decision trace for a card on request', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: items } });

    const wrapper = mountTab();
    await flushPromises();

    const question = 'What is the recommended emergency fund target based on employment status?';
    expect(wrapper.text()).not.toContain(question);

    await wrapper.find('[data-test="savings-rec-emergency_fund_low"] button').trigger('click');

    expect(wrapper.text()).toContain(question);
    expect(wrapper.text()).toContain('retired = 3 months');
  });

  it('shows the empty state only when the endpoint returns nothing', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: [] } });

    const wrapper = mountTab();
    await flushPromises();

    expect(wrapper.text()).toContain('No recommendations');
  });

  it('reports a failed load instead of pretending the strategy looks good', async () => {
    api.get.mockRejectedValue(new Error('Network Error'));

    const wrapper = mountTab();
    await flushPromises();

    expect(wrapper.text()).not.toContain('No recommendations');
    expect(wrapper.text()).toContain('could not load');
  });
});
