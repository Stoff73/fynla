import { test, expect } from '../fixtures/app.js';
import { login } from '../helpers/auth.js';

const PASSWORD = 'Password1!';

async function createUser(request, label, options = {}) {
  const email = `freemium.${label}.${Date.now()}.${Math.random().toString(16).slice(2)}@example.com`;
  const response = await request.post('/__e2e/active-user', {
    data: { email, password: PASSWORD, ...options },
  });
  expect(response.ok()).toBeTruthy();

  return { email, password: PASSWORD, ...(await response.json()) };
}

async function expectPremiumModal(page) {
  const dialog = page.getByRole('dialog', { name: /Upgrade Your Plan|Choose Your Plan/ });
  await expect(dialog).toBeVisible({ timeout: 30_000 });
  await expect(dialog.locator('h3', { hasText: 'Premium' })).toHaveCount(1);
  await expect(dialog.locator('h3', { hasText: 'Free' })).toHaveCount(0);
  await expect(dialog.locator('h3', { hasText: 'Student' })).toHaveCount(0);

  return dialog;
}

async function exerciseCap(page, route, trigger, entityLabel) {
  await page.goto(route);
  const button = trigger(page);
  await expect(button).toBeVisible({ timeout: 20_000 });
  await button.click();

  const limitDialog = page.getByRole('dialog', { name: "You've reached your Free limit" });
  await expect(limitDialog).toBeVisible();
  await expect(limitDialog).toContainText(entityLabel);
  const upgrade = limitDialog.getByRole('link', { name: 'Upgrade', exact: true });
  await expect(upgrade).toHaveAttribute('href', '/settings/subscription?openPricing=1');
  await upgrade.click();

  const planDialog = await expectPremiumModal(page);
  await planDialog.getByRole('button', { name: 'Close' }).click();
}

test.describe('desktop upgrade entry points', () => {
  test.beforeEach(({}, testInfo) => {
    test.skip(testInfo.project.name === 'mobile-webkit');
  });

test('Free exposes the canonical Premium journey and Settings preserves checkout terms', async ({ page, request }) => {
  const user = await createUser(request, 'free');
  await login(page, request, user);

  const upgrade = page.getByRole('button', { name: 'Upgrade Now', exact: true });
  await expect(upgrade).toBeVisible();
  await upgrade.click();
  await expectPremiumModal(page);

  await page.getByRole('button', { name: 'Close' }).click();
  await page.goto('/settings/subscription?openPricing=1');
  const dialog = await expectPremiumModal(page);
  await dialog.getByRole('button', { name: 'Monthly', exact: true }).click();
  await dialog.getByRole('button', { name: 'Choose Plan', exact: true }).click();
  await expect(page).toHaveURL(/\/checkout\?plan=premium&cycle=monthly$/);
});

test('Premium has no paid entry and cannot open an empty plan modal', async ({ page, request }) => {
  const user = await createUser(request, 'premium', { tier: 'premium' });
  await login(page, request, user);

  await expect(page.getByRole('button', { name: 'Upgrade Now', exact: true })).toHaveCount(0);
  await page.goto('/settings/subscription?openPricing=1');
  await expect(page.getByRole('main').getByRole('heading', { name: 'Settings' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'You are on Premium' })).toBeVisible();
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Choose a Plan', exact: true })).toHaveCount(0);
});

test('payment-disabled state exposes no paid call to action', async ({ page, request }) => {
  const user = await createUser(request, 'payment-disabled');
  await page.route('**/api/payment/subscription-status', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        has_subscription: false,
        tier: 'free',
        tier_display_name: 'Free',
        status: null,
        payment_enabled: false,
      }),
    });
  });
  await login(page, request, user);
  await expect(page.getByRole('button', { name: 'Upgrade Now', exact: true })).toHaveCount(0);
  await page.goto('/settings/subscription?openPricing=1');
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.getByRole('button', { name: /Choose a Plan|Subscribe Now/ })).toHaveCount(0);
});

test('all six Free create caps preserve records and open the one Premium option', async ({ page, request }) => {
  test.setTimeout(240_000);
  const user = await createUser(request, 'all-caps', { with_freemium_caps: true });
  await login(page, request, user);

  await exerciseCap(
    page,
    '/net-worth/cash',
    (surface) => surface.locator('button[title="Add Account"]').first(),
    'cash accounts',
  );
  await exerciseCap(
    page,
    '/net-worth/investments',
    (surface) => surface.getByRole('button', { name: 'Add Account', exact: true }),
    'investment accounts',
  );
  await exerciseCap(
    page,
    '/net-worth/retirement',
    (surface) => surface.getByRole('button', { name: 'Add Pension', exact: true }),
    'pensions',
  );
  await exerciseCap(
    page,
    '/net-worth/property',
    (surface) => surface.getByRole('button', { name: 'Add Property', exact: true }),
    'properties',
  );
  await exerciseCap(
    page,
    '/goals',
    (surface) => surface.getByRole('button', { name: 'Add Goal', exact: true }).first(),
    'goals',
  );
  await exerciseCap(
    page,
    '/goals?tab=events',
    (surface) => surface.getByRole('button', { name: 'Add Life Event', exact: true }),
    'life events',
  );

  const counts = await request.get(`/__e2e/freemium-counts?email=${encodeURIComponent(user.email)}`);
  expect(counts.ok()).toBeTruthy();
  expect(await counts.json()).toEqual({
    savings_account: 2,
    investment: 2,
    pension_account: 2,
    property: 1,
    goal: 2,
    life_event: 1,
  });
});

});

test('the /m Estate action bridges auth and breaks out to canonical subscription options', async ({ page, request }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-webkit');
  const user = await createUser(request, 'mobile-estate');
  await page.addInitScript((token) => {
    window.localStorage.setItem('m_scaffold_token', token);
  }, user.token);

  await page.goto('/m');
  const iframe = page.locator('iframe');
  await expect(iframe).toHaveCount(1);
  await iframe.evaluate((element) => { element.src = '/m/app/estate'; });
  const surface = page.frameLocator('iframe');
  const compare = surface.getByRole('button', { name: 'Compare plans', exact: true });
  await expect(compare).toBeVisible({ timeout: 30_000 });
  const currentMobileToken = await surface.locator('body').evaluate(
    () => window.localStorage.getItem('m_scaffold_token'),
  );
  expect(currentMobileToken).toBeTruthy();

  const canonicalRequest = page.waitForRequest((candidate) => {
    const url = new URL(candidate.url());
    return candidate.isNavigationRequest()
      && url.pathname === '/settings/subscription'
      && url.searchParams.get('openPricing') === '1';
  });
  await compare.click();
  expect((await canonicalRequest).url()).toContain('/settings/subscription?openPricing=1');
  await expect(page).toHaveURL(/\/settings\/subscription/);
  expect(await page.evaluate(() => window.sessionStorage.getItem('auth_token'))).toBe(currentMobileToken);
  await expectPremiumModal(page);
});
