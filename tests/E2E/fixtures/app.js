import { test as base, expect } from '@playwright/test';
import { register } from '../helpers/auth.js';

export const test = base.extend({
  acceptedCookieConsent: [async ({ context, baseURL }, use) => {
    // Consent is remembered in TWO places, and this fixture only satisfied one of
    // them. The SPA banner reads `localStorage.cookie_consent`; the server-rendered
    // public pages read a COOKIE named `fyn_cookie_consent`
    // (`public/pages/js/cookie-consent.js:21`). So on the landing page the banner
    // still appeared, and its `.cc-backdrop` sits over the whole viewport and
    // intercepts pointer events — every click on `/` retried until it timed out.
    //
    // That is why `@smoke desktop landing and preview dashboard boot` went red the
    // moment the public-page banner landed (2026-08-22): the fixture was pre-accepting
    // consent for a mechanism the page under test does not use.
    await context.addInitScript(() => {
      window.localStorage.setItem('cookie_consent', 'accepted');
    });

    const { hostname } = new URL(baseURL ?? 'http://127.0.0.1:8000');

    await context.addCookies([{
      name: 'fyn_cookie_consent',
      value: 'accepted',
      domain: hostname,
      path: '/',
    }]);

    await use();
  }, { auto: true }],

  runtimeErrors: async ({ page }, use) => {
    const errors = [];

    page.on('pageerror', (error) => {
      errors.push(`pageerror: ${error.message}`);
    });
    page.on('console', (message) => {
      if (message.type() === 'error') {
        errors.push(`console: ${message.text()}`);
      }
    });
    page.on('response', (response) => {
      if (response.status() >= 500 && response.url().includes('/api/')) {
        errors.push(`${response.status()} ${response.url()}`);
      }
    });
    await use(errors);
  },

  selectPreviewPersona: async ({ page }, use) => {
    await use(async (persona) => {
      await page.goto('/');
      await page.getByRole('link', { name: 'See our demo', exact: true }).click();
      await expect(page.getByRole('heading', { name: 'Choose your demo' })).toBeVisible();

      await page.locator(`[data-persona="${persona}"]`).click();

      await expect(page).toHaveURL(/\/dashboard(?:[/?#]|$)/);
    });
  },

  registerVerifiedUser: async ({ page, request }, use) => {
    await use(async (user) => register(page, request, user));
  },
});

export function expectNoRuntimeErrors(runtimeErrors) {
  expect(runtimeErrors).toEqual([]);
}

export { expect };
