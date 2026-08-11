import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import Achievements from '../Achievements.vue';

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel'],
  template: '<main><h1>{{ title }}</h1><slot /></main>',
};

const firstPage = {
  achievements: [
    {
      key: 'data_savings_account',
      title: 'Added savings details',
      description: 'You started building your savings picture.',
      earned: true,
      earned_at: '2026-08-10T12:00:00Z',
      state: 'earned',
      provenance: {
        kind: 'point_award',
        event: 'data:savings_account:first',
        occurred_at: '2026-08-10T12:00:00Z',
      },
      progress: null,
      next_action: null,
    },
    {
      key: 'data_estate',
      title: 'Added estate details',
      description: 'You started building your estate picture.',
      earned: false,
      earned_at: null,
      state: 'locked',
      provenance: null,
      progress: { current: 1, target: 2, percent: 50, label: '1 of 2' },
      next_action: null,
    },
  ],
  completed: [],
  completed_total: 0,
  milestones: [
    {
      key: 'tax_savings:0:500',
      title: 'We found £500 a year you could save in tax.',
      state: 'earned',
      provenance: { kind: 'user_milestone', event: 'tax_savings:0:500', occurred_at: '2026-08-09T12:00:00Z' },
      progress: null,
      next_action: null,
    },
  ],
  milestones_total: 1,
  per_page: 50,
  next_cursor: 'opaque-cursor+/=',
  upcoming: [
    {
      key: 'net_worth:0:10000', title: 'Net worth £10,000', state: 'in_progress', provenance: null,
      progress: { current: 4500, target: 10000, percent: 45, label: '£4,500 of £10,000' },
      next_action: { label: 'Review your savings', destination: { screen: 'savings', params: {}, fallback: 'dashboard' } },
      route: 'm-net-worth',
    },
    {
      key: 'estate:0:1', title: 'Put your will in place', state: 'inapplicable', provenance: null,
      progress: { current: 1, target: 1, percent: 100, label: 'Not applicable progress' },
      next_action: null, route: 'm-estate',
    },
  ],
};

function mountView() {
  return mount(Achievements, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/achievements', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

describe('Mobile achievements', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    apiGet.mockImplementation((path) => {
      if (path === '/api/v1/mobile/achievements/v2') {
        return Promise.resolve({ ok: true, status: 200, data: { data: firstPage } });
      }
      if (path === '/api/gamification/activity') {
        return Promise.resolve({ ok: true, status: 200, data: { data: [], next_cursor: null } });
      }
      if (path === '/api/v1/mobile/achievements/v2/milestones?cursor=opaque-cursor%2B%2F%3D') {
        return Promise.resolve({
          ok: true,
          status: 200,
          data: { data: { milestones: [{
            key: 'tax_savings:0:500', title: 'We found £500 a year you could save in tax.', state: 'earned',
            provenance: { kind: 'user_milestone', event: 'goal:7:50', occurred_at: '2026-08-09T12:00:00Z' },
            progress: null, next_action: null,
          }, {
            key: 'goal:7:50', title: '50% of Home deposit', state: 'earned',
            provenance: { kind: 'user_milestone', event: 'goal:7:50', occurred_at: '2026-08-09T12:00:00Z' },
            progress: null, next_action: null,
          }], milestones_total: 2, per_page: 50, next_cursor: null } },
        });
      }
      return Promise.resolve({ ok: false, status: 404, data: {} });
    });
  });

  it('renders server-owned badge state, provenance, and only applicable progress', async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/v1/mobile/achievements/v2', 'live-token');
    expect(wrapper.get('[data-achievement-key="data_savings_account"]').text()).toContain('Badge');
    expect(wrapper.get('[data-achievement-key="data_savings_account"]').attributes('aria-label')).toContain('Earned');
    expect(wrapper.text()).toContain('You started building your savings picture.');
    expect(wrapper.text()).not.toContain('data:savings_account:first');
    expect(wrapper.text()).toContain('10/08/2026');

    await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    expect(wrapper.get('[data-achievement-key="net_worth:0:10000"] [role="progressbar"]').attributes('aria-valuenow')).toBe('45');
    expect(wrapper.get('[data-achievement-key="net_worth:0:10000"]').text()).toContain('£4,500 of £10,000');
    expect(wrapper.find('[data-achievement-key="estate:0:1"] [role="progressbar"]').exists()).toBe(false);
  });

  it('uses a semantic next action before any legacy route fallback', async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.get('[data-progress-tab="milestones"]').trigger('click');

    await wrapper.get('[data-next-action="net_worth:0:10000"]').trigger('click');
    expect(wrapper.vm.$router.push).toHaveBeenCalledWith('/savings');
    expect(wrapper.vm.$router.push).not.toHaveBeenCalledWith({ name: 'm-net-worth' });
  });

  it('continues reached milestones with the opaque cursor and deduplicates canonical keys', async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.get('[data-progress-tab="milestones"]').trigger('click');

    await wrapper.get('[data-load-more-milestones]').trigger('click');
    expect(apiGet).toHaveBeenCalledWith('/api/v1/mobile/achievements/v2/milestones?cursor=opaque-cursor%2B%2F%3D', 'live-token');
    expect(wrapper.findAll('[data-reached-milestone]')).toHaveLength(2);
    expect(wrapper.find('[data-load-more-milestones]').exists()).toBe(false);
  });
});
