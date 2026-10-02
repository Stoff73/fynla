import { beforeEach, describe, expect, it, vi } from 'vitest';

// The consent gate in AiChatController answers a send with 403 JSON before any
// stream opens ({"error":"consent_required","required":"ai_chat"}, seen live on
// csjones 2026-10-01). It used to throw a generic "Chat request failed", so the
// store's consent_required handling never ran. It now returns a typed marker.
vi.mock('@/services/api', () => ({
  apiBaseURL: 'http://localhost:8000',
  handleAuthExpiry: vi.fn(() => Promise.resolve()),
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
    headers: { get: () => null },
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
    body: null,
  };
}

describe('aiChatService.sendMessageStream consent refusal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    global.fetch = vi.fn();
  });

  it('returns a consentRequired marker for the pre-stream consent 403', async () => {
    global.fetch.mockResolvedValue(fetchResponse(403, { error: 'consent_required', required: 'ai_chat' }));

    await expect(aiChatService.sendMessageStream(1, 'hi')).resolves.toEqual({ consentRequired: true });
  });

  it('still throws for any other 403', async () => {
    global.fetch.mockResolvedValue(fetchResponse(403, { success: false, reason: 'preview_mode' }));

    await expect(aiChatService.sendMessageStream(1, 'hi')).rejects.toThrow('Chat request failed: 403');
  });
});
