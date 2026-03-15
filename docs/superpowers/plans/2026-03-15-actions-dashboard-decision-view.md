# Actions Dashboard + Decision View Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the Actions Dashboard to show plan-sourced actions grouped by module, with clickable detail views showing visual decision trees and step-by-step traces.

**Architecture:** Frontend fetches all 5 module plans in parallel, extracts actions, renders in a two-column grid by module. Each action links to a detail view with side-by-side decision tree diagram and timeline trace. Backend adds `decision_trace` arrays to all evaluate methods across 5 ActionDefinitionServices + 3 investment pipeline services.

**Tech Stack:** Vue.js 3 (Options API), Vuex, Laravel 10, Tailwind CSS, CSS/SVG decision tree (no charting library)

**Spec:** `docs/superpowers/specs/2026-03-15-actions-dashboard-decision-view-design.md`

---

## File Structure

### New Files
| File | Responsibility |
|------|---------------|
| `resources/js/components/Actions/ActionSummaryCard.vue` | Compact clickable action row with priority badge, title, category, impact |
| `resources/js/components/Actions/DecisionTreeDiagram.vue` | Visual flowchart of decision trace steps |
| `resources/js/components/Actions/DecisionTraceTimeline.vue` | Vertical timeline of decision trace steps |
| `resources/js/views/Actions/ActionDetailView.vue` | Detail page showing both tree + timeline side by side |

### Modified Files
| File | Change |
|------|--------|
| `resources/js/views/Actions/ActionsDashboard.vue` | Full rewrite — two-column grid sourced from plans |
| `resources/js/router/index.js` | Add `/actions/:planType/:actionId` route |
| `app/Services/Plans/BasePlanService.php` | Pass `decision_trace` through `structureActions()` |
| `app/Services/Protection/ProtectionActionDefinitionService.php` | Add trace to 25 evaluate methods |
| `app/Services/Savings/SavingsActionDefinitionService.php` | Add trace to 48 evaluate methods |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Add trace to 20 evaluate methods |
| `app/Services/Investment/InvestmentActionDefinitionService.php` | Add trace to 23 evaluate methods |
| `app/Services/Estate/EstateDataReadinessService.php` | Add trace to 12 evaluate/check methods |
| `app/Services/Investment/Recommendation/TransferRecommendationService.php` | Add trace to 13 scan methods |
| `app/Services/Investment/Recommendation/SpouseOptimisationService.php` | Add trace to 7 strategy methods |
| `app/Services/Investment/Recommendation/ContributionWaterfallService.php` | Add trace to 10 step methods |

---

## Chunk 1: Frontend

### Task 1: BasePlanService passthrough + ActionsDashboard rewrite

**Files:**
- Modify: `app/Services/Plans/BasePlanService.php:270-288`
- Modify: `resources/js/views/Actions/ActionsDashboard.vue` (full rewrite)
- Modify: `resources/js/router/index.js:553-560`

- [ ] **Step 1: Add decision_trace passthrough in BasePlanService**

In `app/Services/Plans/BasePlanService.php`, inside the `structureActions()` method, add after the `'guidance'` line (line 287):

```php
'decision_trace' => $rec['decision_trace'] ?? [],
```

- [ ] **Step 2: Add ActionDetail route**

In `resources/js/router/index.js`, after the existing `/actions` route block (around line 560), add:

```javascript
{
    path: '/actions/:planType/:actionId',
    name: 'ActionDetail',
    component: () => import('@/views/Actions/ActionDetailView.vue'),
    meta: {
        requiresAuth: true,
        breadcrumb: [
            { label: 'Dashboard', path: '/dashboard' },
            { label: 'Actions', path: '/actions' },
            { label: 'Detail', path: '' },
        ],
    },
},
```

- [ ] **Step 3: Rewrite ActionsDashboard.vue**

