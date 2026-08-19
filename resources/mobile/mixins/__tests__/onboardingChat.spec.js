import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

// Regression coverage for the /m dead-token bug (CSJ report, 2026-07-21): a 401
// from any chat API call must log the user out and redirect to the /m login
// screen, instead of rendering a "Sorry, something went wrong" bubble that
// strands them with no way forward. apiPost/apiGet/apiStream all resolve
// { ok, status, ... } on a non-2xx response (see api.js) rather than throwing,
// so this must be checked on the resolved value, not via try/catch.
vi.mock('../../api.js', () => ({
  apiGet: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: {} })),
  apiPost: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: {} })),
  apiStream: vi.fn(() => Promise.resolve({ ok: true, status: 200, text: '' })),
}));

import { apiGet, apiPost, apiStream } from '../../api.js';
import { store } from '../../store.js';
import onboardingChat from '../onboardingChat.js';

// A minimal host so the shared mixin can be exercised without pulling in a
// full view (Dashboard.vue / MobileChrome.vue both mix this in as-is).
const Host = defineComponent({
  mixins: [onboardingChat],
  render() { return h('div'); },
});

describe('onboardingChat mixin — handleAuthExpiry (D1: dead-token chat taps)', () => {
  let wrapper;
  let push;

  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'dead-token';
    store.user = null;
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    push = vi.fn();
    wrapper = mount(Host, {
      global: {
        mocks: {
          $router: { push },
          $route: { path: '/dashboard', query: {} },
        },
      },
    });
  });

  it('logs out and redirects to /m login on a 401, and reports it handled', () => {
    store.token = 'dead-token';
    expect(wrapper.vm.handleAuthExpiry({ status: 401 })).toBe(true);
    expect(store.token).toBeNull();
    expect(push).toHaveBeenCalledWith('/login');
  });

  it('is a no-op for a healthy response', () => {
    store.token = 'still-alive';
    expect(wrapper.vm.handleAuthExpiry({ status: 200 })).toBe(false);
    expect(store.token).toBe('still-alive');
    expect(push).not.toHaveBeenCalled();
  });

  it('is a no-op when there is no response object at all', () => {
    expect(wrapper.vm.handleAuthExpiry(null)).toBe(false);
    expect(push).not.toHaveBeenCalled();
  });

  it('send(): a 401 on the message stream redirects instead of rendering a generic error bubble', async () => {
    store.token = 'dead-token';
    apiPost.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { id: 'conv-1' } } });
    apiStream.mockResolvedValueOnce({ ok: false, status: 401, text: '' });

    await wrapper.vm.send('What should I do next?');

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
    const fynReply = wrapper.vm.messages.find((m) => m.role === 'fyn');
    expect(fynReply?.text || '').not.toMatch(/sorry/i);
  });

  it('send(): a 401 while creating the conversation redirects without a "could not start" bubble', async () => {
    store.token = 'dead-token';
    apiPost.mockResolvedValueOnce({ ok: false, status: 401, data: {} });

    await wrapper.vm.send('Hello');

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
    expect(wrapper.vm.conversationId).toBeNull();
    const fynReply = wrapper.vm.messages.find((m) => m.role === 'fyn');
    expect(fynReply?.text || '').not.toMatch(/could not start/i);
  });
});

