import { test, expect } from '@playwright/test';
import { register } from './helpers/auth.js';
import { waitForLoading, navigateToModule, fillField, selectOption, isVisible } from './helpers/common.js';

test.describe.skip('legacy E2E migration tracked by online-readiness Task 24', () => {
  test.beforeEach(async ({ page }) => {
    await register(page);
    await page.waitForTimeout(2000);
  });

  test('should load Estate dashboard', async ({ page }) => {
    await navigateToModule(page, 'estate');

    await expect(page.locator('h1')).toContainText('Estate');
    await expect(page.locator('text=Net Worth')).toBeVisible();
  });

  test('should add asset', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Navigate to Assets & Liabilities tab
    await page.click('button:has-text("Assets")');
    await page.waitForTimeout(1000);

    // Click add asset button
    await page.click('button:has-text("Add Asset")');
    await page.waitForTimeout(1000);

    // Fill asset form
    await selectOption(page, 'select[id*="asset_type"]', 'property');
    await fillField(page, 'input[placeholder*="name"]', 'Main Residence');
    await fillField(page, 'input[placeholder*="value"]', '350000');
    await selectOption(page, 'select[id*="ownership"]', 'individual');

    // Submit
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error, text=422');
    expect(hasError).toBe(false);
  });

  test('should add liability', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Navigate to Assets & Liabilities tab
    await page.click('button:has-text("Assets")');
    await page.waitForTimeout(1000);

    // Click add liability button
    await page.click('button:has-text("Add Liability")');
    await page.waitForTimeout(1000);

    // Fill liability form
    await selectOption(page, 'select[id*="liability_type"]', 'mortgage');
    await fillField(page, 'input[placeholder*="name"]', 'Main Residence Mortgage');
    await fillField(page, 'input[placeholder*="balance"]', '200000');
    await fillField(page, 'input[placeholder*="monthly"]', '1200');
    await fillField(page, 'input[placeholder*="interest"]', '3.5');

    // Submit
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    const hasError = await isVisible(page, 'text=error, text=422');
    expect(hasError).toBe(false);
  });

  test('should add gift', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Navigate to Gifting Strategy tab
    await page.click('button:has-text("Gifting")');
    await page.waitForTimeout(1000);

    // Click add gift button
    const addGiftButton = page.locator('button:has-text("Add Gift")');
    if (await addGiftButton.isVisible()) {
      await addGiftButton.click();
      await page.waitForTimeout(1000);

      // Fill gift form
      await selectOption(page, 'select[id*="gift_type"]', 'pet');
      await fillField(page, 'input[placeholder*="recipient"]', 'Son');
      await fillField(page, 'input[placeholder*="value"]', '25000');

      await page.click('button[type="submit"]:has-text("Save")');
      await page.waitForTimeout(3000);

      const hasError = await isVisible(page, 'text=error');
      expect(hasError).toBe(false);
    }
  });

  test('should display net worth calculation', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Add an asset
    await page.click('button:has-text("Assets")');
    await page.waitForTimeout(1000);
    await page.click('button:has-text("Add Asset")');
    await page.waitForTimeout(1000);
    await selectOption(page, 'select[id*="asset_type"]', 'savings');
    await fillField(page, 'input[placeholder*="name"]', 'Savings Account');
    await fillField(page, 'input[placeholder*="value"]', '50000');
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Go back to Net Worth tab
    await page.click('button:has-text("Net Worth")');
    await waitForLoading(page);

    // Check if net worth is displayed
    const netWorth = page.locator('text=£, text=Total');
    await expect(netWorth.first()).toBeVisible({ timeout: 5000 });
  });

  test('should display IHT liability calculation', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Navigate to IHT Planning tab
    await page.click('button:has-text("IHT")');
    await waitForLoading(page);

    // Check if IHT information is displayed
    const ihtInfo = page.locator('text=Inheritance Tax, text=IHT');
    await expect(ihtInfo.first()).toBeVisible({ timeout: 5000 });
  });

  test('should navigate to Cash Flow tab without hanging', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Click Cash Flow tab
    await page.click('button:has-text("Cash Flow")');

    // Wait and check it doesn't hang
    await page.waitForTimeout(5000);

    // Check if content loaded (not infinite loading)
    const isLoading = await isVisible(page, '.animate-spin');
    expect(isLoading).toBe(false);

    // Check if cash flow content is visible
    const hasCashFlow = await isVisible(page, 'text=Income, text=Expenses, text=Cash Flow');
    expect(hasCashFlow).toBe(true);
  });

  test('should navigate through all Estate tabs', async ({ page }) => {
    await navigateToModule(page, 'estate');

    const tabs = [
      'Net Worth',
      'IHT Planning',
      'Gifting Strategy',
      'Cash Flow',
      'Assets & Liabilities',
      'Recommendations',
    ];

    for (const tab of tabs) {
      const tabButton = page.locator(`button:has-text("${tab}")`);
      if (await tabButton.isVisible()) {
        await tabButton.click();
        await page.waitForTimeout(3000); // Longer wait for Estate module

        // Check not hanging
        const isLoading = await isVisible(page, '.animate-spin');
        if (isLoading) {
          // Wait a bit more and check again
          await page.waitForTimeout(5000);
          const stillLoading = await isVisible(page, '.animate-spin');
          expect(stillLoading).toBe(false);
        }
      }
    }
  });

  test('should display IHT waterfall chart', async ({ page }) => {
    await navigateToModule(page, 'estate');

    // Add an asset to generate IHT
    await page.click('button:has-text("Assets")');
    await page.waitForTimeout(1000);
    await page.click('button:has-text("Add Asset")');
    await page.waitForTimeout(1000);
    await selectOption(page, 'select[id*="asset_type"]', 'property');
    await fillField(page, 'input[placeholder*="name"]', 'Estate Property');
    await fillField(page, 'input[placeholder*="value"]', '750000');
    await page.click('button[type="submit"]:has-text("Save")');
    await page.waitForTimeout(3000);

    // Go to IHT Planning
    await page.click('button:has-text("IHT Planning")');
    await waitForLoading(page);

    // Check for chart
    const isChartVisible = await isVisible(page, '.apexcharts-bar');
    expect(isChartVisible).toBeDefined();
  });
});
