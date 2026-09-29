import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
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
    expect(wrapper.vm.messages).toEqual([{ role: 'fyn', text: 'First trusted opening.', bubbles: [], actionBubbles: false, multiSelect: false }]);
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

/**
 * MB-24. The front door's "Something else" bubble has the corpus id `skip`
 * (path_choice.bubbles), and so does the spouse step's synthesised skip link.
 * chooseBubble routed every `skip` id to the action endpoint, so "Something
 * else" answered "This step cannot be skipped." Only the synthesised skip link
 * is a director action; a regular bubble sends its label, as web does.
 */
describe('onboardingChat mixin — skip id collision at the front door (MB-24)', () => {
  let wrapper;

  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'tok';
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    wrapper = mount(Host, {
      global: { mocks: { $router: { push: vi.fn() }, $route: { path: '/dashboard', query: {} } } },
    });
    wrapper.vm.messages = [];
  });

  const bubblesTurn = (cursor, extra = {}) => wrapper.vm.handleFynEvent(cursor, {
    type: 'quick_replies',
    prompt_text: 'How would you like to start?',
    bubbles: [
      { id: 'journey', label: 'Follow a journey' },
      { id: 'focus', label: 'Pick a focus' },
      { id: 'skip', label: 'Something else' },
    ],
    ...extra,
  });

  it('sends "Something else" as a message at the front door', () => {
    const send = vi.spyOn(wrapper.vm, 'send').mockResolvedValue();
    const runFynAction = vi.spyOn(wrapper.vm, 'runFynAction').mockResolvedValue();
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    wrapper.vm.messages.push(cursor.reply);
    bubblesTurn(cursor);

    const somethingElse = cursor.reply.bubbles.find((b) => b.id === 'skip');
    wrapper.vm.chooseBubble(somethingElse, cursor.reply);

    expect(send).toHaveBeenCalledWith('Something else');
    expect(runFynAction).not.toHaveBeenCalled();
  });

  it('still runs the skip action for the spouse step skip link', () => {
    const send = vi.spyOn(wrapper.vm, 'send').mockResolvedValue();
    const runFynAction = vi.spyOn(wrapper.vm, 'runFynAction').mockResolvedValue();
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    wrapper.vm.messages.push(cursor.reply);
    wrapper.vm.handleFynEvent(cursor, {
      type: 'quick_replies',
      prompt_text: 'Tell me about your partner',
      bubbles: [{ id: 'yes', label: 'Yes' }],
      skip_link: { label: 'Skip this for now' },
    });

    const skipLink = cursor.reply.bubbles.find((b) => b.label === 'Skip this for now');
    wrapper.vm.chooseBubble(skipLink, cursor.reply);

    expect(runFynAction).toHaveBeenCalledWith('skip');
    expect(send).not.toHaveBeenCalled();
  });
});

// Batch 4 (CSJ 2026-09-19): an edit form's values and record travel with the
// live event and the restored transcript.
describe('edit forms', () => {
  const schema = { name: 'savings', submit_label: 'Save changes', edit: true, kinds: [{ key: 'current_account', label: 'Current account', fields: ['current_value'] }], fields: { current_value: { type: 'money', label: 'Balance', required: true } } };

  it('keeps the values and record from a capture_form event', () => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    w.vm.handleFynEvent(cursor, { type: 'capture_form', prompt_text: 'Here it is.', form: schema, values: { current_account: { current_value: 150 } }, record: { type: 'savings_account', id: 7 } });
    expect(cursor.reply.form.answers).toEqual({ current_account: { current_value: 150 } });
    expect(cursor.reply.form.record).toEqual({ type: 'savings_account', id: 7 });
  });
});

// A Fyn write lands while the user is on a screen (Edit employer benefits on
// /m Protection opens Fyn's form over the page). Without a route change the
// screen never remounts, so it kept the old figures until a reload (fynla.org,
// 2026-09-29). Every write bumps the refresh tick every /m screen watches.
describe('a Fyn write refreshes the screen behind the chat', () => {
  it.each(['entity_created', 'entity_updated', 'entity_deleted'])('bumps the refresh tick on %s', (type) => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    const before = store.screenRefreshTick;

    w.vm.handleFynEvent(cursor, { type, name: 'your employer benefits' });

    expect(store.screenRefreshTick).toBe(before + 1);
  });

  it('bumps the refresh tick on capture_complete', () => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    const before = store.screenRefreshTick;

    w.vm.handleFynEvent(cursor, { type: 'capture_complete', summary: 'Saved.' });

    expect(store.screenRefreshTick).toBe(before + 1);
  });
});

