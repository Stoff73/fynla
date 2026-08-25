# Tech Debt Report — Session 123 (30 April 2026, evening)

**Files analysed:** 7 (the `/tax-strategy` redesign commit `fad6e88`)
**Issues found:** 3 (1 medium, 2 low)
**Severity breakdown:** 0 critical, 1 warning, 2 suggestions

---

## Files audited

| File | Lines | Result |
|---|---|---|
| `resources/js/components/TaxStrategy/AllowanceCard.vue` | 62 | Clean |
| `resources/js/components/TaxStrategy/AllowanceGrid.vue` | 60 | Clean |
| `resources/js/components/TaxStrategy/AssetShiftingPanel.vue` | 38 | Clean |
| `resources/js/components/TaxStrategy/HouseholdView.vue` | 77 | Clean |
| `resources/js/components/TaxStrategy/StrategyRecommendationList.vue` | 133 | 1 suggestion |
| `resources/js/components/TaxStrategy/TaxYearHeader.vue` | 67 | Clean |
| `resources/js/views/TaxStrategy/TaxStrategyDashboard.vue` | 49 | Clean |

All files well under the 500-line refactor threshold. Total 486 lines across 7 files.

---

## Warnings

### W-1 (medium) — Orphaned slider backend pipework after frontend removal

**Files:**
- `resources/js/services/taxStrategyService.js:12` — `recalculate: (overrides) => api.post('/tax-strategy/calculate', overrides)`
- `resources/js/store/modules/taxStrategy.js` — `recalculate` action and `setRecalculating` mutation
- `app/Http/Controllers/Api/TaxStrategyController.php::calculate`
- `app/Http/Requests/TaxStrategyCalculateRequest.php`
- `app/DataTransferObjects/TaxStrategyOverridesDTO.php`
- `app/Services/Tax/TaxStrategyService.php::recalculate`
- `routes/api.php` — `POST /api/tax-strategy/calculate` (auth:sanctum)
- `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` + `tests/Feature/Api/TaxStrategy/` (9 cases)

**Category:** Dead & Redundant Code (Cat 2) + Inconsistency (Cat 6)

**What's wrong:** The `StrategySliderPanel.vue` was deleted in `fad6e88`, removing the only consumer of `taxStrategy/recalculate` Vuex action and `POST /tax-strategy/calculate`. The entire pipeline — frontend service method, store action, mutation, validation request, controller method, DTO, service method, route, and 9+ Pest tests — is now unreachable from the UI.

**Note:** `TaxStrategyMath::availableAnnualAllowance()` and `estimatePensionContributionThisYear()` still accept `?TaxStrategyOverridesDTO` as an *optional* parameter, and the strategies pass `null` through. So the DTO type isn't fully dead — it's still on the type signature of methods called with `null` from `TaxStrategyCalculator`. But the override behaviour itself is unreachable.

**Suggested fix — two options for next session to choose:**

- **(a) Leave intact** if a future "what-if" UI is on the roadmap. Cost: ~9 dead Pest cases, ~250 lines of unused PHP/Vue/JS. Risk: none — endpoint requires `auth:sanctum` and validates inputs.
- **(b) Rip out** if the slider concept is permanently dead per CSJ's "get rid of these fucking things" directive. Cost: delete the 7 files/symbols above + 9 test cases + `setRecalculating`/`recalculating` from the Vuex store. Mechanical refactor — strategy classes already work with `null` overrides so the math service signatures simplify.

**Recommendation:** confirm option (b) with CSJ at the start of the next session (he was emphatic about removing sliders — likely wants the backend gone too).

---

## Suggestions

### S-1 (low) — Hardcoded route paths in `NEXT_STEPS` map

**File:** `resources/js/components/TaxStrategy/StrategyRecommendationList.vue:73-93`

**Category:** Convention / Magic-strings

**What's wrong:** The `NEXT_STEPS` constant at module level maps 17 strategy types to `{ label, path }` objects with paths like `/pension`, `/savings`, `/investments`, `/profile` written as raw strings.

```js
const NEXT_STEPS = {
  pa_taper_rescue: { label: 'Open a pension', path: '/pension' },
  isa_topup_vs_psa: { label: 'Open savings & ISAs', path: '/savings' },
  bed_and_isa: { label: 'Open investments', path: '/investments' },
  ...
};
```

**Suggested fix:** Defer until a 2nd consumer appears. If/when another component starts mapping strategy types to module routes, extract to `resources/js/constants/strategyNextSteps.js`. For now, the locality is fine — one component, one map.

### S-2 (low) — `formatCurrency(Math.round(...))` repeated 5 times across the changed components

**Files:**
- `AllowanceGrid.vue:8`
- `AssetShiftingPanel.vue:18`
- `HouseholdView.vue:38`
- `StrategyRecommendationList.vue:33`
- `TaxYearHeader.vue:39`

**Category:** Duplication (Cat 1)

**What's wrong:** Every recommendation/headroom currency display does `formatCurrency(Math.round(value))` to drop pence. The pattern is a 1-line idiom but appears in 5 files now.

**Suggested fix:** Add `formatCurrencyRounded(value)` to `resources/js/mixins/currencyMixin.js`. Single 5-line addition; replaces 5 call sites with cleaner intent. Defer to a tech-debt sweep — not worth its own commit.

---

## Clean bill of health on these checks

- ✅ Zero `"Your "` headings in any changed file (CSJ scrub directive complied with)
- ✅ Zero hardcoded hex in `<style>` or template colour classes
- ✅ Zero banned colours (`amber-*`, `orange-*`, `primary-*`, `secondary-*`, `gray-*`)
- ✅ Zero `'sole'` instead of `'individual'`
- ✅ Zero `console.log` / `dd()` / `dump()` left behind
- ✅ Zero hardcoded tax values — all live config or consumed via `currencyMixin`
- ✅ Zero icons on banned surfaces (cards, detail views) per Rule #14
- ✅ Zero scores / "X/100" in user-facing UI per Rule #13
- ✅ Zero acronyms — all spelled out (Personal Allowance, Capital Gains Tax, etc.); only `ISA` retained per the exception
- ✅ All Tailwind type tokens used (`text-h1`, `text-h3`, `text-h4`, `text-h5`, `text-body`, `text-body-sm`, `text-caption`) — verified in `tailwind.config.js`
- ✅ All component names multi-word
- ✅ Every `v-for` has `:key`; no `v-if`+`v-for` collisions
- ✅ `formatCurrency` consumed exclusively via `currencyMixin` — no local methods
- ✅ No new local spinner / scrollbar / animation CSS
- ✅ Vuex auth getter used correctly for personalisation

---

## Top 3 most impactful issues

1. **W-1** — orphan slider backend (medium) — confirmation needed from CSJ on rip-out vs preserve
2. **S-1** — hardcoded route paths in NEXT_STEPS — micro, defer
3. **S-2** — duplicated `formatCurrency(Math.round(...))` — micro, defer

**No critical issues. No fixes required before merge to dev.**

---

*Generated by tech-debt-session skill on 30 April 2026 evening.*