Replace the entire contents of `resources/js/views/Actions/ActionsDashboard.vue` with the new two-column grid layout that:
- Fetches all 5 plans via `this.$store.dispatch('plans/fetchPlan', type)` for each of `['protection', 'savings', 'investment', 'retirement', 'estate']`
- Extracts actions from each plan via `this.$store.getters['plans/getPlan'](type)?.actions || []`
- Renders module sections in fixed order, skipping modules with 0 actions
- Uses `ActionSummaryCard` for each action
- Shows loading spinner while fetching, empty state if no actions anywhere
- Page header: `text-h2 font-display text-horizon-500`
- Grid: `grid-cols-1 lg:grid-cols-2 gap-6`
- Each module section: `bg-white rounded-card shadow-card border border-light-gray p-6`
- Module header: icon + `text-h4 font-bold text-horizon-500` + count badge `bg-violet-100 text-violet-700 rounded-full text-caption`
- Uses `currencyMixin`

Module order and icons (use inline SVG matching sidebar):
1. Protection — shield icon
2. Savings — piggy bank icon
3. Investment — chart line icon
4. Retirement — sunset icon
5. Estate — building icon

- [ ] **Step 4: Verify dashboard loads**

Run: `./dev.sh` (if not running), navigate to http://localhost:8000, log in as peak_earners persona, go to `/actions`.

Expected: Two-column grid with Protection (7 actions), Investment (12 actions), Retirement (5 actions), Estate (5 actions) sections. Actions show title, priority badge, category. No errors in console.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Plans/BasePlanService.php resources/js/views/Actions/ActionsDashboard.vue resources/js/router/index.js
git commit -m "feat: rewrite Actions Dashboard with plan-sourced two-column grid"
```

---

### Task 2: ActionSummaryCard component

**Files:**
- Create: `resources/js/components/Actions/ActionSummaryCard.vue`

- [ ] **Step 1: Create ActionSummaryCard.vue**

```vue
<template>
  <div
    class="flex items-center justify-between p-3 rounded-lg cursor-pointer hover:bg-savannah-100 transition-colors border border-transparent hover:border-light-gray"
    @click="goToDetail"
  >
    <div class="flex items-center gap-3 min-w-0">
      <span
        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium flex-shrink-0"
        :class="priorityClass"
      >
        {{ action.priority }}
      </span>
      <div class="min-w-0">
        <p class="text-body-sm font-semibold text-horizon-500 truncate">{{ action.title }}</p>
        <span class="text-caption text-neutral-500">{{ action.category }}</span>
      </div>
    </div>
    <span
      v-if="action.estimated_impact"
      class="text-body-sm font-semibold text-spring-600 flex-shrink-0 ml-4"
    >
      {{ formatCurrency(action.estimated_impact) }}
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ActionSummaryCard',
  mixins: [currencyMixin],
  props: {
    action: { type: Object, required: true },
    planType: { type: String, required: true },
  },
  computed: {
    priorityClass() {
      const classes = {
        critical: 'bg-raspberry-100 text-raspberry-700',
        high: 'bg-raspberry-50 text-raspberry-600',
        medium: 'bg-violet-100 text-violet-700',
        low: 'bg-eggshell-500 text-neutral-500',
      };
      return classes[this.action.priority] || classes.medium;
    },
  },
  methods: {
    goToDetail() {
      this.$router.push(`/actions/${this.planType}/${this.action.id}`);
    },
  },
};
</script>
```

- [ ] **Step 2: Update ActionsDashboard to use ActionSummaryCard**

Import and register the component in `ActionsDashboard.vue`, replace placeholder action rows with `<ActionSummaryCard>`.

- [ ] **Step 3: Verify cards render correctly**

Navigate to `/actions` as peak_earners. Each action should show priority badge, title, category, and impact amount where applicable. Hovering should show savannah background.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Actions/ActionSummaryCard.vue resources/js/views/Actions/ActionsDashboard.vue
git commit -m "feat: add ActionSummaryCard component"
```

---

### Task 3: DecisionTreeDiagram component

**Files:**
- Create: `resources/js/components/Actions/DecisionTreeDiagram.vue`

