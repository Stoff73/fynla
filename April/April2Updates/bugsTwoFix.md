# Bug Report Part 2 — Fix Notes

**Date:** 2 April 2026
**Reporter:** Brett Isenberg (user 491)
**Branch:** bugs
**Status:** All 8 bugs fixed and browser tested on localhost

---

## Bug 1: Add Account modal opens below the fold

**Page:** /net-worth/cash
**Problem:** SaveAccountModal rendered inside CashOverview's DOM tree. A parent with `overflow: hidden` trapped the `fixed` positioning, causing the modal to appear at the bottom of the page content instead of as a centred overlay.
**Fix:** Wrapped SaveAccountModal in `<Teleport to="body">` in CashOverview.vue so it renders at the document root.
**Verified:** Clicked Add Account on cash page — modal renders as centred overlay at `top:0`, fully visible.

## Bug 2: Institution name truncated, no tooltip

**Page:** /net-worth/cash
**Problem:** Long institution names like "Marcus (Goldman Sachs)" get truncated by CSS with no way to see the full name.
**Fix:** Added `:title` attribute to all 4 account-name spans (Current, Savings, Cash ISA, NS&I) in CashOverview.vue.
**Verified:** Inspected DOM — `title="Nationwide"` present on account names. Tooltip shows on hover.

## Bug 3: No Premium Bonds £50,000 maximum validation

**Page:** /net-worth/cash (Add Account form)
**Problem:** Users could enter any balance for Premium Bonds — no validation against the NS&I £50,000 per person limit.
**Fix:** Added validation in `handleSubmit()` of SaveAccountModal.vue: if `account_type === 'premium_bonds'` and balance > 50,000, show warning and block submission. Also added a separate error display div outside the ISA section so the warning is visible for non-ISA account types.
**Verified:** Selected Premium Bonds, entered £75,000, clicked submit — red warning shown: "Premium Bonds have a maximum holding of £50,000 per person." Submission blocked.

## Bug 4: Liabilities mortgage cards don't show joint ownership

**Page:** /net-worth/liabilities
**Problem:** Mortgage cards from the property module showed 100% of the mortgage balance with no indication of joint ownership. Users couldn't reconcile the amounts.
**Fix:**
- **Backend:** Added `ownership_type` and `ownership_percentage` from the property model to the mortgage liability data in EstateController.php (line 60-71).
- **Frontend:** Added `isJoint` and `userShare` computed properties to LiabilityCard.vue. Joint mortgages now show "Ownership: Joint (X% yours)", "Total Balance", and "Your Share".
**Verified:** "Mortgage - 19 Worth Court" shows "Ownership: Joint (50.00% yours)", "Total Balance: £3,500", "Your Share: £1,750". Individual mortgages show just "Balance Owed" as before.

## Bug 5: Mortgage card not clickable to property page

**Page:** /net-worth/liabilities
**Problem:** Clicking a mortgage card did nothing. The "Edit in Property" link existed at the bottom but was easy to miss. The card body had `cursor: default` for external sources.
**Fix:** Changed `handleClick()` in LiabilityCard.vue to navigate to the source route for external liabilities (via `$router.push`). Changed cursor from `default` to `pointer` for external cards. Added hover border effect.
**Verified:** Clicked mortgage card → navigated to /net-worth/property.

## Bug 6: Dashboard pie chart not clickable

**Page:** /dashboard
**Problem:** The net worth donut chart segments had hover tooltips but no click-through to the relevant module pages.
**Fix:**
- Added route mapping (`ASSET_ROUTES`, `LIABILITY_ROUTES`) to `netWorthChartCategories` computed in Dashboard.vue.
- Added `route` field to `netWorthDonutSegments`.
- Added `@click="seg.route && $router.push(seg.route)"` on each SVG circle element.
**Verified:** Clicked segment 0 → /net-worth/retirement. Clicked segment 2 → /net-worth/investments. Different segments navigate to their correct module pages.

## Bug 7+8: Investment detail page blank / no holdings on projected value

**Page:** /net-worth/investment-detail
**Problem:** Navigating directly to `/net-worth/investment-detail` (e.g. from a chart link) crashed with "Cannot read properties of undefined (reading 'account_type')". The `InvestmentProjections.vue` component requires an `account` prop that's only available when rendered inline from `InvestmentList.vue`, not when loaded as a direct route.
**Fix:**
- Changed `account` prop from `required: true` to `default: null`.
- Added `created()` hook: if no account, redirect to `/net-worth/investments`.
- Added `v-if="account"` guard on the template root to prevent rendering with null account.
**Verified:** Navigated to `/net-worth/investment-detail` → automatically redirected to `/net-worth/investments`. 0 console errors.
