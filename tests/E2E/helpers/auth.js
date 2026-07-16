import { expect } from '@playwright/test';

const VERIFICATION_INPUT = 'input[inputmode="numeric"][maxlength="1"]';

export async function fetchVerificationCode(request, email) {
  const response = await request.get(
    `/__e2e/verification-code?email=${encodeURIComponent(email)}`,
  );

  expect(response.ok()).toBeTruthy();

  const payload = await response.json();
  expect(payload.code).toMatch(/^\d{6}$/);

  return payload.code;
}

export async function enterVerificationCode(page, code) {
  const inputs = page.locator(VERIFICATION_INPUT);
  await expect(inputs).toHaveCount(6);

  for (const [index, digit] of [...code].entries()) {
    await inputs.nth(index).fill(digit);
  }
}

export async function login(page, request, { email, password }) {
  if (!/\/login(?:[/?#]|$)/.test(page.url())) {
    await page.goto('/login');
  }
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);

  const loginResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/auth/login')
      && response.request().method() === 'POST',
  );

  await page.getByRole('button', { name: 'Sign in', exact: true }).click();

  const loginResponse = await loginResponsePromise;
  expect(loginResponse.ok()).toBeTruthy();
  await expect(page.getByRole('heading', { name: 'Enter Verification Code' })).toBeVisible();

  const code = await fetchVerificationCode(request, email);
  const verificationResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/auth/verify-code')
      && response.request().method() === 'POST',
  );

  await enterVerificationCode(page, code);

  const verificationResponse = await verificationResponsePromise;
  expect(verificationResponse.ok()).toBeTruthy();
  await expect(page).toHaveURL(/\/dashboard(?:[/?#]|$)/);
  await expect(page.getByRole('main')).toBeVisible();
}

export async function register(page, request, {
  firstName,
  surname,
  email,
  password,
}) {
  await page.goto('/register');

  await page.locator('#first_name').fill(firstName);
  await page.locator('#last_name').fill(surname);
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
  await expect(page).toHaveURL(/\/(?:onboarding|dashboard)(?:[/?#]|$)/, { timeout: 30_000 });

  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/dashboard(?:[/?#]|$)/);
  await expect(page.getByRole('main')).toBeVisible();

  return { firstName, surname, email, password };
}

export async function logout(page) {
  const signOut = page.getByRole('button', { name: 'Sign Out', exact: true }).first();
  await expect(signOut).toBeVisible();

  const logoutResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/auth/logout')
      && response.request().method() === 'POST',
  );

  await signOut.click();

  const logoutResponse = await logoutResponsePromise;
  expect(logoutResponse.ok()).toBeTruthy();
  await expect(page).toHaveURL(/\/login(?:[/?#]|$)/);
}