- [ ] **Step 1: Create DecisionTreeDiagram.vue**

Component that renders a vertical flowchart from `steps` array. Each step is a node connected by a line. Pass nodes use spring colours, fail nodes use raspberry, outcome node uses violet.

Props: `steps` (Array), `outcome` (Object with `title` and `description`)

Each node renders:
- Question text (`text-body-sm font-semibold text-horizon-500`)
- Data value with pass/fail icon (`text-caption font-mono`)
- Threshold (`text-caption text-neutral-500`)

Connecting lines: `w-0.5 h-6 mx-auto` using `bg-light-gray`

Empty state: "No decision trace available" in `text-body-sm text-neutral-500 text-center py-8`

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Actions/DecisionTreeDiagram.vue
git commit -m "feat: add DecisionTreeDiagram component"
```

---

### Task 4: DecisionTraceTimeline component

**Files:**
- Create: `resources/js/components/Actions/DecisionTraceTimeline.vue`

- [ ] **Step 1: Create DecisionTraceTimeline.vue**

Vertical timeline component with left border line. Each step shows a coloured circle on the line with content to the right.

Props: `steps` (Array), `outcome` (Object with `title` and `description`)

Timeline structure:
- Container: `relative border-l-2 border-light-gray ml-4`
- Each step: `relative pl-8 pb-6`
- Circle: `absolute -left-[9px] w-4 h-4 rounded-full` — `bg-spring-500` passed, `bg-raspberry-500` failed
- Question: `text-body-sm font-semibold text-horizon-500`
- Data row: `text-caption text-neutral-500` with `font-mono` value
- Threshold row: `text-caption text-neutral-500` with `font-mono` value
- Explanation: `text-caption text-neutral-500 mt-1 italic`

Outcome bar at end: `bg-violet-50 border border-violet-200 rounded-lg p-4 ml-8 mt-2`
- Title: `text-body-sm font-semibold text-violet-700`
- Description: `text-caption text-neutral-500`

Empty state: same as DecisionTreeDiagram

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Actions/DecisionTraceTimeline.vue
git commit -m "feat: add DecisionTraceTimeline component"
```

---

### Task 5: ActionDetailView page

**Files:**
- Create: `resources/js/views/Actions/ActionDetailView.vue`

- [ ] **Step 1: Create ActionDetailView.vue**

Page component at route `/actions/:planType/:actionId`.

Data loading:
- Read `planType` and `actionId` from `$route.params`
- Get plan from `this.$store.getters['plans/getPlan'](this.planType)`
- If no plan, dispatch `plans/fetchPlan`
- Find action: `plan.actions.find(a => a.id === this.actionId)`
- Show skeleton loading while fetching

Layout:
- Back button: `detail-inline-back` class, navigates to `/actions`
- Header card: `bg-white rounded-card shadow-card border border-light-gray p-6 mb-6`
  - Title: `text-h3 font-bold text-horizon-500`
  - Priority badge (same classes as ActionSummaryCard)
  - Category tag: `bg-eggshell-500 text-horizon-500 rounded-md px-3 py-1 text-body-sm`
  - Description: `text-body text-neutral-500`
  - Impact card (if present): `bg-spring-50 border border-spring-200 rounded-lg px-4 py-2`
- Two-panel grid: `grid grid-cols-1 lg:grid-cols-2 gap-6`
  - Left card: "Decision Tree" heading + `DecisionTreeDiagram`
  - Right card: "Decision Trace" heading + `DecisionTraceTimeline`
  - Both cards: `bg-white rounded-card shadow-card border border-light-gray p-6`
  - Section headings: `text-h4 font-bold text-horizon-500 mb-4`

Uses `currencyMixin`.

- [ ] **Step 2: Verify detail view loads**

