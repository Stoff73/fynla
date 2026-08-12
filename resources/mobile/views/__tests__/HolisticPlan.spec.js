import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn(), apiPost: vi.fn(), apiStream: vi.fn() }));
vi.mock('../../navigation/webHandoff.js', () => ({ issueWebHandoff: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import HolisticPlan from '../HolisticPlan.vue';

function mountPlan() {
  return mount(HolisticPlan, {
    global: { mocks: {
      $route: { path: '/holistic-plan', params: {}, query: {} },
      $router: { push: vi.fn() },
    } },
  });
}

describe('Holistic Plan bounded states', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true, tier: 'premium' };
  });

  afterEach(() => vi.useRealTimers());

  it('renders the real server plan beneath a visible semantic heading', async () => {
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: {
      items: [{ module: 'savings', type: 'emergency_fund', priority: 'high', title: 'Build your emergency fund', affordability: 'fits', required_monthly_cost: 250 }],
      locked: [], available_monthly_surplus: 700, goal_commitments: 100,
      effective_surplus: 600, near_term_capital: 0,
    } } });

    const wrapper = mountPlan();
    await flushPromises();

    expect(wrapper.get('h1').text()).toBe('Holistic Plan');
    expect(wrapper.text()).toContain('Build your emergency fund');
    expect(wrapper.text()).toContain('£600');
  });

  it('renders the authoritative typed subscription gate', async () => {
    apiGet.mockResolvedValue({ ok: false, status: 403, data: {
      error: 'capability_denied', message: 'The Holistic Plan is part of Premium.',
    } });

    const wrapper = mountPlan();
    await flushPromises();

    expect(wrapper.text()).toContain('A premium feature');
    expect(wrapper.text()).toContain('part of Premium');
    expect(wrapper.find('.m-err').exists()).toBe(false);
  });

  it('leaves loading after the request deadline and exposes retry', async () => {
    vi.useFakeTimers();
    apiGet.mockReturnValue(new Promise(() => {}));
    const wrapper = mountPlan();

    await vi.advanceTimersByTimeAsync(15_000);
    await flushPromises();

    expect(wrapper.vm.loading).toBe(false);
    expect(wrapper.text()).toContain('taking longer than expected');
    expect(wrapper.findAll('button').some(button => button.text() === 'Try again')).toBe(true);
  });
});
