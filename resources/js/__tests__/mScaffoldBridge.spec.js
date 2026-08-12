import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('desktop web-session bootstrap', () => {
  beforeEach(() => {
    vi.resetModules();
    localStorage.clear();
    sessionStorage.clear();
    document.cookie = 'fynla_web_session=; Max-Age=0; path=/';
  });

  afterEach(() => {
    document.cookie = 'fynla_web_session=; Max-Age=0; path=/';
  });

  it('uses the non-secret session marker without copying the mobile bearer token', async () => {
    localStorage.setItem('m_scaffold_token', 'mobile-secret');
    document.cookie = 'fynla_web_session=1; path=/; SameSite=Lax';

    await import('../mScaffoldBridge.js');

    expect(sessionStorage.getItem('auth_token')).toBe('web-session');
    expect(localStorage.getItem('m_scaffold_token')).toBe('mobile-secret');
    expect(sessionStorage.getItem('auth_token')).not.toBe('mobile-secret');
    expect(document.cookie).not.toContain('fynla_web_session=');
  });

  it('never reads the mobile bearer token without a web-session marker', async () => {
    localStorage.setItem('m_scaffold_token', 'mobile-secret');

    await import('../mScaffoldBridge.js');

    expect(sessionStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('m_scaffold_token')).toBe('mobile-secret');
  });

  it('replaces an existing desktop bearer after a server handoff changes the web user', async () => {
    sessionStorage.setItem('auth_token', 'desktop-token');
    document.cookie = 'fynla_web_session=1; path=/; SameSite=Lax';

    await import('../mScaffoldBridge.js');

    expect(sessionStorage.getItem('auth_token')).toBe('web-session');
    expect(document.cookie).not.toContain('fynla_web_session=');
  });

  it('never treats the web-session sentinel as a bearer that can be copied into /m', async () => {
    const { isTransferableMobileBearer } = await import('../mScaffoldBridge.js');

    expect(isTransferableMobileBearer('web-session')).toBe(false);
    expect(isTransferableMobileBearer('sanctum-bearer')).toBe(true);
    expect(isTransferableMobileBearer(null)).toBe(false);
  });
});
