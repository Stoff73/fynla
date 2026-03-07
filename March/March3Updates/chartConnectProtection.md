Link Protection Plan Actions to What-If Chart                                                                 │
│                                                                                                               │
│ Context                                                                                                       │
│                                                                                                               │
│ The protection plan's what-if comparison chart (horizontal bar chart) does not respond when action toggles    │
│ are switched on/off. The chart shows static data computed once at plan generation time. When a user toggles   │
│ an action, the plan.actions[i].enabled flag updates in Vuex, but the plan.what_if.projected_scenario object   │
│ passed to the chart never changes.                                                                            │
│                                                                                                               │
│ The retirement plan solves this with a Vue computed property (cascadedActions) that recalculates projections  │
│ client-side whenever action.enabled changes — no API round-trip needed.                                       │
│                                                                                                               │
│ Root Cause                                                                                                    │
│                                                                                                               │
│ In ProtectionPlanContent.vue (line 22-23):                                                                    │
│ :current-scenario="plan.what_if?.current_scenario"                                                            │
│ :projected-scenario="plan.what_if?.projected_scenario"   <!-- STATIC, never updates -->                       │
│                                                                                                               │
│ The projected_scenario is computed once by ProtectionPlanService.buildWhatIfData() using all enabled actions, │
│  then frozen in the plan data. The Vuex toggleAction mutation updates plan.actions[i].enabled but nothing     │
│ recomputes the projected scenario.                                                                            │
│                                                                                                               │
│ Solution: Frontend-Only Computed Property                                                                     │
│                                                                                                               │
│ No backend changes needed. All required data already exists:                                                  │
│                                                                                                               │
│ 1. plan.what_if.current_scenario — has base coverage, need, and gap values for all 3 types                    │
│ 2. plan.actions — each action has category ("Life Insurance", "Critical Illness", "Income Protection") and    │
│ impact_parameters.coverage_amount (the coverage this action adds)                                             │
│ 3. Vuex toggleAction mutation already updates action.enabled reactively                                       │
│                                                                                                               │
│ The fix adds a computedProjectedScenario computed property in ProtectionPlanContent.vue that mirrors the      │
│ backend buildWhatIfData() logic client-side — iterating enabled actions, summing coverage additions by        │
│ category, and computing projected gaps/coverage.                                                              │
│                                                                                                               │
│ ---                                                                                                           │
│ Changes                                                                                                       │
│                                                                                                               │
│ Single file: resources/js/components/Plans/Protection/ProtectionPlanContent.vue                               │
│                                                                                                               │
│ 1. Add computedProjectedScenario computed property:                                                           │
│                                                                                                               │
│ Replicates ProtectionPlanService.buildWhatIfData() logic (lines 261-336):                                     │
│ - Start with current_scenario base values (coverage, need, gap per type)                                      │
│ - For each action where enabled === true, check category to determine coverage type                           │
│ - Add impact_parameters.coverage_amount to the matching coverage type                                         │
│ - Compute projected gaps: max(0, gap - reduction)                                                             │
│ - Compute projected coverage: base_coverage + reduction                                                       │
│ - Return object with same shape as backend projected_scenario                                                 │
│                                                                                                               │
│ Category mapping (matches seeder categories and backend str_contains logic):                                  │
│ - category.includes('life') → life_insurance                                                                  │
│ - category.includes('critical') → critical_illness                                                            │
│ - category.includes('income') → income_protection                                                             │
│ - Other categories (Policy Review, Setup, General) → no coverage impact                                       │
│                                                                                                               │
│ 2. Update template to use computed projected scenario:                                                        │
│                                                                                                               │
│ <!-- Before -->                                                                                               │
│ :projected-scenario="plan.what_if?.projected_scenario"                                                        │
│                                                                                                               │
│ <!-- After -->                                                                                                │
│ :projected-scenario="computedProjectedScenario"                                                               │
│                                                                                                               │
│ Also update the projected ProtectionWhatIfControls slot to use the computed value, so the gap metrics (total  │
│ coverage gap, per-type gaps, additional premium) also update on toggle.                                       │
│                                                                                                               │
│ 3. Add income_protection_coverage to chartMetrics:                                                            │
│                                                                                                               │
│ Currently the chart only shows Life Insurance and Critical Illness. Add Income Protection so all three        │
│ coverage types that can be affected by actions are visible in the chart.                                      │
│                                                                                                               │
│ ---                                                                                                           │
│ How It Works (Data Flow)                                                                                      │
│                                                                                                               │
│ User clicks toggle                                                                                            │
│   → PlanActionCard emits 'toggle'                                                                             │
│   → PlanActionsList emits 'toggle'                                                                            │
│   → ProtectionPlanContent emits 'toggle-action'                                                               │
│   → ProtectionPlan.vue calls Vuex toggleAction                                                                │
│   → Vuex mutation updates plan.actions[i].enabled                                                             │
│   → Vue reactivity triggers computedProjectedScenario recomputation                                           │
│   → PlanWhatIfChart receives new projectedScenario prop                                                       │
│   → Chart re-renders with updated bars (via chartKey computed)                                                │
│   → ProtectionWhatIfControls also updates gap/premium values                                                  │
│                                                                                                               │
│ No API calls. Instant feedback. Same pattern as retirement plan.                                              │
│                                                                                                               │
│ ---                                                                                                           │
│ Verification                                                                                                  │
│                                                                                                               │
│ 1. ./dev.sh — start dev server                                                                                │
│ 2. php artisan db:seed — ensure data is seeded                                                                │
│ 3. Login as David Mitchell (peak_earners) → Plans → Protection Plan                                           │
│ 4. Observe chart shows "Current" and "With Actions" bars                                                      │
│ 5. Toggle an action OFF → chart "With Actions" bars should decrease                                           │
│ 6. Toggle it back ON → bars should return to previous values                                                  │
│ 7. Toggle ALL actions OFF → "With Actions" bars should match "Current" bars exactly                           │
│ 8. Gap metrics in "With Actions" column should also update on toggle                                          │
│ 9. Income Protection coverage should now appear in the chart                                                  │
│ 10. ./vendor/bin/pest — all existing tests still pass (no backend changes)  
