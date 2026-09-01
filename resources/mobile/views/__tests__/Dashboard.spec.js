import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { readFileSync } from 'node:fs';

// Regression coverage for D2 (CSJ report, 2026-07-21): the advice suggestion
// pills must not render until the user's onboarding state is actually known.
// onboardingActive (onboardingChat mixin) is false BOTH when onboarding is
// genuinely finished AND while store.user is still null (a token-only arrival,
// or a non-fatal loadUser() failure) — so gating on onboardingActive alone let
// a mid-onboarding user see and tap pills that route them wrongly before their
// real state resolved. showSuggestionPills additionally requires store.user.
vi.mock('../../api.js', () => ({
  apiGet: vi.fn((path) => {
    if (path === '/api/auth/user') return Promise.resolve({ ok: false, status: 401, data: {} });
    if (path === '/api/v1/mobile/dashboard') {
      return Promise.resolve({ ok: true, status: 200, data: { data: { focus_areas: [] } } });
    }
    return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
  }),
  apiPost: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: {} })),
  apiStream: vi.fn(() => Promise.resolve({ ok: true, status: 200, text: '' })),
}));

import { apiGet } from '../../api.js';
import Dashboard from '../Dashboard.vue';
import { store } from '../../store.js';

const REC_FOCUS_AREAS = [
  {
    key: 'top',
    label: 'Top actions',
    locked: false,
    actions: [
      { id: 'r1', type: 'recommendation', title: 'Top up your ISA', done: false },
    ],
  },
];

function mountDashboard() {
  return mount(Dashboard, {
    global: {
      mocks: {
        $route: { path: '/dashboard', query: {} },
        $router: { push: vi.fn() },
      },
      stubs: {
        GamificationCelebration: true,
      },
    },
  });
}

describe('Dashboard.vue — showSuggestionPills (D2: pills before onboarding state known)', () => {
  let wrapper;

  beforeEach(async () => {
    vi.clearAllMocks();
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
  });

  it('shows pills once the user record is loaded, onboarding is finished, and suggestions exist', async () => {
    store.token = 'live-token';
    store.user = {
      id: 1,
      first_name: 'Jo',
      onboarding_completed: true,
      onboarding_fyn_step: null,
      active_campaign: null,
    };
    wrapper = mountDashboard();
    await flushPromises();
    wrapper.vm.focusAreas = REC_FOCUS_AREAS;
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.suggestions.length).toBeGreaterThan(0);
    expect(wrapper.vm.showSuggestionPills).toBe(true);
  });

  it('hides pills while store.user is still null, even though suggestions are queued', async () => {
    store.token = 'live-token';
    store.user = null; // token-only arrival / loadUser() has not resolved yet
    wrapper = mountDashboard();
    await flushPromises();
    wrapper.vm.focusAreas = REC_FOCUS_AREAS;
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.suggestions.length).toBeGreaterThan(0);
    expect(store.user).toBeNull();
    expect(wrapper.vm.showSuggestionPills).toBe(false);
  });

  it('keeps pills hidden when loadUser() fails non-fatally (store.user stays null)', async () => {
    store.token = 'live-token';
    store.user = null;
    wrapper = mountDashboard();
    // mounted() awaits loadUser(); /api/auth/user is mocked to a 401 above,
    // which loadUser() treats as non-fatal and leaves store.user untouched.
    await flushPromises();

    expect(store.user).toBeNull();
    wrapper.vm.focusAreas = REC_FOCUS_AREAS;
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.showSuggestionPills).toBe(false);
  });
});

// Regression coverage for D3 (CSJ report, 2026-07-21): openRecChat used to call
// openFyn() (which can fire the async, unawaited startOnboarding() stream) and
// immediately send() a follow-up — send() silently no-op'd because sending was
// still true. openFyn() now returns the open+init promise chain, so
// openRecChat must await it before sending.
describe('Dashboard.vue — openRecChat awaits openFyn() (D3: rec-chat race)', () => {
  it('does not send() until openFyn()\'s promise settles', async () => {
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null, active_campaign: null };
    const wrapper = mountDashboard();
    await flushPromises();

    let resolveOpen;
    const openPromise = new Promise((resolve) => { resolveOpen = resolve; });
    const openSpy = vi.spyOn(wrapper.vm, 'openFyn').mockReturnValue(openPromise);
    const sendSpy = vi.spyOn(wrapper.vm, 'send').mockImplementation(() => {});

    const tapPromise = wrapper.vm.openRecChat({ title: 'Top up your ISA' });
    await Promise.resolve();
    await Promise.resolve();
    expect(openSpy).toHaveBeenCalled();
    expect(sendSpy).not.toHaveBeenCalled();

    resolveOpen();
    await tapPromise;
    expect(sendSpy).toHaveBeenCalledWith('How do I "Top up your ISA"?');
  });
});

