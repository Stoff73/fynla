import { randomBytes } from 'node:crypto';
import { devices } from '@playwright/test';
import { test, expect } from '../fixtures/app.js';
import { enterVerificationCode, fetchVerificationCode } from '../helpers/auth.js';

async function completeSaveTaxQuestions(surface) {
  await surface.getByRole('button', { name: 'Full time employed' }).click();
  await expect(surface.getByRole('heading', { name: 'What is your annual income?' })).toBeVisible();
  await surface.getByRole('button', { name: '£50,271 to £100,000' }).click();
  await expect(surface.getByRole('heading', { name: 'Do you have a spouse?' })).toBeVisible();
  await surface.getByRole('button', { name: 'No', exact: true }).click();
  await expect(surface.getByRole('heading', { name: 'Which of these do you have?' })).toBeVisible();
  await surface.getByRole('checkbox', { name: 'ISA' }).click();
  await surface.getByRole('button', { name: 'See your tax insights' }).click();
  await expect(surface.locator('#register-form')).toBeVisible();
}

async function submitCompactRegistration(page, surface, email, password) {
  await surface.locator('#reg-first-name').fill('Campaign');
  await surface.locator('#reg-last-name').fill('Tester');
  await surface.locator('#reg-email').fill(email);
  await surface.locator('#reg-password').fill(password);

  const registration = page.waitForResponse(
    (response) => response.url().includes('/api/auth/register')
      && response.request().method() === 'POST',
  );
  await surface.getByRole('button', { name: 'Register for free' }).click();
  const registrationResponse = await registration;
  expect(registrationResponse.ok()).toBeTruthy();

  await expect(surface.getByRole('heading', { name: 'Enter Verification Code' })).toBeVisible({ timeout: 30_000 });
  await expect(surface.locator('#first_name')).toHaveCount(0);

  return registrationResponse.request().postDataJSON();
}

async function openMobileFyn(surface) {
  const input = surface.getByRole('textbox', { name: 'Ask Fyn a question' });
  if (await input.isVisible().catch(() => false)) return input;

  const chat = surface.locator('section.md-fyn');
  if (await chat.evaluate((element) => element.classList.contains('is-open')).catch(() => false)) {
    await expect(input).toBeVisible({ timeout: 30_000 });
    return input;
  }

  try {
    await surface.getByRole('button', { name: 'Chat with Fyn' }).click({ timeout: 5_000 });
  } catch (error) {
    if (!await input.isVisible().catch(() => false)) throw error;
  }
  await expect(input).toBeVisible();
  return input;
}

async function sendMobileFyn(page, surface, text) {
  const input = await openMobileFyn(surface);
  await input.fill(text);
  const responsePromise = page.waitForResponse((response) => (
    /\/api\/ai-chat\/conversations\/\d+\/messages$/.test(new URL(response.url()).pathname)
      && response.request().method() === 'POST'
  ));
  await surface.getByRole('button', { name: 'Send to Fyn' }).click();
  const response = await responsePromise;
  expect(response.ok()).toBeTruthy();
  await expect(surface.getByRole('button', { name: 'Send to Fyn' })).toBeEnabled({ timeout: 30_000 });
  const innerFrame = page.frames().find((frame) => frame.parentFrame());
  await expect.poll(async () => innerFrame.evaluate((message) => {
    const match = (window.__fynlaCapturedStreams || [])
      .findLast((entry) => entry.message === message && typeof entry.response === 'string');
    return match?.response || null;
  }, text), { timeout: 30_000 }).not.toBeNull();
  return innerFrame.evaluate((message) => {
    const match = (window.__fynlaCapturedStreams || [])
      .findLast((entry) => entry.message === message && typeof entry.response === 'string');
    return match.response;
  }, text);
}