describe('a saved edit form refreshes the screen behind the chat', () => {
  // The edit-form path streams form_received, then plain content: no entity
  // event. The end of an unrefused form turn is the only "saved" signal.
  const events = (list) => (url, body, token, onText, onEvent) => {
    list.forEach((ev) => (ev.type === 'content' ? onText(ev.text) : onEvent(ev)));
    return Promise.resolve({ ok: true, status: 200, text: '' });
  };

  beforeEach(() => {
    store.token = 'token';
    apiPost.mockResolvedValue({ ok: true, status: 201, data: { data: { id: 5 } } });
  });

  it('bumps the refresh tick once when the form is saved', async () => {
    apiStream.mockImplementationOnce(events([
      { type: 'form_received', text: 'My job gives me death in service of 2 times my salary.' },
      { type: 'content', text: 'Updated — My job gives me death in service of 2 times my salary.' },
      { type: 'done' },
    ]));
    const w = mount(Host);
    w.vm.conversationId = 5;
    const before = store.screenRefreshTick;

    await w.vm.submitCaptureForm({ name: 'employer_benefits', answers: {}, record: { type: 'employer_benefits', id: 1 } });

    expect(store.screenRefreshTick).toBe(before + 1);
  });

  it('does not refresh when the form is refused', async () => {
    apiStream.mockImplementationOnce(events([
      { type: 'form_received', text: 'Remove this record.' },
      { type: 'capture_form_errors', form: 'employer_benefits', errors: { _form: { message: 'No.', fields: [] } } },
      { type: 'content', text: 'No.' },
      { type: 'done' },
    ]));
    const w = mount(Host);
    w.vm.conversationId = 5;
    const before = store.screenRefreshTick;

    await w.vm.submitCaptureForm({ name: 'employer_benefits', answers: {}, record: { type: 'employer_benefits', id: 1 } });

    expect(store.screenRefreshTick).toBe(before);
  });
});

