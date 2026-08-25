import { test, expect } from '../fixtures/app.js';

test.use({ viewport: { width: 390, height: 844 } });

const sharedDestinations = [
  ['Dashboard', '/dashboard', null],
  ['Achievements', '/achievements', 'Your progress'],
  ['Conversation History', '/conversation-history', 'Conversation History'],
  ['Income', '/income', 'Income'],
  ['Expenditure', '/expenditure', 'Expenditure'],
  ['Net Worth', '/net-worth', 'Net Worth'],
  ['Bank Accounts', '/savings', 'Bank Accounts'],
  ['Investments', '/investment', 'Investments'],
  ['Retirement', '/retirement', 'Retirement'],
  ['Protection', '/protection', 'Protection'],
  ['Estate Planning', '/estate', 'Estate'],
  ['Goals', '/goals', 'Goals and life events'],
  ['Tax Strategy', '/tax-strategy', 'Tax Strategy'],
  ['Holistic Plan', '/holistic-plan', 'Holistic Plan'],
  ['Personal Information', '/personal-information', 'Personal Information'],
  ['Subscription', '/subscription', 'Subscription'],
  ['Settings', '/settings', 'Settings'],
];

async function dismissCelebration(page) {
  const celebration = page.getByRole('dialog', { name: /^Level up:/ });
  if (await celebration.isVisible()) {
    await celebration.getByRole('button', { name: 'Keep going' }).click();
    await expect(celebration).toBeHidden();
  }
}

async function openSharedDestination(page, runtimeErrors, label, path, heading) {
  await dismissCelebration(page);

  await page.getByRole('button', { name: 'Open menu' }).click();
  const menu = page.getByRole('complementary', { name: 'Menu' });
  await expect(menu).toBeVisible();
  await menu.getByRole('link', { name: label, exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`/m/app${path.replaceAll('/', '\\/')}$`));

  if (heading) {
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
  } else {
    await expect(page.locator('main')).toBeVisible();
  }

  // A heading is intentionally available while many routes are still loading.
  // Wait until the page-level loader and all route requests settle before
  // measuring layout or moving away, otherwise late 5xx/overflow failures can
  // be hidden by the next navigation.
  // The local E2E server is intentionally the single-process PHP development
  // server. On the supported 2014 Intel verification machine, route requests
  // are queued rather than served concurrently; preserve the assertions but
  // allow that hardware enough time to finish the real responses.
  await expect(page.locator('.md-loader')).toHaveCount(0, { timeout: 60_000 });
  await page.waitForLoadState('networkidle', { timeout: 10_000 });
  await page.evaluate(() => new Promise((resolve) => {
    window.requestAnimationFrame(() => window.requestAnimationFrame(resolve));
  }));
  await dismissCelebration(page);
  await expect.poll(async () => page.evaluate(() => ({
    viewport: window.innerWidth,
    width: document.documentElement.scrollWidth,
  }))).toEqual({ viewport: 390, width: 390 });
  expect(runtimeErrors, `${label} emitted a runtime or API error`).toEqual([]);
}

