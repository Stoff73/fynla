# Full Codebase Code Review — `uiUpdates` Branch

**Date:** 5 March 2026
**Branch:** `uiUpdates` (11 commits, 45 files changed, +1,356 / -1,213 lines vs `main`)
**Reviewed by:** Claude Opus 4.6 (5 parallel review agents: CLAUDE.md compliance, bug scan, security, frontend patterns, backend PHP)

---

## Critical Issues (Score 80+)

### 1. Rule 9 Violation — Banned colour tokens throughout Plans/Shared components (Score: 85)

`PlanSectionHeader.vue` defines a colour prop system mapping to `blue-*`, `green-*`, `purple-*`, `teal-*` — all banned by the design system. This is structural: every component using `PlanSectionHeader` inherits banned tokens.

**Affected files:**

| File | Violations |
|------|------------|
| `resources/js/components/Plans/Shared/PlanSectionHeader.vue` | Colour map uses `bg-blue-50`, `bg-green-50`, `bg-purple-50`, `bg-teal-50` etc. Validator codifies banned names as accepted values |
| `resources/js/components/Plans/Shared/PlanMissingDataPrompt.vue` | 10+ instances of `blue-*` tokens (`blue-50`, `blue-200`, `blue-600`, `blue-800`, `blue-900`, `blue-700`, `blue-100/50`) |
| `resources/js/components/Plans/Shared/PlanDashboardCard.vue` | Default `color: 'blue'`, uses `blue-*`, `green-*`, `purple-*`, `teal-*` across icon backgrounds, progress bars, status badges |
| `resources/js/components/Plans/Shared/PlanGoalSection.vue` | `blue-*` tokens + hardcoded `rgba(59, 130, 246, 0.15)` in style block |
| `resources/js/components/Plans/Shared/PlanActionCard.vue` | `border-blue-200 bg-blue-50/30`, `focus:ring-blue-500 focus:border-blue-500` |
| `resources/js/components/Plans/Shared/PlanErrorState.vue` | `red-50`, `red-200`, `red-500`, `red-800`, `red-600`, `red-700` throughout |
| `resources/js/components/Plans/Shared/PlanConclusion.vue` | `bg-blue-100 text-blue-700` (non-critical action badge fallback) |
| `resources/js/components/Plans/Shared/PlanWhatIfMetricRow.vue` | `bg-green-100 text-green-700` / `bg-red-100 text-red-700` |

**Required substitutions:**

| Banned Token | Palette Replacement |
|-------------|-------------------|
| `blue-*` | `violet-*` (info/caution) or `horizon-*` (neutral) |
| `green-*` | `spring-*` (success) |
| `red-*` | `raspberry-*` (error/danger) |
| `purple-*` | `violet-*` |
| `teal-*` | `horizon-*` or `spring-*` |

**Root fix:** Rebuild `PlanSectionHeader.vue` colour map to use design system tokens. This will cascade corrections to all consumer components.

---

### 2. Rule 12 Violation — FinancialHealthScore.vue displays numeric scores (Score: 90)

**File:** `resources/js/components/Dashboard/FinancialHealthScore.vue`

The component renders:
- Large display: `{{ Math.round(compositeScore) }}` with "out of 100"
- Per-module breakdown: `{{ Math.round(protectionScore) }}/100`, `{{ Math.round(emergencyFundScore) }}/100`, etc.
- Title: "Financial Health Score"

Rule 12 explicitly bans "numerical ratings like '75/100', adequacy scores, diversification scores, portfolio health scores" from user-facing UI. The component is complete and the backing store/service code (`dashboardService.getFinancialHealthScore()`, `setFinancialHealthScore` mutation) is already integrated — it will go live when imported by a parent.

**Fix:** Replace the numeric gauge with descriptive text and specific metric cards. The existing `scoreLabel` and `recommendation` computed strings are already Rule 12-compliant. Remove the `/100` display and per-module numeric scores. Keep progress bars but label with qualitative thresholds ("Good", "Needs attention") rather than numbers.

---

### 3. CSS Governance — SecuritySettings.vue has 26+ hardcoded hex values (Score: 80)

**File:** `resources/js/views/Settings/SecuritySettings.vue` (style block, lines 412-557)

Hardcoded hex values found:

