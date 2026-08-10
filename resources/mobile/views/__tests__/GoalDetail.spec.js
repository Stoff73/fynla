import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiStream: vi.fn(),
}));

vi.mock('../../navigation/webHandoff.js', () => ({
  issueWebHandoff: vi.fn(),
}));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import GoalDetail from '../modules/GoalDetail.vue';

describe('Goal detail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true, onboarding_fyn_step: null };
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        data: {
          goal: {
            id: 61,
            name: 'Home deposit',
            display_goal_type: 'Home deposit',
            description: 'Buy a family home near our support network.',
            target_amount: 50000,
            current_amount: 30000,
            target_date: '2028-07-18',
            created_at: '2025-02-03T10:00:00Z',
            status: 'active',
            progress_percentage: 60,
            monthly_contribution: 750,
            contribution_frequency: 'monthly',
            current_milestone: 50,
            next_milestone: 75,
            is_primary_owner: true,
          },
          milestones: [
            { percentage: 50, reached: true },
            { percentage: 75, reached: false },
          ],
        },
      },
    });
  });

  it('renders the canonical rationale, dates, progress, contribution and milestones', async () => {
    const wrapper = mount(GoalDetail, {
      global: {
        mocks: {
          $route: { path: '/goals/61', params: { id: '61' }, query: {} },
          $router: { push: vi.fn() },
        },
      },
    });
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/goals/61', 'live-token');
    expect(wrapper.text()).toContain('Home deposit');
    expect(wrapper.text()).toContain('Buy a family home near our support network.');
    expect(wrapper.text()).toContain('£50,000');
    expect(wrapper.text()).toContain('£30,000');
    expect(wrapper.text()).toContain('18/07/2028');
    expect(wrapper.text()).toContain('03/02/2025');
    expect(wrapper.text()).toContain('£750');
    expect(wrapper.text()).toContain('50%');
    expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit details')).toHaveLength(1);

    expect(wrapper.vm.contextualRequest).toEqual({
      action: 'edit',
      resource_type: 'goal',
      resource_id: 61,
      current_destination: {
        screen: 'goal_detail',
        params: { goal_id: 61 },
        fallback: 'goals',
      },
      origin: { kind: 'surface_action', recommendation_id: null },
    });
    expect(JSON.stringify(wrapper.vm.contextualRequest)).not.toMatch(/50000|30000|750|Home deposit/);
  });
});
