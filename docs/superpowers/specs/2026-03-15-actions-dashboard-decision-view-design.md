# Actions Dashboard + Decision View — Design Spec

## Goal

Rebuild the Actions Dashboard to show plan-sourced actions grouped by module in a two-column grid, with each action clickable to a detail view showing both a visual decision tree and step-by-step trace of the reasoning behind the recommendation.

## Architecture

The Actions Dashboard fetches all 5 module plans (`/api/plans/{type}`) in parallel and extracts their `actions` arrays. Actions are grouped by module and rendered in a two-column grid with module headers. Each action links to a detail view at `/actions/:planType/:actionId` that displays the `decision_trace` data in two formats side by side: a flowchart-style tree (left) and a vertical timeline trace (right).

The backend change adds a `decision_trace` array to each recommendation returned by the `buildRecommendation()` methods in all 5 module ActionDefinitionServices. The trace records each check made during evaluation: question asked, user's actual data, threshold compared against, pass/fail result, and explanation.

## Tech Stack

- Vue.js 3 (Options API, matching codebase convention)
- Vuex (existing `plans` store module)
- CSS/SVG for decision tree visualisation (no external charting library)
- Laravel backend (existing ActionDefinitionService pattern)
- Existing `plansService.js` for API calls

---

## Design System Compliance (fynlaDesignGuide.md v1.2.0)

All components follow these rules:

### Colours (palette tokens only)
- **Priority badges:** Critical/High = `bg-raspberry-100 text-raspberry-700`, Medium = `bg-violet-100 text-violet-700`, Low = `bg-neutral-100 text-neutral-700` (matches Badges & Tags section)
- **Pass indicators:** `bg-spring-100 text-spring-700` / `border-spring-500`
- **Fail indicators:** `bg-raspberry-100 text-raspberry-700` / `border-raspberry-500`
- **Outcome/result:** `bg-violet-100 text-violet-700` / `border-violet-500`
- **Hover states:** `bg-savannah-100` transition
- **Page background:** `bg-eggshell-500`
- **Card background:** `bg-white`
- **Borders:** `border-light-gray`
- **No amber, orange, teal, or hardcoded hex values**

### Typography
- Page title: `text-h2 font-display text-horizon-500`
- Section headers: `text-h4 font-bold text-horizon-500`
- Action titles: `text-body font-semibold text-horizon-500`
- Body/description text: `text-body-sm text-neutral-500`
- Captions/labels: `text-caption text-neutral-500`
- Data values: `font-mono text-body-sm` (for financial figures)
- Font stack: Segoe UI primary, Inter fallback

### Spacing
- Card padding: `p-6` (24px)
- Section gaps: `space-y-6`
- Grid gap: `gap-6`
- Button padding: `px-4 py-2`
- Component spacing: `mb-4` between items

### Cards
- Standard: `bg-white p-6 rounded-card shadow-card border border-light-gray`
- Clickable cards add: `cursor-pointer hover:bg-savannah-100 transition-colors`

### Borders & Radius
- Cards: `rounded-card` (12px)
- Badges: `rounded-full` (pill shape)
- Decision nodes: `rounded-lg` (16px)
- Category tags: `rounded-md` (6px)

### Loading States
- Spinner: `animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500`
- Skeleton: `bg-savannah-100 h-4 w-full rounded animate-pulse`

### Empty States
- Centred layout with icon in `bg-savannah-100 rounded-full`, heading in `text-h4 font-bold text-horizon-500`, description in `text-body text-neutral-500`

### Back Button
- Uses global `.detail-inline-back` class from `app.css`

---

## Frontend Changes

### 1. ActionsDashboard.vue (rewrite)

**Replace** the current component entirely. Remove: summary cards, search bar, filter dropdowns, recommendations API calls.

