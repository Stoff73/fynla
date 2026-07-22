import { beforeEach, describe, expect, it, vi } from 'vitest';

// Regression coverage for the web-SPA 401/419 dead-end bug: the four SSE
// endpoints in aiChatService.js use raw fetch() and so bypass the axios
// response interceptor in api.js entirely. A 401/419 on any of them must
// invoke the SAME auth-expiry behaviour as the interceptor (removeToken +
// redirect to login, extracted as api.js's handleAuthExpiry()) and throw a
// distinguishable `authExpired` error so store catch blocks don't overwrite
// the redirect with a generic error banner or a blank-chat fallback.
const { handleAuthExpiry } = vi.hoisted(() => ({ handleAuthExpiry: vi.fn(() => Promise.resolve()) }));

vi.mock('@/services/api', () => ({
  apiBaseURL: 'http://localhost:8000',
  handleAuthExpiry,
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/services/tokenStorage', () => ({
  getToken: vi.fn(() => Promise.resolve('a-token')),
}));

import aiChatService from '@/services/aiChatService';

function fetchResponse(status, body = {}) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
    body: null,
  };
}

describe('aiChatService SSE 401/419 handling', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    global.fetch = vi.fn();
  });

  it('sendMessageStream: invokes handleAuthExpiry and throws a distinguishable error on 401', async () => {
    global.fetch.mockResolvedValue(fetchResponse(401, { message: 'Unauthenticated.' }));

    await expect(aiChatService.sendMessageStream(1, 'hi')).rejects.toMatchObject({
      authExpired: true,
      status: 401,
    });
    expect(handleAuthExpiry).toHaveBeenCalledTimes(1);
  });

  it('streamQueuedMessage: invokes handleAuthExpiry and throws on 419', async () => {
    global.fetch.mockResolvedValue(fetchResponse(419));

    await expect(aiChatService.streamQueuedMessage(1, 99)).rejects.toMatchObject({
      authExpired: true,
      status: 419,
    });
    expect(handleAuthExpiry).toHaveBeenCalledTimes(1);
  });

  it('startOnboardingStream: invokes handleAuthExpiry and throws on 401 (not the plain onboarding-start error)', async () => {
    global.fetch.mockResolvedValue(fetchResponse(401, { reason: 'unauthenticated' }));

    await expect(aiChatService.startOnboardingStream({})).rejects.toMatchObject({
      authExpired: true,
      status: 401,
    });
    expect(handleAuthExpiry).toHaveBeenCalledTimes(1);
  });

  it('postActionStream: invokes handleAuthExpiry and throws on 401', async () => {
    global.fetch.mockResolvedValue(fetchResponse(401));

    await expect(aiChatService.postActionStream(1, 'resume')).rejects.toMatchObject({
      authExpired: true,
      status: 401,
    });
    expect(handleAuthExpiry).toHaveBeenCalledTimes(1);
  });

  it('does NOT invoke handleAuthExpiry on a healthy stream', async () => {
    global.fetch.mockResolvedValue({
      ok: true,
      status: 200,
      body: null,
      text: () => Promise.resolve(''),
    });

    await aiChatService.postActionStream(1, 'resume');

    expect(handleAuthExpiry).not.toHaveBeenCalled();
  });

  it('does NOT invoke handleAuthExpiry on a non-auth failure (e.g. 500)', async () => {
    global.fetch.mockResolvedValue(fetchResponse(500, { message: 'Server error' }));

    await expect(aiChatService.postActionStream(1, 'resume')).rejects.not.toMatchObject({ authExpired: true });
    expect(handleAuthExpiry).not.toHaveBeenCalled();
  });
});
