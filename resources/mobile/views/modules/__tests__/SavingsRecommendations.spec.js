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
 * fyn-wiring Batch A, Task 8 (Rule 19). The `/m` bank accounts screen showed
 * cash, runway and the ISA allowance but none of the savings recommendations the
 * web Strategy tab, the dashboard and Fyn show for the same household. It now
 * reads the SAME endpoint as the web tab (GET /api/savings/recommendations) —
 * no second evaluation path, no `/m`-only copy.
 */
const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

const savingsPayload = {
  data: { accounts: [], goals: [], expenditure_profile: null, account_count: 0, account_limit: null },
};

const recommendations = [
  {
    title: 'Increase Your Emergency Fund',
    description: 'Your emergency fund covers 2.7 months — below the recommended 6 months.',
    action: 'Prioritise building your emergency fund to at least 6 months of expenses.',
    definition_key: 'emergency_fund_low',
  },
  {
    title: 'Move Everyday Saver to a better rate',
    description: 'Everyday Saver pays 1.50%, below the 4.25% easy-access benchmark.',
    definition_key: 'rate_below_market',
  },
];

function stubLoad({ recs = recommendations, recsOk = true } = {}) {
  apiGet.mockImplementation((path) => {
    if (path === '/api/savings') {
      return Promise.resolve({ ok: true, status: 200, data: savingsPayload });
    }
    if (path === '/api/savings/recommendations') {
      return Promise.resolve({ ok: recsOk, status: recsOk ? 200 : 500, data: recsOk ? { success: true, data: recs } : {} });
    }
    return Promise.resolve({ ok: false, status: 200, data: {} });
  });
}

async function mountView() {
  const wrapper = mount(Savings, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/savings', query: {} } },
      stubs: { MobileChrome: MobileChromeStub, ISAContributionHistory: true },
    },
  });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  apiGet.mockReset();
});

describe('/m Savings — recommended actions', () => {
  it('renders the savings recommendations from the shared endpoint', async () => {
    stubLoad();
    const wrapper = await mountView();

    expect(apiGet.mock.calls.map((c) => c[0])).toContain('/api/savings/recommendations');
    const card = wrapper.find('[data-test="savings-recommendations"]');
    expect(card.exists()).toBe(true);
    expect(card.text()).toContain('Increase Your Emergency Fund');
    expect(card.text()).toContain('Prioritise building your emergency fund');
    expect(card.text()).toContain('1.50%');
  });

  it('omits the card when there is nothing to recommend', async () => {
    stubLoad({ recs: [] });
    const wrapper = await mountView();

    expect(wrapper.find('[data-test="savings-recommendations"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('Total cash');
  });

  it('still shows the accounts when the recommendations call fails', async () => {
    stubLoad({ recsOk: false });
    const wrapper = await mountView();

    expect(wrapper.text()).toContain('Total cash');
    expect(wrapper.find('[data-test="savings-recommendations"]').exists()).toBe(false);
  });
});
