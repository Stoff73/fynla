import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// W-0028 — /m/app/goals is titled "Goals and life events" and rendered only
// goals. It never called /api/life-events, so a user planning a £350,000
// downsizing saw no sign the app knew about it, on the one page claiming to
// cover it. Desktop reads the same endpoint at /goals?tab=events.
vi.mock('../../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: {} })),
  apiStream: vi.fn(() => Promise.resolve({ ok: true, status: 200, text: '' })),
}));

vi.mock('../../../authExpiry.js', () => ({
  handleAuthExpiry: vi.fn(() => false),
}));

import { apiGet } from '../../../api.js';
import Goals from '../Goals.vue';

const GOALS = [
  {
    id: 1,
    goal_name: 'Early Retirement Fund',
    display_goal_type: 'Retirement',
    current_amount: 95000,
    target_amount: 200000,
    progress_percentage: 47.5,
    months_remaining: 77,
    is_on_track: true,
  },
];

const EVENTS = [
  {
    id: 10,
    event_name: 'Downsizing Property Sale',
    display_event_type: 'Property Sale',
    amount: '350000.00',
    impact_type: 'income',
    expected_date: '2046-06-01',
    certainty: 'possible',
    status: 'expected',
    has_occurred: false,
  },
  {
    id: 11,
    event_name: 'Kitchen & Extension',
    display_event_type: 'Home Improvement',
    amount: '85000.00',
    impact_type: 'expense',
    expected_date: '2027-04-01',
    certainty: 'likely',
    status: 'expected',
    has_occurred: false,
  },
  {
    id: 12,
    event_name: "Previous Inheritance (David's Aunt)",
    display_event_type: 'Inheritance',
    amount: '45000.00',
    impact_type: 'income',
    expected_date: '2020-03-15',
    certainty: 'confirmed',
    status: 'completed',
    has_occurred: true,
  },
];

function mockEndpoints({ events = EVENTS } = {}) {
  apiGet.mockImplementation((path) => {
    if (path === '/api/goals') {
      return Promise.resolve({ ok: true, status: 200, data: { data: { goals: GOALS } } });
    }
    if (path === '/api/goals/dashboard-overview') {
      return Promise.resolve({
        ok: true,
        status: 200,
        data: { data: { total_goals: 1, on_track_count: 1, total_target: 200000, total_current: 95000, overall_progress: 47.5 } },
      });
    }
    if (path === '/api/life-events') {
      return Promise.resolve({ ok: true, status: 200, data: { data: { events, count: events.length } } });
    }
    return Promise.resolve({ ok: true, status: 200, data: {} });
  });
}

function mountGoals() {
  return mount(Goals, {
    global: {
      stubs: {
        MobileChrome: { template: '<div><slot /></div>' },
      },
      mocks: {
        $route: { path: '/goals', query: {} },
        $router: { push: vi.fn() },
      },
    },
  });
}

describe('/m goals page', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockEndpoints();
  });

  // W-0411 — /m read `is_on_track` and composed its own label, so an overdue
  // goal read "Behind", and an overdue-but-funded one read "Complete". The
  // server now serves one vocabulary to every surface (Rule 20). FIXTURE NOTE:
  // the shared GOALS fixture holds one healthy future-dated goal, so nothing
  // else in this file enters the overdue branch.
  it('shows the overdue label the server serves, not one it composes itself', async () => {
    apiGet.mockImplementation((path) => {
      if (path === '/api/goals') {
        return Promise.resolve({
          ok: true,
          status: 200,
          data: { data: { goals: [{
            ...GOALS[0],
            id: 99,
            goal_name: "Charlotte's Gap Year Fund",
            progress_percentage: 80,
            months_remaining: 0,
            is_on_track: false,
            is_overdue: true,
            status_label: 'Overdue',
          }] } },
        });
      }
      if (path === '/api/goals/dashboard-overview') {
        return Promise.resolve({ ok: true, status: 200, data: { data: { total_goals: 1, on_track_count: 0 } } });
      }
      return Promise.resolve({ ok: true, status: 200, data: { data: { events: [], count: 0 } } });
    });

    const wrapper = mountGoals();
    await flushPromises();

    const text = wrapper.text();

    expect(text).toContain('Overdue');
    expect(text).not.toContain('On track');
  });

  it('fetches life events alongside goals', async () => {
    mountGoals();
    await flushPromises();

    expect(apiGet.mock.calls.map(([path]) => path)).toContain('/api/life-events');
  });

  it('renders every life event with amount, date, impact direction and certainty', async () => {
    const wrapper = mountGoals();
    await flushPromises();

    const text = wrapper.text();

    expect(text).toContain('Downsizing Property Sale');
    expect(text).toContain('Kitchen & Extension');
    expect(text).toContain('Property Sale');
    expect(text).toContain('01 Jun 2046');
    expect(text).toContain('Possible');
    expect(text).toContain('+£350,000');
    expect(text).toContain('-£85,000');
  });

  it('labels a completed event by its status rather than its certainty', async () => {
    const wrapper = mountGoals();
    await flushPromises();

    expect(wrapper.text()).toContain('Completed');
    expect(wrapper.text()).not.toContain('Confirmed');
  });

  it('separates expected income from expected expenses', async () => {
    const wrapper = mountGoals();
    await flushPromises();

    expect(wrapper.text()).toContain('£350,000 expected in');
    expect(wrapper.text()).toContain('£85,000 expected out');
  });

  // W-0207 — this assertion used to read £395,000, because the 2020 inheritance
  // in the fixture above was counted as money still expected in. The test shared
  // the code's misconception and so could never have failed on it; it named the
  // offending record and asserted it as income in the same breath.
  it('leaves an event that has already happened out of the expected totals', async () => {
    const wrapper = mountGoals();
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain("Previous Inheritance (David's Aunt)");
    expect(text).not.toContain('£395,000');
    expect(text).toContain('£350,000 expected in');
  });

  it('says so when there are no life events rather than showing nothing', async () => {
    mockEndpoints({ events: [] });
    const wrapper = mountGoals();
    await flushPromises();

    expect(wrapper.text()).toContain("You haven't recorded any life events yet.");
  });
});
