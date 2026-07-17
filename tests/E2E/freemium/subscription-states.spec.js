import { test, expect } from '../fixtures/app.js';

const PASSWORD = 'Password1!';
const FUTURE_END = '2027-01-31T23:59:59Z';

async function createUser(request, label) {
  const email = `subscription.${label}.${Date.now()}.${Math.random().toString(16).slice(2)}@example.com`;
  const response = await request.post('/__e2e/active-user', {
    data: { email, password: PASSWORD },
  });
  expect(response.ok()).toBeTruthy();

  return { email, password: PASSWORD, ...(await response.json()) };
}

function response(overrides = {}) {
  return {
    has_subscription: false,
    tier: 'free',
    tier_display_name: 'Free',
    status: null,
    subscription_status: null,
    plan: null,
    billing_cycle: null,
    amount: null,
    current_period_end: null,
    cancelled_at: null,
    auto_renew: false,
    next_renewal_date: null,
    grace_period_ends_at: null,
    is_in_grace_period: false,
    is_terminal_paid: false,
    payment_enabled: true,
    ...overrides,
  };
}

async function openState(page, request, label, subscriptionStatus) {
  const user = await createUser(request, label);
  await page.route('**/api/payment/subscription-status', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(subscriptionStatus),
  }));
  await page.addInitScript((token) => {
    window.sessionStorage.setItem('auth_token', token);
  }, user.token);
  await page.goto('/settings/subscription');
  await expect(page.getByRole('heading', { name: 'Settings' }).first()).toBeVisible();
}

async function expectNoRetiredPresentation(page) {
  await expect(page.getByText(/free trial|\bstudent\b|\bstandard\b|\bpro\b|tier [123]/i)).toHaveCount(0);
}

async function choosePremiumMonthly(page, buttonName) {
  await page.getByRole('button', { name: buttonName, exact: true }).click();
  const modal = page.getByRole('dialog', { name: /Choose Your Plan|Upgrade Your Plan|Your subscription has ended/i });
  await expect(modal).toBeVisible({ timeout: 30_000 });
  await modal.getByRole('button', { name: 'Monthly', exact: true }).click();
  await modal.getByRole('button', { name: 'Choose Plan', exact: true }).click();
  await expect(page).toHaveURL(/\/checkout\?plan=premium&cycle=monthly$/);
}

test('Free remains writable and can continue to Premium checkout', async ({ page, request }) => {
  await openState(page, request, 'free', response());
  await expect(page.getByRole('heading', { name: 'Free', exact: true })).toBeVisible();
  await expectNoRetiredPresentation(page);
  await choosePremiumMonthly(page, 'Compare plans');
});

test('pending Premium checkout remains Free without trial presentation', async ({ page, request }) => {
  await openState(page, request, 'pending', response({
    has_subscription: true,
    status: 'pending',
    subscription_status: 'pending',
    plan: 'premium',
  }));
  await expect(page.getByRole('heading', { name: 'Payment pending' })).toBeVisible();
  await expect(page.getByText('Free — payment pending')).toBeVisible();
  await expectNoRetiredPresentation(page);
  await choosePremiumMonthly(page, 'Continue to Premium');
});

test('active Premium has billing controls and no upgrade action', async ({ page, request }) => {
  await openState(page, request, 'active', response({
    has_subscription: true,
    tier: 'premium',
    tier_display_name: 'Premium',
    status: 'active',
    subscription_status: 'active',
    plan: 'premium',
    billing_cycle: 'monthly',
    amount: 499,
    current_period_end: FUTURE_END,
    auto_renew: true,
    next_renewal_date: FUTURE_END,
  }));
  await expect(page.getByRole('heading', { name: 'Your Subscription' })).toBeVisible();
  await expect(page.getByText('Your Premium plan is active')).toBeVisible();
  await expect(page.getByRole('button', { name: /Compare plans|Continue to Premium|Resolve payment|Subscribe to Premium/ })).toHaveCount(0);
  await expectNoRetiredPresentation(page);
});

test('cancelled Premium retains access to its period end without upgrade controls', async ({ page, request }) => {
  await openState(page, request, 'cancelled', response({
    has_subscription: true,
    tier: 'premium',
    tier_display_name: 'Premium',
    status: 'cancelled',
    subscription_status: 'cancelled',
    plan: 'premium',
    billing_cycle: 'yearly',
    amount: 5999,
    cancelled_at: '2026-07-14T09:00:00Z',
    current_period_end: FUTURE_END,
  }));
  await expect(page.getByRole('heading', { name: 'Subscription Cancelled' })).toBeVisible();
  await expect(page.getByText('Premium — ends 31 January 2027')).toBeVisible();
  await expect(page.getByRole('button', { name: /Compare plans|Continue to Premium|Resolve payment|Subscribe to Premium/ })).toHaveCount(0);
  await expectNoRetiredPresentation(page);
});

test('past-due Premium presents the payment issue and can return to Premium checkout', async ({ page, request }) => {
  await openState(page, request, 'past-due', response({
    has_subscription: true,
    tier: 'premium',
    tier_display_name: 'Premium',
    status: 'past_due',
    subscription_status: 'past_due',
    plan: 'premium',
    billing_cycle: 'monthly',
    amount: 499,
    current_period_end: FUTURE_END,
  }));
  await expect(page.getByRole('heading', { name: 'Payment Issue' })).toBeVisible();
  await expect(page.getByText('Premium — payment issue')).toBeVisible();
  await expectNoRetiredPresentation(page);
  await choosePremiumMonthly(page, 'Resolve payment');
});

test('grace and expired states use subscription-ended language and Premium recovery', async ({ page, request }) => {
  await openState(page, request, 'grace', response({
    has_subscription: true,
    tier: 'premium',
    tier_display_name: 'Premium',
    status: 'expired',
    subscription_status: 'expired',
    plan: 'premium',
    current_period_end: '2026-07-01T00:00:00Z',
    grace_period_ends_at: '2026-08-14T00:00:00Z',
    is_in_grace_period: true,
    is_terminal_paid: true,
  }));
  await expect(page.getByText('Subscription ended — read-only access')).toBeVisible();
  await expectNoRetiredPresentation(page);

  await page.unroute('**/api/payment/subscription-status');
  await page.route('**/api/payment/subscription-status', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(response({
      has_subscription: true,
      tier: 'premium',
      tier_display_name: 'Premium',
      status: 'expired',
      subscription_status: 'expired',
      plan: 'premium',
      current_period_end: '2026-07-01T00:00:00Z',
      is_terminal_paid: true,
    })),
  }));
  await page.reload();
  const endedModal = page.getByRole('dialog', { name: 'Your subscription has ended' });
  await expect(endedModal).toBeVisible({ timeout: 30_000 });
  await expect(endedModal.getByRole('heading', { name: 'Premium' })).toBeVisible();
  await expectNoRetiredPresentation(page);
});