async function captureMobileStreams(page) {
  const innerFrame = page.frames().find((frame) => frame.parentFrame());
  await innerFrame.evaluate(() => {
    if (window.__fynlaStreamCaptureInstalled) return;
    window.__fynlaStreamCaptureInstalled = true;
    window.__fynlaCapturedStreams = [];
    const realFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
      const response = await realFetch(...args);
      const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';
      const options = args[1] || {};
      if (/\/api\/ai-chat\/conversations\/\d+\/messages$/.test(new URL(url, window.location.origin).pathname)
          && options.method === 'POST') {
        let message = null;
        try { message = JSON.parse(options.body || '{}').message || null; } catch { /* invalid request body */ }
        const entry = { message, response: null };
        window.__fynlaCapturedStreams.push(entry);
        response.clone().text().then((body) => { entry.response = body; });
      }
      return response;
    };
  });
}

async function chooseMobileBubble(page, surface, label) {
  await openMobileFyn(surface);
  await expect(surface.getByRole('button', { name: 'Send to Fyn' })).toBeEnabled({ timeout: 30_000 });
  const bubble = surface.locator('.md-fyn__bubble').filter({ hasText: label }).last();
  await expect(bubble).toBeVisible({ timeout: 30_000 });
  const responsePromise = page.waitForResponse((response) => (
    /\/api\/ai-chat\/conversations\/\d+\/(?:messages|action)$/.test(new URL(response.url()).pathname)
      && response.request().method() === 'POST'
  ));
  await bubble.click();
  const response = await responsePromise;
  expect(response.ok()).toBeTruthy();
  await expect(surface.getByRole('button', { name: 'Send to Fyn' })).toBeEnabled({ timeout: 30_000 });
}

async function openMobileScenario(browser, request, scenario) {
  const email = `savetax.mobile.${scenario}.${Date.now()}@example.com`;
  const setup = await request.post('/__e2e/onboarding-scenario-user', {
    data: { email, scenario },
  });
  expect(setup.ok()).toBeTruthy();
  const { token } = await setup.json();
  const context = await browser.newContext({ ...devices['Pixel 7'] });
  await context.addInitScript((authToken) => {
    window.localStorage.setItem('cookie_consent', 'accepted');
    window.localStorage.setItem('m_scaffold_token', authToken);
  }, token);
  const page = await context.newPage();
  await page.goto('/m');
  await page.locator('iframe').evaluate((iframe) => { iframe.src = '/m/app'; });
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app(?:\/|$)/);
  const surface = page.frameLocator('iframe');
  await expect(surface.getByRole('main')).toBeVisible();

  return { context, page, surface, email };
}