**New behaviour:**
- On mount, fetch all 5 plans via Vuex `plans/fetchPlan` for each type: `protection`, `savings`, `investment`, `retirement`, `estate`
- Show loading spinner (`animate-spin border-b-2 border-raspberry-500`) while fetching
- Extract the `actions` array from each plan
- Render in a two-column CSS grid (`grid-cols-1 lg:grid-cols-2 gap-6`)
- Module sections in fixed order: Protection, Savings, Investment, Retirement, Estate
- Skip modules with no actions or where `can_proceed` is false
- Each section: white card (`bg-white rounded-card shadow-card border border-light-gray p-6`)
- Module header inside card with icon, name, and action count badge
- Under header: list of `ActionSummaryCard` components with `space-y-3` gap
- If no modules have actions: empty state with centred icon, `text-h4` heading, `text-body text-neutral-500` description

**Page structure:**
```html
<AppLayout>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page header -->
    <h1 class="text-h2 font-display text-horizon-500 mb-2">Actions</h1>
    <p class="text-body-sm text-neutral-500 mb-8">
      Recommended actions from your financial plans
    </p>

    <!-- Two-column grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Module sections -->
    </div>
  </div>
</AppLayout>
```

**Module header inside each card:**
```html
<div class="flex items-center justify-between mb-4 pb-3 border-b border-light-gray">
  <div class="flex items-center gap-3">
    <!-- Module icon (SVG, matching sidebar) -->
    <h2 class="text-h4 font-bold text-horizon-500">Protection</h2>
  </div>
  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium
               bg-violet-100 text-violet-700">
    7 actions
  </span>
</div>
```

### 2. ActionSummaryCard.vue (new component)

**Location:** `resources/js/components/Actions/ActionSummaryCard.vue`

**Props:** `action` (Object, required), `planType` (String, required)

**Template:**
```html
<div
  class="flex items-center justify-between p-3 rounded-lg cursor-pointer
         hover:bg-savannah-100 transition-colors border border-transparent
         hover:border-light-gray"
  @click="goToDetail"
>
  <div class="flex items-center gap-3 min-w-0">
    <!-- Priority badge (pill) -->
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium flex-shrink-0"
          :class="priorityClass">
      {{ action.priority }}
    </span>
    <!-- Title + category -->
    <div class="min-w-0">
      <p class="text-body-sm font-semibold text-horizon-500 truncate">{{ action.title }}</p>
      <span class="text-caption text-neutral-500">{{ action.category }}</span>
    </div>
  </div>
  <!-- Impact (right side) -->
  <span v-if="action.estimated_impact" class="text-body-sm font-semibold text-spring-600 flex-shrink-0 ml-4">
    {{ formatCurrency(action.estimated_impact) }}
  </span>
</div>
```

**Priority badge classes:**
- `critical`: `bg-raspberry-100 text-raspberry-700`
- `high`: `bg-raspberry-50 text-raspberry-600`
- `medium`: `bg-violet-100 text-violet-700`
- `low`: `bg-neutral-100 text-neutral-700` (using `neutral` not `gray`)

**Mixins:** `currencyMixin` for `formatCurrency()`

### 3. ActionDetailView.vue (new view)

**Location:** `resources/js/views/Actions/ActionDetailView.vue`

**Route:** `/actions/:planType/:actionId`

**Data loading:**
- Get plan from Vuex store (`plans/getPlan(planType)`)
- Find the specific action by `actionId` in the plan's `actions` array
- If plan not loaded, fetch it first via `plans/fetchPlan`
- Show skeleton loading (`bg-savannah-100 animate-pulse`) while loading

