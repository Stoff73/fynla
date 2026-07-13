import { test, expect } from '@playwright/test';
import { register } from './helpers/auth.js';
import { waitForLoading, navigateToModule, fillField, selectOption, isVisible } from './helpers/common.js';

test.describe.skip('legacy E2E migration tracked by online-readiness Task 24', () => {
  test.beforeEach(async ({ page }) => {
    // Register a new user for each test
    await register(page);
    await page.waitForTimeout(2000);
  });

  test('should load Protection dashboard', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Check if main elements are visible
    await expect(page.locator('h1')).toContainText('Protection');
    await expect(page.locator('text=Coverage Score')).toBeVisible();
  });

  test('should add Life Insurance policy', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Click add policy button
    await page.click('button:has-text("Add Policy")');
    await page.waitForTimeout(1000);

    // Fill life insurance form
    await selectOption(page, 'select', 'life');
    await fillField(page, 'input[placeholder*="provider"]', 'Test Life Insurance Co');
    await fillField(page, 'input[placeholder*="policy number"]', 'LIFE12345');
    await fillField(page, 'input[placeholder*="coverage"]', '250000');
    await fillField(page, 'input[placeholder*="premium"]', '50');
    await selectOption(page, 'select[id*="frequency"]', 'monthly');

    // Submit form
    await page.click('button[type="submit"]:has-text("Add")');
    await page.waitForTimeout(3000);

    // Verify policy was added
    const policyCard = page.locator('.policy-card, [data-testid="policy-card"]').first();
    await expect(policyCard).toBeVisible({ timeout: 10000 });
  });

  test('should add Critical Illness policy', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Click add policy button
    await page.click('button:has-text("Add Policy")');
    await page.waitForTimeout(1000);

    // Fill critical illness form
    await selectOption(page, 'select', 'criticalIllness');
    await fillField(page, 'input[placeholder*="provider"]', 'Critical Illness Provider');
    await fillField(page, 'input[placeholder*="policy number"]', 'CI12345');
    await fillField(page, 'input[placeholder*="coverage"]', '100000');
    await fillField(page, 'input[placeholder*="premium"]', '30');
    await selectOption(page, 'select[id*="frequency"]', 'monthly');

    // Submit form
    await page.click('button[type="submit"]:has-text("Add")');
    await page.waitForTimeout(3000);

    // Verify policy was added (should not get 422 error)
    const hasError = await isVisible(page, 'text=422');
    expect(hasError).toBe(false);
  });

  test('should add Income Protection policy', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Click add policy button
    await page.click('button:has-text("Add Policy")');
    await page.waitForTimeout(1000);

    // Fill income protection form
    await selectOption(page, 'select', 'incomeProtection');
    await fillField(page, 'input[placeholder*="provider"]', 'Income Protection Provider');
    await fillField(page, 'input[placeholder*="coverage"]', '2000');
    await fillField(page, 'input[placeholder*="premium"]', '25');

    // Submit form
    await page.click('button[type="submit"]:has-text("Add")');
    await page.waitForTimeout(3000);

    // Check for errors
    const hasError = await isVisible(page, 'text=error, text=failed');
    expect(hasError).toBe(false);
  });

  test('should navigate through all Protection tabs', async ({ page }) => {
    await navigateToModule(page, 'protection');

    const tabs = [
      'Current Situation',
      'Gap Analysis',
      'Recommendations',
      'What-If Scenarios',
      'Policy Details',
    ];

    for (const tab of tabs) {
      await page.click(`button:has-text("${tab}")`);
      await waitForLoading(page);
      await page.waitForTimeout(1000);

      // Verify tab content loaded
      const hasContent = await isVisible(page, '.tab-content, [role="tabpanel"]');
      expect(hasContent).toBe(true);
    }
  });

  test('should display coverage adequacy gauge', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Check if gauge is visible
    const gauge = page.locator('[data-testid="coverage-gauge"], .apexcharts-radialbar');
    await expect(gauge.first()).toBeVisible({ timeout: 10000 });
  });

  test('should display policy details after adding policy', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Add a policy first
    await page.click('button:has-text("Add Policy")');
    await page.waitForTimeout(1000);

    await selectOption(page, 'select', 'life');
    await fillField(page, 'input[placeholder*="provider"]', 'Test Provider');
    await fillField(page, 'input[placeholder*="coverage"]', '100000');
    await fillField(page, 'input[placeholder*="premium"]', '25');

    await page.click('button[type="submit"]:has-text("Add")');
    await page.waitForTimeout(3000);

    // Go to Policy Details tab
    await page.click('button:has-text("Policy Details")');
    await waitForLoading(page);

    // Verify policy is listed
    await expect(page.locator('text=Test Provider')).toBeVisible({ timeout: 5000 });
  });

  test('should handle form validation errors', async ({ page }) => {
    await navigateToModule(page, 'protection');

    // Click add policy button
    await page.click('button:has-text("Add Policy")');
    await page.waitForTimeout(1000);

    // Try to submit empty form
    await page.click('button[type="submit"]:has-text("Add")');
    await page.waitForTimeout(1000);

    // Check for validation messages
    const hasValidation = await isVisible(page, 'input:invalid, .error-message, text=required');
    expect(hasValidation).toBe(true);
  });
});
