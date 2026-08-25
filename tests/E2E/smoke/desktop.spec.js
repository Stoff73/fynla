import { test, expect } from '../fixtures/app.js';

test('@smoke desktop landing and preview dashboard boot', async ({
  page,
  runtimeErrors,
  selectPreviewPersona,
}) => {
  await page.goto('/');
  await expect(page.getByRole('link', { name: /sign in/i })).toBeVisible();

  await selectPreviewPersona('young_family');

  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.getByRole('main')).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});
