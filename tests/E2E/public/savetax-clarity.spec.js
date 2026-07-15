import { test, expect, expectNoRuntimeErrors } from '../fixtures/app.js';

const pricingRows = (monthly, yearly) => ({
  data: [
    { tier: 'free', price_monthly_pence: 0, price_annual_pence: 0, capability_matrix: {}, count_caps: {} },
    { tier: 'tier1', price_monthly_pence: monthly, price_annual_pence: yearly, capability_matrix: {}, count_caps: {} },
  ],
});

test('SaveTax claim, allowance states and action hierarchy remain clear at supported widths', async ({
  page,
  runtimeErrors,
}, testInfo) => {
  await page.addInitScript(() => {
    window.__fynlaCls = 0;
    new PerformanceObserver((list) => {
      list.getEntries().forEach((entry) => {
        if (!entry.hadRecentInput) window.__fynlaCls += entry.value;
      });
    }).observe({ type: 'layout-shift', buffered: true });
  });

  await page.goto('/savetax/plan?income=50271_100000&spouse=no&assets=savings');

  for (const width of [320, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    const claim = (await page.locator('#savings-claim').textContent()).replace(/\s+/g, ' ').trim();
    expect(claim).toMatch(/^An average estimated saving of up to £[\d,]+ each year$/);
    await expect(page.getByText(
      'This is an average based on your answers — not your personal potential savings per year. Register for free and get your personalised tax strategy.',
      { exact: true },
    )).toBeVisible();
    const taxYear = (await page.locator('#tax-year').textContent()).trim();
    expect(taxYear).toMatch(/^\d{4}\/\d{2}$/);
    await expect(page.getByText(`Tax year ${taxYear}`, { exact: true })).toBeVisible();
    const typography = await page.evaluate(() => ({
      claimFamily: getComputedStyle(document.querySelector('#savings-claim')).fontFamily,
      claimWeight: Number(getComputedStyle(document.querySelector('#savings-claim')).fontWeight),
      figureWeight: Number(getComputedStyle(document.querySelector('#savings-figure')).fontWeight),
      taxYearFamily: getComputedStyle(document.querySelector('#tax-year')).fontFamily,
    }));
    expect(typography.claimFamily).toMatch(/Segoe UI|Inter/);
    expect(typography.taxYearFamily).toMatch(/Segoe UI|Inter/);
    expect(typography.claimWeight).toBeGreaterThanOrEqual(700);
    expect(typography.figureWeight).toBeGreaterThanOrEqual(900);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath(`savetax-${width}px.png`), fullPage: true });
  }

  const allowanceCards = page.locator('.sp4-alw');
  await expect(allowanceCards).not.toHaveCount(0);
  await expect(allowanceCards.locator('.sp4-alw__state')).toHaveCount(await allowanceCards.count());
  await expect(page.getByText('Used automatically', { exact: true })).toBeVisible();
  await expect(page.getByText('Available to you', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Not applicable', { exact: true }).first()).toBeVisible();
  await expect(page.getByText(/open or contribute to a pension/i)).toBeVisible();
  const registrationLinks = page.locator('#allowances .sp4-combined__cta-btn');
  await expect(registrationLinks).toHaveCount(2);
  await expect(registrationLinks.nth(0)).toHaveAttribute('href', '#register-form');
  await expect(registrationLinks.nth(1)).toHaveAttribute('href', '#register-form');
  await expect(page.getByRole('link', { name: 'Register for free' })).toHaveCount(2);
  await expect(page.getByRole('button', { name: 'Register for free' })).toHaveCount(1);

  const allowanceActionOrder = await page.locator('#allowances').evaluate((section) => (
    [...section.querySelectorAll('.sp4-combined__cta-btn, #allowances-render')]
      .map((element) => element.id === 'allowances-render' ? 'allowances' : 'register')
  ));
  expect(allowanceActionOrder).toEqual(['register', 'allowances', 'register']);

  for (let index = 0; index < 2; index += 1) {
    await page.evaluate(() => history.replaceState(null, '', `${location.pathname}${location.search}`));
    await registrationLinks.nth(index).scrollIntoViewIfNeeded();
    await registrationLinks.nth(index).click();
    await expect(page).toHaveURL(/#register-form$/);
    await expect.poll(() => page.locator('#register-form').evaluate((form) => {
      const bounds = form.getBoundingClientRect();
      return bounds.top >= 0 && bounds.top < window.innerHeight;
    })).toBe(true);
  }

  expect(await page.evaluate(() => window.__fynlaCls)).toBeLessThanOrEqual(0.01);
  expectNoRuntimeErrors(runtimeErrors);
});

test('fresh campaign URL answers override stale browser storage before registration', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('savetax_answers', JSON.stringify({
      employment: 'retired',
      income: 'over_125140',
      spouse: 'yes',
      spouseIncome: 'zero',
      assets: ['property'],
    }));
    localStorage.setItem('pensioncheck_answers', JSON.stringify({
      employment: 'retired',
      income: 'over_125140',
      age: '60_plus',
      pensions: ['none'],
      pot: 'none',
      spouse: 'yes',
    }));
  });

  const submitted = [];
  await page.route('**/api/auth/register', async (route) => {
    submitted.push(route.request().postDataJSON());
    await route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ email_exists: true }),
    });
  });

  const scenarios = [
    {
      url: '/savetax/plan?employment=full-time&income=upto_50270&spouse=no&assets=bank,isa',
      expected: {
        campaign: 'savetax',
        employment: 'full-time',
        income: 'upto_50270',
        spouse: 'no',
        spouseIncome: null,
        assets: ['bank', 'isa'],
      },
    },
    {
      url: '/pensioncheck/plan?employment=self-employed&income=50271_100000&age=40s&pensions=workplace,personal_sipp&pot=100k_250k&spouse=no',
      expected: {
        campaign: 'pensioncheck',
        employment: 'self-employed',
        income: '50271_100000',
        age: '40s',
        pensions: ['workplace', 'personal_sipp'],
        pot: '100k_250k',
        spouse: 'no',
      },
    },
  ];

  for (const [index, scenario] of scenarios.entries()) {
    await page.goto(scenario.url);
    await page.locator('#reg-first-name').fill('Fresh');
    await page.locator('#reg-last-name').fill('Answers');
    await page.locator('#reg-email').fill(`fresh-${index}@example.com`);
    await page.locator('#reg-password').fill('Password1!');
    await page.locator('#register-form button[type="submit"]').click();
    await expect.poll(() => submitted.length).toBe(index + 1);
    expect(submitted[index].funnel_answers).toEqual(scenario.expected);
  }
});