test('fresh Save Tax registration survives unavailable answer storage and opens the campaign dashboard', async ({
  page,
  request,
  runtimeErrors,
}) => {
  const email = `savetax.${Date.now()}@example.com`;
  const password = `${randomBytes(12).toString('hex')}aA1!`;

  await page.addInitScript(() => {
    const getItem = Storage.prototype.getItem;
    const setItem = Storage.prototype.setItem;
    Storage.prototype.getItem = function (key) {
      return key === 'savetax_answers' ? null : getItem.call(this, key);
    };
    Storage.prototype.setItem = function (key, value) {
      if (key === 'savetax_answers') throw new DOMException('Storage unavailable', 'QuotaExceededError');
      return setItem.call(this, key, value);
    };
  });
  await page.goto('/savetax');
  await completeSaveTaxQuestions(page);
  const registrationBody = await submitCompactRegistration(page, page, email, password);

  await expect(page).toHaveURL(/\/register\?from=savetax$/);
  expect(page.url()).not.toContain(email);
  expect(registrationBody.funnel_answers).toMatchObject({
    campaign: 'savetax',
    employment: 'full-time',
    income: '50271_100000',
    spouse: 'no',
    assets: ['isa'],
  });
  const code = await fetchVerificationCode(request, email);
  const campaignStart = page.waitForRequest((outgoing) => (
    outgoing.url().includes('/api/ai-chat/onboarding/start')
      && outgoing.method() === 'POST'
  ));
  await enterVerificationCode(page, code);

  const startRequest = await campaignStart;
  expect(startRequest.postDataJSON()).toEqual({ from: 'savetax' });
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole('heading', { name: 'Fyn', exact: true }).first()).toBeVisible();
  await expect(page.getByRole('main')).toBeVisible();

  const draft = page.getByPlaceholder('Ask Fyn...');
  await draft.fill('A partial Save Tax answer');
  await page.getByRole('button', { name: 'Collapse', exact: true }).click();
  await expect(draft).toBeHidden();
  const availableScroll = await page.evaluate(() => (
    document.scrollingElement.scrollHeight - document.scrollingElement.clientHeight
  ));
  expect(availableScroll).toBeGreaterThan(0);
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
  expect(await page.evaluate(() => document.scrollingElement.scrollTop)).toBeGreaterThan(0);
  expect(await page.evaluate(() => getComputedStyle(document.body).overflow)).not.toBe('hidden');
  await page.getByTitle('Expand Fyn chat').click();
  const reopenedDraft = page.getByPlaceholder('Ask Fyn...');
  await expect(reopenedDraft).toHaveValue('A partial Save Tax answer');
  await expect(reopenedDraft).toBeFocused();

  await page.goto('/savetax');
  await expect(page).toHaveURL(/\/dashboard(?:[/?#]|$)/);
  await expect(page.locator('#register-form')).toHaveCount(0);
  expect(runtimeErrors).toEqual([]);
});

test('phone Save Tax registration hands the verified token into the /m app', async ({ browser, request }) => {
  const context = await browser.newContext({ ...devices['Pixel 7'] });
  await context.addInitScript(() => window.localStorage.setItem('cookie_consent', 'accepted'));
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });

  const email = `savetax.mobile.${Date.now()}@example.com`;
  const password = `${randomBytes(12).toString('hex')}aA1!`;

  await page.goto('/savetax');
  await expect(page).toHaveURL(/\/m\?to=%2Fsavetax/);
  const surface = page.frameLocator('iframe');
  await completeSaveTaxQuestions(surface);
  await submitCompactRegistration(page, surface, email, password);

  const code = await fetchVerificationCode(request, email);
  await enterVerificationCode(surface, code);

  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app(?:\/|$)/);
  await expect(surface.getByRole('main')).toBeVisible();

  const draft = surface.getByRole('textbox', { name: 'Ask Fyn a question' });
  await draft.fill('A partial mobile Save Tax answer');
  await surface.getByRole('button', { name: 'Close Fyn chat' }).click();
  await expect(draft).toBeHidden();
  expect(await surface.locator('body').evaluate((body) => getComputedStyle(body).overflow)).not.toBe('hidden');
  const scrollArea = surface.locator('.md-main');
  const availableScroll = await scrollArea.evaluate((element) => element.scrollHeight - element.clientHeight);
  expect(availableScroll).toBeGreaterThan(0);
  await scrollArea.evaluate((element) => element.scrollTo(0, element.scrollHeight));
  expect(await scrollArea.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);
  await surface.getByRole('button', { name: 'Chat with Fyn' }).click();
  const reopenedDraft = surface.getByRole('textbox', { name: 'Ask Fyn a question' });
  await expect(reopenedDraft).toHaveValue('A partial mobile Save Tax answer');
  await expect(reopenedDraft).toBeFocused();
  await expect(surface.getByRole('button', { name: 'Send to Fyn' })).toBeEnabled();

  await page.goto('/savetax');
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app(?:\/|$)/);
  await expect(surface.locator('#register-form')).toHaveCount(0);
  expect(errors).toEqual([]);

  await context.close();
});

test('phone existing-account response comes from the real registration boundary and keeps sign-in operable', async ({
  browser,
  request,
}) => {
  const email = `savetax.mobile.existing.${Date.now()}@example.com`;
  const setup = await request.post('/__e2e/active-user', {
    data: { email, password: 'Password1!' },
  });
  expect(setup.ok()).toBeTruthy();

  const context = await browser.newContext({ ...devices['Pixel 7'] });
  await context.addInitScript(() => window.localStorage.setItem('cookie_consent', 'accepted'));
  const page = await context.newPage();
  await page.goto('/savetax');
  const surface = page.frameLocator('iframe');
  await completeSaveTaxQuestions(surface);
  await surface.locator('#reg-first-name').fill('Existing');
  await surface.locator('#reg-last-name').fill('Account');
  await surface.locator('#reg-email').fill(email);
  await surface.locator('#reg-password').fill('Password1!');

  const registration = page.waitForResponse(
    (response) => response.url().includes('/api/auth/register')
      && response.request().method() === 'POST',
  );
  await surface.getByRole('button', { name: 'Register for free' }).click();
  const response = await registration;
  expect(response.status()).toBe(422);
  expect(await response.json()).toMatchObject({ email_exists: true });

  const link = surface.getByRole('link', { name: 'Sign in to your account' });
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute('href', '/login?from=savetax');
  await surface.locator('#reg-password').focus();
  await page.keyboard.press('Tab');
  await expect(link).toBeFocused();

  await context.close();
});

