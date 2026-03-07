# Retirement Plan Updates - Deployment Notes

**Date:** 2-3 March 2026
**Branch:** `retirementPlanFix`
**Scope:** Executive summary UI restructure + database-driven action definitions + admin edit modal + goal pension-awareness fix + login auth fix + goal completion action blocks + cascading per-action what-if charts + contribution action funding source dropdown

---

## Summary

Eight changes in this deployment:

1. **Executive Summary restructure** — Replaced the narrative-only executive summary on the retirement plan page with a structured layout (greeting, goals table, actions table, closing).

2. **Database-driven retirement action definitions** — Moved all 10 hardcoded retirement action types into a configurable `retirement_action_definitions` database table. Added Admin Panel tab for managing actions. Fixed the `Employer_match` what-if bug where it fell to the default 1% gain instead of using contribution-based calculation.

3. **Admin edit modal** — Rewrote the retirement action edit/create modal with design system compliant structure, organised sections (Identity, Templates, Classification, Trigger Configuration, Settings), contextual hints per trigger condition, proper async save with server error display, and loading states.

4. **Goal pension-awareness fix** — Goal-sourced action evaluators (no-contribution, behind-schedule) now account for DC pension contributions. Previously, goals tracked `monthly_contribution` independently with no bridge to pension data, so a user contributing via workplace pension could still get "Start contributing" or "behind schedule" actions. The fix passes DC pension data through to `evaluateGoalActions()` and adds pension contributions to the effective monthly contribution when evaluating triggers.

5. **Login auth fix** — After email/MFA verification, the user's role and permissions were not being set in the Vuex store, causing the Admin menu link (and other role-dependent features) to not appear until page refresh.

6. **Cascading per-action what-if charts** — Replaced the flat action list + single aggregate chart with cascading action cards. Each action now has its own before/after projection chart. Actions cascade: action 1's result becomes action 2's baseline. Toggling off any action reactively collapses its chart and updates all subsequent charts. Works for both single-pension and multi-pension paths. Pension groups with no actions are filtered out (no empty headings).

7. **Goal completion action blocks** — Each incomplete linked goal card now shows an action block with: lump sum calculation (target − current = needed), tax year end deadline, and a recommended funding source. Funding source logic prioritises liquid cash accounts (current/instant access) and only falls back to GIA with a Capital Gains Tax warning when cash would breach the 6-month emergency fund. ISAs, premium bonds, notice accounts, and pensions are never recommended. Also replaced Mitchells' child support goals with a Drawdown Reserve Fund and increased current account balance to better fit their retirement-focused profile.

8. **Contribution action funding source dropdown** — Contribution-type retirement actions (Start contributions, Increase contributions, Maximise employer match) now show a "Fund from" dropdown recommending where the money should come from. Eligible accounts: liquid cash (current/instant access, non-ISA) and GIA. Cash accounts are auto-recommended first; GIA shows a "may cause a tax event" warning. Cash accounts near the 6-month emergency threshold show a warning too. Real users' selections persist across sessions via `plan_action_funding_selections` table. Preview users see the dropdown but selections reset on reload (PreviewWriteInterceptor blocks writes).

---

## Files Changed

