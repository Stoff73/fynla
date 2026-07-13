import { test, expect } from '../fixtures/app.js';

test('@smoke phone traffic reaches the mobile web application', async ({ page, runtimeErrors }) => {
  await page.goto('/');

  await expect(page).toHaveURL(/\/m(?:\/|$)/);
  await expect(page.frameLocator('iframe').getByRole('main')).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});
