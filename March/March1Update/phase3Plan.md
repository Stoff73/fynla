# Phase 3: Goal Integration into Plan Services

## Context

Plans currently operate independently from Goals. Users set goals (emergency fund, retirement, property purchase, etc.) and link them to savings/investment accounts, but module plans (Investment, Retirement, Estate) don't surface these goals or their progress. This creates a disconnect — the plan gives recommendations without knowing what the user is actually working towards.

Phase 3 adds goal awareness to each module plan: showing linked goals with progress, generating goal-specific recommendations that appear first, and prompting users to link unlinked goals.

## Files to Modify

**Backend:**
- `app/Services/Plans/BasePlanService.php` — Add shared goal-fetching, formatting, and recommendation methods
- `app/Services/Plans/InvestmentPlanService.php` — Integrate goals into `generatePlan()`
- `app/Services/Plans/RetirementPlanService.php` — Same
- `app/Services/Plans/EstatePlanService.php` — Same (only in full-analysis path after gate checks)

**Frontend:**
- `resources/js/components/Plans/Shared/PlanGoalSection.vue` — NEW shared component
- `resources/js/components/Plans/Investment/InvestmentPlanContent.vue` — Add PlanGoalSection
- `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` — Same
- `resources/js/components/Plans/Estate/EstatePlanContent.vue` — Same

**Tests:**
- `tests/Unit/Services/Plans/GoalIntegrationTest.php` — NEW

## Reusable Existing Code

- `Goal` model has `HasJointOwnership` trait → `scopeForUserOrJoint($userId)` scope
- `Goal::scopeActive()` — filters `status = 'active'`
- `Goal::scopeForModule($module)` — filters by `assigned_module`
- Goal computed attributes: `progress_percentage`, `is_on_track`, `amount_remaining`, `required_monthly_contribution`, `months_remaining`, `display_goal_type`
- `BasePlanService::formatCurrency()` via `FormatsCurrency` trait
- `GoalProgressBar.vue` component — props: percentage, currentAmount, targetAmount, isOnTrack, size, showAmounts
- `PlanSectionHeader.vue` — props: title, subtitle, color (supports 'teal')

## Implementation Steps

### Step 1: BasePlanService — Add shared goal methods

Add `use App\Models\Goal;` import, then add three protected methods:

**`getGoalsForPlan(int $userId, string $planType): array`**
- Uses `Goal::forUserOrJoint($userId)->active()` as base query
- Match logic per plan type:
  - `investment`: `assigned_module IN ('investment', 'savings')` OR `linked_investment_account_id IS NOT NULL` OR `linked_savings_account_id IS NOT NULL`
  - `retirement`: `assigned_module = 'retirement'` OR `goal_type = 'retirement'`
  - `estate`: `goal_type = 'wealth_accumulation'`
- Separate query for unlinked: `whereNull('linked_savings_account_id')->whereNull('linked_investment_account_id')`
- Returns `['linked' => [...], 'unlinked' => [...]]`

**`formatGoalForPlan(Goal $goal): array`**
- Maps Goal model to plan-friendly array: id, name, type, display_type, assigned_module, priority, target_amount, current_amount, progress_percentage, is_on_track, target_date, months_remaining, monthly_contribution, required_monthly_contribution, linked account IDs

**`buildGoalRecommendations(array $linkedGoals): array`**
- For each linked goal, generate a recommendation if:
  1. No monthly contribution set and not complete → "Start contributing to [goal]"
  2. Behind schedule → "[goal] is behind schedule" with shortfall amount
  3. Approaching deadline (≤6 months) and <75% complete → "[goal] target date is approaching"
- Each rec includes `'source' => 'goal'` and `'goal_id' => $goal['id']`
- On-track goals generate no recommendation (don't nag)

**Modify `structureActions()`**
- Add `'source' => $rec['source'] ?? 'module'` and `'goal_id' => $rec['goal_id'] ?? null` to action structure
- After building array, sort: goal-sourced first, then by priority within each group
- Re-index action IDs after sorting

### Step 2: InvestmentPlanService — Add goals to generatePlan()

After building `$recommendations` and before `$actions = $this->structureActions(...)`:
```
$goals = $this->getGoalsForPlan($userId, 'investment');
$goalRecommendations = $this->buildGoalRecommendations($goals['linked']);
$allRecs = array_merge($goalRecommendations, $recommendations);
$actions = $this->structureActions($allRecs, 'investment');
```

Add to return array:
```
'linked_goals' => $goals['linked'],
'unlinked_goals' => $goals['unlinked'],
```

### Step 3: RetirementPlanService — Same pattern

In the success path (after `$recommendations = ...`), merge goal recs. Add `linked_goals` and `unlinked_goals` to return array.

Also add to the early-return error path: `'linked_goals' => [], 'unlinked_goals' => []`.

### Step 4: EstatePlanService — Same pattern (after gate checks)

Only in the full-analysis path (after both age gate and IHT gate pass). The early returns (`not_applicable`) do NOT include goals.

Add to the error early-return: `'linked_goals' => [], 'unlinked_goals' => []`.

### Step 5: Create PlanGoalSection.vue

Shared component at `resources/js/components/Plans/Shared/PlanGoalSection.vue`:

**Props:** `linkedGoals: Array`, `unlinkedGoals: Array`

**Renders:**
- Nothing if both arrays empty (`v-if="hasContent"`)
- Linked goals section: `PlanSectionHeader` (title="Linked Goals", color="teal") + goal cards with `GoalProgressBar` (size="sm", showAmounts), progress status badge, months remaining, monthly contribution
- Unlinked goals prompt: blue-50 info box listing unlinked goal names + `router-link` to `/goals`

**Design compliance:** No amber/orange. No acronyms. No scores. Uses existing PlanSectionHeader and GoalProgressBar.

### Step 6-8: Add PlanGoalSection to each plan content component

Import `PlanGoalSection` and place between `PlanExecutiveSummary` and the CurrentSituation component:

```vue
<PlanGoalSection
  :linked-goals="plan.linked_goals || []"
  :unlinked-goals="plan.unlinked_goals || []"
/>
```

### Step 9: Tests

Create `tests/Unit/Services/Plans/GoalIntegrationTest.php` with:
- 3A.T1: savings-linked goal appears in investment plan's `linked_goals`
- 3A.T2: investment-linked goal appears in investment plan's `linked_goals`
- 3A.T3: retirement goal appears in retirement plan's `linked_goals`
- 3A.T4: wealth_accumulation goal appears in estate plan's `linked_goals`
- 3A.T5: goal-sourced actions appear before module-sourced actions
- 3A.T6: unlinked goal appears in `unlinked_goals`
- 3A.T7: no goals → `linked_goals` and `unlinked_goals` are empty arrays, plan works normally

### Step 10: Run tests + formatting + visual verification

- `./vendor/bin/pest tests/Unit/Services/Plans/` — all plan tests pass
- `./vendor/bin/pint` — formatting clean
- Playwright: verify plan pages show goal sections for preview personas with goals

## Verification

1. Run `./vendor/bin/pest tests/Unit/Services/Plans/` — all tests pass
2. Run `./vendor/bin/pint` — no formatting issues
3. Playwright: Login as peak_earners (David Mitchell), verify:
   - `/plans/investment` shows linked goals section with progress bars
   - `/plans/retirement` shows retirement goals if any
   - `/plans/estate` shows wealth_accumulation goals if any
   - Goal-sourced actions appear at top of actions list
4. Playwright: Login as young_saver (John Morgan), verify:
   - Plans show unlinked goals prompt if goals lack linked accounts
5. Run full test suite: `./vendor/bin/pest`
