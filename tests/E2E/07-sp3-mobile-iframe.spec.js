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

  // Default behaviour (MOBILE_PHONE_REDIRECT off): the public marketing site is
  // fully responsive, so a phone UA gets it directly — no /m scaffold redirect.
  test('phone UA: / serves the responsive public site, no /m redirect by default', async ({ browser }) => {
    const context = await browser.newContext({ userAgent: PHONE_UA });
    const page = await context.newPage();
    const response = await page.goto('/');
    expect(response.status()).toBe(200);
    await expect(page).not.toHaveURL(/\/m$/);
    await context.close();
  });

  test('phone UA: the isolated mobile SPA is still reachable directly at /m/app', async ({ browser }) => {
    const context = await browser.newContext({ userAgent: PHONE_UA });
    const page = await context.newPage();
    await page.goto('/m/app');
    // /m/app boots the SPA shell; the inner router lands an unauthed user on login.
    await expect(page.locator('#m-app')).toBeAttached({ timeout: 15000 });
    await context.close();
  });

  // The full phone auth journey (login → verify → dashboard) needs a rewrite
  // against the #448-redesigned Dashboard.vue (the old 'Signed in' / 'Dashboard'
  // / <pre> scaffold selectors no longer exist) and should navigate /m/app
  // directly now that the phone→/m redirect is disabled by default. Tracked as a
  // mobile-dashboard E2E follow-up.
  test.skip('phone UA full auth journey: login -> verify -> redesigned dashboard', async ({ browser }) => {
    const context = await browser.newContext({ userAgent: PHONE_UA });
    const page = await context.newPage();
    await page.goto('/m/app');

    await page.locator('input[type="email"]').fill('john@example.com');
    await page.locator('input[type="password"]').fill('password');
    await page.locator('button[type="submit"]').click();

    const code = fetchLatestVerificationCode('john@example.com');
    await page.locator('input[inputmode="numeric"]').fill(code);
    await page.locator('button[type="submit"]').click();

    await context.close();
  });
});
