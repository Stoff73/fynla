import { beforeEach, describe, expect, it, vi } from 'vitest';

// Regression coverage for the web-SPA 401/419 dead-end bug (see
// resources/js/services/aiChatService.js): once a stream call rejects with
// `err.authExpired = true` (aiChatService already redirected to login via
// api.js's handleAuthExpiry()), the store must NOT overwrite that redirect
// with SET_ERROR, and startOnboardingConversation must NOT fall back to
// startNewConversation (that would silently abandon onboarding behind the
// in-flight redirect).
vi.mock('@/services/aiChatService', () => ({
  default: {
    sendMessageStream: vi.fn(),
    streamQueuedMessage: vi.fn(),
    postActionStream: vi.fn(),
    startOnboardingStream: vi.fn(),
    getOnboardingStatus: vi.fn(),
  },
}));

import aiChatService from '@/services/aiChatService';
import aiChat from '@/store/modules/aiChat';

function authExpiredError(status = 401) {
  const err = new Error(`Session expired: ${status}`);
  err.authExpired = true;
  err.status = status;
  return err;
}

function baseState(overrides = {}) {
  return {
    ...aiChat.state,
    currentConversation: { id: 10, title: 'Fyn' },
    messages: [],
    conversations: [],
    streamingText: '',
    error: null,
    isOnboardingActive: false,
    streaming: false,
    ...overrides,
  };
}

describe('aiChat store — 401/419 auth-expiry does not dead-end the turn', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('sendMessage: skips SET_ERROR and the empty-response fallback when the stream 401s', async () => {
    aiChatService.sendMessageStream.mockRejectedValue(authExpiredError());

    const localState = baseState();
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.sendMessage({
      commit,
      dispatch,
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    }, 'Hello Fyn');

    expect(localState.error).toBeNull();
    // The queued-turn pop must not fire either — the turn never streamed.
    expect(dispatch).not.toHaveBeenCalledWith('streamNextQueued');
  });

  it('streamNextQueued: skips SET_ERROR when the queued-turn stream 401s', async () => {
    aiChatService.streamQueuedMessage.mockRejectedValue(authExpiredError(419));

    const localState = baseState({
      messages: [{ id: 43, role: 'user', content: 'Add my Cash ISA', status: 'queued' }],
    });
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.streamNextQueued({
      commit,
      dispatch,
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    });

    expect(localState.error).toBeNull();
  });

  it('postAction: skips the "Could not complete that action" banner when the stream 401s', async () => {
    aiChatService.postActionStream.mockRejectedValue(authExpiredError());

    const localState = baseState();
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.postAction({
      commit,
      dispatch,
      state: localState,
      rootState: { route: { path: '/dashboard' } },
    }, 'resume');

    expect(localState.error).toBeNull();
  });

  it('startOnboardingConversation: does NOT fall back to startNewConversation on a 401', async () => {
    aiChatService.getOnboardingStatus.mockRejectedValue(new Error('status check failed'));
    aiChatService.startOnboardingStream.mockRejectedValue(authExpiredError());

    const localState = baseState();
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.startOnboardingConversation({
      commit,
      dispatch,
      state: localState,
    }, {});

    expect(localState.error).toBeNull();
    expect(dispatch).not.toHaveBeenCalledWith('startNewConversation');
  });

  it('startOnboardingConversation: STILL falls back to startNewConversation on a non-auth failure (e.g. 503)', async () => {
    aiChatService.getOnboardingStatus.mockRejectedValue(new Error('status check failed'));
    const disabledError = new Error('Onboarding start failed: 503');
    disabledError.status = 503;
    aiChatService.startOnboardingStream.mockRejectedValue(disabledError);

    const localState = baseState();
    const commit = (name, payload) => aiChat.mutations[name](localState, payload);
    const dispatch = vi.fn().mockResolvedValue(undefined);

    await aiChat.actions.startOnboardingConversation({
      commit,
      dispatch,
      state: localState,
    }, {});

    expect(dispatch).toHaveBeenCalledWith('startNewConversation');
  });
});
