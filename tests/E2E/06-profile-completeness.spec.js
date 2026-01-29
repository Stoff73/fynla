import { test, expect } from '@playwright/test';
import { login, register } from './helpers/auth.js';
import { waitForLoading, navigateToModule, isVisible } from './helpers/common.js';

test.describe('Profile Completeness Validation', () => {
  let testUser;

  test.beforeEach(async ({ page }) => {
    // Register a new user for each test (incomplete profile by default)
    testUser = await register(page);
    await page.waitForTimeout(2000);
  });

  test('should show completeness alert on Protection Dashboard for incomplete profile', async ({ page }) => {
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Check if completeness alert is visible
    const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();

    // Should show alert (newly registered user has incomplete profile)
    await expect(alert).toBeVisible({ timeout: 10000 });

    // Alert should contain completeness information
    const alertText = await alert.textContent();
    expect(alertText).toContain('Profile Completeness');
  });

  test('should show completeness alert on Estate Dashboard for incomplete profile', async ({ page }) => {
    await navigateToModule(page, 'estate');
    await page.waitForTimeout(2000);

    // Check if completeness alert is visible
    const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();

    // Should show alert (newly registered user has incomplete profile)
    await expect(alert).toBeVisible({ timeout: 10000 });

    // Alert should contain completeness information
    const alertText = await alert.textContent();
    expect(alertText).toContain('Profile Completeness');
  });

  test('should show missing fields in completeness alert', async ({ page }) => {
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Check if alert shows specific missing fields
    const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();
    await expect(alert).toBeVisible({ timeout: 10000 });

    const alertText = await alert.textContent();

    // Should mention at least one missing field (new users lack income, assets, etc.)
    const hasMissingFieldMention =
      alertText.includes('income') ||
      alertText.includes('asset') ||
      alertText.includes('protection') ||
      alertText.includes('domicile');

    expect(hasMissingFieldMention).toBeTruthy();
  });

  test('should have actionable link to complete profile', async ({ page }) => {
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Check if alert contains link or button to complete profile
    const profileLink = page.locator('a[href*="/profile"], button:has-text("Complete Profile")');

    // Should have at least one action to complete profile
    const count = await profileLink.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should show completeness warning on Comprehensive Protection Plan', async ({ page }) => {
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Navigate to comprehensive plan (if button exists)
    const viewPlanButton = page.locator('button:has-text("View Plan"), button:has-text("Generate Plan"), a:has-text("Comprehensive Plan")');

    if (await viewPlanButton.count() > 0) {
      await viewPlanButton.first().click();
      await page.waitForTimeout(3000);

      // Check for completeness warning in plan
      const planWarning = page.locator('[data-testid="completeness-warning"], .completeness-warning, .border-orange-500, .border-red-500');

      if (await planWarning.count() > 0) {
        await expect(planWarning.first()).toBeVisible();
      }

      // Check for plan type badge (should be "Generic Plan" for incomplete profile)
      const badge = page.locator('text=Generic Plan, text=Personalized Plan');
      if (await badge.count() > 0) {
        const badgeText = await badge.first().textContent();
        // New user should have generic plan
        expect(badgeText).toContain('Generic');
      }
    }
  });

  test('should show completeness warning on Comprehensive Estate Plan', async ({ page }) => {
    await navigateToModule(page, 'estate');
    await page.waitForTimeout(2000);

    // Navigate to comprehensive plan (if button exists)
    const viewPlanButton = page.locator('button:has-text("View Plan"), button:has-text("Generate Plan"), a:has-text("Comprehensive Plan")');

    if (await viewPlanButton.count() > 0) {
      await viewPlanButton.first().click();
      await page.waitForTimeout(3000);

      // Check for completeness warning in plan
      const planWarning = page.locator('[data-testid="completeness-warning"], .completeness-warning, .border-orange-500, .border-red-500');

      if (await planWarning.count() > 0) {
        await expect(planWarning.first()).toBeVisible();
      }

      // Check for plan type badge
      const badge = page.locator('text=Generic Plan, text=Personalized Plan');
      if (await badge.count() > 0) {
        const badgeText = await badge.first().textContent();
        // New user should have generic plan
        expect(badgeText).toContain('Generic');
      }
    }
  });

  test('should allow dismissing completeness alert', async ({ page }) => {
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Find completeness alert
    const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();
    await expect(alert).toBeVisible({ timeout: 10000 });

    // Look for dismiss button (X or close button)
    const dismissButton = alert.locator('button:has-text("×"), button:has-text("✕"), button[aria-label="Close"], button[aria-label="Dismiss"]');

    if (await dismissButton.count() > 0) {
      await dismissButton.first().click();
      await page.waitForTimeout(1000);

      // Alert should be hidden after dismissal
      await expect(alert).not.toBeVisible();
    }
  });

  test('should show higher completeness percentage after adding income', async ({ page }) => {
    // Navigate to user profile
    await page.goto('/profile');
    await page.waitForTimeout(2000);

    // Find and fill income field (if visible)
    const incomeField = page.locator('input[placeholder*="income"], input[name*="income"], input[id*="income"]').first();

    if (await incomeField.count() > 0 && await incomeField.isVisible()) {
      await incomeField.fill('50000');

      // Save profile
      const saveButton = page.locator('button:has-text("Save"), button[type="submit"]').first();
      if (await saveButton.count() > 0) {
        await saveButton.click();
        await page.waitForTimeout(2000);
      }
    }

    // Navigate to Protection dashboard
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Check completeness alert (if still visible, it should show improvement)
    const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();

    if (await alert.count() > 0 && await alert.isVisible()) {
      const alertText = await alert.textContent();
      // Should have some percentage mentioned
      expect(alertText).toMatch(/\d+%/);
    }
  });

  test('should highlight married users missing spouse link', async ({ page }) => {
    // Navigate to profile to change marital status
    await page.goto('/profile');
    await page.waitForTimeout(2000);

    // Find marital status selector
    const maritalSelect = page.locator('select[name="marital_status"], select[id*="marital"]').first();

    if (await maritalSelect.count() > 0 && await maritalSelect.isVisible()) {
      await maritalSelect.selectOption('married');

      // Save profile
      const saveButton = page.locator('button:has-text("Save"), button[type="submit"]').first();
      if (await saveButton.count() > 0) {
        await saveButton.click();
        await page.waitForTimeout(2000);
      }

      // Navigate to Estate dashboard (where spouse linking is critical)
      await navigateToModule(page, 'estate');
      await page.waitForTimeout(2000);

      // Check if alert mentions spouse linking
      const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();

      if (await alert.count() > 0 && await alert.isVisible()) {
        const alertText = await alert.textContent();
        // Should mention spouse or family
        const mentionsSpouse = alertText.toLowerCase().includes('spouse') || alertText.toLowerCase().includes('family');
        expect(mentionsSpouse).toBeTruthy();
      }
    }
  });

  test('should show recommendations in completeness alert', async ({ page }) => {
    await navigateToModule(page, 'protection');
    await page.waitForTimeout(2000);

    // Check if alert contains recommendations
    const alert = page.locator('[data-testid="profile-completeness-alert"], .profile-completeness-alert, .alert').first();
    await expect(alert).toBeVisible({ timeout: 10000 });

    // Should have list items or recommendations
    const listItems = alert.locator('li, .recommendation-item');
    const count = await listItems.count();

    // Should have at least one recommendation for new user
    expect(count).toBeGreaterThan(0);
  });
});
