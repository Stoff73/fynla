import { test, expect } from '../fixtures/app.js';

const obsoleteTrialCopy = /\bfree trial\b|7-day trial|trial has ended|trial ends|trial expires/i;

const serverRenderedRoutes = [
  '/about',
  '/calculators',
  '/faq',
  '/features',
  '/help',
  '/how-it-works',
  '/features/ice-letters',
  '/features/iht-planning',
  '/features/monte-carlo',
  '/features/net-worth-dashboard',
  '/features/pension-tracker',
  '/features/protection-gap',
  '/features/when-can-i-retire',
  '/why-fynla/alternatives',
  '/why-fynla/independent',
  '/why-fynla/one-platform',
  '/why-fynla/our-approach',
];

test('every remediated server-rendered route is free of obsolete trial claims', async ({ page }) => {
  test.setTimeout(120_000);

  for (const route of serverRenderedRoutes) {
    const response = await page.goto(route);

    expect(response?.ok(), `${route} should return a successful response`).toBeTruthy();
    await expect(page.locator('body')).not.toContainText(obsoleteTrialCopy);
  }
});

test('terms and privacy render the permanent Free and paid-churn retention contract', async ({ page }) => {
  await page.goto('/terms');
  await expect(page.getByText('Free Access', { exact: true })).toBeVisible();
  await expect(page.getByText(/30-day read-only grace period/)).toBeVisible();
  await expect(page.getByText(/currently seven years/)).toBeVisible();
  await expect(page.locator('body')).not.toContainText(obsoleteTrialCopy);

  await page.goto('/privacy');
  await expect(page.getByText('Duration of account, then the seven-year regulatory retention period').first()).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Deleted immediately on account closure|Duration of account plus 30 days/);
});