**Template structure:**
```html
<AppLayout>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Back button (global class) -->
    <button class="detail-inline-back mb-6" @click="$router.push('/actions')">
      <svg><!-- back arrow --></svg>
      Back to Actions
    </button>

    <!-- Action header card -->
    <div class="bg-white rounded-card shadow-card border border-light-gray p-6 mb-6">
      <div class="flex items-start justify-between mb-4">
        <h1 class="text-h3 font-bold text-horizon-500">{{ action.title }}</h1>
        <span :class="['...rounded-full text-caption font-medium', priorityClass]">
          {{ action.priority }}
        </span>
      </div>
      <span class="inline-flex items-center px-3 py-1 rounded-md text-body-sm font-medium
                   bg-eggshell-500 text-horizon-500 mb-4">
        {{ action.category }}
      </span>
      <p class="text-body text-neutral-500 mb-4">{{ action.description }}</p>
      <div v-if="action.estimated_impact"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-spring-50 border border-spring-200">
        <span class="text-body-sm text-neutral-500 mr-2">Estimated impact:</span>
        <span class="text-body font-bold text-spring-700">
          {{ formatCurrency(action.estimated_impact) }}
        </span>
      </div>
    </div>

    <!-- Decision trace panels (side by side) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
        <h2 class="text-h4 font-bold text-horizon-500 mb-4">Decision Tree</h2>
        <DecisionTreeDiagram :steps="action.decision_trace" :outcome="outcome" />
      </div>
      <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
        <h2 class="text-h4 font-bold text-horizon-500 mb-4">Decision Trace</h2>
        <DecisionTraceTimeline :steps="action.decision_trace" :outcome="outcome" />
      </div>
    </div>
  </div>
</AppLayout>
```

### 4. DecisionTreeDiagram.vue (new component)

**Location:** `resources/js/components/Actions/DecisionTreeDiagram.vue`

**Props:** `steps` (Array, required), `outcome` (Object, required)

**Rendering approach:**
- Pure CSS/HTML — no charting library
- Each node: `div` with `p-4 rounded-lg border-2 max-w-sm mx-auto`
- Nodes stacked vertically with connecting lines via CSS `::after` pseudo-element (`w-0.5 h-6 bg-light-gray mx-auto`)
- Pass nodes: `border-spring-500 bg-spring-50`
- Fail nodes: `border-raspberry-500 bg-raspberry-50`
- Outcome node: `border-violet-500 bg-violet-50`
- Inside each node:
  - Question: `text-body-sm font-semibold text-horizon-500`
  - Data value: `text-caption font-mono` with inline icon (checkmark or cross)
  - Threshold: `text-caption text-neutral-500`
- Pass/fail icon: inline SVG, `w-5 h-5` — `text-spring-600` for pass, `text-raspberry-600` for fail

### 5. DecisionTraceTimeline.vue (new component)

**Location:** `resources/js/components/Actions/DecisionTraceTimeline.vue`

**Props:** `steps` (Array, required), `outcome` (Object, required)

**Rendering approach:**
- Vertical timeline with `border-l-2 border-light-gray ml-4`
- Each step: positioned relative with circle indicator at `-left-[9px]`
- Circle: `w-4 h-4 rounded-full` — `bg-spring-500` for passed, `bg-raspberry-500` for failed
- Step content (padded left `pl-6 pb-6`):
  - Question: `text-body-sm font-semibold text-horizon-500`
  - Data row: `text-caption text-neutral-500` — "Your data: " + `font-mono` value
  - Threshold row: `text-caption text-neutral-500` — "Target: " + `font-mono` value
  - Explanation: `text-caption text-neutral-500 mt-1`
- Outcome bar at bottom: `bg-violet-50 border border-violet-200 rounded-lg p-4 mt-4`
  - Title: `text-body-sm font-semibold text-violet-700`
  - Description: `text-caption text-neutral-500`

### 6. Router changes

Add route in `resources/js/router/index.js`:

```javascript
{
    path: '/actions/:planType/:actionId',
    name: 'ActionDetail',
    component: () => import('../views/Actions/ActionDetailView.vue'),
    meta: { requiresAuth: true },
}
```

---

## Backend Changes

### 7. Decision trace in buildRecommendation

**All 5 ActionDefinitionServices** need to pass a `decision_trace` array into their `buildRecommendation()` call.

**Pattern:** Each `evaluate*` method builds a `$trace` array as it runs checks:

