import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn() }));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import Achievements from '../Achievements.vue';

const MobileChromeStub = { props: ['title', 'subtitle', 'loading', 'loadingLabel'], template: '<main><h1>{{ title }}</h1><slot /></main>' };
const cursor = 'eyJpdiI6IjEyMzQ1Njc4OTAxMjM0NTYifQ==';
const nextCursor = 'eyJpdiI6Ijk4NzY1NDMyMTA5ODc2NTQifQ==';

const milestone = (number, key = `goal:${number}:50`) => ({
  key, title: `Milestone ${number}`, state: 'earned', progress: null, next_action: null,
  provenance: { kind: 'user_milestone', event: key, occurred_at: '2026-08-09T12:00:00Z' },
});

function canonicalPage(overrides = {}) {
  const milestones = Array.from({ length: 50 }, (_, index) => milestone(index + 1));
  return {
    achievements: [{
      key: 'data_savings_account', title: 'Added savings details', description: 'You started building your savings picture.',
      earned: true, earned_at: '2026-08-10T12:00:00Z', state: 'earned', progress: null, next_action: null,
      provenance: { kind: 'point_award', event: 'data:savings_account:first', occurred_at: '2026-08-10T12:00:00Z' },
    }, {
      key: 'data_estate', title: 'Added estate details', description: 'You started building your estate picture.',
      earned: false, earned_at: null, state: 'locked', progress: null, next_action: null, provenance: null,
    }],
    completed: [], completed_total: 0, milestones, milestones_total: 53, per_page: 50, next_cursor: cursor,
    upcoming: [{
      key: 'net_worth:0:10000', group: 'Wealth', title: 'Net worth £10,000', steps: 'Add to your savings — you are £5,500 away.', state: 'in_progress',
      progress: { current: 4500, target: 10000, percent: 45, label: '£4,500 of £10,000' },
      next_action: { label: 'Review net worth', destination: { screen: 'net_worth', params: {}, fallback: 'dashboard' } }, route: 'm-net-worth',
    }, {
      key: 'will:0:1', group: 'Protection & estate', title: 'Put your will in place', steps: 'Add your estate details.', state: 'locked', progress: null,
      next_action: { label: 'Add estate details', destination: { screen: 'estate', params: {}, fallback: 'dashboard' } }, route: 'm-estate',
    }, {
      key: 'lpa:0:1', group: 'Protection & estate', title: 'Consider an LPA', steps: 'Tell us about your estate plan.', state: 'inapplicable', progress: null,
      next_action: { label: 'Review estate plan', destination: { screen: 'estate', params: {}, fallback: 'dashboard' } }, route: 'm-estate',
    }, {
      key: 'unsafe:0:1', group: 'Journey', title: 'No safe action', steps: 'This remains informational.', state: 'locked', progress: null, next_action: null, route: 'evil-route',
    }],
    ...overrides,
  };
}

function mountView() {
  const push = vi.fn();
  return mount(Achievements, { global: { mocks: { $router: { push }, $route: { path: '/achievements', query: {} } }, stubs: { MobileChrome: MobileChromeStub } } });
}

function itemByTitle(wrapper, title) {
  return wrapper.findAll('[data-achievement-item]').find((item) => item.text().includes(title));
}

function mockCanonical(page = canonicalPage(), continuations = []) {
  let continuation = 0;
  apiGet.mockImplementation((path) => {
    if (path === '/api/v1/mobile/achievements/v2') return Promise.resolve({ ok: true, status: 200, data: { data: page } });
    if (path === '/api/gamification/activity') return Promise.resolve({ ok: true, status: 200, data: { data: [], next_cursor: null } });
    if (path.startsWith('/api/v1/mobile/achievements/v2/milestones?cursor=')) {
      const response = continuations[continuation++];
      return typeof response === 'function' ? response() : response || Promise.resolve({ ok: false, status: 500, data: {} });
    }
    return Promise.resolve({ ok: false, status: 404, data: {} });
  });
}

