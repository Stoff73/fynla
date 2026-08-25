import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../api.js', () => ({
  apiPost: vi.fn(),
}));

import { apiPost } from '../../api.js';
import { store } from '../../store.js';
import {
  issueWebHandoff,
  openPublicWebPath,
  publicWebUrl,
} from '../webHandoff.js';

describe('webHandoff', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'mobile-bearer';
  });

  it('issues an allowlisted handoff and navigates only to the server URL', async () => {
    apiPost.mockResolvedValue({
      ok: true,
      data: { data: { url: 'https://fynla.test/auth/web-handoff/one-time' } },
    });
    const target = { location: { href: '' } };
    const storageSpy = vi.spyOn(window.sessionStorage.__proto__, 'setItem');

    await issueWebHandoff('privacy', target);

    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/mobile/web-handoffs',
      { destination: 'privacy' },
      'mobile-bearer',
    );
    expect(target.location.href).toBe('https://fynla.test/auth/web-handoff/one-time');
    expect(storageSpy).not.toHaveBeenCalled();
    storageSpy.mockRestore();
  });

  it('fails closed when no handoff URL is available', async () => {
    apiPost.mockResolvedValue({ ok: false, data: {} });
    const target = { location: { href: '' } };

    await expect(issueWebHandoff('subscription', target)).rejects.toThrow('handoff_unavailable');
    expect(target.location.href).toBe('');
  });

  it('builds base-aware public links without credentials', () => {
    const target = { location: { href: '' } };

    expect(publicWebUrl('/help', '/fynla/')).toBe('/fynla/help');
    openPublicWebPath('/terms', target, '/fynla/');

    expect(target.location.href).toBe('/fynla/terms');
    expect(apiPost).not.toHaveBeenCalled();
  });
});
