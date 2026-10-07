import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/aiChatService', () => ({
  default: {
    sendMessageStream: vi.fn(),
    streamQueuedMessage: vi.fn(),
  },
}));

import aiChatService from '@/services/aiChatService';
import aiChat from '@/store/modules/aiChat';

function streamReader(events) {
  const payload = `${events.map((event) => `data: ${JSON.stringify(event)}`).join('\n\n')}\n\n`;
  const chunks = [new TextEncoder().encode(payload)];

  return {
    read: vi.fn(async () => (
      chunks.length > 0
        ? { done: false, value: chunks.shift() }
        : { done: true, value: undefined }
    )),
  };
}

describe('desktop Fyn stream event parity', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // CSJ 2026-10-04: the screen shows the reply as stored, never a second version.
  it('shows the stored reply from done in place of what streamed', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'content', text: 'First. ' },
      { type: 'content', text: 'Second. Done.' },
      { type: 'done', message_id: 43, content: 'Done.' },
    ]));

    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [],
      conversations: [],
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);

    await aiChat.actions.sendMessage({
      commit,
      dispatch: vi.fn().mockResolvedValue(undefined),
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    }, 'Break down my income');

    const reply = localState.messages.filter((message) => message.role === 'assistant').at(-1);
    expect(reply.content).toBe('Done.');
  });

  it('never queues a celebration from a level_up frame — the climb belongs to the dashboard', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'content', text: 'Your savings account is recorded.' },
      { type: 'done', message_id: 42 },
      { type: 'level_up', level: 3, level_name: 'Building', next_actions: ['Add a goal'] },
    ]));

    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [],
      conversations: [],
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.sendMessage({
      commit,
      dispatch,
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    }, 'Add my savings account');

    // A level-up must never interrupt a Fyn turn. The frame stays on the wire
    // for older clients, but this one banks nothing and shows nothing; the
    // climb is spent on the dashboard hero circle (CSJ 2026-09-17).
    expect(dispatch).not.toHaveBeenCalledWith(
      expect.stringContaining('gamification/'),
      expect.anything(),
      expect.anything(),
    );
    expect(localState.messages.some((m) => m.role === 'assistant')).toBe(true);
  });

  it('keeps the queued capture confirmation, and still ignores the level-up frame', async () => {
    aiChatService.streamQueuedMessage.mockResolvedValue(streamReader([
      { type: 'entity_created', entity_type: 'savings_account', entity_id: 7, name: 'Cash ISA' },
      { type: 'content', text: 'A Cash ISA keeps the interest tax-free.' },
      { type: 'capture_complete', summary: 'Saved to your records', records_created: [{ id: 7 }] },
      { type: 'done', message_id: 44 },
      { type: 'level_up', level: 3, level_name: 'Building', next_actions: [] },
    ]));

    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [{ id: 43, role: 'user', content: 'Add my Cash ISA', status: 'queued' }],
      streaming: false,
      isOnboardingActive: false,
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.streamNextQueued({
      commit,
      dispatch,
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    });

    expect(localState.messages).toEqual(expect.arrayContaining([
      expect.objectContaining({
        role: 'capture_complete',
        content: 'Saved to your records',
      }),
    ]));
    expect(localState.messages.findIndex((message) => message.role === 'assistant'))
      .toBeLessThan(localState.messages.findIndex((message) => message.role === 'capture_complete'));
    expect(dispatch).not.toHaveBeenCalledWith(
      expect.stringContaining('gamification/'),
      expect.anything(),
      expect.anything(),
    );
  });

  it('does not repeat the reply as the record card heading (one version of a message)', async () => {
    const line = 'Recorded — date of birth 14 March 1981.';
    aiChatService.streamQueuedMessage.mockResolvedValue(streamReader([
      { type: 'content', text: line },
      { type: 'capture_complete', summary: line, records_created: [{ id: 3, type: 'personal' }] },
      { type: 'done', message_id: 45, content: line },
    ]));
    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [{ id: 43, role: 'user', content: '14/03/1981', status: 'queued' }],
      streaming: false,
      isOnboardingActive: false,
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);

    await aiChat.actions.streamNextQueued({
      commit,
      dispatch: vi.fn().mockResolvedValue(undefined),
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    });

    expect(localState.messages.filter((m) => m.content === line)).toHaveLength(1);
    expect(localState.messages.find((m) => m.role === 'capture_complete').metadata.records_created).toHaveLength(1);
  });

  it('carries the server-resolved page onto every entity write message', async () => {
    // SPEC-crud-handler-contract 5.4 — the route comes from the server so the
    // panel stops keeping its own table (it had one until 2026-08-17, the
    // fourth copy). An edit and a delete had no event at all before this.
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      {
        type: 'entity_created',
        entity_type: 'savings_account',
        entity_id: 7,
        name: 'Cash ISA',
        route: '/savings',
        mobile_route: '/savings',
        label: 'Bank Accounts',
      },
      {
        type: 'entity_updated',
        entity_type: 'dc_pension',
        entity_id: 9,
        name: 'Aviva Pension',
        route: '/retirement',
        mobile_route: '/retirement',
        label: 'Retirement',
      },
      {
        type: 'entity_deleted',
        entity_type: 'dc_pension',
        entity_id: 9,
        name: 'Aviva Pension',
        route: '/retirement',
        mobile_route: '/retirement',
        label: 'Retirement',
      },
      { type: 'done', message_id: 51 },
    ]));

    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [],
      conversations: [],
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);
    const refreshes = vi.fn();
    window.addEventListener('fyn-screen-refresh', refreshes);

    await aiChat.actions.sendMessage({
      commit,
      dispatch,
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    }, { message: 'Add my Cash ISA' });
    window.removeEventListener('fyn-screen-refresh', refreshes);

    // Each write tells the screen behind the chat to refetch (a gift saved
    // from the gifts card's Fyn link left the card stale until a reload).
    expect(refreshes).toHaveBeenCalledTimes(3);

    const writes = localState.messages.filter((message) => message.role.startsWith('entity_'));

    expect(writes.map((message) => message.role)).toEqual([
      'entity_created',
      'entity_updated',
      'entity_deleted',
    ]);
    expect(writes[0].metadata).toMatchObject({
      entity_type: 'savings_account',
      entity_id: 7,
      route: '/savings',
      label: 'Bank Accounts',
    });
  });

  it.each([
    ['refreshes the screen behind the chat after a saved form', [{ type: 'form_received', summary: 'I gave Sam £5,000 on 1 May 2025 (a gift to a person).' }, { type: 'content', text: 'Saved.' }], 1],
    ['leaves the screen alone when the form is refused', [{ type: 'capture_form_errors', errors: { gift_value: 'Required' } }], 0],
  ])('%s', async (_name, events, expected) => {
    // A gift saved from the gifts card's Fyn link confirms in plain text with
    // no entity event, so the card stayed stale until a reload (2026-10-07).
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([...events, { type: 'done', message_id: 60 }]));
    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [],
      conversations: [],
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const refreshes = vi.fn();
    window.addEventListener('fyn-screen-refresh', refreshes);

    await aiChat.actions.sendMessage({
      commit,
      dispatch: vi.fn().mockResolvedValue(undefined),
      state: localState,
      rootState: { route: { path: '/actions/estate_gifts_pet_window' } },
    }, { form: { name: 'gift', answers: { pet: { recipient: 'Sam', gift_date: '2025-05-01', gift_value: 5000 } } } });
    window.removeEventListener('fyn-screen-refresh', refreshes);

    expect(refreshes).toHaveBeenCalledTimes(expected);
  });

  it('renders a subscription action after the accurate at-cap reply', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'content', text: "You've reached your plan's limit of 2 goals. To add more, upgrade your plan." },
      {
        type: 'action',
        action: 'subscription_options',
        reason: 'tier_limit_reached',
        entity_key: 'goal',
        current_count: 2,
        limit: 2,
        tier: 'free',
      },
      { type: 'done', message_id: 45 },
    ]));

    const localState = {
      ...aiChat.state,
      currentConversation: { id: 10, title: 'Fyn' },
      messages: [],
      conversations: [],
      streamingText: '',
      error: null,
    };
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);

    await aiChat.actions.sendMessage({
      commit,
      dispatch: vi.fn().mockResolvedValue(undefined),
      state: localState,
      rootState: { route: { path: '/goals' } },
    }, 'Add a third goal');

    const assistantIndex = localState.messages.findIndex(message => message.role === 'assistant');
    const actionIndex = localState.messages.findIndex(message => message.role === 'action');
    expect(localState.messages[assistantIndex].content).toContain('upgrade your plan');
    expect(localState.messages[actionIndex].metadata).toMatchObject({
      action: 'subscription_options',
      entity_key: 'goal',
      limit: 2,
      tier: 'free',
    });
    expect(actionIndex).toBeGreaterThan(assistantIndex);
  });
});
