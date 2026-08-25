import { test as base, expect } from '@playwright/test';
import { register } from '../helpers/auth.js';

/**
 * Mark this browser context as having already answered the cookie banner.
 *
 * Consent lives in the `fyn_cookie_consent` COOKIE and nowhere else — it is read
 * by `resources/js/utils/cookieConsent.js`, `public/pages/js/cookie-consent.js`
 * and the server-side affiliate middleware, and `cookieConsent.spec.js` pins the
 * behaviour with "reads the decision from the cookie, not from local storage".
 *
 * Every caller used to seed `localStorage.cookie_consent`, a key no production
 * code reads. The banner therefore appeared in every E2E run, and because it is a
 * modal its backdrop legitimately covered the page — so any test that clicked
 * landing-page content timed out. That is what kept the desktop smoke test red.
 *
 * Written through an init script rather than `context.addCookies()` so it works
 * for contexts built with `browser.newContext()`, which have no `baseURL` to
 * anchor a cookie to. One definition, used by every surface (Rule 20). W-0484.
 */
export async function seedCookieConsent(context) {
  await context.addInitScript(() => {
    document.cookie = 'fyn_cookie_consent=accepted; path=/; max-age=31536000; SameSite=Lax';
  });
}

export const test = base.extend({
  acceptedCookieConsent: [async ({ context }, use) => {
    await seedCookieConsent(context);

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
