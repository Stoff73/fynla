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

import { apiPost, apiStream } from '../../api.js';
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