// L3-2 (fynla.org /m, 29 Sep 2026, conversation 940): the stream closed after
// "Let me pull the full details..." and the preamble stood as the answer, with
// no error. An interrupted turn now says so and offers the same turn again.
describe('onboardingChat mixin — interrupted turns', () => {
  let wrapper;

  beforeEach(() => {
    vi.clearAllMocks();
    // clearAllMocks keeps one-shot values an earlier test queued but never used.
    apiStream.mockReset();
    apiStream.mockResolvedValue({ ok: true, status: 200, text: '' });
    // An earlier block leaves fake timers installed.
    vi.useRealTimers();
    store.token = 'live-token';
    store.user = { first_name: 'Jordan', onboarding_completed: true };
    wrapper = mount(Host, {
      global: { mocks: { $router: { push: vi.fn() }, $route: { path: '/dashboard', query: {} } } },
    });
    wrapper.vm.conversationId = 940;
  });

  it('replaces the cut-off preamble with the interrupted message and a Try again bubble', async () => {
    apiStream.mockImplementationOnce(async (path, body, token, onDelta) => {
      onDelta("I'll fetch the latest tax information. ");
      return { ok: true, status: 200, text: '', interrupted: true };
    });

    await wrapper.vm.send('How does the tax trap work?');

    const reply = wrapper.vm.messages[wrapper.vm.messages.length - 1];
    expect(reply.text).toBe('Sorry, my reply was cut off before I finished. Please try again.');
    expect(reply.bubbles).toEqual([{
      id: 'fyn_retry',
      label: 'Try again',
      // The cut-off turn's own id goes with the retry (FynTurnLedger).
      retry: { text: 'How does the tax trap work?', form: null, turnId: apiStream.mock.calls[0][1].turn_id },
    }]);
    expect(wrapper.vm.sending).toBe(false);
  });

  it('Try again asks the same question once more, without doubling it in the chat', async () => {
    apiStream
      .mockResolvedValueOnce({ ok: true, status: 200, text: '', interrupted: true })
      .mockImplementationOnce(async (path, body, token, onDelta) => {
        onDelta('Above £100,000 you lose £1 of allowance for every £2.');
        return { ok: true, status: 200, text: '', interrupted: false };
      });

    await wrapper.vm.send('How does the tax trap work?');
    const reply = wrapper.vm.messages[wrapper.vm.messages.length - 1];
    await wrapper.vm.chooseBubble(reply.bubbles[0], reply);
    await new Promise((r) => { setTimeout(r, 0); });

    expect(apiStream).toHaveBeenCalledTimes(2);
    expect(apiStream.mock.calls[1][1]).toMatchObject({ message: 'How does the tax trap work?' });
    // The same turn id, so a turn the server already took is not taken twice.
    expect(apiStream.mock.calls[0][1].turn_id).toBeTruthy();
    expect(apiStream.mock.calls[1][1].turn_id).toBe(apiStream.mock.calls[0][1].turn_id);
    expect(wrapper.vm.messages.map((m) => [m.role, m.text])).toEqual([
      ['user', 'How does the tax trap work?'],
      ['fyn', 'Above £100,000 you lose £1 of allowance for every £2.'],
    ]);
  });

  it('shows the stored reply when the server has already answered the retried turn', async () => {
    apiStream
      .mockResolvedValueOnce({ ok: true, status: 200, text: '', interrupted: true })
      .mockResolvedValueOnce({ ok: true, status: 200, text: '', turnTaken: 'answered' });
    const loadTranscript = vi.spyOn(wrapper.vm, 'loadTranscript').mockResolvedValue(true);

    await wrapper.vm.send('How does the tax trap work?');
    const reply = wrapper.vm.messages[wrapper.vm.messages.length - 1];
    await wrapper.vm.chooseBubble(reply.bubbles[0], reply);
    await new Promise((r) => { setTimeout(r, 0); });

    expect(loadTranscript).toHaveBeenCalledWith(940);
    expect(apiStream).toHaveBeenCalledTimes(2);
  });

  it('offers Try again when the connection drops mid-stream', async () => {
    apiStream.mockRejectedValueOnce(new TypeError('network error'));

    await wrapper.vm.send('How does the tax trap work?');

    const reply = wrapper.vm.messages[wrapper.vm.messages.length - 1];
    expect(reply.text).toBe('Sorry, my reply was cut off before I finished. Please try again.');
    expect(reply.bubbles[0]).toMatchObject({ id: 'fyn_retry', label: 'Try again' });
  });

  it('leaves a finished turn alone', async () => {
    apiStream.mockImplementationOnce(async (path, body, token, onDelta) => {
      onDelta('Here is the answer.');
      return { ok: true, status: 200, text: '', interrupted: false };
    });

    await wrapper.vm.send('Hello');

    const reply = wrapper.vm.messages[wrapper.vm.messages.length - 1];
    expect(reply.text).toBe('Here is the answer.');
    expect(reply.bubbles).toEqual([]);
  });
});

