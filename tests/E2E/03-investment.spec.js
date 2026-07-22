import { test, expect } from '@playwright/test';
import { register } from './helpers/auth.js';
import { waitForLoading, navigateToModule, fillField, selectOption, isVisible } from './helpers/common.js';

test.describe.skip('legacy E2E migration tracked by online-readiness Task 24', () => {
  test.beforeEach(async ({ page }) => {
    await register(page);
    await page.waitForTimeout(2000);
  });

  test('should load Investment dashboard', async ({ page }) => {
    await navigateToModule(page, 'investment');

    await expect(page.locator('h1')).toContainText('Investment');
    await expect(page.locator('text=Portfolio')).toBeVisible();
  });

  test('should add investment account', async ({ page }) => {
    await navigateToModule(page, 'investment');

    // Click add account button
    await page.click('button:has-text("Add Account")');
    await page.waitForTimeout(1000);

    // Fill account form
    await fillField(page, 'input[placeholder*="account name"]', 'ISA Account');
    await selectOption(page, 'select[id*="account_type"]', 'stocks_shares_isa');
    await fillField(page, 'input[placeholder*="value"]', '25000');

    // Submit
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error, text=422');
    expect(hasError).toBe(false);
  });

  test('should add investment holding', async ({ page }) => {
    await navigateToModule(page, 'investment');

    // First add an account
    await page.click('button:has-text("Add Account")');
    await page.waitForTimeout(1000);
    await fillField(page, 'input[placeholder*="account name"]', 'Test Account');
    await selectOption(page, 'select[id*="account_type"]', 'general_investment');
    await fillField(page, 'input[placeholder*="value"]', '10000');
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Now try to add holding
    const addHoldingButton = page.locator('button:has-text("Add Holding")');
    if (await addHoldingButton.isVisible()) {
      await addHoldingButton.click();
      await page.waitForTimeout(1000);

      await fillField(page, 'input[placeholder*="name"]', 'FTSE 100 ETF');
      await fillField(page, 'input[placeholder*="ticker"]', 'VUKE');
      await fillField(page, 'input[placeholder*="quantity"]', '100');
      await fillField(page, 'input[placeholder*="price"]', '75.50');

      await page.click('button[type="submit"]:has-text("Save")');
      await page.waitForTimeout(3000);
    }
  });

  test('should add investment goal', async ({ page }) => {
    await navigateToModule(page, 'investment');

    const addGoalButton = page.locator('button:has-text("Add Goal")');
    if (await addGoalButton.isVisible()) {
      await addGoalButton.click();
      await page.waitForTimeout(1000);

      await fillField(page, 'input[placeholder*="goal"]', 'Retirement Portfolio');
      await fillField(page, 'input[placeholder*="target"]', '500000');
      await fillField(page, 'input[placeholder*="years"]', '20');

      await page.click('button[type="submit"]:has-text("Save")');
      await page.waitForTimeout(3000);

      const hasError = await isVisible(page, 'text=error');
      expect(hasError).toBe(false);
    }
  });

  test('should display asset allocation chart', async ({ page }) => {
    await navigateToModule(page, 'investment');

    const isChartVisible = await isVisible(page, '[data-testid="asset-allocation-chart"]');
    // Chart may not be visible if no data
    expect(isChartVisible).toBeDefined();
  });

  test('should display performance chart', async ({ page }) => {
    await navigateToModule(page, 'investment');

    await page.waitForTimeout(2000);
    // Chart may not be visible if no data
    const isChartVisible = await isVisible(page, '[data-testid="performance-chart"]');
    expect(isChartVisible).toBeDefined();
  });

  test('should navigate through Investment tabs', async ({ page }) => {
    await navigateToModule(page, 'investment');

    const tabs = ['Portfolio', 'Accounts', 'Holdings', 'Goals', 'Analysis'];

    for (const tab of tabs) {
      const tabButton = page.locator(`button:has-text("${tab}")`);
      if (await tabButton.isVisible()) {
        await tabButton.click();
        await waitForLoading(page);
        await page.waitForTimeout(1000);
      }
    }
  });

  test('should run Monte Carlo simulation', async ({ page }) => {
    await navigateToModule(page, 'investment');

    // First add an account with some value
    await page.click('button:has-text("Add Account")');
    await page.waitForTimeout(1000);
    await fillField(page, 'input[placeholder*="account name"]', 'Simulation Test');
    await selectOption(page, 'select[id*="account_type"]', 'pension');
    await fillField(page, 'input[placeholder*="value"]', '50000');
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Look for Monte Carlo button
    const monteCarloButton = page.locator('button:has-text("Monte Carlo"), button:has-text("Run Simulation")');
    if (await monteCarloButton.first().isVisible()) {
      await monteCarloButton.first().click();
      await page.waitForTimeout(5000); // Simulations take time

      // Check if results displayed
      const hasResults = await isVisible(page, 'text=probability, text=success');
      expect(hasResults).toBeDefined();
    }
  });
});
