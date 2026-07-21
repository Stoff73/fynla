import { randomBytes } from 'node:crypto';
import { test, expect } from '../fixtures/app.js';
import { generateEmail } from '../helpers/common.js';
import {
  enterVerificationCode,
  fetchVerificationCode,
  login,
  logout,
} from '../helpers/auth.js';

test.describe.configure({ timeout: 90_000 });

test.beforeEach(async ({ request }) => {
  const response = await request.post('/__e2e/registration-throttle/reset');
  expect(response.ok()).toBeTruthy();
});

async function registerAndVerify(page, request, { path, email }) {
  const password = `${randomBytes(12).toString('hex')}aA1!`;

  await page.goto(path);
  await page.locator('#first_name').fill('Checkout');
  await page.locator('#last_name').fill('Intent');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password_confirmation').fill(password);

  const registrationResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/auth/register')
      && response.request().method() === 'POST',
  );

  await page.getByRole('button', { name: 'Create Account', exact: true }).click();

  const registrationResponse = await registrationResponsePromise;
  expect(registrationResponse.ok()).toBeTruthy();
  await expect(page.getByRole('heading', { name: 'Enter Verification Code' })).toBeVisible();

  const code = await fetchVerificationCode(request, email);
  const verificationResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/auth/verify-code')
      && response.request().method() === 'POST',
  );

  await enterVerificationCode(page, code);

  const verificationResponse = await verificationResponsePromise;
  expect(verificationResponse.ok()).toBeTruthy();

  return {
    email,
    password,
    verification: await verificationResponse.json(),
  };
}

test('registration verification creates a real user who can sign out and sign back in', async ({
  page,
  request,
  registerVerifiedUser,
}) => {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const email = generateEmail(runId, Date.now());
  const password = `${randomBytes(12).toString('hex')}aA1!`;

  await registerVerifiedUser({
    firstName: 'E2E',
    surname: 'User',
    email,
    password,
  });

  const userResponse = await request.get(`/__e2e/user?email=${encodeURIComponent(email)}`);
  expect(userResponse.ok()).toBeTruthy();

  const user = await userResponse.json();
  expect(user.email).toBe(email);
  expect(user.is_preview_user).toBe(false);

  await logout(page);
  await login(page, request, { email, password });
});

test('Free registration continues to onboarding without a checkout intent', async ({
  page,
  request,
}) => {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const email = generateEmail(runId, Date.now());
  const result = await registerAndVerify(page, request, {
    path: '/register',
    email,
  });

  expect(result.verification.data.checkout_intent).toBeNull();
  await expect(page).toHaveURL(/\/onboarding\?newUser=1$/, { timeout: 30_000 });
});

for (const billingCycle of ['monthly', 'yearly']) {
  test(`Premium ${billingCycle} registration continues to checkout`, async ({
    page,
    request,
  }) => {
    const runId = process.env.GITHUB_RUN_ID || 'local';
    const email = generateEmail(runId, Date.now());
    const result = await registerAndVerify(page, request, {
      path: `/register?plan=premium&billing=${billingCycle}`,
      email,
    });

    expect(result.verification.data.checkout_intent).toEqual({
      tier: 'premium',
      billing_cycle: billingCycle,
    });
    await expect(page).toHaveURL(
      `/checkout?plan=premium&cycle=${billingCycle}`,
      { timeout: 30_000 },
    );
  });
}

test('campaign registration without a paid intent keeps the campaign destination', async ({
  page,
  request,
}) => {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const email = generateEmail(runId, Date.now());
  const result = await registerAndVerify(page, request, {
    path: '/register?from=savetax',
    email,
  });

  expect(result.verification.data.checkout_intent).toBeNull();
  await expect(page).toHaveURL(/\/dashboard$/, { timeout: 30_000 });
  await expect(page.getByText(/gross annual income/i)).toBeVisible({ timeout: 30_000 });
});

test('persisted Premium intent takes precedence over the campaign destination', async ({
  page,
  request,
}) => {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const email = generateEmail(runId, Date.now());
  const result = await registerAndVerify(page, request, {
    path: '/register?from=savetax&plan=premium&billing=yearly',
    email,
  });

  expect(result.verification.data.checkout_intent).toEqual({
    tier: 'premium',
    billing_cycle: 'yearly',
  });
  await expect(page).toHaveURL('/checkout?plan=premium&cycle=yearly', {
    timeout: 30_000,
  });
});
