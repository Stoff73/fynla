import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/aiChatService', () => ({
  default: {
    sendMessageStream: vi.fn(),
    getConversation: vi.fn(),
  },
}));

import aiChatService from '@/services/aiChatService';
import aiChat from '@/store/modules/aiChat';

// M4 (live fynla.org /m, 2026-09-29): the director's multi_select flag must
// reach the quick_replies row on the live stream and on a resumed transcript,
// so the step renders as toggle-then-submit either way.

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

const bubbles = [{ id: 'bank', label: 'Bank account' }, { id: 'done', label: "That's everything" }];

function harness() {
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

  return { localState, commit, dispatch };
}

describe('aiChat store — multi-select quick replies (M4)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('carries multi_select from the live quick_replies event', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'quick_replies', prompt_text: 'Which of these do you have?', bubbles, multi_select: true },
      { type: 'done', message_id: 5 },
    ]));
    const { localState, commit, dispatch } = harness();

    await aiChat.actions.sendMessage({ commit, dispatch, state: localState, rootState: { route: { path: '/dashboard' } } }, 'No');

    const row = localState.messages.find((m) => m.role === 'quick_replies');
    expect(row.metadata.multi_select).toBe(true);
    expect(row.metadata.bubbles).toEqual(bubbles);
  });

  it('carries multi_select from a stored message when a conversation is resumed', async () => {
    aiChatService.getConversation.mockResolvedValue({ data: {
      conversation: { id: 10, title: 'Fyn' },
      messages: [{ id: 3, role: 'assistant', content: 'Which of these do you have?', metadata: { bubbles, multi_select: true } }],
    } });
    const { localState, commit } = harness();

    await aiChat.actions.loadConversation({ commit }, 10);

    const row = localState.messages.find((m) => m.role === 'quick_replies');
    expect(row.metadata.multi_select).toBe(true);
  });

  it('leaves single-choice rows unflagged', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'quick_replies', prompt_text: 'Do you have a spouse?', bubbles: [{ id: 'yes', label: 'Yes' }] },
      { type: 'done', message_id: 6 },
    ]));
    const { localState, commit, dispatch } = harness();

    await aiChat.actions.sendMessage({ commit, dispatch, state: localState, rootState: { route: { path: '/dashboard' } } }, 'Full-time');

    expect(localState.messages.find((m) => m.role === 'quick_replies').metadata.multi_select).toBe(false);
  });
});
