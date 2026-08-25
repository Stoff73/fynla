import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/utils/awinTracking', () => ({
  loadMasterTag: vi.fn(),
  unloadMasterTag: vi.fn(),
  shouldLoadAwin: vi.fn(() => false),
}));

function clearCookies() {
  document.cookie = 'fyn_cookie_consent=; Max-Age=0; path=/';
}

function gaScripts() {
  return [...document.head.querySelectorAll('script')]
    .filter((s) => s.src.includes('googletagmanager.com'));
}

describe('cookie consent', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.unstubAllEnvs();
    clearCookies();
    document.head.replaceChildren();
    delete window.dataLayer;
    delete window.gtag;
    global.fetch = vi.fn(() => Promise.resolve({ ok: true }));
  });

  afterEach(() => {
    clearCookies();
    vi.unstubAllEnvs();
  });

  describe('Google Analytics loading (W-0047)', () => {
    it('loads no analytics at all when VITE_GA_ID is unset', async () => {
      vi.stubEnv('VITE_GA_ID', '');

      const { acceptCookies } = await import('@/utils/cookieConsent');
      await acceptCookies();

      // Never a fallback to the production measurement property.
      expect(gaScripts()).toHaveLength(0);
      expect(window.gtag).toBeUndefined();
    });

    it('loads the configured measurement id when one is set', async () => {
      vi.stubEnv('VITE_GA_ID', 'G-TESTID123');

      const { acceptCookies } = await import('@/utils/cookieConsent');
      await acceptCookies();

      const scripts = gaScripts();
      expect(scripts).toHaveLength(1);
      expect(scripts[0].src).toContain('id=G-TESTID123');
    });

    it('loads no analytics when the visitor declines', async () => {
      vi.stubEnv('VITE_GA_ID', 'G-TESTID123');

      const { declineCookies } = await import('@/utils/cookieConsent');
      await declineCookies();

      expect(gaScripts()).toHaveLength(0);
    });
  });

  describe('recording the decision (W-0049)', () => {
    it('sends an acceptance to the one endpoint that records it', async () => {
      const { acceptCookies } = await import('@/utils/cookieConsent');
      await acceptCookies();

      expect(global.fetch).toHaveBeenCalledTimes(1);
      const [url, options] = global.fetch.mock.calls[0];
      expect(url).toContain('/api/cookie-consent');
      expect(options.method).toBe('POST');
      expect(JSON.parse(options.body)).toEqual({ status: 'accepted' });
    });

    it('sends a refusal so the server can expire the HttpOnly affiliate cookie', async () => {
      const { declineCookies } = await import('@/utils/cookieConsent');
      await declineCookies();

      expect(JSON.parse(global.fetch.mock.calls[0][1].body)).toEqual({ status: 'declined' });
    });

    it('carries the affiliate click reference from the current URL when accepting', async () => {
      const original = window.location;
      delete window.location;
      window.location = { ...original, search: '?awc=click-ref-xyz', protocol: 'http:' };

      const { acceptCookies } = await import('@/utils/cookieConsent');
      await acceptCookies();

      expect(JSON.parse(global.fetch.mock.calls[0][1].body)).toEqual({
        status: 'accepted',
        awc: 'click-ref-xyz',
      });

      window.location = original;
    });

    it('reads the decision from the cookie, not from local storage', async () => {
      const { getConsentStatus, hasConsent } = await import('@/utils/cookieConsent');

      expect(getConsentStatus()).toBeNull();

      localStorage.setItem('cookie_consent', 'accepted');
      expect(hasConsent()).toBe(false);

      document.cookie = 'fyn_cookie_consent=accepted; path=/';
      expect(getConsentStatus()).toBe('accepted');
      expect(hasConsent()).toBe(true);

      localStorage.removeItem('cookie_consent');
    });

    it('applies the choice locally so a failed request cannot trap the visitor', async () => {
      global.fetch = vi.fn(() => Promise.reject(new Error('offline')));

      const { acceptCookies, hasConsent } = await import('@/utils/cookieConsent');
      await acceptCookies();

      expect(hasConsent()).toBe(true);
    });
  });
});
