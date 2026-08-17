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
import Goals from '../modules/Goals.vue';

describe('Goals overview actions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true, onboarding_fyn_step: null };
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/goals') {
        return {
          ok: true,
          status: 200,
          data: {
            data: {
              goals: [{
                id: 61,
                name: 'Home deposit',
                display_goal_type: 'Home deposit',
                target_amount: 50000,
                current_amount: 30000,
                progress_percentage: 60,
                is_on_track: true,
                is_primary_owner: true,
              }],
            },
          },
        };
      }

      return {
        ok: true,
        status: 200,
        data: { data: { total_goals: 1, on_track_count: 1, total_target: 50000, total_current: 30000 } },
      };
    });
  });

  it('offers one Add goal action at the top and none inside the goals card', async () => {
    const push = vi.fn();
    const wrapper = mount(Goals, {
      global: {
        mocks: {
          $route: { path: '/goals', query: {} },
          $router: { push },
        },
      },
    });
    await flushPromises();

    const addButtons = wrapper.findAll('button')
      .filter((button) => button.text().trim() === 'Add goal');

    expect(addButtons).toHaveLength(1);
    expect(addButtons[0].classes()).toContain('md-edit-details');
    expect(wrapper.findAll('button').some((button) => button.text().trim() === 'Edit')).toBe(false);

    await wrapper.get('[data-goal-id="61"]').trigger('click');
    expect(push).toHaveBeenCalledWith({ name: 'm-goal', params: { id: 61 } });
  });
});