test('phone Save Tax spouse skip is visible, keyboard operable and creates no spouse', async ({ browser, request }) => {
  const { context, page, surface, email } = await openMobileScenario(browser, request, 'spouse_skip');
  await openMobileFyn(surface);
  await chooseMobileBubble(page, surface, 'Continue');
  const skip = surface.locator('.md-fyn__bubble').filter({ hasText: 'Skip this for now' }).last();
  await expect(skip).toBeVisible({ timeout: 30_000 });
  await skip.focus();
  await expect(skip).toBeFocused();
  const action = page.waitForResponse((response) => (
    /\/api\/ai-chat\/conversations\/\d+\/action$/.test(new URL(response.url()).pathname)
      && response.request().method() === 'POST'
  ));
  await skip.press('Enter');
  expect((await action).ok()).toBeTruthy();

  await expect.poll(async () => {
    const response = await request.get(`/__e2e/campaign-state?email=${encodeURIComponent(email)}`);
    return response.json();
  }).toMatchObject({
    onboarding_step: 'base_dependants',
    spouse_id: null,
    family_members: [],
  });

  await context.close();
});

test('phone Save Tax dependant capture rejects an age and stores the exact stated date', async ({ browser, request }) => {
  test.setTimeout(120_000);
  const { context, page, surface, email } = await openMobileScenario(browser, request, 'dependant_exact');
  const messages = surface.locator('.md-fyn__messages');
  await openMobileFyn(surface);
  await chooseMobileBubble(page, surface, 'Continue');
  await chooseMobileBubble(page, surface, 'Yes');
  await expect(messages).toContainText(/dates? of birth|born/i, { timeout: 30_000 });
  await captureMobileStreams(page);
  await sendMobileFyn(page, surface, 'Sam is 8 years old.');

  let stateResponse = await request.get(`/__e2e/campaign-state?email=${encodeURIComponent(email)}`);
  expect((await stateResponse.json()).family_members).toEqual([]);
  await expect(messages).toContainText(/exact date|date of birth|born/i);

  await sendMobileFyn(page, surface, 'Sam was born on 14 September 2017.');
  await expect.poll(async () => {
    stateResponse = await request.get(`/__e2e/campaign-state?email=${encodeURIComponent(email)}`);
    return (await stateResponse.json()).family_members;
  }, { timeout: 30_000 }).toEqual([
    expect.objectContaining({
      first_name: 'Sam',
      date_of_birth: '2017-09-14',
    }),
  ]);

  await context.close();
});

