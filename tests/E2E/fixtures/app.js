import { test as base, expect } from '@playwright/test';
import { register } from '../helpers/auth.js';

export const test = base.extend({
  acceptedCookieConsent: [async ({ context }, use) => {
    await context.addInitScript(() => {
      window.localStorage.setItem('cookie_consent', 'accepted');
    });

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