test('existing-account response presents a real keyboard-operable sign-in link', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const email = `savetax.existing.${Date.now()}@example.com`;
  const setup = await request.post('/__e2e/active-user', {
    data: { email, password: 'Password1!' },
  });
  expect(setup.ok()).toBeTruthy();
  await page.goto('/savetax/plan?income=upto_50270&spouse=no&assets=bank');
  await page.locator('#reg-first-name').fill('Existing');
  await page.locator('#reg-last-name').fill('Account');
  await page.locator('#reg-email').fill(email);
  await page.locator('#reg-password').fill('Password1!');
  const registration = page.waitForResponse(
    (response) => response.url().includes('/api/auth/register')
      && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Register for free' }).click();
  const response = await registration;
  expect(response.status()).toBe(422);
  expect(await response.json()).toMatchObject({ email_exists: true });

  const link = page.getByRole('link', { name: 'Sign in to your account' });
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute('href', '/login?from=savetax');
  await page.locator('#reg-password').focus();
  await page.keyboard.press('Tab');
  await expect(link).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/login\?from=savetax$/);
  expect(runtimeErrors.filter((error) => !/422 \(Unprocessable (?:Entity|Content)\)/.test(error))).toEqual([]);
});

test('pricing shows only the saving percentage supported by live configuration', async ({ page }) => {
  const cases = [
    { monthly: 499, yearly: 4990, expected: 'Save 17%' },
    { monthly: 1000, yearly: 9000, expected: 'Save 25%' },
    { monthly: 499, yearly: 5988, expected: null },
    { monthly: 0, yearly: 1000, expected: null },
  ];

  for (const scenario of cases) {
    await page.unroute('**/api/pricing-config');
    await page.route('**/api/pricing-config', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(pricingRows(scenario.monthly, scenario.yearly)),
      });
    });
    await page.goto('/pricing');

    const label = page.locator('#save-label');
    if (scenario.expected) {
      await expect(label).toBeVisible();
      await expect(label).toHaveText(scenario.expected);
    } else {
      await expect(label).toBeHidden();
      await expect(label).toHaveText('');
      if (scenario.yearly === 0) {
        await expect(page.locator('#btn-yearly')).toBeDisabled();
        await expect(page.locator('#cta-tier1')).toHaveAttribute('href', /billing=monthly/);
      }
    }
  }
});
