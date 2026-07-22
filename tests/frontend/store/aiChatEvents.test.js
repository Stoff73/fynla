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

  it('queues the desktop celebration when level_up follows done', async () => {
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

    expect(dispatch).toHaveBeenCalledWith('gamification/queueCelebration', {
      level: 3,
      level_name: 'Building',
      next_actions: ['Add a goal'],
    }, { root: true });
  });

  it('keeps queued capture confirmation and level-up events', async () => {
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
    expect(dispatch).toHaveBeenCalledWith('gamification/queueCelebration', {
      level: 3,
      level_name: 'Building',
      next_actions: [],
    }, { root: true });
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
