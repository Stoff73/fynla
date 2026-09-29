import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/aiChatService', () => ({
  default: {
    sendMessageStream: vi.fn(),
    streamQueuedMessage: vi.fn(),
    postActionStream: vi.fn(),
  },
}));

import aiChatService from '@/services/aiChatService';
import aiChat from '@/store/modules/aiChat';
import { FYN_INTERRUPTED_MESSAGE } from '../../../resources/mobile/utils/fynStream.js';

// L3-2 (fynla.org, 29 Sep 2026): a stream that closed after Fyn's "let me pull
// the full details" preamble left the web chat with its partial text wiped and
// no error. A turn without a terminal frame is now an interrupted turn: the
// error says so and "Try again" runs the same turn.
function streamReader(events) {
  const payload = events.map((event) => `data: ${JSON.stringify(event)}\n\n`).join('');
  const chunks = [new TextEncoder().encode(payload)];
  return {
    read: vi.fn(async () => (chunks.length
      ? { done: false, value: chunks.shift() }
      : { done: true, value: undefined })),
  };
}

function harness() {
  const localState = {
    ...aiChat.state,
    currentConversation: { id: 10, title: 'Fyn' },
    messages: [],
    conversations: [],
    streamingText: '',
    error: null,
    retryTurn: null,
  };
  const commit = (name, payload) => aiChat.mutations[name](localState, payload);
  const dispatch = vi.fn().mockResolvedValue(undefined);
  const context = { commit, dispatch, state: localState, rootState: { route: { path: '/dashboard' } } };
  return { localState, dispatch, context };
}

describe('an interrupted Fyn turn on web', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the interrupted error with a retry instead of a half answer', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'thinking' },
      { type: 'content', text: "I'll fetch the latest tax information." },
    ]));
    const { localState, dispatch, context } = harness();

    await aiChat.actions.sendMessage(context, 'How does the tax trap work?');

    expect(localState.error).toBe(FYN_INTERRUPTED_MESSAGE);
    expect(localState.retryTurn).toMatchObject({ arg: 'How does the tax trap work?' });
    expect(localState.messages.some((m) => m.role === 'assistant')).toBe(false);
    expect(localState.streaming).toBe(false);
    // A cut-off turn is not a finished one, so the queue is not popped behind it.
    expect(dispatch).not.toHaveBeenCalledWith('streamNextQueued');
  });

  it('keeps a finished turn exactly as before', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'content', text: 'Here is the answer.' },
      { type: 'done', message_id: 5 },
    ]));
    const { localState, context } = harness();

    await aiChat.actions.sendMessage(context, 'Hello');

    expect(localState.error).toBeNull();
    expect(localState.retryTurn).toBeNull();
    expect(localState.messages.find((m) => m.role === 'assistant')?.content).toBe('Here is the answer.');
  });

  it('does not offer a retry when the server reported the failure itself', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'content', text: 'Let me look.' },
      { type: 'error', message: 'An unexpected error occurred. Please try again.' },
    ]));
    const { localState, context } = harness();

    await aiChat.actions.sendMessage(context, 'Hello');

    expect(localState.error).toBe('An unexpected error occurred. Please try again.');
    expect(localState.retryTurn).toBeNull();
  });

  it('"Try again" drops the first bubble and sends the same question', async () => {
    const { localState, dispatch, context } = harness();
    localState.messages = [{ id: 'temp_1', role: 'user', content: 'How does the tax trap work?' }];
    localState.error = FYN_INTERRUPTED_MESSAGE;
    localState.retryTurn = { arg: 'How does the tax trap work?', messageId: 'temp_1' };

    await aiChat.actions.retryInterruptedTurn(context);

    expect(localState.messages).toHaveLength(0);
    expect(localState.error).toBeNull();
    expect(localState.retryTurn).toBeNull();
    expect(dispatch).toHaveBeenCalledWith('sendMessage', 'How does the tax trap work?');
  });

  it('"Try again" after an interrupted action runs the action again', async () => {
    aiChatService.postActionStream.mockResolvedValue(streamReader([{ type: 'content', text: 'Welcome back' }]));
    const { localState, dispatch, context } = harness();

    await aiChat.actions.postAction(context, 'resume');
    expect(localState.retryTurn).toEqual({ action: 'resume' });

    await aiChat.actions.retryInterruptedTurn(context);
    expect(dispatch).toHaveBeenCalledWith('postAction', 'resume');
  });
});