test('restorable Save Tax registration keeps the campaign destination without repeating account details', async ({
  page,
  request,
}) => {
  const email = `savetax.restore.${Date.now()}@example.com`;
  const password = `${randomBytes(12).toString('hex')}aA1!`;
  const setup = await request.post('/__e2e/restorable-user', {
    data: { email, password },
  });
  expect(setup.ok()).toBeTruthy();

  await page.goto('/savetax');
  await completeSaveTaxQuestions(page);
  await page.locator('#reg-first-name').fill('Returner');
  await page.locator('#reg-last-name').fill('Campaign');
  await page.locator('#reg-email').fill(email);
  await page.locator('#reg-password').fill(password);

  const registration = page.waitForResponse(
    (response) => response.url().includes('/api/auth/register')
      && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Register for free' }).click();
  expect((await registration).ok()).toBeTruthy();

  await expect(page).toHaveURL(/\/register\?from=savetax&handoff=[^&]+$/);
  await expect(page.getByRole('heading', { name: 'Welcome back, there' })).toBeVisible();
  await expect(page.locator('#first_name')).toHaveCount(0);
  await page.locator('input[type="password"]').fill(password);

  const restored = page.waitForResponse(
    (response) => response.url().endsWith('/api/auth/restore')
      && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Restore my account' }).click();
  const restorePayload = await (await restored).json();
  expect(restorePayload.redirect_to).toBe('/dashboard?openFyn=journey&from=savetax');
  await expect(page).toHaveURL(/\/dashboard(?:[/?#]|$)/);
  expect(page.url()).not.toContain('openPricing');
});

test('phone restoration enters the /m app with the Save Tax journey intact', async ({ browser, request }) => {
  const context = await browser.newContext({ ...devices['Pixel 7'] });
  await context.addInitScript(() => window.localStorage.setItem('cookie_consent', 'accepted'));
  const page = await context.newPage();
  const email = `savetax.mobile.restore.${Date.now()}@example.com`;
  const password = `${randomBytes(12).toString('hex')}aA1!`;
  const setup = await request.post('/__e2e/restorable-user', {
    data: { email, password },
  });
  expect(setup.ok()).toBeTruthy();

  await page.goto('/savetax');
  await expect(page).toHaveURL(/\/m\?to=%2Fsavetax/);
  const surface = page.frameLocator('iframe');
  await completeSaveTaxQuestions(surface);
  await surface.locator('#reg-first-name').fill('Returner');
  await surface.locator('#reg-last-name').fill('Campaign');
  await surface.locator('#reg-email').fill(email);
  await surface.locator('#reg-password').fill(password);

  const registration = page.waitForResponse(
    (response) => response.url().includes('/api/auth/register')
      && response.request().method() === 'POST',
  );
  await surface.getByRole('button', { name: 'Register for free' }).click();
  expect((await registration).ok()).toBeTruthy();
  await expect(surface.getByRole('heading', { name: 'Welcome back, there' })).toBeVisible();
  await expect(surface.locator('#first_name')).toHaveCount(0);
  await surface.locator('input[type="password"]').fill(password);

  const restored = page.waitForResponse(
    (response) => response.url().endsWith('/api/auth/restore')
      && response.request().method() === 'POST',
  );
  await surface.getByRole('button', { name: 'Restore my account' }).click();
  const restorePayload = await (await restored).json();
  expect(restorePayload.redirect_to).toBe('/dashboard?openFyn=journey&from=savetax');
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app(?:\/|$)/);
  expect(page.frames().some((frame) => frame.url().includes('openPricing'))).toBeFalsy();

  await context.close();
});

test('phone Save Tax journey captures an explicit ISA and £10,000 expenditure before creating the strategy', async ({
  browser,
  request,
}) => {
  test.setTimeout(300_000);
  expect((await request.post('/__e2e/registration-throttle/reset')).ok()).toBeTruthy();
  const context = await browser.newContext({ ...devices['Pixel 7'] });
  await context.addInitScript(() => window.localStorage.setItem('cookie_consent', 'accepted'));
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });

  const email = `savetax.mobile.full.${Date.now()}@example.com`;
  const password = `${randomBytes(12).toString('hex')}aA1!`;
  await page.goto('/savetax');
  const surface = page.frameLocator('iframe');
  await completeSaveTaxQuestions(surface);
  await submitCompactRegistration(page, surface, email, password);
  await enterVerificationCode(surface, await fetchVerificationCode(request, email));
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app(?:\/|$)/);

  const messages = surface.locator('.md-fyn__messages');
  await openMobileFyn(surface);
  await expect(messages).toContainText(/gross annual income|annual income/i, { timeout: 30_000 });
  await captureMobileStreams(page);
  await sendMobileFyn(page, surface, 'My gross annual income is £75,000.');
  await chooseMobileBubble(page, surface, "No, that's everything");
  await chooseMobileBubble(page, surface, 'Okay');
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app\/income/);
  await chooseMobileBubble(page, surface, "Yes, that's right");

  await openMobileFyn(surface);
  await expect(messages).toContainText(/ISAs/i, { timeout: 30_000 });
  const isaStream = await sendMobileFyn(
    page,
    surface,
    'My Barclays account is a Cash ISA with £20,000. I added £5,000 this tax year and own it individually.',
  );
  expect((isaStream.match(/"type":"capture_complete"/g) || [])).toHaveLength(1);
  await expect(messages).toContainText(/Recorded|Saved/i);
  await chooseMobileBubble(page, surface, 'Okay');
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app\/savings/);
  await chooseMobileBubble(page, surface, "Yes, that's right");

  await openMobileFyn(surface);
  await expect(messages).toContainText(/date of birth/i, { timeout: 30_000 });
  await sendMobileFyn(page, surface, '14 September 1985');
  await openMobileFyn(surface);
  await expect(messages).toContainText(/monthly spending|monthly expenditure|spend each month|goes out each month/i, { timeout: 30_000 });

  const expenditureStream = await sendMobileFyn(page, surface, 'My total monthly expenditure is £10,000.');
  expect((expenditureStream.match(/"type":"capture_complete"/g) || [])).toHaveLength(1);
  expect(expenditureStream).toContain('users.monthly_expenditure');
  expect(expenditureStream).toContain('expenditure_profiles.total_monthly_expenditure');
  await expect(messages).toContainText('Recorded monthly spending of £10,000.');
  await chooseMobileBubble(page, surface, 'Okay');
  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url())
    .toMatch(/\/m\/app\/expenditure/);
  await expect(surface.getByRole('main')).toContainText('£10,000');
  const expenditureFrame = page.frames().find((frame) => frame.parentFrame());
  await expenditureFrame.goto(expenditureFrame.url());
  await expect(surface.getByRole('main')).toContainText('£10,000');
  await chooseMobileBubble(page, surface, "Yes, that's right");

  await openMobileFyn(surface);
  const viewStrategy = surface.locator('.md-fyn__bubble')
    .filter({ hasText: 'Take me to my tax strategy' })
    .last();
  await expect(viewStrategy).toBeVisible({ timeout: 30_000 });
  await viewStrategy.click();

  await expect.poll(() => page.frames().find((frame) => frame.parentFrame())?.url(), { timeout: 60_000 })
    .toMatch(/\/m\/app\/tax-strategy/);
  await expect(surface.getByRole('main')).toContainText(/Tax Strategy|tax strategy/i);

  const authToken = await page.evaluate(() => sessionStorage.getItem('auth_token'));
  expect(authToken).toBeTruthy();
  const desktopContext = await browser.newContext();
  await desktopContext.addInitScript((token) => {
    sessionStorage.setItem('auth_token', token);
    localStorage.setItem('cookie_consent', 'accepted');
  }, authToken);
  const desktopPage = await desktopContext.newPage();
  await desktopPage.goto('/valuable-info?section=expenditure');
  await expect(desktopPage.getByRole('heading', { name: 'Household Expenditure' })).toBeVisible();
  await expect(desktopPage.getByText('£10,000', { exact: true }).first()).toBeVisible();
  await desktopPage.reload();
  await expect(desktopPage.getByText('£10,000', { exact: true }).first()).toBeVisible();
  await desktopContext.close();

  const stateResponse = await request.get(`/__e2e/campaign-state?email=${encodeURIComponent(email)}`);
  expect(stateResponse.ok()).toBeTruthy();
  const state = await stateResponse.json();
  expect(state).toMatchObject({
    onboarding_completed: true,
    date_of_birth: '1985-09-14',
    marital_status: 'single',
    spouse_id: null,
    monthly_expenditure: 10000,
    profile_monthly_expenditure: 10000,
  });
  expect(state.savings).toEqual(expect.arrayContaining([
    expect.objectContaining({
      account_type: 'cash_isa',
      is_isa: true,
      ownership_type: 'individual',
      current_balance: '20000.00',
    }),
  ]));
  expect(errors).toEqual([]);

  await context.close();
});
