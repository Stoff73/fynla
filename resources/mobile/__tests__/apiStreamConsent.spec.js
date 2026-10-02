import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiStream } from '../api.js';

// The consent gate in AiChatController answers a send with 403 JSON before any
// stream opens ({"error":"consent_required","required":"ai_chat"}, seen live on
// csjones 2026-10-01). /m showed "Sorry, I had trouble responding just now."
// apiStream now hands it to onEvent as the consent_required event the stream
// itself would send, so the mixin's one consent message shows.
function jsonResponse(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    json: () => Promise.resolve(body),
  };
}

describe('apiStream pre-stream refusals', () => {
  beforeEach(() => {
    global.fetch = vi.fn();
  });

  it('emits consent_required for the consent 403', async () => {
    global.fetch.mockResolvedValue(jsonResponse(403, { error: 'consent_required', required: 'ai_chat' }));
    const onEvent = vi.fn();

    const result = await apiStream('/api/ai-chat/conversations/1/messages', { message: 'hi' }, 't', vi.fn(), onEvent);

    expect(onEvent).toHaveBeenCalledWith({ type: 'consent_required' });
    expect(result).toMatchObject({ ok: false, status: 403 });
  });

  it('emits nothing for any other 403', async () => {
    global.fetch.mockResolvedValue(jsonResponse(403, { success: false, reason: 'preview_mode' }));
    const onEvent = vi.fn();

    const result = await apiStream('/api/ai-chat/conversations/1/messages', { message: 'hi' }, 't', vi.fn(), onEvent);

    expect(onEvent).not.toHaveBeenCalled();
    expect(result).toMatchObject({ ok: false, status: 403 });
  });
});
