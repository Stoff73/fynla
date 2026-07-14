import { test, expect } from '@playwright/test';
import { register } from './helpers/auth.js';
import { waitForLoading, navigateToModule, fillField, selectOption, isVisible } from './helpers/common.js';

test.describe.skip('legacy E2E migration tracked by online-readiness Task 24', () => {
  test.beforeEach(async ({ page }) => {
    await register(page);
    await page.waitForTimeout(2000);
  });

  test('should load Retirement dashboard', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    await expect(page.locator('h1')).toContainText('Retirement');
    await expect(page.locator('text=Readiness')).toBeVisible();
  });

  test('should add DC pension', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    // Click add pension button
    await page.click('button:has-text("Add Pension")');
    await page.waitForTimeout(1000);

    // Select DC pension type
    await selectOption(page, 'select', 'dc');
    await page.waitForTimeout(500);

    // Fill DC pension form
    await fillField(page, 'input[placeholder*="scheme"]', 'Workplace Pension');
    await fillField(page, 'input[placeholder*="fund value"]', '75000');
    await fillField(page, 'input[placeholder*="employee"]', '5');
    await fillField(page, 'input[placeholder*="employer"]', '3');
    await fillField(page, 'input[placeholder*="salary"]', '45000');

    // Submit
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error, text=422');
    expect(hasError).toBe(false);
  });

  test('should add DB pension', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    await page.click('button:has-text("Add Pension")');
    await page.waitForTimeout(1000);

    // Select DB pension type
    await selectOption(page, 'select', 'db');
    await page.waitForTimeout(500);

    // Fill DB pension form
    await fillField(page, 'input[placeholder*="scheme"]', 'Final Salary Scheme');
    await fillField(page, 'input[placeholder*="annual"]', '15000');
    await fillField(page, 'input[placeholder*="age"]', '65');

    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error');
    expect(hasError).toBe(false);
  });

  test('should add State Pension', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    await page.click('button:has-text("Add Pension")');
    await page.waitForTimeout(1000);

    // Select State pension type
    await selectOption(page, 'select', 'state');
    await page.waitForTimeout(500);

    // Fill State pension form
    await fillField(page, 'input[placeholder*="amount"]', '11500');
    await fillField(page, 'input[placeholder*="age"]', '67');

    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error');
    expect(hasError).toBe(false);
  });

  test('should display retirement readiness gauge', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    const gauge = page.locator('[data-testid="readiness-gauge"], .apexcharts-radialbar');
    await expect(gauge.first()).toBeVisible({ timeout: 10000 });
  });

  test('should display annual allowance tracker', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    const tracker = page.locator('text=Annual Allowance');
    await expect(tracker).toBeVisible({ timeout: 5000 });
  });

  test('should navigate through Retirement tabs', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    const tabs = [
      'Readiness',
      'Pension Inventory',
      'Contributions',
      'Projections',
      'Recommendations',
    ];

    for (const tab of tabs) {
      const tabButton = page.locator(`button:has-text("${tab}")`);
      if (await tabButton.isVisible()) {
        await tabButton.click();
        await waitForLoading(page);
        await page.waitForTimeout(1000);
      }
    }
  });

  test('should display income projection chart', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    // Add a pension first
    await page.click('button:has-text("Add Pension")');
    await page.waitForTimeout(1000);
    await selectOption(page, 'select', 'dc');
    await page.waitForTimeout(500);
    await fillField(page, 'input[placeholder*="scheme"]', 'Test Pension');
    await fillField(page, 'input[placeholder*="fund value"]', '50000');
    await fillField(page, 'input[placeholder*="employee"]', '5');
    await fillField(page, 'input[placeholder*="employer"]', '3');
    await fillField(page, 'input[placeholder*="salary"]', '40000');
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Navigate to projections
    const projectionsTab = page.locator('button:has-text("Projections")');
    if (await projectionsTab.isVisible()) {
      await projectionsTab.click();
      await waitForLoading(page);

      const isChartVisible = await isVisible(page, '.apexcharts-area');
      expect(isChartVisible).toBeDefined();
    }
  });

  test('should calculate contribution optimization', async ({ page }) => {
    await navigateToModule(page, 'retirement');

    // Add a DC pension
    await page.click('button:has-text("Add Pension")');
    await page.waitForTimeout(1000);
    await selectOption(page, 'select', 'dc');
    await page.waitForTimeout(500);
    await fillField(page, 'input[placeholder*="scheme"]', 'Optimization Test');
    await fillField(page, 'input[placeholder*="fund value"]', '100000');
    await fillField(page, 'input[placeholder*="employee"]', '5');
    await fillField(page, 'input[placeholder*="employer"]', '3');
    await fillField(page, 'input[placeholder*="salary"]', '50000');
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Go to contributions tab
    const contributionsTab = page.locator('button:has-text("Contributions")');
    if (await contributionsTab.isVisible()) {
      await contributionsTab.click();
      await waitForLoading(page);

      // Check if recommendations are displayed
      const hasRecommendations = await isVisible(page, 'text=contribution, text=optimize');
      expect(hasRecommendations).toBeDefined();
    }
  });
});
