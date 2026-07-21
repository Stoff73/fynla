import { test, expect, expectNoRuntimeErrors } from '../fixtures/app.js';

const capabilities = (premium) => ({
  dashboard: 'full',
  protection: 'full',
  income: 'full',
  liabilities: 'full',
  expenditure: 'full',
  expenditure_detailed: premium ? 'full' : 'none',
  tax_strategy: 'full',
  risk_profile: 'full',
  future_value_projections: 'full',
  savings_account: premium ? 'full' : 'limited',
  investment: premium ? 'full' : 'limited',
  pension_account: premium ? 'full' : 'limited',
  property: premium ? 'full' : 'limited',
  goals: premium ? 'full' : 'limited',
  life_events: premium ? 'full' : 'limited',
  chattels: 'full',
  estate: premium ? 'full' : 'teaser',
  joint_household_view: premium ? 'full' : 'none',
  letter_to_spouse: premium ? 'full' : 'none',
  retirement_decumulation: premium ? 'full' : 'none',
  what_if: premium ? 'full' : 'none',
  advisor_export: premium ? 'full' : 'none',
  statement_upload: premium ? 'full' : 'none',
  investment_cost_analysis: premium ? 'full' : 'none',
});

const changedPricingRows = {
  data: [
    {
      tier: 'free',
      display_name: 'Free',
      price_monthly_pence: 0,
      price_annual_pence: 0,
      capability_matrix: capabilities(false),
      count_caps: {
        savings_account: 4,
        investment: 3,
        pension_account: 6,
        property: 2,
        goal: 3,
        life_event: 2,
      },
      document_storage_gb: null,
      fyn_weekly_token_budget: 111111,
      snapshot_surfacing_window_days: 45,
    },
    {
      tier: 'premium',
      display_name: 'Premium',
      price_monthly_pence: 777,
      price_annual_pence: 7000,
      capability_matrix: capabilities(true),
      count_caps: {
        savings_account: null,
        investment: null,
        pension_account: null,
        property: null,
        goal: null,
        life_event: null,
      },
      document_storage_gb: 2.5,
      fyn_weekly_token_budget: 654321,
      snapshot_surfacing_window_days: null,
    },
  ],
};

async function routeChangedPricing(page) {
  await page.route('**/api/pricing-config', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(changedPricingRows),
    });
  });
}

async function seriousAccessibilityIssues(page) {
  return page.evaluate(() => {
    const issues = [];
    const text = (element) => (element.getAttribute('aria-label') || element.textContent || '').trim();
    const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
    const duplicates = ids.filter((id, index) => ids.indexOf(id) !== index);

    if (document.documentElement.lang !== 'en') issues.push('html language is missing');
    if (duplicates.length) issues.push(`duplicate ids: ${[...new Set(duplicates)].join(', ')}`);

    document.querySelectorAll('a, button').forEach((element) => {
      if (!text(element)) issues.push(`${element.tagName.toLowerCase()} has no accessible name`);
    });

    document.querySelectorAll('img').forEach((image) => {
      if (!image.hasAttribute('alt')) issues.push('image has no alt attribute');
    });

    document.querySelectorAll('table th').forEach((heading) => {
      if (!heading.hasAttribute('scope')) issues.push('table heading has no scope');
    });

    const headings = [...document.querySelectorAll('h1, h2, h3')].map((heading) => Number(heading.tagName.slice(1)));
    headings.forEach((level, index) => {
      if (index && level - headings[index - 1] > 1) issues.push('heading level is skipped');
    });

    return issues;
  });
}

