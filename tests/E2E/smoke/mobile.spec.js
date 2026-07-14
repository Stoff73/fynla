import { test, expect } from '../fixtures/app.js';

test('@smoke phone traffic reaches the mobile web application', async ({ page, runtimeErrors }) => {
  await page.goto('/');

  await expect(page).toHaveURL(/\/m(?:\/|$)/);
  await expect(page.frameLocator('iframe').getByRole('main')).toBeVisible();

  await page.goto('/m/app/login');

  await expect(page).toHaveURL(/\/m\/app\/login(?:\?.*)?$/);
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});