```php
private function evaluateEmergencyFundCritical(...): array
{
    $trace = [];
    $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
    $target = $this->getTargetEmergencyMonths($user);

    $trace[] = [
        'question' => 'How many months of emergency fund runway do you have?',
        'data_field' => 'Emergency fund runway',
        'data_value' => $runway . ' months',
        'threshold' => $target . ' months target',
        'passed' => $runway >= $target,
        'explanation' => $runway >= $target
            ? 'Your emergency fund meets the target.'
            : 'Your runway is ' . ($target - $runway) . ' months short of the recommended target.',
    ];

    if ($runway >= 1) {
        return []; // Not critical
    }

    // ... more checks ...

    $rec = $this->buildRecommendation($definition, $vars, $priority);
    $rec['decision_trace'] = $trace;
    return [$rec];
}
```

**Files to modify:**
- `app/Services/Savings/SavingsActionDefinitionService.php` — 41 evaluate methods
- `app/Services/Protection/ProtectionActionDefinitionService.php` — 28 evaluate methods
- `app/Services/Retirement/RetirementActionDefinitionService.php` — 18 evaluate methods
- `app/Services/Investment/InvestmentActionDefinitionService.php` — 14 evaluate methods
- `app/Services/Estate/EstateDataReadinessService.php` — estate checks

**Also for pipeline-sourced actions (investment only):**
- `app/Services/Investment/Recommendation/TransferRecommendationService.php` — 13 scan methods
- `app/Services/Investment/Recommendation/SpouseOptimisationService.php` — 7 strategy methods
- `app/Services/Investment/Recommendation/ContributionWaterfallService.php` — 11 step methods

### 8. BasePlanService.structureActions() passthrough

**File:** `app/Services/Plans/BasePlanService.php`

Add `decision_trace` to the fields passed through in `structureActions()`:

```php
'decision_trace' => $rec['decision_trace'] ?? [],
```

No other backend changes needed — the existing plan endpoints return the enriched actions automatically.

---

## Data Structure

### Decision trace step

```json
{
    "question": "Do you have critical illness cover?",
    "data_field": "Critical Illness Cover",
    "data_value": "£200,000",
    "threshold": "£290,000 (2x annual income)",
    "passed": false,
    "explanation": "Your cover is £90,000 below the recommended target of 2x your annual income."
}
```

### Action with trace (in plan response)

```json
{
    "id": "protection_action_1",
    "title": "Add critical illness cover for £90,000",
    "description": "Your current cover of £200,000 is below the recommended...",
    "category": "Critical Illness",
    "priority": "high",
    "enabled": true,
    "estimated_impact": 90000,
    "decision_trace": [
        { "question": "...", "data_field": "...", "data_value": "...", "threshold": "...", "passed": false, "explanation": "..." },
        { "question": "...", "data_field": "...", "data_value": "...", "threshold": "...", "passed": true, "explanation": "..." }
    ]
}
```

---

## What's NOT Changing

- Plan endpoints stay the same (`/api/plans/{type}`)
- Plan views (`/plans/investment`, etc.) are unaffected
- Holistic plan unaffected
- No new API endpoints
- No database changes
- Existing `PlanActionCard` component in plan views untouched
- Recommendation tracking system (`/api/recommendations`) remains but is no longer used by the Actions Dashboard

---

## Implementation Order

1. Backend: add `decision_trace` to `BasePlanService.structureActions()` passthrough
2. Backend: add traces to Protection (simplest, fewest triggers — good first test)
3. Frontend: rewrite `ActionsDashboard.vue` with two-column grid
4. Frontend: create `ActionSummaryCard.vue`
5. Frontend: create `ActionDetailView.vue` with router
6. Frontend: create `DecisionTreeDiagram.vue`
7. Frontend: create `DecisionTraceTimeline.vue`
8. Backend: add traces to Savings (41 triggers — largest batch)
9. Backend: add traces to Retirement (18 triggers)
10. Backend: add traces to Investment (14 triggers + pipeline services)
11. Backend: add traces to Estate
12. Test all personas end-to-end