// M6 (live fynla.org /m, 2026-09-29): after a SaveTax registrant finished
// onboarding and asked Fyn a question, returning to the dashboard re-opened Fyn
// full-screen and the conversation was gone. Two causes, both in this mixin's
// remit: the user snapshot kept onboarding_fyn_needs_start from the first fetch,
// and the conversation id lived only in component data, which /m (no
// <keep-alive>) destroys on every route change.
describe('onboardingChat mixin — M6: onboarding flags stay truthful', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    store.setFynConversation(null);
  });

  function mountHost() {
    return mount(Host, {
      global: { mocks: { $router: { push: vi.fn() }, $route: { path: '/tax-strategy', query: {} } } },
    });
  }

  it('onboarding_complete clears needs_start at once and re-reads the user only after the stream ends', async () => {
    store.user = {
      id: 7,
      onboarding_completed: false,
      onboarding_fyn_step: 'campaign_terminal',
      onboarding_fyn_needs_start: true,
      active_campaign: 'savetax',
    };
    let userFetchedMidStream = null;
    apiPost.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { id: 'conv-1' } } });
    apiStream.mockImplementationOnce(async (_url, _body, _token, _onDelta, onEvent) => {
      onEvent({ type: 'onboarding_complete', nextRoute: '/tax-strategy' });
      userFetchedMidStream = apiGet.mock.calls.some(([path]) => path === '/api/auth/user');
      return { ok: true, status: 200, text: '' };
    });
    apiGet.mockImplementation((path) => Promise.resolve(path === '/api/auth/user'
      ? { ok: true, status: 200, data: { data: { user: { id: 7, onboarding_completed: true, onboarding_fyn_step: null, onboarding_fyn_needs_start: false, active_campaign: null } } } }
      : { ok: true, status: 200, data: {} }));

    const wrapper = mountHost();
    await wrapper.vm.send("Yes, that's right");

    expect(userFetchedMidStream).toBe(false);
    expect(apiGet).toHaveBeenCalledWith('/api/auth/user', 'live-token');
    await flushPromises();
    expect(store.user.onboarding_completed).toBe(true);
    expect(store.user.onboarding_fyn_needs_start).toBe(false);
    expect(store.user.active_campaign).toBeNull();
    expect(wrapper.vm.onboardingNeedsStart).toBe(false);
    expect(wrapper.vm.onboardingActive).toBe(false);
    apiGet.mockReset();
    apiGet.mockImplementation(() => Promise.resolve({ ok: true, status: 200, data: {} }));
  });

  it('a first onboarding start re-reads the user so a stale needs_start cannot outlive it', async () => {
    store.user = { id: 7, onboarding_completed: false, onboarding_fyn_step: null, onboarding_fyn_needs_start: true };
    apiStream.mockImplementationOnce(async (_url, _body, _token, onDelta, onEvent) => {
      onEvent({ type: 'conversation_created', conversation_id: 'conv-start' });
      onDelta('Hello');
      return { ok: true, status: 200, text: '' };
    });
    const wrapper = mountHost();

    await wrapper.vm.startOnboarding();

    expect(store.user.onboarding_fyn_needs_start).toBe(false);
    expect(apiGet).toHaveBeenCalledWith('/api/auth/user', 'live-token');
  });

  it('does not re-read the user after an ordinary advice turn', async () => {
    store.user = { id: 7, onboarding_completed: true, onboarding_fyn_step: null, onboarding_fyn_needs_start: false };
    apiPost.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { id: 'conv-2' } } });
    apiStream.mockImplementationOnce(async (_url, _body, _token, onDelta) => {
      onDelta('An answer');
      return { ok: true, status: 200, text: '' };
    });
    const wrapper = mountHost();

    await wrapper.vm.send('How much can I put in my pension?');

    expect(apiGet).not.toHaveBeenCalledWith('/api/auth/user', expect.anything());
  });
});

