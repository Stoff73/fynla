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

describe('capture forms', () => {
  const schema = { name: 'property', submit_label: 'Save', kinds: [{ key: 'main_residence', label: 'Home', fields: ['current_value'] }], fields: { current_value: { type: 'money', label: 'Value', required: true } } };

  it('renders a capture_form event as a form on the Fyn row and marks the reply as received', () => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    w.vm.handleFynEvent(cursor, { type: 'capture_form', prompt_text: 'Now your property.', form: schema });
    expect(cursor.got).toBe(true);
    expect(cursor.reply.text).toBe('Now your property.');
    expect(cursor.reply.form.schema).toEqual(schema);
    expect(cursor.reply.form.locked).toBe(false);
  });

  it('posts a form answer with the forms header and no message, and replaces the placeholder user row', async () => {
    const { apiStream } = await import('../../api.js');
    apiStream.mockImplementation(async (path, body, token, onDelta, onEvent) => {
      onEvent({ type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' });
      onEvent({ type: 'done' });
      return { ok: true, status: 200, text: '' };
    });
    const w = mount(Host);
    w.vm.conversationId = 7;
    const form = { name: 'property', answers: { main_residence: { current_value: 750000 } } };

    await w.vm.submitCaptureForm(form);

    const body = apiStream.mock.calls.at(-1)[1];
    expect(body.form).toEqual(form);
    expect(body.message).toBeUndefined();
    const user = w.vm.messages.find((m) => m.role === 'user');
    expect(user.text).toBe('Home worth £750,000, no mortgage, individual.');
  });

  it('keeps a form row that arrives with no lead-in after "Yes, add another" (CSJ 2026-09-16)', async () => {
    const { apiStream } = await import('../../api.js');
    apiStream.mockImplementation(async (path, body, token, onDelta, onEvent) => {
      onEvent({ type: 'onboarding_advance', from_step: 'campaign_isa_more', to_step: 'campaign_isa_holdings' });
      onEvent({ type: 'capture_form', prompt_text: '', form: schema });
      onEvent({ type: 'done' });
      return { ok: true, status: 200, text: '' };
    });
    const w = mount(Host);
    w.vm.conversationId = 7;

    await w.vm.send('Yes, add another');

    const formRow = w.vm.messages.find((m) => m.form && m.form.schema);
    expect(formRow).toBeTruthy();
    expect(formRow.text).toBe('');
    expect(formRow.form.locked).toBe(false);
  });

  it('locks every earlier form and attaches errors to the latest', () => {
    const w = mount(Host);
    const row = { role: 'fyn', text: 'x', bubbles: [], form: { schema, errors: null, answers: null, locked: false } };
    w.vm.messages = [row];
    w.vm.handleFynEvent({ reply: row, got: true }, { type: 'capture_form_errors', form: 'property', errors: { main_residence: { message: 'Too many', fields: {} } } });
    expect(row.form.errors.main_residence.message).toBe('Too many');
  });

  it('re-renders a persisted form from history as a locked-or-open form row', async () => {
    const { apiGet } = await import('../../api.js');
    apiGet.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { messages: [
      { role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema } },
    ] } } });
    const w = mount(Host);
    await w.vm.loadTranscript(7);
    expect(w.vm.messages.at(-1).form.schema).toEqual(schema);
    expect(w.vm.messages.at(-1).form.locked).toBe(false);
  });

  // Ruling 9: the persisted schema lives on the assistant row, but the
  // answers the user actually submitted are on the NEXT row (a user turn
  // carrying metadata.form.answers) — mirroring the web panel's lookup.
  // Once a later row exists, the form turn is no longer the last message, so
  // it locks too.
  it('finds a locked form\'s answers on the following persisted user row', async () => {
    const { apiGet } = await import('../../api.js');
    apiGet.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { messages: [
      { role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema } },
      { role: 'user', content: 'Home worth £750,000, no mortgage, individual.', metadata: { form: { answers: { main_residence: { current_value: 750000 } } } } },
    ] } } });
    const w = mount(Host);
    await w.vm.loadTranscript(7);
    const formRow = w.vm.messages.find((m) => m.form);
    expect(formRow.form.answers).toEqual({ main_residence: { current_value: 750000 } });
    expect(formRow.form.locked).toBe(true);
  });

  // Ruling 13: send() locks every form on the optimistic assumption it will
  // be accepted. A refusal must reopen it with the values intact (spec §4
  // item 4 — "the step stays parked and the form stays open").
  it('reopens a refused form with its errors attached', async () => {
    const { apiStream } = await import('../../api.js');
    apiStream.mockImplementation(async (path, body, token, onDelta, onEvent) => {
      onEvent({ type: 'form_received', text: 'A buy-to-let worth £300,000.' });
      onEvent({ type: 'capture_form_errors', form: 'property', errors: { buy_to_let: { message: "You have reached your plan's property limit.", fields: {} } } });
      onEvent({ type: 'done' });
      return { ok: true, status: 200, text: '' };
    });
    const w = mount(Host);
    w.vm.conversationId = 7;
    const formRow = { role: 'fyn', text: 'Now your property.', bubbles: [], form: { schema, errors: null, answers: null, locked: false } };
    w.vm.messages = [formRow];
    const form = { name: 'property', answers: { buy_to_let: { current_value: 300000 } } };

    await w.vm.send(null, form);

    expect(formRow.form.locked).toBe(false);
    expect(formRow.form.errors.buy_to_let.message).toContain('property limit');
  });

  it('locks a successfully accepted form and clears any earlier refusal errors', async () => {
    const { apiStream } = await import('../../api.js');
    apiStream.mockImplementation(async (path, body, token, onDelta, onEvent) => {
      onEvent({ type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' });
      onEvent({ type: 'done' });
      return { ok: true, status: 200, text: '' };
    });
    const w = mount(Host);
    w.vm.conversationId = 7;
    const formRow = { role: 'fyn', text: 'Now your property.', bubbles: [], form: { schema, errors: { main_residence: { message: 'Too many', fields: {} } }, answers: null, locked: false } };
    w.vm.messages = [formRow];
    const form = { name: 'property', answers: { main_residence: { current_value: 750000 } } };

    await w.vm.send(null, form);

    expect(formRow.form.locked).toBe(true);
    expect(formRow.form.errors).toBeNull();
  });
});
