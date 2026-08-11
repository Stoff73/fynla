import { test, expect } from '../fixtures/app.js';

test.use({ viewport: { width: 390, height: 844 } });

test('installed Chrome renders the canonical personalised achievements journey', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const email = `achievements.personalisation.${Date.now()}.${Math.random().toString(16).slice(2)}@example.com`;
  const setup = await request.post('/__e2e/active-user', {
    data: {
      email,
      password: 'Password1!',
      tier: 'premium',
      with_achievement_personalisation: true,
    },
  });
  expect(setup.ok(), await setup.text()).toBeTruthy();
  const persona = await setup.json();

  await page.addInitScript((token) => {
    window.localStorage.setItem('m_scaffold_token', token);
  }, persona.token);

  const canonicalResponse = page.waitForResponse((response) => (
    new URL(response.url()).pathname === '/api/v1/mobile/achievements/v2'
      && response.request().method() === 'GET'
  ));
  await page.goto('/m/app/achievements');
  const canonical = await canonicalResponse;
  expect(canonical.ok(), `${canonical.status()} ${await canonical.text()}`).toBeTruthy();

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
  await expect(reached).not.toContainText('emergency_fund:0:1');

  const inProgress = page.locator('[data-achievement-item]', {
    hasText: 'Net worth £10,000',
  });
  await expect(inProgress).toContainText('In progress');
  const progress = inProgress.getByRole('progressbar');
  await expect(progress).toHaveAttribute('aria-valuenow', '40');
  await expect(progress).toHaveAttribute('aria-valuetext', '£4,000 of £10,000');

  const inapplicable = page.locator('[data-achievement-item]', {
    hasText: 'On track for retirement',
  });
  await expect(inapplicable).toContainText('Not applicable');
  await expect(inapplicable.getByRole('progressbar')).toHaveCount(0);

  const action = inProgress.getByRole('button', { name: 'Review your net worth' });
  await expect(action).toBeVisible();
  await action.click();
  await expect(page).toHaveURL(/\/m\/app\/net-worth$/);
  expect(runtimeErrors).toEqual([]);
});
