import { test, expect } from './fixtures/app.js';

test('mobile WebKit renders full balance history from the shared endpoint', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const email = `balance.history.mobile.${Date.now()}@example.com`;
  const setup = await request.post('/__e2e/active-user', {
    data: {
      email,
      password: 'Password1!',
      tier: 'premium',
      with_balance_history: true,
    },
  });
  expect(setup.ok()).toBeTruthy();
  const fixture = await setup.json();

  await page.addInitScript((token) => {
    window.localStorage.setItem('m_scaffold_token', token);
  }, fixture.token);

  const historyResponse = page.waitForResponse((response) => (
    response.url().includes('/api/balance-history') && response.request().method() === 'GET'
  ));
  await page.goto('/m/app/net-worth/history');
  expect((await historyResponse).ok()).toBeTruthy();

  await expect(page.getByRole('heading', { name: 'Balance history' })).toBeVisible();
  await expect(page.getByText('Full available history')).toBeVisible();
  await expect(page.getByText('+£2,500')).toBeVisible();
  await expect(page.locator('.apexcharts-canvas')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Download adviser export pack' })).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});