describe('onboardingChat mixin — M6: the current conversation survives a remount', () => {
  const transcript = {
    ok: true,
    status: 200,
    data: {
      data: {
        messages: [
          { role: 'user', content: 'How much can I put in my pension?', metadata: {} },
          { role: 'assistant', content: 'Here is how the annual allowance works.', metadata: {} },
        ],
      },
    },
  };

  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    store.user = { id: 7, onboarding_completed: true, onboarding_fyn_step: null, onboarding_fyn_needs_start: false };
    store.setFynConversation(null);
  });

  function mountHost() {
    return mount(Host, {
      global: { mocks: { $router: { push: vi.fn() }, $route: { path: '/dashboard', query: {} } } },
    });
  }

  it('records the conversation in the store and reloads its transcript in a freshly mounted chat', async () => {
    apiPost.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { id: 'conv-9' } } });
    apiStream.mockImplementationOnce(async (_url, _body, _token, onDelta) => {
      onDelta('Here is how the annual allowance works.');
      return { ok: true, status: 200, text: '' };
    });
    const first = mountHost();
    await first.vm.send('How much can I put in my pension?');
    await first.vm.$nextTick();
    first.unmount();

    expect(store.currentFynConversationId()).toBe('conv-9');
    expect(JSON.parse(window.sessionStorage.getItem('m_fyn_conversation')).id).toBe('conv-9');

    apiGet.mockImplementation((path) => Promise.resolve(path === '/api/ai-chat/conversations/conv-9'
      ? transcript
      : { ok: true, status: 200, data: {} }));
    const second = mountHost();
    expect(second.vm.conversationId).toBeNull();

    await expect(second.vm.resumeCurrentConversation()).resolves.toBe(true);

    expect(second.vm.conversationId).toBe('conv-9');
    expect(second.vm.messages.map((m) => m.text)).toEqual([
      'How much can I put in my pension?',
      'Here is how the annual allowance works.',
    ]);
    apiGet.mockReset();
    apiGet.mockImplementation(() => Promise.resolve({ ok: true, status: 200, data: {} }));
  });

  it('forgets a conversation that can no longer be loaded and lets the caller greet', async () => {
    store.setFynConversation('conv-gone');
    apiGet.mockImplementation(() => Promise.resolve({ ok: false, status: 404, data: {} }));
    const wrapper = mountHost();

    await expect(wrapper.vm.resumeCurrentConversation()).resolves.toBe(false);

    expect(wrapper.vm.conversationId).toBeNull();
    expect(wrapper.vm.messages).toEqual([]);
    expect(store.currentFynConversationId()).toBeNull();
    apiGet.mockReset();
    apiGet.mockImplementation(() => Promise.resolve({ ok: true, status: 200, data: {} }));
  });

  it('logout clears the current conversation from memory and session storage', () => {
    store.setFynConversation('conv-9');
    expect(store.currentFynConversationId()).toBe('conv-9');

    store.logout();

    expect(store.currentFynConversationId()).toBeNull();
    expect(window.sessionStorage.getItem('m_fyn_conversation')).toBeNull();
  });

  it('never hands one user\'s conversation to another user on the same device', () => {
    store.user = { id: 7 };
    store.setFynConversation('conv-9');
    store.user = { id: 8 };

    expect(store.currentFynConversationId()).toBeNull();
  });

  it('a contextual launch opens its own conversation and becomes the current one', async () => {
    store.setFynConversation('conv-9');
    apiPost.mockResolvedValueOnce({
      ok: true,
      status: 200,
      data: { data: { conversation: { id: 'conv-ctx' }, opening_message: { role: 'assistant', content: 'Which account?' } } },
    });
    apiGet.mockImplementation(() => Promise.resolve({ ok: true, status: 200, data: { data: { messages: [] } } }));
    const wrapper = mountHost();

    await wrapper.vm.createContextualConversation({ action: 'edit', resource_type: 'savings' });
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.conversationId).toBe('conv-ctx');
    expect(apiGet).not.toHaveBeenCalledWith('/api/ai-chat/conversations/conv-9', expect.anything());
    expect(store.currentFynConversationId()).toBe('conv-ctx');
    apiGet.mockReset();
    apiGet.mockImplementation(() => Promise.resolve({ ok: true, status: 200, data: {} }));
  });
});

// M4 (live fynla.org /m, 2026-09-29): the multi-select flag travels with the
// live quick_replies event and the restored transcript, and the combined
// submission is one message.
describe('multi-select bubbles (M4)', () => {
  const bubbles = [{ id: 'bank', label: 'Bank account' }, { id: 'isa', label: 'ISA' }, { id: 'done', label: "That's everything" }];

  it('flags a multi-select quick_replies event on the reply row', () => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    w.vm.handleFynEvent(cursor, { type: 'quick_replies', prompt_text: 'Which of these do you have?', bubbles, multi_select: true });
    expect(cursor.reply.multiSelect).toBe(true);

    const other = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.handleFynEvent(other, { type: 'quick_replies', prompt_text: 'Yes or no?', bubbles: [{ id: 'yes', label: 'Yes' }] });
    expect(other.reply.multiSelect).toBe(false);
  });

  it('restores the flag from the stored message metadata', async () => {
    const { apiGet } = await import('../../api.js');
    apiGet.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { messages: [
      { role: 'assistant', content: 'Which of these do you have?', metadata: { bubbles, multi_select: true } },
    ] } } });
    const w = mount(Host);
    await w.vm.loadTranscript(7);
    expect(w.vm.messages.at(-1).multiSelect).toBe(true);
    expect(w.vm.messages.at(-1).bubbles).toHaveLength(3);
  });

  it('sends the combined submission as one message', () => {
    const w = mount(Host);
    const send = vi.spyOn(w.vm, 'send').mockResolvedValue();
    w.vm.chooseBubble({ id: 'done', label: "Bank account, ISA, That's everything" }, { bubbles, multiSelect: true });
    expect(send).toHaveBeenCalledTimes(1);
    expect(send).toHaveBeenCalledWith("Bank account, ISA, That's everything");
  });
});