Navigate to `/actions` as peak_earners, click any action. Should navigate to detail view showing the action header. Decision tree and timeline panels should show "No decision trace available" (traces not yet added to backend).

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass (no backend logic changed except passthrough).

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Actions/ActionDetailView.vue
git commit -m "feat: add ActionDetailView with decision tree and timeline panels"
```

---

## Chunk 2: Backend — Decision Traces

### Task 6: Protection decision traces

**Files:**
- Modify: `app/Services/Protection/ProtectionActionDefinitionService.php`

- [ ] **Step 1: Add decision traces to all 25 evaluate methods**

For each `evaluate*` method in `ProtectionActionDefinitionService.php`:
1. Create a `$trace = []` array at the top
2. After each conditional check, append a trace step:
```php
$trace[] = [
    'question' => 'Does user have critical illness cover?',
    'data_field' => 'Critical Illness Cover',
    'data_value' => '£' . number_format($currentCover, 0),
    'threshold' => '£' . number_format($targetCover, 0) . ' (2x income)',
    'passed' => $currentCover >= $targetCover,
    'explanation' => $currentCover >= $targetCover
        ? 'Your cover meets the recommended target.'
        : 'Your cover is £' . number_format($targetCover - $currentCover, 0) . ' below the target.',
];
```
3. Attach trace to recommendation: `$rec['decision_trace'] = $trace;`

Each evaluate method typically has 2-5 checks. Capture ALL of them — the user wants full transparency.

- [ ] **Step 2: Verify protection plan includes traces**

```bash
php artisan cache:clear
# Then test via curl or browser — /api/plans/protection should include decision_trace arrays on each action
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Protection/
```

- [ ] **Step 4: Verify in browser**

Navigate to `/actions`, click a Protection action. The decision tree and timeline should now render with actual trace steps.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Protection/ProtectionActionDefinitionService.php
git commit -m "feat: add decision traces to all 25 Protection evaluate methods"
```

---

### Task 7: Savings decision traces

**Files:**
- Modify: `app/Services/Savings/SavingsActionDefinitionService.php`

- [ ] **Step 1: Add decision traces to all 48 evaluate methods**

Same pattern as Protection. This is the largest service — 48 methods covering:
- Data readiness (4 methods)
- Emergency fund (5 methods)
- Tax efficiency / Personal Savings Allowance (6 methods)
- Rate optimisation (6 methods)
- Financial Services Compensation Scheme (2 methods)
- Debt vs savings (2 methods)
- Cash vs investment (4 methods)
- Goal-linked (5 methods)
- Children's savings (5 methods)
- Spouse coordination (2 methods)
- Per-account evaluators (7 methods)

Each method: build `$trace`, capture checks, attach to recommendation.

- [ ] **Step 2: Clear cache and verify**

```bash
php artisan cache:clear
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/pest tests/Unit/Agents/SavingsAgentTest.php tests/Feature/Savings/
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/Savings/SavingsActionDefinitionService.php
git commit -m "feat: add decision traces to all 48 Savings evaluate methods"
```

---

### Task 8: Retirement decision traces

**Files:**
- Modify: `app/Services/Retirement/RetirementActionDefinitionService.php`

- [ ] **Step 1: Add decision traces to all 20 evaluate methods**

Same pattern. Retirement covers:
- Contribution triggers (increase, salary sacrifice, auto-enrolment)
- Projection triggers (income gap, capital shortfall)
- Tax planning (Annual Allowance, Lifetime Allowance)
- Decumulation (withdrawal rate, annuity assessment, care costs)
- State Pension checks

- [ ] **Step 2: Clear cache, run tests, commit**

```bash
php artisan cache:clear
./vendor/bin/pest tests/Unit/Services/Retirement/
git add app/Services/Retirement/RetirementActionDefinitionService.php
git commit -m "feat: add decision traces to all 20 Retirement evaluate methods"
```

---

### Task 9: Investment decision traces (triggers + pipeline)

**Files:**
- Modify: `app/Services/Investment/InvestmentActionDefinitionService.php`
- Modify: `app/Services/Investment/Recommendation/TransferRecommendationService.php`
- Modify: `app/Services/Investment/Recommendation/SpouseOptimisationService.php`
- Modify: `app/Services/Investment/Recommendation/ContributionWaterfallService.php`

