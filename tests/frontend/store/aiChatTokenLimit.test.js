import { describe, expect, it, vi } from 'vitest';

vi.mock('@/services/aiChatService', () => ({ default: {} }));

import aiChat from '@/store/modules/aiChat';

/**
 * T48 (CSJ 2026-10-12): the token_limit frame's message is the one the
 * panel shows, so every surface reads "weekly" from the server.
 */
const WEEKLY = "You've reached your weekly Fyn usage limit.";

describe('aiChat store — token_limit keeps the server message (T48)', () => {
  it('stores the message with the limit and clears it on reset', () => {
    const state = { tokenLimitReached: false, tokenLimitMessage: null, tokenResetAt: null, secondsUntilReset: null };
    aiChat.mutations.SET_TOKEN_LIMIT(state, { reached: true, message: WEEKLY, resetAt: 'x', secondsUntilReset: 60 });
    expect(state.tokenLimitMessage).toBe(WEEKLY);
    expect(aiChat.getters.tokenLimitMessage(state)).toBe(WEEKLY);

    aiChat.mutations.SET_TOKEN_LIMIT(state, { reached: false, resetAt: null, secondsUntilReset: null });
    expect(state.tokenLimitMessage).toBeNull();
  });
});