// Adjacent instance of the D3 race (flagged, not fixed, in the CSJ report):
// openFynForCapture had the identical unawaited openFyn() -> immediate send()
// shape as openRecChat. Same fix applies — await openFyn() before sending.
describe('Dashboard.vue — openFynForCapture awaits openFyn() (D3 adjacent: capture-nudge race)', () => {
  it('does not send() until openFyn()\'s promise settles', async () => {
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null, active_campaign: null };
    const wrapper = mountDashboard();
    await flushPromises();

    let resolveOpen;
    const openPromise = new Promise((resolve) => { resolveOpen = resolve; });
    const openSpy = vi.spyOn(wrapper.vm, 'openFyn').mockReturnValue(openPromise);
    const sendSpy = vi.spyOn(wrapper.vm, 'send').mockImplementation(() => {});

    const tapPromise = wrapper.vm.openFynForCapture('savings');
    await Promise.resolve();
    await Promise.resolve();
    expect(openSpy).toHaveBeenCalled();
    expect(sendSpy).not.toHaveBeenCalled();

    resolveOpen();
    await tapPromise;
    expect(sendSpy).toHaveBeenCalledWith('Help me add my savings details');
  });
});

// Adjacent instance of D1 (dead-token dead-end), flagged in the CSJ report:
// load()'s dashboard fetch had the same "generic dead-end error on a failed
// response" shape as the chat paths, but for a different code path (dashboard
// load, not chat). A 401 must now route through handleAuthExpiry (logout +
// redirect) instead of rendering the generic "could not load your dashboard"
// error message.
describe('Dashboard.vue — load() routes a 401 through handleAuthExpiry', () => {
  it('logs out and redirects to /m login on a 401, without setting the generic error', async () => {
    apiGet.mockImplementation((path) => {
      if (path === '/api/v1/mobile/dashboard') {
        return Promise.resolve({ ok: false, status: 401, data: {} });
      }
      return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
    });
    store.token = 'dead-token';
    store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null, active_campaign: null };
    const push = vi.fn();
    const wrapper = mount(Dashboard, {
      global: {
        mocks: {
          $route: { path: '/dashboard', query: {} },
          $router: { push },
        },
        stubs: { GamificationCelebration: true },
      },
    });
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
    expect(wrapper.vm.error).toBe('');
  });

  it('shows the generic error on a non-401 failure (unchanged behaviour)', async () => {
    apiGet.mockImplementation((path) => {
      if (path === '/api/v1/mobile/dashboard') {
        return Promise.resolve({ ok: false, status: 500, data: {} });
      }
      return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
    });
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null, active_campaign: null };
    const push = vi.fn();
    const wrapper = mount(Dashboard, {
      global: {
        mocks: {
          $route: { path: '/dashboard', query: {} },
          $router: { push },
        },
        stubs: { GamificationCelebration: true },
      },
    });
    await flushPromises();

    expect(push).not.toHaveBeenCalledWith('/login');
    expect(store.token).toBe('live-token');
    expect(wrapper.vm.error).toBe('We could not load your dashboard. Please try again.');
  });
});