| Hex | Count | Context |
|-----|:-----:|---------|
| `#111827` | 2 | Text colour (should be `text-horizon-500`) |
| `#6b7280` | 4 | Muted text (should be `text-neutral-500`) |
| `#d1fae5` / `#065f46` | 1 | Enabled badge (should be `spring-100` / `spring-800`) |
| `#fee2e2` / `#991b1b` | 1 | Disabled badge (should be `raspberry-100` / `raspberry-800`) |
| `#f9fafb` | 2 | Background (should be `eggshell-500`) |
| `#dbeafe` / `#1e40af` | 1 | Current badge (should be `violet-100` / `violet-800`) |
| `#f0fdf4` / `#bbf7d0` | 1 | Tips section (should be `spring-50` / `spring-200`) |
| `#166534` / `#16a34a` | 2 | Tip text/icon (should be `spring-800` / `spring-500`) |
| `#fca5a5` / `#dc2626` | 1 | Danger button (should be `raspberry-300` / `raspberry-600`) |
| `#fef2f2` | 1 | Danger hover (should be `raspberry-50`) |

**Fix:** Replace all with `@apply` directives using palette tokens.

---

### 4. Dev placeholder text visible to users in PlanActionCard (Score: 80)

**File:** `resources/js/components/Plans/Shared/PlanActionCard.vue` (line 19-20)

```html
<p v-if="action.estimated_impact" class="text-xs text-green-700 mt-1 font-medium">
  Estimated impact: {{ formatCurrency(action.estimated_impact) }} (this is not a real figure until we connect to a quote engine)
</p>
```

The text "(this is not a real figure until we connect to a quote engine)" is a developer note that renders in the user-facing UI on all Plans pages. Also uses banned `text-green-700` token.

**Fix:** Remove the parenthetical developer note. Replace `text-green-700` with `text-spring-700`.

---

### 5. Hardcoded '#EF4444' in chart components (Score: 80)

**Files:**
- `resources/js/components/Investment/InvestmentProjectionChart.vue` — lines 147, 161
- `resources/js/components/Retirement/PensionPotProjectionChart.vue` — lines 140, 154

Both use `'#EF4444'` (Tailwind red-500) for expense life event colours. This is not a palette colour — the correct token for errors/danger is `ERROR_COLORS[500]` (`#E83E6D`, raspberry-500) already exported from `@/constants/designSystem`.

**Fix:**
```js
import { PRIMARY_COLORS, SUCCESS_COLORS, ERROR_COLORS, BORDER_COLORS } from '@/constants/designSystem';

// Replace both occurrences of '#EF4444' with:
ERROR_COLORS[500]
```

---

## Important Bugs (Score 70-79)

### 6. PlanActionsList.vue silently drops 'update-funding-source' events (Score: 75)

**File:** `resources/js/components/Plans/Shared/PlanActionsList.vue` (lines 13-15, 43)

`PlanActionCard` emits `update-funding-source` when a user changes a funding source dropdown. `PlanActionsList` renders `PlanActionCard` instances but only declares `emits: ['toggle']` and only binds `@toggle`. The `update-funding-source` event is silently dropped.

This breaks funding source selection for any plan that renders actions via `PlanActionsList` — currently Protection and Goal plans. The user can interact with the dropdown but the change is never committed to the store or API.

**Fix:** Add `@update-funding-source="$emit('update-funding-source', $event)"` to the `PlanActionCard` in `PlanActionsList.vue` template, and add `'update-funding-source'` to the `emits` declaration. Parent content components (`ProtectionPlanContent`, `GoalPlanContent`) also need to handle the event.

---

### 7. PlanWhatIfMetricRow.vue uses banned green-*/red-* tokens (Score: 75)

**File:** `resources/js/components/Plans/Shared/PlanWhatIfMetricRow.vue` (lines 63-64)

```html
'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
```

Delta indicators use banned tokens. Should use `bg-spring-100 text-spring-700` / `bg-raspberry-100 text-raspberry-700`.

---

### 8. IHTController overwrites projected liability with current RNRB allowances (Score: 70)

**File:** `app/Http/Controllers/Api/Estate/IHTController.php` (lines 88-90)

```php
$totalAllowances = $calculation['nrb_available'] + $calculation['rnrb_available'];
$calculation['projected_taxable_estate'] = max(0, $calculation['projected_net_estate'] - $totalAllowances);
$calculation['projected_iht_liability'] = $calculation['projected_taxable_estate'] * ($calculation['iht_rate'] ?? 0.40);
```

The controller recalculates projected taxable estate using **current** `nrb_available + rnrb_available`, but the RNRB tapers above the £2M estate threshold. At projected death, estate growth may push past the taper threshold, making the current RNRB incorrect for the projection. The `IHTCalculationService` already computes projected values with correct taper logic, but the controller overwrites them.

Additionally, the `?? 0.40` fallback is a hardcoded tax value that should come from `TaxConfigService` per project rules (though `IHTCalculationService` always sets `iht_rate`, so the fallback is currently unreachable).