test('pricing uses only the two canonical tier rows and updates every live value', async ({
  page,
  runtimeErrors,
}, testInfo) => {
  await page.addInitScript(() => {
    window.__pricingPerformance = { cls: 0, lcp: 0 };

    new PerformanceObserver((list) => {
      list.getEntries().forEach((entry) => {
        if (!entry.hadRecentInput) window.__pricingPerformance.cls += entry.value;
      });
    }).observe({ type: 'layout-shift', buffered: true });

    new PerformanceObserver((list) => {
      const entries = list.getEntries();
      const last = entries[entries.length - 1];
      if (last) window.__pricingPerformance.lcp = last.startTime;
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  });

  await routeChangedPricing(page);
  await page.goto('/pricing');

  await expect(page.locator('.plan-card')).toHaveCount(2);
  await expect(page.locator('#plan-free')).toBeVisible();
  await expect(page.locator('#plan-premium')).toBeVisible();
  await expect(page.locator('#plan-tier1, #plan-tier2, #plan-tier3')).toHaveCount(0);

  await expect(page.locator('#premium-price')).toHaveText('£70.00');
  await expect(page.locator('#premium-period')).toHaveText('/year');
  await expect(page.locator('#premium-equiv')).toHaveText('£5.83 per month equivalent');
  await expect(page.locator('#save-label')).toHaveText('Save 25%');
  await expect(page.locator('#cta-premium')).toHaveAttribute('href', '/register?plan=premium&billing=yearly');

  const performance = await page.evaluate(() => {
    const navigation = performance.getEntriesByType('navigation')[0];
    const firstContentfulPaint = performance.getEntriesByName('first-contentful-paint')[0];

    return {
      ttfb: Math.round(navigation.responseStart),
      fcp: Math.round(firstContentfulPaint.startTime),
      lcp: Math.round(window.__pricingPerformance.lcp),
      cls: Number(window.__pricingPerformance.cls.toFixed(4)),
    };
  });

  expect(performance.ttfb).toBeLessThan(500);
  expect(performance.fcp).toBeLessThan(2000);
  expect(performance.lcp).toBeGreaterThan(0);
  expect(performance.lcp).toBeLessThan(2500);
  expect(performance.cls).toBe(0);
  await testInfo.attach('pricing-performance.json', {
    body: JSON.stringify(performance),
    contentType: 'application/json',
  });

  const expectedCells = {
    '#comparison-free-cash-accounts': '4 cash or savings accounts',
    '#comparison-free-investments': '3 investments',
    '#comparison-free-property': '2 properties',
    '#comparison-free-pensions': '6 pensions',
    '#comparison-free-goals': '3 Goals',
    '#comparison-free-life-events': '2 Life Events',
    '#comparison-free-fyn': '111,111 Fyn tokens each week',
    '#comparison-free-balance-history': '45 days',
    '#comparison-premium-cash-accounts': 'Unlimited cash or savings accounts',
    '#comparison-premium-fyn': '654,321 Fyn tokens each week',
    '#comparison-premium-balance-history': 'Full available history',
    '#comparison-premium-document-storage': '2.5 GB document storage',
  };

  for (const [selector, value] of Object.entries(expectedCells)) {
    await expect(page.locator(selector)).toHaveText(value);
  }

  await page.getByRole('button', { name: 'Monthly', exact: true }).focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('#premium-price')).toHaveText('£7.77');
  await expect(page.locator('#premium-period')).toHaveText('/month');
  await expect(page.locator('#cta-premium')).toHaveAttribute('href', '/register?plan=premium&billing=monthly');

  await page.getByRole('button', { name: /Yearly/ }).focus();
  await page.keyboard.press('Space');
  await expect(page.locator('#premium-price')).toHaveText('£70.00');
  await expect(page.locator('#cta-premium')).toHaveAttribute('href', '/register?plan=premium&billing=yearly');

  expect(await seriousAccessibilityIssues(page)).toEqual([]);
  await page.screenshot({ path: testInfo.outputPath('pricing-1280px.png'), fullPage: true });
  expectNoRuntimeErrors(runtimeErrors);
});

test('pricing remains readable at an iPhone-width viewport without page overflow', async ({
  page,
  runtimeErrors,
}, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await routeChangedPricing(page);
  await page.goto('/pricing');

  await expect(page.locator('#plan-free')).toBeVisible();
  await expect(page.locator('#plan-premium')).toBeVisible();
  await expect(page.locator('.comparison-table')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
  expect(await seriousAccessibilityIssues(page)).toEqual([]);

  await page.screenshot({ path: testInfo.outputPath('pricing-390px.png'), fullPage: true });
  expectNoRuntimeErrors(runtimeErrors);
});