describe('Dashboard.vue — recommendation text remains readable', () => {
  it('renders complete long action copy and uses wrapping CSS with a full tap target', async () => {
    apiGet.mockImplementation((path) => {
      if (path === '/api/v1/mobile/dashboard') {
        return Promise.resolve({ ok: true, status: 200, data: { data: { focus_areas: [] } } });
      }
      return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
    });
    store.token = 'live-token';
    store.user = {
      id: 1,
      onboarding_completed: true,
      onboarding_fyn_step: null,
      active_campaign: null,
    };
    const wrapper = mountDashboard();
    await flushPromises();

    const title = 'Review whether increasing your workplace pension contributions could improve your retirement outcome';
    const meta = 'This explanation must remain readable across multiple lines on a narrow mobile screen.';
    wrapper.vm.focusAreas = [{
      key: 'top',
      label: 'Top actions',
      locked: false,
      actions: [{ id: 'long-1', type: 'recommendation', title, meta, done: false }],
    }];
    await wrapper.vm.$nextTick();

    expect(wrapper.get('.md-rec__title').text()).toBe(title);
    expect(wrapper.get('.md-rec__meta').text()).toBe(meta);

    const css = readFileSync('resources/mobile/views/dashboard.css', 'utf8');
    const titleRule = css.match(/(?:^|\n)\.md-rec__title\s*\{([^}]*)\}/)?.[1] || '';
    const metaRule = css.match(/(?:^|\n)\.md-rec__meta\s*\{([^}]*)\}/)?.[1] || '';
    const actionRule = css.match(/(?:^|\n)\.md-rec__action\s*\{([^}]*)\}/)?.[1] || '';

    expect(titleRule).toContain('white-space: normal');
    expect(titleRule).toContain('overflow-wrap: anywhere');
    expect(titleRule).not.toContain('text-overflow: ellipsis');
    expect(metaRule).toContain('overflow-wrap: anywhere');
    expect(actionRule).toContain('min-height: 44px');
  });
});

describe('Dashboard.vue — Bank Accounts presentation naming', () => {
  it('keeps the savings key and compatible route while presenting the finance panel as Bank Accounts', async () => {
    apiGet.mockImplementation((path) => {
      if (path === '/api/v1/mobile/dashboard') {
        return Promise.resolve({
          ok: true,
          status: 200,
          data: {
            data: {
              focus_areas: [],
              modules: { savings: { total_savings: 5000 } },
              net_worth: { total: 5000, breakdown: { assets: { savings: 5000 } } },
            },
          },
        });
      }
      return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
    });
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null, active_campaign: null };
    const wrapper = mountDashboard();
    await flushPromises();

    const panel = wrapper.vm.finances.find((item) => item.key === 'savings');
    expect(panel).toMatchObject({ label: 'Bank Accounts', route: '/savings' });
  });
});

/**
 * W-0504. Three of the five rings were filled to constants from the 2026 redesign
 * (7eaa085cb) — 72, `covered ? 85 : 0`, and `value > 0 ? 72 : 0` — while the captions
 * beside them were wired to real data. So the arc and the number inside it described
 * different quantities: on `peak_earners` the net-worth ring rendered at 72% next to
 * `+0%`, and the investment ring at 72% while investments were 11% of assets.
 *
 * An arc at 72% looks deliberate, which is why it went unnoticed.
 *
 * The rule these pin is the one acceptance 2 states: whatever a ring renders must agree
 * with the number printed inside it, independent of which metric wins.
 */
describe('W-0504 donut rings are derived, not constants', () => {
  const source = readFileSync('resources/mobile/views/Dashboard.vue', 'utf8');

  // Comments stripped before matching. Without this the guard reads the comments
  // that DESCRIBE the old constants — "this ring was `progress: 72`" — and reports the
  // defect it is documenting as a live one.
  const cardsBlock = source
    .slice(source.indexOf('const f = dashboardFigures'))
    .split('\n')
    .filter((line) => !line.trim().startsWith('//'))
    .join('\n');

  it('fills no ring with a hardcoded percentage', () => {
    // The three literals as they were written, so a reintroduction in any of the three
    // shapes fails rather than only the bare number.
    expect(cardsBlock).not.toMatch(/progress:\s*72\b/);
    expect(cardsBlock).not.toMatch(/progress:\s*[^,\n]*\?\s*85\s*:/);
    expect(cardsBlock).not.toMatch(/progress:\s*[^,\n]*\?\s*72\s*:/);
  });

  it('drives every ring from a figure on the shared derivation', () => {
    const progresses = [...cardsBlock.matchAll(/progress:\s*([^,\n]+)/g)].map((m) => m[1].trim());

    expect(progresses.length).toBeGreaterThan(0);
    progresses.forEach((expression) => {
      expect(expression).toMatch(/\bf\./);
    });
  });

  it('shows the net worth ring the same quantity it fills the arc with', () => {
    // The defect in one line: `progress: 72` beside `vizNum: trend%`. Whatever the ring
    // is labelled, the arc and the number have to be the same thing.
    expect(cardsBlock).toMatch(/progress:\s*f\.netWorth\.equityPct[\s\S]{0,120}vizNum:\s*f\.netWorth\.equityPct/);
  });
});
