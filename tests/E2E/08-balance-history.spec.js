import { readFile, writeFile } from 'node:fs/promises';
import { test, expect } from './fixtures/app.js';
import { login } from './helpers/auth.js';

async function createHistoryUser(request, tier) {
  const email = `balance.history.${tier}.${Date.now()}@example.com`;
  const password = 'Password1!';
  const response = await request.post('/__e2e/active-user', {
    data: {
      email,
      password,
      tier,
      with_balance_history: true,
    },
  });

  expect(response.ok()).toBeTruthy();

  return { email, password };
}

test('desktop Free balance history is limited to 90 days with the shared Premium action', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const user = await createHistoryUser(request, 'free');
  await login(page, request, user);

  const historyResponse = page.waitForResponse((response) => (
    response.url().includes('/api/balance-history') && response.request().method() === 'GET'
  ));
  await page.goto('/net-worth/history');
  expect((await historyResponse).ok()).toBeTruthy();

  await expect(page.getByRole('heading', { name: 'Balance history' })).toBeVisible();
  await expect(page.getByText('90 days of history are included on Free.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'See plans and upgrade' })).toBeVisible();
  await expect(page.locator('.apexcharts-canvas')).toBeVisible();
  await expect(page.getByText('£12,500').first()).toBeVisible();
  expect(runtimeErrors).toEqual([]);
});

test('desktop Premium shows full history, exact annual movement and a real adviser PDF', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const user = await createHistoryUser(request, 'premium');
  await login(page, request, user);
  await page.goto('/net-worth/history');

  await expect(page.getByText('Full available history')).toBeVisible();
  await expect(page.getByText('+£2,500')).toBeVisible();
  await expect(page.getByText('(+25.0%)')).toBeVisible();
  await expect(page.getByRole('button', { name: 'See plans and upgrade' })).toHaveCount(0);

  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Download adviser export pack' }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toMatch(/^fynla-adviser-export-\d{4}-\d{2}-\d{2}\.pdf$/);
  const bytes = await readFile(await download.path());
  expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
  if (process.env.PDF_INSPECTION_PATH) {
    await writeFile(process.env.PDF_INSPECTION_PATH, bytes);
  }
  expect(runtimeErrors).toEqual([]);
});