### New Files (15)

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_03_000001_create_retirement_action_definitions_table.php` | Creates `retirement_action_definitions` table |
| `database/seeders/RetirementActionDefinitionSeeder.php` | Seeds 10 action definitions (7 agent, 3 goal) |
| `database/factories/RetirementActionDefinitionFactory.php` | Factory with `disabled()` and `goalSourced()` states |
| `app/Models/RetirementActionDefinition.php` | Model with scopes, template rendering, `findByKey()` |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Core service: evaluates triggers, renders templates, replaces hardcoded logic |
| `app/Http/Controllers/Api/RetirementActionDefinitionController.php` | Admin CRUD controller for action definitions |
| `app/Http/Requests/StoreRetirementActionDefinitionRequest.php` | Form request validation |
| `resources/js/components/Admin/AdminRetirementActions.vue` | Admin table component with toggle, edit, delete; passes saving/error state to modal |
| `resources/js/components/Admin/RetirementActionModal.vue` | Design system compliant detail/edit modal with sectioned form, async save, server error display |
| `resources/js/components/Plans/Retirement/RetirementExecutiveSummary.vue` | Retirement-specific executive summary component |
| `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php` | 17 unit tests for the service (includes 3 pension-awareness tests) |
| `resources/js/components/Plans/Retirement/CascadingActionChart.vue` | Per-action before/after projection chart (180px, slate/green series, difference badge) |
| `tests/Feature/Api/RetirementActionDefinitionTest.php` | 10 feature tests for admin API |
| `database/migrations/2026_03_04_000001_create_plan_action_funding_selections_table.php` | Creates `plan_action_funding_selections` table for persisting funding source choices |
| `app/Models/PlanActionFundingSelection.php` | Model with `getForUser()` and `upsertSelection()` static helpers |

### Modified Files (19)

| File | Change |
|------|--------|
| `app/Agents/RetirementAgent.php` | Injects `RetirementActionDefinitionService`, delegates `generateRecommendations()` to service |
| `app/Services/Plans/RetirementPlanService.php` | Injects service, uses it for goal actions + what-if impact type lookup. Rewrote `buildExecutiveSummary()` for structured data. Added `enrichActionsWithCascadeParams()` and `calculateTotalAnnualContributions()` for per-action cascade data. Added `current_annual_contribution` to `frontend_calc_params`. Added `enrichActionsWithFundingSource()` with `buildEligibleFundingAccounts()` and `autoRecommendFundingAccount()` for contribution action funding sources. |
| `app/Services/Plans/BasePlanService.php` | Added `funding_source` to `formatGoalForPlan()` with tax-aware `resolveFundingSource()` method (cash-first, GIA fallback with CGT warning). Uses `ResolvesExpenditure` trait for emergency fund threshold. |
| `resources/js/components/Plans/Shared/PlanGoalSection.vue` | Added action block below incomplete goal cards (lump sum calc, tax year deadline, funding source with optional CGT warning) |
| `resources/js/data/personas/peak_earners.json` | Replaced child support goals with Drawdown Reserve Fund; increased David's Current Account to £25,000 |
| `database/seeders/DatabaseSeeder.php` | Added `RetirementActionDefinitionSeeder` to Phase 1 seeders |
| `routes/api.php` | Added admin retirement-actions route group (CRUD + toggle) |
| `resources/js/views/Admin/AdminPanel.vue` | Added "Retirement Actions" tab |
| `resources/js/services/adminService.js` | Added 5 CRUD methods for retirement action definitions |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Replaced flat action list + single chart with cascading per-action charts. Both single and multi-pension paths. Added `cascadedActions`, `cascadedActionMap`, `projectSeries()`. Removed empty pension group headings. |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Swapped shared `PlanExecutiveSummary` for retirement-specific `RetirementExecutiveSummary` |
| `resources/js/views/Login.vue` | Fixed `handleVerified()` and `handleMFAVerified()` to call `fetchUser()` (sets role/permissions) |
| `resources/js/views/Register.vue` | Fixed `completeRegistration()` to call `fetchUser()` (sets role/permissions) |
| `app/Http/Controllers/Api/Plans/PlanController.php` | Added `updateFundingSource()` endpoint with account ownership validation |
| `resources/js/components/Plans/Shared/PlanActionCard.vue` | Added "Fund from" dropdown with eligible accounts, balance display, and warning text for contribution actions |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Passes through `update-funding-source` emit |
| `resources/js/views/Plans/RetirementPlan.vue` | Handles `update-funding-source` emit, dispatches Vuex `updateActionFundingSource` action |
| `resources/js/services/plansService.js` | Added `updateFundingSource()` API method |
| `resources/js/store/modules/plans.js` | Added `setActionFundingSource` mutation and `updateActionFundingSource` action with optimistic local update |
| `routes/api.php` | Added `PUT /plans/{type}/funding-source` route |

---

## Upload to Production

### 1. Run migration on server

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan db:seed --class=RetirementActionDefinitionSeeder --force
```

### 2. Build frontend

```bash
./deploy/fynla-org/build.sh
```

### 3. Upload files via SiteGround File Manager

