import { afterEach, describe, expect, it, vi } from 'vitest';
import { apiStream } from '../../../resources/mobile/api.js';

/**
 * T6: "take me to…" is decided on the server, which sends each surface its
 * own page. The /m bundle names itself on every Fyn turn.
 */
describe('/m Fyn turns name the surface', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('sends X-Fynla-Surface: m with the turn', async () => {
    const fetchMock = vi.fn(async () => ({ ok: false, status: 500, headers: new Headers(), json: async () => ({}) }));
    vi.stubGlobal('fetch', fetchMock);

    await apiStream('/api/ai-chat/conversations/1/messages', { message: 'take me to my savings' }, 'token', () => {}, () => {}).catch(() => {});

    expect(fetchMock).toHaveBeenCalled();
    expect(fetchMock.mock.calls[0][1].headers['X-Fynla-Surface']).toBe('m');
  });
});