describe('Mobile achievements', () => {
  beforeEach(() => { vi.restoreAllMocks(); vi.clearAllMocks(); store.token = 'live-token'; mockCanonical(); });

  it('renders server-owned provenance and an independently accessible progressbar', async () => {
    const wrapper = mountView(); await flushPromises();
    expect(apiGet).toHaveBeenCalledWith('/api/v1/mobile/achievements/v2', 'live-token');
    expect(wrapper.text()).toContain('You started building your savings picture.');
    expect(wrapper.text()).toContain('Earned on 10/08/2026');
    expect(wrapper.text()).not.toContain('data:savings_account:first');
    expect(wrapper.html()).not.toContain('data:savings_account:first');
    await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    const card = itemByTitle(wrapper, 'Net worth £10,000');
    const progress = card.get('[role="progressbar"]');
    expect(progress.element.closest('button')).toBeNull();
    expect(progress.attributes('aria-labelledby')).toBeTruthy();
    expect(progress.attributes('aria-valuenow')).toBe('45');
    expect(progress.attributes('aria-valuetext')).toBe('£4,500 of £10,000');
    expect(card.text()).toContain('Add to your savings — you are £5,500 away.');
    expect(card.get('button').text()).toBe('Review net worth');
  });

  it('keeps locked and inapplicable rows actionable only through visible semantic actions', async () => {
    const wrapper = mountView(); await flushPromises(); await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    const locked = itemByTitle(wrapper, 'Put your will in place');
    const inapplicable = itemByTitle(wrapper, 'Consider an LPA');
    expect(locked.text()).toContain('Locked'); expect(locked.find('[role="progressbar"]').exists()).toBe(false);
    expect(inapplicable.text()).toContain('Not applicable'); expect(inapplicable.find('[role="progressbar"]').exists()).toBe(false);
    await locked.get('button').trigger('click'); await inapplicable.get('button').trigger('click');
    expect(wrapper.vm.$router.push).toHaveBeenNthCalledWith(1, '/estate');
    expect(wrapper.vm.$router.push).toHaveBeenNthCalledWith(2, '/estate');
    expect(itemByTitle(wrapper, 'No safe action').find('button').exists()).toBe(false);
  });

  it('prefers semantic actions and only allows explicit legacy route fallback', async () => {
    const page = canonicalPage({ upcoming: [
      { key: 'precedence:0:1', group: 'Journey', title: 'Semantic wins', steps: 'Server step.', state: 'in_progress', progress: null, route: 'm-net-worth', next_action: { label: 'Go to savings', destination: { screen: 'savings', params: {}, fallback: 'dashboard' } } },
      { key: 'legacy:0:1', group: 'Journey', title: 'Legacy allowed', steps: 'Server step.', state: 'locked', progress: null, route: 'm-estate', next_action: null },
      { key: 'bad:0:1', group: 'Journey', title: 'Legacy rejected', steps: 'Server step.', state: 'locked', progress: null, route: 'not-a-route', next_action: null },
    ] });
    mockCanonical(page); const wrapper = mountView(); await flushPromises(); await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    await itemByTitle(wrapper, 'Semantic wins').get('button').trigger('click');
    await itemByTitle(wrapper, 'Legacy allowed').get('button').trigger('click');
    expect(wrapper.vm.$router.push).toHaveBeenNthCalledWith(1, '/savings');
    expect(wrapper.vm.$router.push).toHaveBeenNthCalledWith(2, { name: 'm-estate' });
    expect(itemByTitle(wrapper, 'Legacy rejected').find('button').exists()).toBe(false);
  });

  it('deduplicates initial and continuation keys while advancing the successful opaque cursor', async () => {
    const page = canonicalPage({ milestones: [...canonicalPage().milestones.slice(0, 49), milestone(1)] });
    mockCanonical(page, [Promise.resolve({ ok: true, status: 200, data: { data: { milestones: [milestone(1), milestone(51), milestone(51)], milestones_total: 53, per_page: 50, next_cursor: nextCursor } } })]);
    const wrapper = mountView(); await flushPromises(); await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    expect(wrapper.findAll('[data-reached-milestone]')).toHaveLength(49);
    await wrapper.get('[data-load-more-milestones]').trigger('click');
    expect(wrapper.findAll('[data-reached-milestone]')).toHaveLength(50);
    expect(wrapper.get('[data-load-more-milestones]').exists()).toBe(true);
  });

  it('preserves rows and cursor, announces continuation failures, and retries successfully', async () => {
    mockCanonical(canonicalPage(), [Promise.resolve({ ok: false, status: 500, data: {} }), Promise.resolve({ ok: true, status: 200, data: { data: { milestones: [milestone(51)], milestones_total: 53, per_page: 50, next_cursor: null } } })]);
    const wrapper = mountView(); await flushPromises(); await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    await wrapper.get('[data-load-more-milestones]').trigger('click');
    expect(wrapper.get('[data-milestone-load-error]').attributes('aria-live')).toBe('polite');
    expect(wrapper.findAll('[data-reached-milestone]')).toHaveLength(50);
    expect(wrapper.get('[data-load-more-milestones]').exists()).toBe(true);
    await wrapper.get('[data-retry-milestones]').trigger('click');
    expect(wrapper.findAll('[data-reached-milestone]')).toHaveLength(51);
    expect(wrapper.find('[data-milestone-load-error]').exists()).toBe(false);
  });

  it('handles continuation 401, rejected requests, and duplicate loading clicks', async () => {
    let release; const pending = new Promise((resolve) => { release = resolve; });
    mockCanonical(canonicalPage(), [pending]); const logout = vi.spyOn(store, 'logout');
    const wrapper = mountView(); await flushPromises(); await wrapper.get('[data-progress-tab="milestones"]').trigger('click');
    const button = wrapper.get('[data-load-more-milestones]'); await button.trigger('click'); await button.trigger('click');
    expect(apiGet.mock.calls.filter(([path]) => path.startsWith('/api/v1/mobile/achievements/v2/milestones?')).length).toBe(1);
    release({ ok: false, status: 401, data: {} }); await flushPromises();
    expect(logout).toHaveBeenCalledOnce(); expect(wrapper.vm.$router.push).toHaveBeenCalledWith('/login');
    mockCanonical(canonicalPage(), [() => Promise.reject(new Error('offline'))]); const rejected = mountView(); await flushPromises(); await rejected.get('[data-progress-tab="milestones"]').trigger('click'); await rejected.get('[data-load-more-milestones]').trigger('click');
    expect(rejected.get('[data-milestone-load-error]').text()).toContain('Could not load more reached milestones');
  });
});