**Fix:** Remove the controller's recalculation of `projected_taxable_estate` and `projected_iht_liability`. The service already computes these correctly. Only adjust `projected_net_estate` for the liabilities correction:

```php
$calculation['projected_net_estate'] = $calculation['projected_gross_assets'] - $projectedLiabilities;
// Let the service's projected_taxable_estate and projected_iht_liability stand
```

---

### 9. RecommendationsAggregatorService — 'iht_saving: Variable' string causes TypeError (Score: 70)

**File:** `app/Services/Coordination/RecommendationsAggregatorService.php` (lines 114, 303)

`ComprehensiveEstatePlanService` sets `'iht_saving' => 'Variable'` (a string) for the property downsizing action. This flows into `potential_benefit` in the recommendations. In `getSummary()`, the line:

```php
$summary['total_potential_benefit'] += $rec['potential_benefit'];
```

will throw a `TypeError` under `declare(strict_types=1)` when it tries to add the string "Variable" to a float. This breaks the summary endpoint for any user with an estate plan containing a downsizing recommendation.

**Fix:** Guard numeric operations:

```php
// At storage point (line 114):
'potential_benefit' => is_numeric($item['iht_saving'] ?? null) ? $item['iht_saving'] : null,

// In getSummary() (line 303):
if (isset($rec['potential_benefit']) && is_numeric($rec['potential_benefit'])) {
    $summary['total_potential_benefit'] += $rec['potential_benefit'];
}
```

---

## Security Review

**1 medium-severity issue found:**

### IHT rate fallback uses hardcoded value instead of TaxConfigService (Medium)

**File:** `app/Http/Controllers/Api/Estate/IHTController.php` (line 90)

The `?? 0.40` fallback is a hardcoded tax value. If the IHT rate changes (e.g. charitable giving reduces it to 36%) and the service fails to include `iht_rate`, the controller would silently apply 40%.

**Fix:** Use `TaxConfigService` for the fallback:
```php
$ihtConfig = $this->taxConfig->getInheritanceTax();
$defaultRate = $ihtConfig['rate'] ?? 0.40;
```

### Items reviewed with no issues:
- Authentication/authorisation on all recommendation routes (auth:sanctum confirmed)
- Preview mode isolation in SideMenu.vue (properly checks isPreviewMode)
- XSS — all bindings use `{{ }}` text interpolation, no `v-html`
- CSRF — all API calls through existing axios instance
- SQL injection — no raw queries introduced
- Mass assignment — RecommendationTracking uses explicit `$fillable`
- Data exposure — recommendations API returns only user-visible data
- Client-side storage — no localStorage usage introduced

---

## Below Threshold (Not Flagged)

These issues were identified but scored below 70 and are not flagged for action:

| Issue | Score | Reason |
|-------|:-----:|--------|
| PlanConclusion.vue null guard on conclusion prop | 50 | Parents likely guard rendering |
| RetirementGroupedActions dead import of PensionGrowthProjectionChart | 50 | Dead code, not a crash |
| ActionsDashboard unhandled promise rejections on action buttons | 65 | Common pattern, backend errors caught |
| Missing v-preview-disabled on ActionsDashboard buttons | 60 | Backend PreviewWriteInterceptor protects data |
| PlanLoadingState.vue non-canonical SVG spinner | 50 | Functionally equivalent |
| PlanGoalSection.vue custom @keyframes instead of global class | 60 | Has stagger delay justification |
| RecommendationsAggregatorService sparse array keys from array_filter | 60 | Main paths use array_values |
| InvestmentGroupedActions cascade chart baseline mixing | 25 | Mixed-scope actions may not occur in practice |
| Estate timeline string priority comparison | 25 | String priorities are in a different code path |

---

## Recommended Fix Priority

### Immediate (before next deploy)
1. Remove dev placeholder text from `PlanActionCard.vue` (Issue 4)
2. Fix `'iht_saving: Variable'` TypeError in `RecommendationsAggregatorService` (Issue 9)
3. Replace `#EF4444` with `ERROR_COLORS[500]` in both chart components (Issue 5)

### Next sprint
4. Rebuild `PlanSectionHeader.vue` colour map with design system tokens (Issue 1 — cascades to all Plans/Shared)
5. Fix `SecuritySettings.vue` hardcoded hex values (Issue 3)
6. Add `update-funding-source` event forwarding in `PlanActionsList.vue` (Issue 6)
7. Remove or refactor `FinancialHealthScore.vue` to comply with Rule 12 (Issue 2)

### Backlog
8. Review IHTController projected liability calculation vs service values (Issue 8)
9. Migrate remaining `green-*/red-*` tokens in `PlanWhatIfMetricRow.vue` (Issue 7)
