# Save Tax Allowance Registration CTAs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore two mobile-accessible registration buttons around the Save Tax allowance results, both returning to the existing registration form.

**Architecture:** Update the shared server-rendered Save Tax result template so desktop and `/m` receive the same CTA placement. Reuse page-specific CSS for presentation and extend the existing public Playwright contract to verify count, order, target, scrolling, and responsive behaviour.

**Tech Stack:** PHP server-rendered HTML, CSS custom properties, Playwright JavaScript tests.

## Global Constraints

- The shared `/savetax/plan` page must serve both web and `/m`; do not create a mobile-only duplicate.
- Keep one registration form only.
- Use palette CSS variables only; use Spring for the forward registration action.
- Add no icon, emoji, hardcoded tax value, API change, or Fyn behaviour change.
- Use test-driven development: demonstrate the regression before changing the production page.
- Do not deploy to production.

---

### Task 1: Add the regression contract

**Files:**
- Modify: `tests/E2E/public/savetax-clarity.spec.js`
- Modify: `tests/Feature/Marketing/SaveTaxClaimClarityTest.php`

**Interfaces:**
- Consumes: the existing `/savetax/plan` HTML and `#register-form` target.
- Produces: a browser contract for `.sp4-combined__cta-btn` and its placement around `#allowances-render`.

- [x] **Step 1: Replace the old single-return-link assertion with the approved CTA contract**

```js
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
```

- [x] **Step 2: Run the focused test and verify the regression is red**

Run: `npx playwright test tests/E2E/public/savetax-clarity.spec.js --project=desktop-chromium --grep "action hierarchy"`

Expected: FAIL because `.sp4-combined__cta-btn` has count `0` instead of `2`.

- [x] **Step 3: Replace the stale PHP single-return-link assertion**

Replace the `href="#hero"` count with a contract requiring exactly two `href="#register-form"` links and no remaining `href="#hero"` link.

### Task 2: Restore both registration CTAs

**Files:**
- Modify: `public/pages/savetax-plan.php`
- Modify: `public/pages/css/savetax-plan-v4.css`

**Interfaces:**
- Consumes: `#register-form`, `#allowances-render`, public CSS palette variables, and global smooth scrolling.
- Produces: two `.sp4-combined__cta-btn` fragment links around the allowance grid.

- [x] **Step 1: Add the top and bottom CTA markup and remove the old return link**

Insert before `#allowances-render`:

```html
<div class="sp4-combined__cta sp4-combined__cta--top">
  <p class="sp4-combined__cta-text">Find out how</p>
  <a href="#register-form" class="sp4-combined__cta-btn">Register for free</a>
</div>
```

Replace the current `.sp4-combined__return` block after `#allowances-render` with:

```html
<div class="sp4-combined__cta">
  <p class="sp4-combined__cta-text">Find out how</p>
  <a href="#register-form" class="sp4-combined__cta-btn">Register for free</a>
</div>
```

Change the stylesheet cache buster from `savetax-plan-v4.css?v=10` to `savetax-plan-v4.css?v=11`.

- [x] **Step 2: Replace the obsolete return-link styles with the CTA styles**

```css
.sp4-register { scroll-margin-top: 5rem; }
.sp4-combined__cta { margin-top: 2.5rem; text-align: center; }
.sp4-combined__cta--top { margin-top: 1.5rem; margin-bottom: 1.5rem; }
.sp4-combined__cta-text {
  margin-bottom: 0.75rem;
  color: var(--horizon-500);
  font-size: 1rem;
  font-weight: 700;
}
.sp4-combined__cta-btn {
  display: inline-flex;
  min-height: 3rem;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1.75rem;
  border-radius: var(--radius-button);
  background: var(--spring-500);
  color: var(--white);
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.5;
  text-decoration: none;
  transition: background-color 0.2s ease;
}
.sp4-combined__cta-btn:hover { background: var(--spring-600); }
.sp4-combined__cta-btn:focus-visible {
  outline: 3px solid var(--horizon-500);
  outline-offset: 3px;
}
```

- [x] **Step 3: Run the focused test and verify it is green**

Run: `npx playwright test tests/E2E/public/savetax-clarity.spec.js --project=desktop-chromium --grep "action hierarchy"`

Expected: PASS.

### Task 3: Verify the complete shared-page behaviour

**Files:**
- Verify: `public/pages/savetax-plan.php`
- Verify: `public/pages/css/savetax-plan-v4.css`
- Verify: `tests/E2E/public/savetax-clarity.spec.js`

**Interfaces:**
- Consumes: the completed CTA markup and styles.
- Produces: browser evidence that the shared result page remains correct at supported web and `/m` widths.

- [x] **Step 1: Run the complete public Save Tax clarity test**

Run: `npx playwright test tests/E2E/public/savetax-clarity.spec.js --project=desktop-chromium`

Expected: all tests PASS with no runtime errors or responsive overflow failures.

- [x] **Step 2: Run matched public Save Tax route checks**

Run: `npx playwright test tests/E2E/public --project=desktop-chromium --grep "SaveTax|savetax"`

Expected: all matched Save Tax public-page tests PASS.

- [x] **Step 3: Verify `/m` through the outer mobile route in a real browser**

Open `/m?to=/savetax`, complete the Save Tax questions, reach the result page, and verify both registration links in the iframe return to the existing registration form. Repeat at a mobile viewport and confirm no clipping or horizontal overflow.

- [x] **Step 4: Review the final diff for scope and design compliance**

Run `git diff --check`, then inspect the diff for the three implementation files and these two documents.

Expected: no whitespace errors; the diff contains only the approved CTA restoration, regression coverage, and documentation.