- [ ] **Step 1: Add traces to 23 InvestmentActionDefinitionService evaluate methods**

Same pattern as other services.

- [ ] **Step 2: Add traces to 13 TransferRecommendationService scan methods**

Each `scan*` method builds a recommendation via `buildRecommendation()`. Add `$trace` before the checks and attach via `$rec['decision_trace'] = $trace` after `buildRecommendation()`.

- [ ] **Step 3: Add traces to 7 SpouseOptimisationService strategy methods**

Each `strategy*` method returns a recommendation via `buildRecommendation()`. Same pattern — capture the checks made (income comparison, allowance usage, tax band differences) as trace steps.

- [ ] **Step 4: Add traces to 10 ContributionWaterfallService step methods**

Each `step*` method (stepISA, stepPension, stepLISA, etc.) returns a step result via `buildStep()` or `skipStep()`. Add trace capturing the allocation logic.

- [ ] **Step 5: Clear cache, run tests, commit**

```bash
php artisan cache:clear
./vendor/bin/pest tests/Unit/Services/Investment/ tests/Feature/InvestmentModuleTest.php
git add app/Services/Investment/InvestmentActionDefinitionService.php app/Services/Investment/Recommendation/TransferRecommendationService.php app/Services/Investment/Recommendation/SpouseOptimisationService.php app/Services/Investment/Recommendation/ContributionWaterfallService.php
git commit -m "feat: add decision traces to all Investment evaluate, scan, strategy, and step methods"
```

---

### Task 10: Estate decision traces

**Files:**
- Modify: `app/Services/Estate/EstateDataReadinessService.php`

- [ ] **Step 1: Add traces to 12 estate evaluate/check methods**

Estate checks cover: IHT liability, NRB usage, RNRB eligibility, gifting strategy, will review, liquidity, life cover, trust setup, charitable bequest.

- [ ] **Step 2: Clear cache, run tests, commit**

```bash
php artisan cache:clear
./vendor/bin/pest tests/Unit/Services/Estate/
git add app/Services/Estate/EstateDataReadinessService.php
git commit -m "feat: add decision traces to all 12 Estate evaluate methods"
```

---

## Chunk 3: Verification

### Task 11: Full test suite + persona verification

- [ ] **Step 1: Run full test suite**

```bash
./vendor/bin/pest
```

Expected: All 1873+ tests pass.

- [ ] **Step 2: Reseed database**

```bash
php artisan db:seed
```

- [ ] **Step 3: Test all personas via API**

For each persona (peak_earners, young_family, widow, entrepreneur, young_saver, retired_couple):
- Hit `/api/plans/{type}` for all 5 types
- Verify `decision_trace` arrays are present on actions
- Verify no 500 errors

- [ ] **Step 4: Browser test**

Navigate to `/actions` as peak_earners:
- Two-column grid renders with module sections
- Click a Protection action → detail view loads with decision tree + timeline
- Click a Savings action → traces render
- Click an Investment action → traces render
- Click a Retirement action → traces render
- Click an Estate action → traces render
- Back button returns to `/actions`
- Mobile responsive: panels stack vertically

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "test: verify all decision traces across 6 personas"
```

---

## Summary

| Task | Scope | Methods | Estimated Size |
|------|-------|---------|---------------|
| 1 | Dashboard rewrite + passthrough + route | — | Large (full component) |
| 2 | ActionSummaryCard | — | Small |
| 3 | DecisionTreeDiagram | — | Medium |
| 4 | DecisionTraceTimeline | — | Medium |
| 5 | ActionDetailView | — | Large |
| 6 | Protection traces | 25 methods | Large |
| 7 | Savings traces | 48 methods | Very large |
| 8 | Retirement traces | 20 methods | Large |
| 9 | Investment traces (4 files) | 53 methods | Very large |
| 10 | Estate traces | 12 methods | Medium |
| 11 | Verification | — | Medium |
