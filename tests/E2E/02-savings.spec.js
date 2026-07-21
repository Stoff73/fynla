import { test, expect } from '@playwright/test';
import { register } from './helpers/auth.js';
import { waitForLoading, navigateToModule, fillField, selectOption, isVisible } from './helpers/common.js';

test.describe.skip('legacy E2E migration tracked by online-readiness Task 24', () => {
  test.beforeEach(async ({ page }) => {
    await register(page);
    await page.waitForTimeout(2000);
  });

  test('should load Savings dashboard', async ({ page }) => {
    await navigateToModule(page, 'savings');

    await expect(page.locator('h1')).toContainText('Savings');
    await expect(page.locator('text=Emergency Fund')).toBeVisible();
  });

  test('should add savings account', async ({ page }) => {
    await navigateToModule(page, 'savings');

    // Click add account button
    await page.click('button:has-text("Add Account")');
    await page.waitForTimeout(1000);

    // Fill account form
    await fillField(page, 'input[placeholder*="account name"]', 'Emergency Savings');
    await selectOption(page, 'select', 'cash_isa');
    await fillField(page, 'input[placeholder*="balance"]', '10000');
    await fillField(page, 'input[placeholder*="interest"]', '2.5');

    // Submit
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Verify account was added
    const hasError = await isVisible(page, 'text=error, text=422');
    expect(hasError).toBe(false);
  });

  test('should add savings goal', async ({ page }) => {
    await navigateToModule(page, 'savings');

    // Click add goal button
    await page.click('button:has-text("Add Goal")');
    await page.waitForTimeout(1000);

    // Fill goal form
    await fillField(page, 'input[placeholder*="goal name"]', 'House Deposit');
    await fillField(page, 'input[placeholder*="target"]', '50000');
    await fillField(page, 'input[placeholder*="current"]', '15000');

    // Submit
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error');
    expect(hasError).toBe(false);
  });

  test('should display Emergency Fund gauge', async ({ page }) => {
    await navigateToModule(page, 'savings');

    const gauge = page.locator('[data-testid="emergency-fund-gauge"], .apexcharts-radialbar');
    await expect(gauge.first()).toBeVisible({ timeout: 10000 });
  });

  test('should display ISA allowance tracker', async ({ page }) => {
    await navigateToModule(page, 'savings');

    const tracker = page.locator('text=ISA Allowance');
    await expect(tracker).toBeVisible({ timeout: 5000 });
  });

  test('should navigate through Savings tabs', async ({ page }) => {
    await navigateToModule(page, 'savings');

    const tabs = ['Emergency Fund', 'Accounts', 'Goals', 'Recommendations'];

    for (const tab of tabs) {
      const tabButton = page.locator(`button:has-text("${tab}")`);
      if (await tabButton.isVisible()) {
        await tabButton.click();
        await waitForLoading(page);
        await page.waitForTimeout(1000);
      }
    }
  });
});