**PHP files (13):**
- `app/Models/RetirementActionDefinition.php`
- `app/Models/PlanActionFundingSelection.php`
- `app/Services/Retirement/RetirementActionDefinitionService.php`
- `app/Services/Plans/BasePlanService.php`
- `app/Services/Plans/RetirementPlanService.php`
- `app/Http/Controllers/Api/RetirementActionDefinitionController.php`
- `app/Http/Controllers/Api/Plans/PlanController.php`
- `app/Http/Requests/StoreRetirementActionDefinitionRequest.php`
- `app/Agents/RetirementAgent.php`
- `database/migrations/2026_03_03_000001_create_retirement_action_definitions_table.php`
- `database/migrations/2026_03_04_000001_create_plan_action_funding_selections_table.php`
- `database/seeders/RetirementActionDefinitionSeeder.php`
- `routes/api.php`

**Frontend build:**
- `public/build/` (entire directory)

### 4. Clear caches via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## What Changed (Detail)

### Executive Summary — Before
- Single `narrative` string (multi-paragraph text block)
- Listed every pension by name and value inline
- All rendered via shared `PlanExecutiveSummary.vue` as plain text

### Executive Summary — After
- **Greeting:** "Dear {firstName},"
- **Introduction:** Personalised retirement goal statement
- **Retirement Goals table:** Goal name, target amount, progress %, on-track badge (shown only if user has retirement goals)
- **Key Actions table:** Top 5 actions by priority with priority badge
- **Closing:** Contextual message based on on-track status

### Retirement Action Definitions — Before
- 10 action types hardcoded across `RetirementAgent.php`, `ContributionOptimizer.php`, and `BasePlanService.php`
- Trigger thresholds (5% employer match, 10% income gap, etc.) embedded in PHP
- Action text, priorities, categories all in code
- `Employer_match` what-if used `str_contains()` which fell to default 1% gain
- Goal-sourced actions in separate `buildGoalRecommendations()` method

### Retirement Action Definitions — After
- All 10 action types stored in `retirement_action_definitions` database table
- Configurable via Admin Panel > Retirement Actions tab
- Each definition has: key, source, title/description/action templates with `{placeholder}` substitution, category, priority, scope, what-if impact type, JSON trigger_config with editable thresholds, enabled toggle, sort order
- `RetirementActionDefinitionService` evaluates triggers against user data, renders templates
- `Employer_match` now correctly uses `what_if_impact_type: 'contribution'` from the database
- Goal actions use the same system (source='goal')
- Admin can: create, edit, delete, toggle enable/disable, adjust thresholds without code changes

### Admin Edit Modal — Before
- Basic flat form with no visual sections
- Save was synchronous (no loading state, no server error feedback in modal)
- No contextual help for trigger conditions

### Admin Edit Modal — After
- Design system compliant modal: backdrop, header with close button, scrollable body, gray-50 footer
- Five organised sections: Identity, Templates, Classification, Trigger Configuration, Settings
- Contextual hint text per trigger condition explaining what it does
- Threshold unit labels (%, decimal, etc.) next to input fields
- Async save: spinner on button, disabled state during save, modal stays open on error
- Server validation errors displayed inline at top of modal
- Key shown as monospace badge in header when editing

### Goal Pension-Awareness Fix — Before
- `evaluateGoalActions($goals)` only received goal data — no pension data
- `evaluateGoalNoContribution()` checked `$goal['monthly_contribution'] == 0` — if the goal record had no contribution, it triggered even if the user was contributing via workplace pension
- `evaluateGoalOffTrack()` checked `$goal['is_on_track'] == false` — no awareness of pension contributions covering the shortfall
- Result: Users like David Mitchell (8% employee + 8% employer = £23,200/yr workplace pension) could get "Start contributing" or "behind schedule" actions on pension-related goals

### Goal Pension-Awareness Fix — After
- `evaluateGoalActions($goals, $dcPensions = null)` now accepts optional DC pension collection
- New `calculateTotalMonthlyPensionContributions($dcPensions)` method sums all DC pension annual contributions / 12
- `evaluateGoalNoContribution()` uses `$effectiveContribution = $monthlyContribution + $monthlyPensionContribution` — suppresses action if effective > 0
- `evaluateGoalOffTrack()` uses effective contribution — if `$effectiveContribution >= $required`, treats goal as on-track regardless of goal record
- `RetirementPlanService.generatePlan()` passes `$dcPensions` collection to `evaluateGoalActions()`
- 3 new tests: suppress no-contribution with pension, suppress behind-schedule when pension covers shortfall, still trigger when pension insufficient

