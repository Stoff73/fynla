import { test, expect, devices } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const PHONE_UA = devices['iPhone 14'].userAgent;

function fetchLatestVerificationCode(email) {
  const phpExpr = `$u = \\App\\Models\\User::where('email','${email}')->first(); echo \\App\\Models\\EmailVerificationCode::where('user_id', $u->id)->latest()->first()->code ?? 'none';`;
  const out = execFileSync('php', ['artisan', 'tinker', '--execute', phpExpr], { cwd: process.cwd() }).toString().trim();
  const match = out.match(/\b\d{6}\b/);
  if (!match) throw new Error(`No verification code found for ${email}. tinker output: ${out}`);
  return match[0];
}

test.describe('SP3 mobile iframe scaffold', () => {
  test('desktop UA: / still serves the full web app (no /m redirect)', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const response = await page.goto('/');
    expect(response.status()).toBe(200);
    expect(page.url()).toMatch(/127\.0\.0\.1:8000\/$/);
    const iframeCount = await page.locator('iframe').count();
    expect(iframeCount).toBe(0);
    await context.close();
  });

  test('phone UA: / redirects to /m and renders iframe with src=/m/app', async ({ browser }) => {
    const context = await browser.newContext({ userAgent: PHONE_UA });
    const page = await context.newPage();
    await page.goto('/');
    await expect(page).toHaveURL(/\/m$/);
    const iframe = page.locator('iframe');
    await expect(iframe).toHaveCount(1);
    await expect(iframe).toHaveAttribute('src', '/m/app');

    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1.m-h1')).toContainText('Sign in', { timeout: 15000 });
    await context.close();
  });

  test('phone UA full auth journey: login -> verify -> dashboard renders real /api/v1/mobile/dashboard data', async ({ browser }) => {
    const context = await browser.newContext({ userAgent: PHONE_UA });
    const page = await context.newPage();
    await page.goto('/');
    await expect(page).toHaveURL(/\/m$/);

    const frame = page.frameLocator('iframe');

    await frame.locator('input[type="email"]').fill('john@example.com');
    await frame.locator('input[type="password"]').fill('password');
    await frame.locator('button[type="submit"]').click();

    await expect(frame.locator('h1.m-h1')).toContainText('Enter code', { timeout: 15000 });

    const code = fetchLatestVerificationCode('john@example.com');
    await frame.locator('input[inputmode="numeric"]').fill(code);
    await frame.locator('button[type="submit"]').click();

    await expect(frame.locator('h1.m-h1').first()).toContainText('Signed in', { timeout: 15000 });
    await expect(frame.locator('h1.m-h1').nth(1)).toContainText('Dashboard');

    const summary = await frame.locator('pre').textContent({ timeout: 15000 });
    expect(summary).toBeTruthy();
    expect(summary.length).toBeGreaterThan(20);
    await expect(frame.locator('.m-err')).toHaveCount(0);

    await context.close();
  });

  test('phone UA: ?full=1 escape hatch pins to the full site via cookie', async ({ browser }) => {
    const context = await browser.newContext({ userAgent: PHONE_UA });
    const page = await context.newPage();

    await page.goto('/?full=1');
    await expect(page).not.toHaveURL(/\/m$/);

    const cookies = await context.cookies();
    const pin = cookies.find(c => c.name === 'm_full_site');
    expect(pin).toBeTruthy();
    expect(pin.value).toBe('1');

    await page.goto('/');
    await expect(page).not.toHaveURL(/\/m$/);

    await context.close();
  });
});
