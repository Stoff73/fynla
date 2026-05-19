# Frontend Code Review — `/Users/CSJ/Desktop/fynla/resources/js/`

**Date:** 2026-05-12 (amended 2026-05-12 session 3)
**Scope:** 733 Vue components, 134 JS files, 35 Vuex modules, 45 services. Reviewed against CLAUDE.md Rules #4, #6, #9, #10, #11, #12, #13, #14, #16.

**Amendment 2026-05-12 (CSJ):** Rule #16 reinterpreted — existing site icons are canonical and grandfathered. Critical-tag count adjusted: original 8 critical → **1 critical (C-1, Rule #14) + 1 critical hex-in-attribute (C-2 Rule #12 portion) + 6 grandfathered (C-3 through C-8) + 2 open (L-5 emoji, M-11 Unicode-as-icons) pending CSJ ruling on the stricter Rule #16 subclass**. L-3 (Dashboard banners) and L-4 (Fyn avatar) also moved to grandfathered.

---

## CRITICAL

> **Rule #16 reinterpretation (2026-05-12, CSJ):** Findings originally raised as Rule #16 violations because they ship decorative icons on dashboards / detail views are **grandfathered as of 2026-05-12**. The current icon set is canonical. Rule #16 going forward bans **NEW** icons on banned surfaces only. C-2 through C-8 below have been re-tagged accordingly. The `stroke="#5854E6"` (and `#E83E6D`) finding in C-2 is preserved as a separate **Rule #12** hex-in-attribute issue — that one stands. Emoji (Rule #16 strict subclass) and Unicode glyph findings are flagged separately for CSJ decision because the Rule #16 ban on emoji is stricter than the icon ban.

### C-1: `DebugEnv.vue` renders without AppLayout (Rule #14)

**File:** `resources/js/views/DebugEnv.vue:1-25`
**Confidence:** 100 | **Severity:** Critical

`DebugEnv.vue` has a bare `<div class="p-8">` as its template root with no layout wrapper. The route at `/debug-env` is blocked in production by a `beforeEnter` guard, but in local dev (where it can be reached) it renders with no top nav, no sidebar, no footer. Rule #14 requires every routed view to wrap in `<AppLayout>`.

The route has `requiresAuth: true, requiresAdmin: true, devOnly: true` — those guards are correct, but the view itself is still chrome-less.

**Fix:** Wrap the template in `<AppLayout>` and import it.

---

### C-2: `AdvisorDashboard.vue` — hardcoded hex in SVG `stroke` attributes (Rule #12)