describe('onboardingChat mixin — contextual and explicit conversation loading', () => {
  let wrapper;

  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = {
      id: 1,
      onboarding_completed: false,
      onboarding_fyn_step: 'campaign_verify_navigate',
    };
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    wrapper = mount(Host, {
      global: {
        mocks: {
          $router: { push: vi.fn() },
          $route: { path: '/savings', query: {} },
        },
      },
    });
  });

  it('creates a fresh contextual conversation on every call and loads its persisted opening', async () => {
    const request = {
      action: 'edit',
      resource_type: 'savings',
      current_destination: { screen: 'savings', params: {}, fallback: 'dashboard' },
      origin: { kind: 'surface_action', recommendation_id: null },
    };
    apiPost
      .mockResolvedValueOnce({ ok: true, status: 201, data: { data: { conversation: { id: 101 } } } })
      .mockResolvedValueOnce({ ok: true, status: 201, data: { data: { conversation: { id: 102 } } } });
    apiGet
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { data: { messages: [{ role: 'assistant', content: 'First trusted opening.', metadata: {} }] } },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { data: { messages: [{ role: 'assistant', content: 'Second trusted opening.', metadata: {} }] } },
      });

    expect(await wrapper.vm.createContextualConversation(request)).toBe(101);
    expect(wrapper.vm.messages).toEqual([{ role: 'fyn', text: 'First trusted opening.', bubbles: [], actionBubbles: false }]);
    expect(await wrapper.vm.createContextualConversation(request)).toBe(102);
    expect(wrapper.vm.messages[0].text).toBe('Second trusted opening.');
    expect(apiPost).toHaveBeenCalledTimes(2);
    expect(apiPost).toHaveBeenNthCalledWith(1, '/api/ai-chat/contextual-conversations', request, 'live-token');
    expect(apiPost).toHaveBeenNthCalledWith(2, '/api/ai-chat/contextual-conversations', request, 'live-token');
  });

  it('returns null on creation failure so the caller can keep the current screen visible and retry', async () => {
    wrapper.vm.conversationId = 88;
    wrapper.vm.messages = [{ role: 'user', text: 'stale' }];
    apiPost.mockResolvedValue({ ok: false, status: 422, data: {} });

    expect(await wrapper.vm.createContextualConversation({ action: 'edit' })).toBeNull();
    expect(wrapper.vm.conversationId).toBeNull();
    expect(wrapper.vm.messages).toEqual([]);
  });

  it('keeps the server opening and retries the same conversation when transcript loading fails', async () => {
    const request = { action: 'edit' };
    apiPost.mockResolvedValueOnce({
      ok: true,
      status: 201,
      data: {
        data: {
          conversation: { id: 103 },
          opening_message: {
            role: 'assistant', content: 'Trusted opening from Laravel.', metadata: {},
          },
        },
      },
    });
    apiGet
      .mockResolvedValueOnce({ ok: false, status: 503, data: {} })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { data: { messages: [{ role: 'assistant', content: 'Persisted trusted opening.', metadata: {} }] } },
      });

    expect(await wrapper.vm.createContextualConversation(request)).toBe(103);
    expect(wrapper.vm.messages.map((message) => message.text)).toEqual(['Trusted opening from Laravel.']);
    expect(wrapper.vm.transcriptLoadError).toContain('full conversation');

    expect(await wrapper.vm.retryTranscript()).toBe(true);
    expect(wrapper.vm.messages.map((message) => message.text)).toEqual(['Persisted trusted opening.']);
    expect(wrapper.vm.transcriptLoadError).toBe('');
    expect(apiPost).toHaveBeenCalledTimes(1);
    expect(apiGet).toHaveBeenCalledTimes(2);
  });

  it('opens an exact history conversation without creating or resuming another one', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { data: { messages: [{ role: 'user', content: 'Exact prior turn', metadata: {} }] } },
    });

    expect(await wrapper.vm.openConversation(77)).toBe(77);
    expect(wrapper.vm.conversationId).toBe(77);
    expect(apiGet).toHaveBeenCalledWith('/api/ai-chat/conversations/77', 'live-token');
    expect(apiPost).not.toHaveBeenCalled();
    expect(apiStream).not.toHaveBeenCalled();
  });

  it('returns a typed canonical fallback instead of opening an unavailable transcript', async () => {
    apiGet.mockResolvedValue({
      ok: false,
      status: 410,
      data: {
        error: 'contextual_resource_unavailable',
        data: { fallback_destination: { screen: 'savings', params: {}, fallback: 'dashboard' } },
      },
    });

    expect(await wrapper.vm.openConversation(77)).toBeNull();
    expect(wrapper.vm.transcriptFallbackDestination).toEqual({
      screen: 'savings', params: {}, fallback: 'dashboard',
    });
    expect(wrapper.vm.messages).toEqual([]);
  });
});

// The View link the capture layer emits (GateRoutes resolves `mobile_route`
// server-side) used to be filtered through a hardcoded list of "/m routes"
// that had drifted — /personal-information was never in it, so every Personal
// or Family Details View link rendered and then did nothing when tapped.
describe('onboardingChat mixin — View link navigation', () => {
  // closeFyn lives on the host (MobileChrome / Dashboard), not the mixin.
  const NavHost = defineComponent({
    mixins: [onboardingChat],
    methods: { closeFyn() {} },
    render() { return h('div'); },
  });

  const mountOn = (path) => {
    const push = vi.fn();
    const resolve = vi.fn((target) => ({
      matched: ['/dashboard', '/personal-information', '/expenditure'].includes(target) ? [{}] : [],
    }));
    const wrapper = mount(NavHost, {
      global: { mocks: { $router: { push, resolve }, $route: { path, query: {} } } },
    });

    return { wrapper, push };
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    store.token = 'live-token';
  });

  it('follows a View link to a screen /m has', () => {
    const { wrapper, push } = mountOn('/dashboard');
    wrapper.vm.chooseBubble({ id: 'view_record', label: 'View Family Details', route: '/personal-information' });
    vi.advanceTimersByTime(400);

    expect(push).toHaveBeenCalledWith('/personal-information');
  });

  it('ignores a route /m does not have rather than pushing a dead path', () => {
    const { wrapper, push } = mountOn('/dashboard');
    wrapper.vm.chooseBubble({ id: 'view_record', label: 'View Risk Profile', route: '/risk-profile' });
    vi.advanceTimersByTime(400);

    expect(push).not.toHaveBeenCalled();
  });

  it('refreshes in place when the View link points at the screen already open', () => {
    const before = store.screenRefreshTick;
    const { wrapper, push } = mountOn('/personal-information');
    wrapper.vm.chooseBubble({ id: 'view_record', label: 'View Family Details', route: '/personal-information' });
    vi.advanceTimersByTime(400);

    expect(push).not.toHaveBeenCalled();
    expect(store.screenRefreshTick).toBe(before + 1);
  });
});
