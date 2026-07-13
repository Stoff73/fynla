import { expect } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

export async function waitForApiResponse(page, urlPattern) {
  const response = await page.waitForResponse(
    (candidate) => candidate.url().includes(urlPattern) && candidate.ok(),
  );

  return response.json();
}

export async function fillField(page, selector, value) {
  const field = page.locator(selector);
  await expect(field).toBeVisible();
  await field.fill(value);
}

export async function selectOption(page, selector, value) {
  const field = page.locator(selector);
  await expect(field).toBeVisible();
  await field.selectOption(value);
  await expect(field).toHaveValue(value);
}

export async function clickAndWait(page, selector, responsePattern) {
  if (!responsePattern) {
    throw new Error('clickAndWait requires an API response pattern');
  }

  const responsePromise = page.waitForResponse(
    (response) => response.url().includes(responsePattern),
  );
  const control = page.locator(selector);

  await expect(control).toBeVisible();
  await control.click();

  const response = await responsePromise;
  expect(response.ok()).toBeTruthy();

  return response;
}

export async function takeScreenshot(page, name) {
  await mkdir('test-results/screenshots', { recursive: true });
  await page.screenshot({
    path: `test-results/screenshots/${name}.png`,
    fullPage: true,
  });
}

export async function isVisible(page, selector) {
  await expect(page.locator(selector)).toBeVisible();
  return true;
}

export async function waitForLoading(page) {
  await expect(page.locator('.animate-spin')).toHaveCount(0);
}

export async function navigateToModule(page, module) {
  const response = await page.goto(`/${module}`);
  expect(response?.ok()).toBeTruthy();
  await expect(page).toHaveURL(new RegExp(`/${module}(?:[/?#]|$)`));
  await waitForLoading(page);
}

export function formatCurrencyInput(amount) {
  return String(amount);
}

export function generateEmail(runId, timestamp) {
  if (!runId || !Number.isInteger(timestamp)) {
    throw new Error('generateEmail requires a run identifier and integer timestamp');
  }

  const safeRunId = String(runId).replace(/[^A-Za-z0-9]/g, '').toLowerCase();
  return `e2e.${safeRunId}.${timestamp}@example.com`;
}