test('installed Chrome closes the complete shared /m parity route and projection ledger', async ({
  page,
  request,
  runtimeErrors,
}) => {
  test.setTimeout(300_000);
  page.setDefaultTimeout(30_000);

  const email = `parity.closure.${Date.now()}.${Math.random().toString(16).slice(2)}@example.com`;
  const setup = await request.post('/__e2e/active-user', {
    data: {
      email,
      password: 'Password1!',
      tier: 'premium',
      with_projection_parity: true,
      with_achievement_personalisation: true,
    },
  });
  expect(setup.ok(), await setup.text()).toBeTruthy();
  const persona = await setup.json();

  await page.addInitScript((token) => {
    // Installed Chrome keeps its profile between local runs. Bootstrap this
    // newly-created persona once per tab session, then leave the rotated bearer
    // untouched across same-session reloads.
    if (!window.sessionStorage.getItem('pr7_parity_persona_bootstrapped')) {
      window.localStorage.setItem('m_scaffold_token', token);
      window.sessionStorage.setItem('pr7_parity_persona_bootstrapped', 'true');
    }
  }, persona.token);

  await page.goto('/m/app/dashboard');
  await expect(page).toHaveURL(/\/m\/app\/dashboard$/);

  for (const [label, path, heading] of sharedDestinations) {
    await openSharedDestination(page, runtimeErrors, label, path, heading);
  }

  await openSharedDestination(page, runtimeErrors, 'Dashboard', '/dashboard', null);
  const retirementRecommendation = page.locator('a.md-rec__action', {
    hasText: /retirement|pension/i,
  }).first();
  await retirementRecommendation.scrollIntoViewIfNeeded();
  await expect(retirementRecommendation).toBeVisible();
  await retirementRecommendation.click();
  await expect(page).toHaveURL(/\/m\/app\/retirement$/);
  await expect(page.getByRole('heading', { name: 'Retirement', exact: true })).toBeVisible();

  await openSharedDestination(page, runtimeErrors, 'Achievements', '/achievements', 'Your progress');
  const savingsBadge = page.locator('[data-achievement-item]', {
    hasText: 'Added savings details',
  });
  await expect(savingsBadge).toContainText('You started building your savings picture.');
  await expect(savingsBadge).toContainText('Earned on 01/08/2026');
  await expect(savingsBadge).not.toContainText('data:savings_account:first');
  await page.getByRole('tab', { name: 'Milestones' }).click();
  const reached = page.locator('[data-reached-milestone]', {
    hasText: 'Your emergency fund covers a month of your spending.',
  });
  await expect(reached).toContainText('Reached on 02/08/2026');
  const inProgress = page.locator('[data-achievement-item]', {
    // The combined projection persona has £484,000 of canonical net worth.
    // Assert the milestone selected from that server-owned position rather
    // than the smaller standalone achievements fixture.
    hasText: 'Net worth £500,000',
  });
  await expect(inProgress.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '96.8');
  await expect(inProgress.getByRole('progressbar')).toHaveAttribute(
    'aria-valuetext',
    '£484,000 of £500,000',
  );
  const inapplicable = page.locator('[data-achievement-item]', {
    hasText: 'On track for retirement',
  });
  await expect(inapplicable).toContainText('Not applicable');
  await expect(inapplicable.getByRole('progressbar')).toHaveCount(0);
  await inProgress.getByRole('button', { name: 'Review your net worth' }).click();
  await expect(page).toHaveURL(/\/m\/app\/net-worth$/);

  await openSharedDestination(page, runtimeErrors, 'Retirement', '/retirement', 'Retirement');
  for (const band of ['Age 65–66', 'Age 67–69', 'Age 70–100']) {
    const ageBand = page.getByText(band, { exact: true });
    await ageBand.scrollIntoViewIfNeeded();
    await expect(ageBand).toBeVisible();
  }
  const assumptions = page.getByText(/4\.7%\s+sustainable withdrawal rate/);
  await assumptions.scrollIntoViewIfNeeded();
  await expect(assumptions).toBeVisible();
  await expect(page.getByText('Median projection')).toHaveCount(0);

  await openSharedDestination(page, runtimeErrors, 'Net Worth', '/net-worth', 'Net Worth');
  await expect(page.getByText('Recorded balance history')).toBeVisible();
  await expect(page.getByText('Projected net worth', { exact: true })).toBeVisible();

  const propertyRate = page.getByTestId('forecast-rate-property');
  await expect(propertyRate).toHaveValue('3');
  await propertyRate.fill('6.25');
  await page.getByTestId('forecast-save').click();
  await expect(page.getByRole('status')).toHaveText('Assumptions saved.');
  await page.reload();
  await expect(page.getByTestId('forecast-rate-property')).toHaveValue('6.25');
  await page.getByTestId('forecast-reset').click();
  await expect(page.getByRole('status')).toHaveText('Assumptions reset to Fynla defaults.');
  await expect(propertyRate).toHaveValue('3');

  expect(runtimeErrors).toEqual([]);
});