### Cascading Per-Action What-If Charts — Before
- All actions shown as flat list of toggle cards
- Single aggregate chart (PensionGrowthProjectionChart for single-pension, portfolio chart for multi-pension)
- Toggling actions only affected the aggregate chart via linear interpolation
- Multi-pension path showed empty pension group headings for pensions with no actions

### Cascading Per-Action What-If Charts — After
- Each action card is followed by its own CascadingActionChart (180px, before/after lines)
- Actions cascade: action 1's "after" becomes action 2's "before" baseline
- Toggling off an action collapses its chart (before = after, no difference badge) and shifts all subsequent charts down
- Backend `enrichActionsWithCascadeParams()` computes `cascade_params.additional_monthly` per action using DistributionAccount for contribution types and PlanConfigService rates for tax/consolidation/default
- `current_annual_contribution` added to `frontend_calc_params` so frontend projections include existing contributions
- `cascadedActions` computed runs globally across all actions; `cascadedActionMap` provides lookup by action ID for multi-pension template
- Pension groups with zero actions are filtered out (no empty headings)
- What-if summary metrics remain at the bottom showing cumulative effect

### Contribution Action Funding Source Dropdown — New
- Contribution-type actions (Employer_match, Start_contributions, Contribution_increase) now show a "Fund from" row below the description
- **Dropdown** lists eligible accounts with `name (£balance)` format
- **Single account** shows as static text (no dropdown)
- **No accounts** shows "No eligible accounts" in italic
- **Warning text** in red below dropdown when selected account has a warning
- **Eligible accounts:**
  - Cash: non-ISA savings accounts of type current_account, instant_access, business_current, business_savings — ordered by balance desc
  - GIA: investment accounts of type gia — ordered by value desc
- **Warnings per account:**
  - Cash: "Withdrawing would reduce your emergency fund below 6 months of expenditure." if `balance - (additional_monthly * 12) < monthlyExpenditure * 6`
  - GIA: always "Using this account may cause a tax event."
- **Auto-recommendation priority:** safe cash (no warning) → cash with warning → GIA
- **Persistence:** Real users' selections saved to `plan_action_funding_selections` table via `PUT /api/plans/{type}/funding-source`. Preview users' selections work locally in the session but are blocked by PreviewWriteInterceptor (returns fake success, not persisted).
- **Vuex:** Optimistic local update via `setActionFundingSource` mutation, then async persist

### Goal Completion Action Blocks — New
- Each incomplete linked goal card now shows a blue-50 action block with:
  - **Lump sum calculation:** `{target} − {current} = {lump sum} lump sum needed`
  - **Deadline:** "Before tax year end — 5 April {year}" (derived from current date)
  - **Funding source:** Recommended account name, or "Link an account" prompt if none found
  - **CGT warning** (red text): Shown only when GIA is the fallback source
- **Funding source priority:**
  1. Liquid cash (current_account, instant_access) — only if withdrawal won't breach 6-month emergency threshold
  2. GIA — with Capital Gains Tax warning explaining why cash wasn't used
  3. No recommendation — prompt to link an account
- **Never recommended:** ISA, premium bonds, notice accounts, pensions, VCT/EIS, employee share schemes

### Peak Earners Persona Update
- Removed "William's House Deposit Help" (£40k child support) and "Charlotte's Gap Year Fund" (£15k child support) — didn't fit a couple in their 50s focused on retirement
- Added "Drawdown Reserve Fund" (£50k target, £35k current, retirement module) — cash reserve to bridge early retirement at 58 before state pension at 67
- David's Current Account increased from £8,450 to £25,000 so retirement goal lump sums resolve to cash funding

### Login Auth Fix — Before
- After email verification: `handleVerified()` set token and user but NOT role/permissions
- After MFA verification: `handleMFAVerified()` set token and user but NOT role/permissions
- `isAdmin` getter returned false because `state.role` was null
- Admin menu only appeared after page refresh (when `App.vue` called `fetchUser()`)

### Login Auth Fix — After
- Both handlers now call `await store.dispatch('auth/fetchUser')` after setting the token
- `fetchUser()` calls `/api/auth/user` which returns user, role, AND permissions
- Admin menu and role-dependent features work immediately after verification

---

## Test Checklist