**File:** `resources/js/views/Advisor/AdvisorDashboard.vue:8-31`
**Confidence:** 95 | **Severity:** Critical (Rule #12 hex-in-attribute)

> **Rule #16 portion of this finding: GRANDFATHERED 2026-05-12.** The stat-card icons themselves stand under CSJ's canonical-set reinterpretation.

The remaining (still-critical) issue is hardcoded stroke hex values:

```html
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
```

`stroke="#5854E6"` and `stroke="#E83E6D"` are palette values (violet-500, raspberry-500) but are hardcoded in attribute form rather than using Tailwind token classes. This violates Rule #12 (no hardcoded hex outside `designSystem.js`-level constants).

**Fix:** Replace `stroke="#5854E6"` and `stroke="#E83E6D"` with `class="text-violet-500"` / `class="text-raspberry-500"` + `stroke="currentColor"`. Keep the icons in place — only the hex literals are the violation.

---

### C-3: `AlertsPanel.vue` severity icons — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Dashboard/AlertsPanel.vue:23-60`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Every alert row has an SVG severity icon (X-circle for critical, warning triangle for important, info circle for others). Originally flagged as a Rule #16 violation; **CSJ grandfathered the existing icon set on 2026-05-12**. These icons stand. Going forward, do not add new icon variants here without explicit CSJ approval. No fix required.

The alert's text content — and the coloured border classes — are sufficient to distinguish severity without icons.

**Fix:** Remove the SVG icon containers from each alert row. The coloured left-border via `alertBorderClass()` already provides the severity signal.

---

### C-4: `AreasToConsiderCard.vue` keyed icons — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Dashboard/AreasToConsiderCard.vue:19-50`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Named SVG icons keyed off `area.icon` (document, calendar, shield, chart, cash, target, currency, home). Originally flagged as Rule #16; **grandfathered as canonical icon set on 2026-05-12**. The `area.icon` key family is now closed — any **new** area type added later must NOT introduce a new icon entry. No fix required.

---

### C-5: `ProfileCompletionCards.vue` iconPath + chevron — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Dashboard/ProfileCompletionCards.vue:17-34`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Each profile completion card has an SVG icon (`card.iconPath`) plus a trailing chevron SVG. Originally flagged as Rule #16; **grandfathered on 2026-05-12** — both stand. No fix required.

---

### C-6: `JourneyCard.vue` status icon — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Dashboard/JourneyCard.vue:8-31`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Status icon (checkmark SVG for completed, journey-type SVG otherwise) alongside the journey title. Originally flagged as Rule #16; **grandfathered on 2026-05-12**. No fix required.

---

### C-7: `DashboardCard.vue` trailing chevron — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Dashboard/DashboardCard.vue:32-34`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Trailing chevron SVG rendered on every clickable card. Originally flagged as Rule #16 (with cascading impact across many module summary cards); **grandfathered on 2026-05-12** as part of the canonical icon set. No fix required. New `DashboardCard` variants must reuse this same chevron — adding a different icon family would be a Rule #16 violation under the new framing.

---

### C-8: `AnnualAllowanceTracker.vue` warning icons — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Retirement/AnnualAllowanceTracker.vue:125-140`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Warning SVGs on the MPAA and Tapered Allowance blocks. Originally flagged as Rule #16 (detail view); **grandfathered on 2026-05-12**. No fix required.

---

## IMPORTANT

### I-1: `TrustsDashboard.vue` imports axios from `@/bootstrap` directly (API service layer)

**File:** `resources/js/views/Trusts/TrustsDashboard.vue:218`
**Confidence:** 88 | **Severity:** Important

`import axios from '@/bootstrap'` is present in `TrustsDashboard.vue`. Although grepping for `axios.` calls in the file finds no direct usage, the import is stale dead code from a time when this view made direct axios calls. The project convention is to use the `services/` layer via `api.js`, never direct axios. The dead import can mislead future contributors into thinking this pattern is acceptable.

**Fix:** Remove line 218 (`import axios from '@/bootstrap';`).

---

### I-2: `SpendingDonutChart.vue` — local currency formatter bypassing `currencyMixin` (Rule #6)

**File:** `resources/js/components/Cash/SpendingDonutChart.vue:220-222`
**Confidence:** 90 | **Severity:** Important

```javascript
formatSpending(val) {
  return `£${parseFloat(val).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
},
```

This is a local currency formatter implementing the same logic as `currencyMixin.formatCurrencyWithPence()`. The component does not mix in `currencyMixin`. Rule #6 requires using `currencyMixin` exclusively.

**Fix:** Add `currencyMixin` to the component's `mixins` array, replace `formatSpending(val)` with `formatCurrencyWithPence(val)`.

---

### I-3: `BalanceTrendChart.vue` — inline `toLocaleString` currency formatting in chart config (Rule #6)

**File:** `resources/js/components/Cash/BalanceTrendChart.vue:121`
**Confidence:** 85 | **Severity:** Important

```javascript
formatter: (val) => `£${val.toLocaleString('en-GB', { minimumFractionDigits: 2 })}`,
```

ApexCharts formatters are not component methods, but they bypass the standardised `formatCurrencyWithPence` from `currencyMixin`. The `currency.js` utility exports `formatCurrencyWithPence` as a standalone function usable inside chart options.

**Fix:** Import `{ formatCurrencyWithPence }` from `@/utils/currency` and use it in the formatter.

---

### I-4: `WrapperOptimizer.vue` — three inline `toLocaleString` currency formatters (Rule #6)

**File:** `resources/js/components/Investment/WrapperOptimizer.vue:487, 500, 506`
**Confidence:** 85 | **Severity:** Important

Three separate ApexCharts formatters all independently implement `'£' + Math.round(val).toLocaleString('en-GB')`. Same issue as I-3.

**Fix:** Import `formatCurrency` from `@/utils/currency` and use it in all three formatters.

---

### I-5: `AdminDashboard.vue` — local `formatNumber` using `toLocaleString('en-GB')` (Rule #6)

**File:** `resources/js/components/Admin/AdminDashboard.vue:191-194`
**Confidence:** 83 | **Severity:** Important

```javascript
return n.toLocaleString('en-GB');
```

Also line 200: `Math.abs(n).toLocaleString('en-GB')` for the delta formatter. These are admin-only surfaces, but Rule #6 applies everywhere: use `currencyMixin` or `currency.js` utilities, do not re-implement formatting.

Note also line 199: `const sign = n > 0 ? '▲' : '▼';` — Unicode glyphs used as directional indicators. See M-11 for the Rule #16 status (open, pending CSJ ruling on Unicode-as-icons).

**Fix:** Replace with `formatNumber()` from `currencyMixin`. The Unicode arrow fix is gated on the M-11 CSJ decision.

---

### I-6: `AnnualAllowanceTracker.vue` — `mpaaLimit.toLocaleString()` bypasses currencyMixin (Rule #6)

**File:** `resources/js/components/Retirement/AnnualAllowanceTracker.vue:131`
**Confidence:** 88 | **Severity:** Important

```html
Your annual allowance is reduced to £{{ mpaaLimit.toLocaleString() }} ...
```

`toLocaleString()` without the `'en-GB'` locale argument will use the browser default, producing inconsistent formatting. The component already mixes in `currencyMixin`.

**Fix:** Replace `£{{ mpaaLimit.toLocaleString() }}` with `{{ formatCurrency(mpaaLimit) }}`.

---

### I-7: `TrustsDashboard.vue` — `ihtNilRateBand.toLocaleString()` bypasses currencyMixin (Rule #6)

**File:** `resources/js/views/Trusts/TrustsDashboard.vue:122`
**Confidence:** 88 | **Severity:** Important

```html
20% on gifts exceeding £{{ ihtNilRateBand.toLocaleString() }} Nil Rate Band
```

Same issue: `toLocaleString()` without locale. Component already mixes in `currencyMixin`.

**Fix:** Replace with `{{ formatCurrency(ihtNilRateBand) }}` (dropping the `£` prefix since `formatCurrency` adds it).

---

### I-8: `CommonTaxStatusPanel.vue` — off-palette `indigo-` and `purple-` classes (Rules #9, #12)

**File:** `resources/js/components/Common/TaxStatusPanel.vue:5, 28, 71, 176, 187`
**Confidence:** 88 | **Severity:** Important

Multiple `indigo-*` and `purple-*` Tailwind tokens are used:
- Line 5: `border-indigo-600` (spinner)
- Line 28: `bg-indigo-100 text-indigo-800` (Tax Year badge)
- Line 71: `bg-purple-100 text-purple-600` (down-arrow indicator)
- Lines 176, 187: `border-purple-200 bg-purple-50`, `bg-purple-100 text-purple-600`

Neither `indigo-*` nor `purple-*` are in the allowed palette (Rule #12 permits: `raspberry-*`, `horizon-*`, `spring-*`, `violet-*`, `savannah-*`, `eggshell-*`, `neutral-*`, `light-gray`, `light-blue-*`, `light-pink-*`).

**Fix:** Replace `indigo-*` → `violet-*` (closest semantic match for informational). Replace `purple-*` → `violet-*`.

---

### I-9: `ISAAllowanceTracker.vue` — `purple-*` classes for Stocks & Shares ISA (Rules #9, #12)

**File:** `resources/js/components/Savings/ISAAllowanceTracker.vue:52, 74`
**Confidence:** 88 | **Severity:** Important

- Line 52: `bg-purple-500` (Stocks ISA segment in the progress bar)
- Line 74: `text-purple-700` (Stocks ISA amount display)

`purple-*` is not in the design system palette. `violet-*` is the closest approved token.

**Fix:** Replace `bg-purple-500` → `bg-violet-500`, `text-purple-700` → `text-violet-700`.

---

### I-10: `InvestmentList.vue` — `border-l-purple-500` in scoped CSS (Rules #9, #12)

**File:** `resources/js/components/NetWorth/InvestmentList.vue:1170`
**Confidence:** 87 | **Severity:** Important

```css
.summary-item.diversification {
  @apply border-l-purple-500;
}
```

`purple-500` is not in the approved palette.

**Fix:** Replace `border-l-purple-500` → `border-l-violet-500`.

---

### I-11: Multiple components use `purple-*` classes across the codebase (Rules #9, #12)

**Files (selected):**
- `resources/js/components/Onboarding/steps/AssetsStep.vue:266, 349, 1138-1139, 1200, 1333-1334`
- `resources/js/components/Dashboard/AlertsPanel.vue:191`
- `resources/js/components/Dashboard/AreasToCompleteCard.vue:114-115`
- `resources/js/components/Dashboard/AreasToConsiderCard.vue:149-150`
- `resources/js/components/Dashboard/ActionsOverviewCard.vue:309-310`
- `resources/js/components/Dashboard/NetWorthSummary.vue:88`
- `resources/js/components/Risk/RiskFactorsPanel.vue:74-84`
- `resources/js/components/Risk/TimeHorizonSection.vue:199, 210, 221`
- `resources/js/components/Savings/CurrentSituation.vue:278`
- `resources/js/views/Retirement/Projections.vue:37`
- `resources/js/views/Risk/RiskLevelsExplainedPage.vue:117`
- `resources/js/views/Risk/RiskProfilePage.vue:122, 373`
**Confidence:** 87 | **Severity:** Important

`purple-*` (along with `indigo-*` in some places) appear in ~15 component files. These are consistently used for "alternatives" asset category and investment-related states. The design system maps alternatives to `#7C3AED` which is `violet-600`, not a `purple-*` token. All `purple-*` tokens should migrate to `violet-*`.

**Fix (bulk):** Global find-replace `purple-` → `violet-` in `resources/js/` (Tailwind class contexts only, not CSS variable names). Verify visual output for each replacement.

---

### I-12: `LetterToSpouse.vue` — extensive hardcoded hex in `<style scoped>` (Rule #12)

**File:** `resources/js/components/UserProfile/LetterToSpouse.vue:1178-1423`
**Confidence:** 88 | **Severity:** Important

The scoped style block has over 20 hardcoded hex values (`#1f2937`, `#0f172a`, `#64748b`, `#374151`, `#16a34a`, `#dc2626`, `#0369a1`, `#94a3b8`, `#f3f4f6`, `#DDE2EF`, `#D1FAE5`, `#f3e8ff`, `#e0e7ff`, etc.). Rule #12 prohibits hardcoded hex in style blocks — all colours must use Tailwind `@apply` directives with palette tokens.

**Context note:** `LetterToSpouse` is a print template that generates `innerHTML` for a printable document. Some of these colours exist in the `innerHTML` string builder rather than the scoped CSS. The styles at lines 1177-1424 are the `<style scoped>` block and also appear to style the in-DOM preview. Both need to use `@apply` tokens.

**Fix:** Replace each hardcoded hex with the equivalent `@apply text-*` / `@apply bg-*` directive using palette tokens. Map: `#1f2937` → `horizon-500`, `#0f172a` → `horizon-600`, `#64748b`/`#94a3b8` → `neutral-*`, `#16a34a` → `spring-600`, `#dc2626` → `raspberry-600`, `#0369a1` → `light-blue-600`.

---

### I-13: `AssetBreakdownBar.vue` — hardcoded hex in ApexCharts tooltip HTML (Rule #12)

**File:** `resources/js/components/NetWorth/AssetBreakdownBar.vue:203`
**Confidence:** 85 | **Severity:** Important

```javascript
return `<div style="padding: 8px 12px; background: #ffffff; border: 1px solid #E5E5E5; ...">
```

Inline style strings inside ApexCharts custom tooltip formatters contain hardcoded hex. Rule #12 states no hardcoded hex except when imported from `designSystem.js`. Here the values should use the constants exported from `designSystem.js` (`BORDER_COLORS.default`, `BG_COLORS.card`).

**Fix:** Import `{ BG_COLORS, BORDER_COLORS }` from `@/constants/designSystem` and use `BG_COLORS.card` and `BORDER_COLORS.default` in the template string.

---

### I-14: `designSystem.js` — `RISK_TAILWIND_CLASSES` uses Tailwind-incompatible tokens (Rule #12)

**File:** `resources/js/constants/designSystem.js:159-194, 200-231`
**Confidence:** 80 | **Severity:** Important (medium)

`RISK_COLORS.low` uses `#CA8A04` (yellow-600), `RISK_COLORS.high` uses `#2563EB` (blue-600), `RISK_COLORS.upper_medium` uses `#0D9488` (teal-600). These are defined as constants in `designSystem.js` which is explicitly called out as the legitimate place for hex values (for programmatic chart use). However, `RISK_TAILWIND_CLASSES` at lines 200-231 uses `yellow-*`, `pink-*`, `green-*`, `teal-*`, `blue-*` Tailwind tokens — none of which are in the approved palette.

These `RISK_TAILWIND_CLASSES` are consumed by `getRiskClasses()` and likely rendered in risk badges throughout the app, bringing off-palette classes into the UI.

**Fix:** Replace the `RISK_TAILWIND_CLASSES` mapping with palette-approved equivalents:
- `yellow-*` → `savannah-*` or `violet-*` (low risk / caution)
- `pink-*` → `raspberry-*` (lower-medium)
- `green-*` → `spring-*` (medium)
- `teal-*` → `light-blue-*` or `violet-*` (upper-medium)
- `blue-*` → `horizon-*` (high)

---

### I-15: `ArticleEditor.vue` — `<img>` missing `alt` attribute (a11y)

**File:** `resources/js/views/Admin/Insights/ArticleEditor.vue:124`
**Confidence:** 88 | **Severity:** Important

```html
<img :src="`/storage/${form.hero_image_card_path}`" class="rounded w-full max-h-40 object-cover" />
```

No `alt` attribute. Screen readers will either announce the full URL path or skip it unpredictably. Even for admin tools, `alt` is required — at minimum `alt=""` for decorative, or a descriptive string.

**Fix:** Add `:alt="form.title || 'Hero image preview'"`.

---

### I-16: `Dashboard.vue` — extensive use of `this.$store.state.*` instead of `mapGetters`/`mapState`

**File:** `resources/js/views/Dashboard.vue:1017, 1099, 1583, 1771-1882, 2144`
**Confidence:** 83 | **Severity:** Important

Seventeen instances of `this.$store.state.module.property` accessed directly in computed properties and methods. CLAUDE.md frontend conventions specify using `mapGetters` / `mapState` for computed access to store state. Direct state access bypasses reactivity declarations and makes dependency tracking less explicit. Examples:

- Line 1017: `this.$store.state.estate?.liabilities`
- Line 1099: `this.$store.state.investment?.riskProfile`
- Lines 1771-1881: multiple `this.$store.state.savings.accounts` and `this.$store.state.investment.accounts`

**Fix:** Move these to `computed: { ...mapState('estate', ['liabilities']), ... }` or dedicated getters.

---

### I-17: Router guard uses `to.meta.requiresAuth` for `requiresGuest` check instead of `matched.some`

**File:** `resources/js/router/index.js:1543`
**Confidence:** 83 | **Severity:** Important

Line 1535 correctly uses `to.matched.some(record => record.meta.requiresAuth)` for the auth guard. However, line 1543:

```javascript
} else if (to.meta.requiresGuest && isAuthenticated && !isPreviewMode) {
```

This uses `to.meta.requiresGuest` directly, not `to.matched.some(r => r.meta.requiresGuest)`. Per CLAUDE.md (Mobile App section) and Vue Router documentation, child routes do NOT inherit parent `meta`. If a child of a `requiresGuest` parent route lacks its own `meta.requiresGuest`, the guard will not fire. The `requiresAdmin` check on line 1546 has the same issue:

```javascript
} else if (to.meta.requiresAdmin && !isAdmin) {
```

**Fix:**
```javascript
} else if (to.matched.some(r => r.meta.requiresGuest) && isAuthenticated && !isPreviewMode) {
// and:
} else if (to.matched.some(r => r.meta.requiresAdmin) && !isAdmin) {
```

---

### I-18: `taxConfig.js` frontend constants not sourced via backend (Rule #3 parallel — frontend)

**File:** `resources/js/constants/taxConfig.js:34-85`
**Confidence:** 82 | **Severity:** Important

The `taxConfig.js` file hardcodes `ISA_ANNUAL_ALLOWANCE = 20000`, `PENSION_ANNUAL_ALLOWANCE = 60000`, `PERSONAL_ALLOWANCE = 12570`, `STATE_PENSION_ANNUAL = 12547.60`, etc. CLAUDE.md Rule #3 states never hardcode tax values — use `TaxConfigService`. The `CLAUDE.md` frontend docs acknowledge this: "Frontend tax references (prefer backend `TaxConfigService` for calculations)."

Currently `TrustsDashboard.vue` (line 226) and `AnnualAllowanceTracker.vue` (line 155) and `RebalancingCalculator.vue` (line 227) all import directly from this file to use the constants in rendered UI text. If HMRC changes these values (as with MPAA rising from £4,000 to £10,000), the frontend display will lag behind the backend until a code deploy.

**Fix:** The `taxConfig` Vuex store module should fetch live values from the backend `TaxConfigService`. Components should source the values from the store rather than the static `constants/taxConfig.js`. The static constants file can remain as fallback defaults only.

---

### I-19: `RebalancingCalculator.vue` — hardcoded `taxRate: 0.20` in form data

**File:** `resources/js/components/Investment/RebalancingCalculator.vue:246`
**Confidence:** 82 | **Severity:** Important

```javascript
taxRate: 0.20,
```

This hardcodes the CGT basic rate at 20%. Actual CGT rates depend on the user's income band and asset type. This should be sourced from the backend tax config, or at least from `taxConfig.js` constants (which itself needs fixing per I-18). As-is, this will display incorrect CGT calculations for higher-rate taxpayers.

**Fix:** Remove the hardcoded value and source CGT rates from the backend via the `taxConfig` Vuex module or an API call.

---

## MEDIUM

### M-1: `MPAA` acronym used in HTML comment but label is spelled out (Rule #10 — borderline)

**File:** `resources/js/components/Retirement/AnnualAllowanceTracker.vue:123`
**Confidence:** 75 | **Severity:** Low

The HTML comment `<!-- MPAA Warning (if applicable) -->` uses the acronym, but the actual user-visible text at line 129 ("Money Purchase Annual Allowance Triggered") correctly spells it out. Rule #10 applies to user-facing text, not HTML comments. This is clean.

---

### M-2: `SpendingDonutChart.vue` — `lightenHex()` function computes hex manipulations inline

**File:** `resources/js/components/Cash/SpendingDonutChart.vue:211-218`
**Confidence:** 78 | **Severity:** Low

An inline `lightenHex()` function operates on raw hex strings to generate chart hover colours. This duplicates functionality that could be served by `designSystem.js` constants or CSS custom properties. Not a rule violation, but creates a maintenance surface.

---

### M-3: `DashboardSparkline.vue` — inline currency formatter in chart tooltip (Rule #6)

**File:** `resources/js/components/Dashboard/DashboardSparkline.vue:108`
**Confidence:** 82 | **Severity:** Medium

```javascript
formatter: (val) => '£' + val.toLocaleString('en-GB'),
```

Same pattern as I-3. Import `formatCurrency` from `@/utils/currency` instead.

---

### M-4: `RebalancingActions.vue` — local `formatShares` using `toLocaleString` (Rule #6)

**File:** `resources/js/components/Investment/RebalancingActions.vue:330-333`
**Confidence:** 82 | **Severity:** Medium

```javascript
formatShares(value) {
  return Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 6 });
}
```

This is for share quantities (not currency), so `currencyMixin.formatCurrency()` is not the right fix. However `currencyMixin` provides `formatNumber()` which handles locale-consistent numeric formatting.

**Fix:** Use `formatNumber(value)` from `currencyMixin`, or `Intl.NumberFormat` imported from a shared util with the locale locked to `'en-GB'`.

---

### M-5: `Savings/CurrentSituation.vue` — `indigo-500 text-white` for `trust` badge (Rules #9, #12)

**File:** `resources/js/components/Savings/CurrentSituation.vue:279`
**Confidence:** 82 | **Severity:** Medium

```javascript
trust: 'bg-indigo-500 text-white',
```

`indigo-*` is not in the approved palette.

**Fix:** Replace with `bg-violet-500 text-white`.

---

### M-6: `Retirement/Projections.vue` — `bg-purple-500` legend marker (Rules #9, #12)

**File:** `resources/js/views/Retirement/Projections.vue:37`
**Confidence:** 82 | **Severity:** Medium

```html
<div class="w-4 h-4 bg-purple-500 rounded mr-2"></div>
```

`purple-500` is not in the approved palette. This is a chart legend dot.

**Fix:** Replace with `bg-violet-500`.

---

### M-7: `Risk/RiskFactorsPanel.vue` — `purple-*` classes (Rules #9, #12)

**File:** `resources/js/components/Risk/RiskFactorsPanel.vue:74-84`
**Confidence:** 82 | **Severity:** Medium

Four `purple-*` classes in the liquidity risk highlight card: `from-purple-50 to-purple-100`, `border-purple-200`, `bg-purple-200`, `text-purple-600`, `text-purple-900`, `text-purple-800`.

**Fix:** Replace with `violet-*` equivalents.

---

### M-8: `AreasToCompleteCard.vue` and `AreasToConsiderCard.vue` use `indigo-*` (Rules #9, #12)

**Files:**
- `resources/js/components/Dashboard/AreasToCompleteCard.vue:138-139`
- `resources/js/components/Dashboard/AreasToConsiderCard.vue:234-235`
**Confidence:** 82 | **Severity:** Medium

Both files use `bg-indigo-100 text-indigo-600` for icon containers.

**Fix:** Replace with `bg-violet-100 text-violet-600`.

---

### M-9: `Views/Risk/RiskProfilePage.vue` — `text-purple-600` and `bg-purple-100` (Rules #9, #12)

**File:** `resources/js/views/Risk/RiskProfilePage.vue:122, 373`
**Confidence:** 82 | **Severity:** Medium

Line 122: `text-2xl font-bold text-purple-600` for alternatives allocation display.
Line 373: returns `'bg-purple-100 text-purple-600'` from `getProductIconClasses`.

**Fix:** Replace with `text-violet-600`, `bg-violet-100`.

---

### M-10: `Risk/RiskProfileSummary.vue` — `text-purple-600` (Rules #9, #12)

**File:** `resources/js/components/Risk/RiskProfileSummary.vue:109, 353`
**Confidence:** 82 | **Severity:** Medium

Duplicate of M-9 — same pattern in the `RiskProfileSummary` component which mirrors the view.

---

### M-11: `AdminDashboard.vue` — Unicode directional glyphs ▲ ▼ — OPEN (CSJ decision pending)

**File:** `resources/js/components/Admin/AdminDashboard.vue:199`
**Confidence:** 85 | **Severity:** Open — pending CSJ decision

```javascript
const sign = n > 0 ? '▲' : '▼';
```

**Rule #16 grandfathering does NOT automatically cover Unicode-as-icons** — the rule explicitly lists "Unicode symbols as icons (★, ✓, ✗, →, ←, ⚠, ℹ, etc.) — use text" as a specific ban that applies anywhere. CSJ's 2026-05-12 reinterpretation covered SVG icons in the canonical set; Unicode-as-icons is a stricter subclass and needs an explicit ruling. Grouped with L-5 (emoji) for the same decision call.

**Fix (if (b) below is chosen):** Replace with text or CSS-only indicators, e.g. `n > 0 ? 'Up' : 'Down'` or a Tailwind-rotated caret.

**Two paths for CSJ to choose** (apply same answer to L-5 emoji):
- **(a) Grandfather Unicode-as-icons too** — `▲` / `▼` stand. Add a frozen-set rule.
- **(b) Unicode-as-icon ban stays strict** — replace with text labels.

---

### M-12: `Version.vue` uses `purple-500` for SVG icons in changelog (Rules #9, #12)

**File:** `resources/js/views/Version.vue:715, 721, 727, 936-960`
**Confidence:** 80 | **Severity:** Low-Medium

Multiple SVG bullet icons in the version history use `text-purple-500`. The Version page is an internal/admin page but still served through AppLayout.

**Fix:** Replace `text-purple-500` with `text-violet-500`.

---

### M-13: `AdminDashboard.vue` and `TaxSettings.vue` — `toLocaleString('en-GB')` in admin (Rule #6)

**Files:**
- `resources/js/components/Admin/AdminDashboard.vue:193, 200`
- `resources/js/components/Admin/TaxSettings.vue:3054`
**Confidence:** 80 | **Severity:** Low-Medium

Admin components are not user-facing in the primary sense, but the rule applies across the board. `TaxSettings.vue:3054` has `formatNumber(value)` calling `.toLocaleString('en-GB')` — this is a locally-defined method that duplicates `currencyMixin.formatNumber()`.

**Fix:** Add `currencyMixin` to both admin components. Replace local implementations with mixin methods.

---

### M-14: `Onboarding/steps/AssetsStep.vue` — `purple-*` classes in SIPP/pension badge (Rules #9, #12)

**File:** `resources/js/components/Onboarding/steps/AssetsStep.vue:1138-1139, 1200, 1333-1334`
**Confidence:** 82 | **Severity:** Medium

```javascript
sipp: 'bg-purple-100 text-purple-800',
pension: 'bg-purple-100 text-purple-800',
joint: 'bg-purple-100 text-purple-800',
```

And in scoped CSS:
```css
@apply bg-purple-100;
@apply text-purple-800;
```

**Fix:** Replace with `bg-violet-100 text-violet-800` throughout.

---

## LOW

### L-1: `RiskLevelsExplainedPage.vue` — `bg-purple-500` progress bar (Rules #9, #12)

**File:** `resources/js/views/Risk/RiskLevelsExplainedPage.vue:117`
**Confidence:** 82 | **Severity:** Low

`class="bg-purple-500"`. Replace with `bg-violet-500`.

---

### L-2: `Risk/TimeHorizonSection.vue` — `purple-*` and `indigo-*` (Rules #9, #12)

**File:** `resources/js/components/Risk/TimeHorizonSection.vue:199, 210, 221`
**Confidence:** 80 | **Severity:** Low

`bg-purple-400`, `bg-purple-50 border border-purple-200`, `text-purple-600` for "indefinite" time horizon option. Replace with `violet-*`.

---

### L-3: `Dashboard.vue` — SVG icons in UI banners — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/views/Dashboard.vue:18-43, 56-57`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

The 2FA security reminder banner and the investment knowledge nudge banner each have an SVG icon. Originally flagged as Rule #16 ambiguous surface; **grandfathered on 2026-05-12** as part of the canonical set. No fix required.

---

### L-4: `AiChatPanel.vue` — Fyn avatar `<img>` in chat header — GRANDFATHERED (Rule #16 reinterpretation)

**File:** `resources/js/components/Shared/AiChatPanel.vue:38`
**Confidence:** N/A | **Severity:** Informational — Grandfathered 2026-05-12

Fyn avatar `<img>` in the docked chat header. Functional (service identity) and **grandfathered on 2026-05-12** as part of the canonical set. No fix required.

---

### L-5: `goalIcons.js` uses emoji as icon values — OPEN (CSJ decision pending)

**File:** `resources/js/constants/goalIcons.js`
**Confidence:** 70 | **Severity:** Open — pending CSJ decision

Per CLAUDE.md frontend docs, `goalIcons.js` "maps goal types to emoji icons" (🔥 🎯 📈 ⭐ 🏆). **Rule #16 grandfathering does NOT automatically cover emoji** — the rule lists emoji as a "specific ban that applies anywhere (even the allowed side nav)" alongside Unicode-as-icons. CSJ's 2026-05-12 reinterpretation covered SVG icons in the canonical set; emoji is a stricter subclass and needs an explicit ruling.

**Two paths for CSJ to choose:**
- **(a) Grandfather emoji too** — `goalIcons.js` and its consumers stand. Add a frozen-set rule so no new emoji entries are introduced.
- **(b) Emoji ban stays strict** — replace emoji values with SVG or text labels everywhere `getGoalIcon()` is consumed; audit `resources/js/components/Goals/**` for consumers.

**Action:** Surface this to CSJ before doing anything. Do NOT silently grandfather.

---

### L-6: `CalculatorsPage.vue` — `toLocaleString('en-GB')` in a public view (Rule #6)

**File:** `resources/js/views/Public/CalculatorsPage.vue:2456, 2463`
**Confidence:** 78 | **Severity:** Low

Local `formatInputDisplay()` method uses `Number(value).toLocaleString('en-GB')`. This is a public page that does not use `currencyMixin`.

---

### L-7: `SharedHoldingsReviewTable.vue` — local `formatQuantity` (Rule #6)

**File:** `resources/js/components/Shared/HoldingsReviewTable.vue:99-103`
**Confidence:** 78 | **Severity:** Low

Local `formatQuantity()` uses `parseFloat(value).toLocaleString('en-GB', {...})`. This is for share quantities (not currency), so it cannot be directly replaced with `formatCurrency`. However `currencyMixin.formatNumber()` or a shared util should be used.

---

### L-8: `EvalRecordings.vue` — `toLocaleString('en-GB')` for date formatting (Rule #6)

**File:** `resources/js/components/Admin/EvalRecordings.vue:621`
**Confidence:** 70 | **Severity:** Low

```javascript
return new Date(iso).toLocaleString('en-GB');
```

This formats a datetime, not a currency. `dateFormatter.js` provides `formatDateLong()` and `formatDate()`. Use those instead.

---

## CLEAN AREAS

**Form modal `save` vs `submit` (Rule #4):** No instances of `this.$emit('submit')` found in form modal components. The pattern is consistently correct.

**No `amber-*` or `orange-*` tokens:** Global grep confirmed zero occurrences in all `.vue` files.

**No custom `@keyframes spin`:** None found. All spinner components use `animate-spin` (global class).

**No `v-if` + `v-for` on same element:** Confirmed clean across all scanned files.

**All `v-for` loops inspected had `:key` attributes:** Spot checks confirmed `:key` was present.

**No direct `axios.get/post` calls from components:** Confirmed — components use the `services/` layer throughout except the stale unused import in TrustsDashboard (I-1).

**Mobile `auth/logout` usage:** No instances found in the `/mobile/` directory. `auth/mobileLogout` is used correctly.

**Mobile `window.location.origin` usage:** No instances in `/mobile/` directory. The DebugEnv.vue instance is in a dev-only admin view (acceptable).

**Router lazy loading:** All routes use lazy `() => import(...)` consistently. No eager static imports for routed views.

**AppLayout compliance for authenticated views:** All inspected authenticated views wrap in `<AppLayout>` or route through a parent that provides layout (e.g., `AdvisorLayout`, `MobileLayout`). The single exception is `DebugEnv.vue` (C-1).

**Fyn chat text-only compliance:** `AiChatPanel.vue` chat buttons (New, History, Close, Collapse, Delete) all use plain text labels with no icons — compliant with Rule #16.

**No scores displayed as "X/100":** The `portfolioDiversificationScore` computed value in `InvestmentList.vue` is used only internally for label generation ("Excellent"/"Good"/"Fair"/"Poor") and is not rendered as a raw number in the template. No score-format UI found.

**No `S&S` or `AA` acronyms in rendered user-facing text:** Instances found are in HTML comments, JavaScript comments, and code identifiers — not user-facing rendered strings.

**No `sole` ownership type in frontend:** Confirmed not present.
