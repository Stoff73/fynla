import { test, expect } from './fixtures/app.js';

test.use({ viewport: { width: 390, height: 844 } });

test('installed Chrome keeps retirement and net worth projection contracts in parity', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const email = `projection.parity.${Date.now()}@example.com`;
  const setup = await request.post('/__e2e/active-user', {
    data: {
      email,
      password: 'Password1!',
      tier: 'premium',
      with_projection_parity: true,
    },
  });
  expect(setup.ok(), await setup.text()).toBeTruthy();
  const fixture = await setup.json();

  await page.addInitScript((token) => {
    if (!window.localStorage.getItem('m_scaffold_token')) {
      window.localStorage.setItem('m_scaffold_token', token);
    }
  }, fixture.token);

  const projectionsResponse = page.waitForResponse((response) => (
    response.url().includes('/api/retirement/projections')
      && response.request().method() === 'GET'
  ));
  await page.goto('/m/app/retirement');
  const projections = await projectionsResponse;
  expect(projections.ok()).toBeTruthy();
  const projectionPayload = await projections.json();
  const planning = projectionPayload.data.planning_projection;
  expect(planning.products).toHaveLength(3);
  expect(planning.age_bands).toHaveLength(3);
  expect(planning.assumptions.sustainable_withdrawal_rate.percent).toBe(4.7);

  await expect(page.getByText('Age 65–66')).toBeVisible();
  await expect(page.getByText('Age 67–69')).toBeVisible();
  await expect(page.getByText('Age 70–100')).toBeVisible();
  await expect(page.getByText(/4\.7%\s+sustainable withdrawal rate/)).toBeVisible();
  await expect(page.getByText('Median projection')).toHaveCount(0);

  const forecastResponse = page.waitForResponse((response) => (
    response.url().includes('/api/net-worth/forecast')
      && response.request().method() === 'GET'
  ));
  await page.getByRole('button', { name: 'Open menu' }).click();
  await page.getByRole('link', { name: 'Net Worth', exact: true }).click();
  await expect(page).toHaveURL(/\/m\/app\/net-worth$/);
  const forecast = await forecastResponse;
  expect(
    forecast.ok(),
    `${forecast.status()} ${await forecast.text()}`,
  ).toBeTruthy();
  await expect(page.getByText('Recorded balance history')).toBeVisible();
  await expect(page.getByText('Projected net worth', { exact: true })).toBeVisible();

  const propertyRate = page.getByTestId('forecast-rate-property');
  await expect(propertyRate).toHaveValue('3');
  await propertyRate.fill('6.25');

  const saveResponse = page.waitForResponse((response) => (
    response.url().includes('/api/net-worth/forecast/assumptions')
      && response.request().method() === 'PUT'
  ));
  await page.getByTestId('forecast-save').click();
  expect((await saveResponse).ok()).toBeTruthy();
  await expect(page.getByRole('status')).toHaveText('Assumptions saved.');
  await expect(propertyRate).toHaveValue('6.25');

  await page.reload();
  await expect(page.getByTestId('forecast-rate-property')).toHaveValue('6.25');

  const resetResponse = page.waitForResponse((response) => (
    response.url().includes('/api/net-worth/forecast/assumptions')
      && response.request().method() === 'DELETE'
  ));
  await page.getByTestId('forecast-reset').click();
  expect((await resetResponse).ok()).toBeTruthy();
  await expect(page.getByRole('status')).toHaveText('Assumptions reset to Fynla defaults.');
  await expect(page.getByTestId('forecast-rate-property')).toHaveValue('3');
  expect(runtimeErrors).toEqual([]);
});