### Executive Summary
- [ ] Preview persona with retirement data (e.g. peak_earners) shows greeting, introduction, goals table, actions table, closing
- [ ] Preview persona without retirement goals (e.g. young_family) hides goals table, shows actions table
- [ ] User with no retirement profile sees empty state message
- [ ] User on track (no income gap) sees green closing statement

### Action Definitions & Admin Modal
- [ ] Admin Panel > Retirement Actions tab shows 10 seeded actions
- [ ] Click "Edit" on any row > modal opens with all fields populated
- [ ] Modal shows 5 sections: Identity, Templates, Classification, Trigger Configuration, Settings
- [ ] Trigger condition dropdown shows contextual hint text
- [ ] Edit a field and click Update > spinner shows, modal closes on success, table refreshes
- [ ] Submit invalid data > server errors display inline in modal, modal stays open
- [ ] Toggle an action off > regenerate retirement plan > that action is absent
- [ ] Edit a threshold (e.g. employer match 5% to 8%) > regenerate > observe changed trigger behaviour
- [ ] Create a new action via "Add Action" button > fills form > Create > appears in table
- [ ] Non-admin user gets 403 on admin endpoints
- [ ] Employer_match what-if now shows contribution-based improvement (not default 1%)

### Goal Pension-Awareness Fix
- [ ] User with workplace pension and pension-related goal with zero goal contribution does NOT get "Start contributing" action
- [ ] User with workplace pension covering goal shortfall does NOT get "behind schedule" action
- [ ] User with workplace pension insufficient to cover shortfall still gets "behind schedule" action
- [ ] 17 unit tests pass: `./vendor/bin/pest tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php`

### Login Auth Fix
- [ ] Login as admin (chris@fynla.org) > enter verification code > Admin menu link appears immediately
- [ ] Login as regular user > no admin link shown
- [ ] Page refresh still works correctly for admin users

### Goal Completion Action Blocks
- [ ] peak_earners retirement plan shows action block on incomplete goals with lump sum calculation
- [ ] Funding source shows "David's Current Account" (cash) for goals where withdrawal stays above 6-month emergency threshold
- [ ] Large goals (ISA Wealth Building, Early Retirement) fall back to GIA with CGT warning text in red
- [ ] Goals at 100% progress do NOT show the action block
- [ ] Unlinked goals show "Link an account to identify a funding source"
- [ ] Tax year end date shows correctly (5 April 2026 if before April, 5 April 2027 if after)
- [ ] Mitchells no longer have house deposit or gap year goals; replaced with Drawdown Reserve Fund
- [ ] Plan tests pass: `./vendor/bin/pest tests/Unit/Services/Plans/`

### Cascading Per-Action Charts
- [ ] peak_earners retirement plan shows each action card with its own chart below it
- [ ] Charts show slate "Before" line and green "After this action" line
- [ ] Green "+£X at retirement" badge shows on charts where action has positive impact
- [ ] Toggle off the first action > its chart collapses (before = after), subsequent charts shift down
- [ ] Toggle all actions off > all charts show flat lines (before = after everywhere)
- [ ] Toggle all actions on > last action's "after" reflects cumulative effect of all actions
- [ ] No empty pension group headings for pensions with no recommendations
- [ ] What-if metrics at bottom still show cumulative current vs projected
- [ ] Plan tests pass: `./vendor/bin/pest tests/Unit/Services/Plans/`

### Contribution Action Funding Source Dropdown
- [ ] peak_earners retirement plan shows "Fund from" dropdown on contribution actions (Start contributions, Increase contributions, Employer match)
- [ ] Dropdown lists eligible cash and GIA accounts with balances
- [ ] Select a GIA → red warning "Using this account may cause a tax event."
- [ ] Select a cash account near emergency threshold → emergency fund warning appears
- [ ] Non-contribution actions (consolidate, state pension, tax optimisation) do NOT show funding source
- [ ] Single eligible account → shows as text, not dropdown
- [ ] Refresh page as preview user → selection resets to auto-recommended
- [ ] Plan tests pass: `./vendor/bin/pest tests/Unit/Services/Plans/`

### General
- [ ] All tests pass: `./vendor/bin/pest` (960+ tests)
- [ ] No amber/orange colours present (Rule 9)
- [ ] Currency formatted via currencyMixin (Rule 6)
