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
      { id: 'r1', type: 'recommendation', title: 'Top up your ISA', ask_fyn_prompt: 'Tell me more about: Top up your ISA', done: false },
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

    const tapPromise = wrapper.vm.openRecChat({ title: 'Top up your ISA', ask_fyn_prompt: 'Tell me more about: Top up your ISA' });
    await Promise.resolve();
    await Promise.resolve();
    expect(openSpy).toHaveBeenCalled();
    expect(sendSpy).not.toHaveBeenCalled();

    resolveOpen();
    await tapPromise;
    expect(sendSpy).toHaveBeenCalledWith('Tell me more about: Top up your ISA');
  });
});

// Adjacent instance of the D3 race (flagged, not fixed, in the CSJ report):
describe('Dashboard.vue — refetches on the shared screen-refresh tick', () => {
  it('reloads the dashboard silently when a Fyn turn closes on this screen', async () => {
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null, active_campaign: null };
    const wrapper = mountDashboard();
    await flushPromises();

    const loadSpy = vi.spyOn(wrapper.vm, 'load').mockResolvedValue(undefined);
    store.bumpScreenRefresh();
    await flushPromises();

    expect(loadSpy).toHaveBeenCalledWith({ silent: true });
  });
});

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

    const tapPromise = wrapper.vm.openFynForCapture({ kind: 'fyn_capture', payload: 'savings', prompt: 'Help me add my savings details' });
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
 * One figure, every surface (CSJ 2026-10-01). The five cards render the server's
 * `cards` block (DashboardCards) as sent: no ring, bar or caption is worked out
 * here. W-0504's constants (72, 85) cannot come back because this file no longer
 * sets any ring at all.
 */
describe('Dashboard.vue — finance cards are the server cards, as sent', () => {
  const source = readFileSync('resources/mobile/views/Dashboard.vue', 'utf8');
  const block = source.slice(source.indexOf('    finances() {'), source.indexOf('  watch: {'));

  it('sets no ring, bar or caption of its own', () => {
    expect(block).not.toMatch(/progress:|barFill:|barValue:|vizNum:|caption:/);
  });

  it('renders the server card for each panel', () => {
    const cards = {
      net_worth: { value: 480000, value_is_income: false, caption: '£700,000 assets', visual: { type: 'donut', progress: 69, number: '69%', label: 'Equity' } },
      retirement: { value: 26514, value_is_income: true, caption: 'Your income this year', visual: { type: 'bar', progress: 0, number: 'runs out by about age 76', label: '' } },
    };
    const ctx = { data: { cards }, fmt: (v) => `£${Number(v).toLocaleString('en-GB')}` };
    const finances = Dashboard.computed.finances.call(ctx);

    expect(finances.find((p) => p.key === 'net_worth')).toMatchObject({
      label: 'Net worth', value: '£480,000', caption: '£700,000 assets', viz: 'donut', progress: 69, vizNum: '69%', vizCap: 'Equity',
    });
    expect(finances.find((p) => p.key === 'retirement')).toMatchObject({
      value: '£26,514/year', caption: 'Your income this year', viz: 'bar', barValue: 'runs out by about age 76',
    });
  });
});

// M6 (live fynla.org /m, 2026-09-29): a SaveTax registrant finished onboarding,
// asked Fyn a question, came back to the dashboard — and Fyn re-opened
// full-screen with the conversation gone.
describe('Dashboard.vue — M6: returning to the dashboard after onboarding', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    store.setFynConversation(null);
    // The user re-read fails here, so each test proves what the locally
    // mirrored flags do on their own.
    apiGet.mockImplementation((path) => {
      if (path === '/api/auth/user') return Promise.resolve({ ok: false, status: 500, data: {} });
      if (path === '/api/v1/mobile/dashboard') {
        return Promise.resolve({ ok: true, status: 200, data: { data: { focus_areas: [] } } });
      }
      return Promise.resolve({ ok: true, status: 200, data: { data: {} } });
    });
  });

  it('control: a user who still needs their first turn gets Fyn opened on mount', async () => {
    store.user = { id: 7, first_name: 'Jo', onboarding_completed: false, onboarding_fyn_step: null, onboarding_fyn_needs_start: true };
    const wrapper = mountDashboard();
    await flushPromises();

    expect(wrapper.vm.fynMounted).toBe(true);
    wrapper.unmount();
  });

  it('does not re-open Fyn on a remount once the stream has reported onboarding_complete', async () => {
    store.user = {
      id: 7,
      first_name: 'Jo',
      onboarding_completed: false,
      onboarding_fyn_step: 'campaign_terminal',
      onboarding_fyn_needs_start: true,
      active_campaign: 'savetax',
    };
    const first = mountDashboard();
    await flushPromises();
    first.vm.handleFynEvent({ reply: { role: 'fyn', text: '', bubbles: [] } }, { type: 'onboarding_complete', nextRoute: '/tax-strategy' });
    first.vm.settleUserSnapshot();
    await flushPromises();
    first.unmount();

    const second = mountDashboard();
    await flushPromises();

    expect(second.vm.fynMounted).toBe(false);
    expect(second.vm.fynOpen).toBe(false);
  });

  it('re-opening Fyn after a remount resumes the conversation instead of greeting afresh', async () => {
    store.user = { id: 7, first_name: 'Jo', onboarding_completed: true, onboarding_fyn_step: null, onboarding_fyn_needs_start: false, active_campaign: null };
    store.setFynConversation('conv-9');
    const defaultGet = apiGet.getMockImplementation();
    apiGet.mockImplementation((path) => {
      if (path === '/api/ai-chat/conversations/conv-9') {
        return Promise.resolve({
          ok: true,
          status: 200,
          data: { data: { messages: [
            { role: 'user', content: 'How much can I put in my pension?', metadata: {} },
            { role: 'assistant', content: 'Here is how the annual allowance works.', metadata: {} },
          ] } },
        });
      }
      return defaultGet(path);
    });
    const wrapper = mountDashboard();
    await flushPromises();
    expect(wrapper.vm.fynMounted).toBe(false);

    await wrapper.vm.openFyn();

    expect(wrapper.vm.conversationId).toBe('conv-9');
    expect(wrapper.vm.messages.map((m) => m.text)).toEqual([
      'How much can I put in my pension?',
      'Here is how the annual allowance works.',
    ]);
    expect(wrapper.vm.messages.some((m) => /What would you like to look at/.test(m.text))).toBe(false);
    apiGet.mockImplementation(defaultGet);
  });

  it('with no conversation this session, opening Fyn still greets', async () => {
    store.user = { id: 7, first_name: 'Jo', onboarding_completed: true, onboarding_fyn_step: null, onboarding_fyn_needs_start: false, active_campaign: null };
    const wrapper = mountDashboard();
    await flushPromises();

    await wrapper.vm.openFyn();

    expect(wrapper.vm.conversationId).toBeNull();
    expect(wrapper.vm.messages[0].text).toBe('Hi Jo. What would you like to look at?');
  });
});
