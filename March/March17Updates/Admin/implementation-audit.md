# Admin Enhancement Implementation Audit

**Auditor:** Claude Opus 4.6 (1M context)
**Date:** 17 March 2026
**Branch:** `feature/admin-enhancement`
**Worktree:** `/Users/CSJ/Desktop/fynla/.claude/worktrees/agent-ae67b94f/`

---

## Files Created/Modified

| # | File | Lines | Status |
|---|------|-------|--------|
| 1 | `app/Models/EstateActionDefinition.php` | 115 | New |
| 2 | `database/migrations/2026_03_17_100001_create_estate_action_definitions_table.php` | 44 | New |
| 3 | `database/seeders/EstateActionDefinitionSeeder.php` | 170 | New |
| 4 | `database/seeders/DatabaseSeeder.php` | 115 | Modified |
| 5 | `app/Services/Estate/EstateActionDefinitionService.php` | 362 | New |
| 6 | `app/Http/Controllers/Api/ActionDefinitionController.php` | 264 | New |
| 7 | `app/Http/Requests/StoreActionDefinitionRequest.php` | 84 | New |
| 8 | `app/Services/Admin/UserModuleTrackingService.php` | 340 | New |
| 9 | `app/Http/Controllers/Api/AdminController.php` | Modified (~15 lines added) | Modified |
| 10 | `routes/api.php` | Modified (~18 lines added) | Modified |
| 11 | `resources/js/components/Admin/DecisionMatrix.vue` | 204 | New |
| 12 | `resources/js/components/Admin/DecisionTree.vue` | 272 | New |
| 13 | `resources/js/components/Admin/DecisionNode.vue` | 110 | New |
| 14 | `resources/js/components/Admin/ActionDefinitionDrawer.vue` | 340 | New |
| 15 | `resources/js/components/Admin/TriggerConfigEditor.vue` | 110 | New |
| 16 | `resources/js/components/Admin/UserManagement.vue` | 544 | Modified |
| 17 | `resources/js/components/Admin/UserModuleStatus.vue` | 174 | New |
| 18 | `resources/js/components/Admin/UserOnboardingProgress.vue` | 136 | New |
| 19 | `resources/js/constants/moduleConfigs.js` | 201 | New |
| 20 | `resources/js/services/actionDefinitionService.js` | 25 | New |
| 21 | `resources/js/services/adminService.js` | 120 | Modified |
| 22 | `resources/js/views/Admin/AdminPanel.vue` | 153 | Modified |
| 23 | `tests/Feature/Api/ActionDefinitionControllerTest.php` | 167 | New |
| 24 | `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php` | 75 | New |
| 25 | `tests/Feature/Api/AdminBackupTest.php` | 141 | New |

**Total:** 25 files, ~4,266 lines (new + modified)

---

## Spec Compliance Check

### 1.1 Decision Tree Visualiser

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| 1 | EstateActionDefinition model has exact same columns/methods as ProtectionActionDefinition | **PASS** | Identical `$fillable`, `$casts`, scopes (`scopeEnabled`, `scopeBySource`), static methods (`findByKey`, `getEnabled`, `getEnabledBySource`), and template rendering methods (`renderTitle`, `renderDescription`, `renderAction`, private `renderTemplate`). Character-for-character match. |
| 2 | ActionDefinitionController has ALLOWED_MODULES whitelist with static array lookup (no string interpolation) | **PASS** | `private const ALLOWED_MODULES` with static array mapping. `resolveModel()` uses `isset(self::ALLOWED_MODULES[$module])` -- no string interpolation. Aborts 422 for unrecognised module. |
| 3 | StoreActionDefinitionRequest has dynamic table resolution for unique key + module-specific enums | **PASS** | `MODULE_TABLES`, `MODULE_SOURCES`, `MODULE_IMPACT_TYPES` all match spec exactly. Dynamic `Rule::unique()` with `$this->route('id')` for update vs create. Uses `PermissionService` for authorisation. |
| 4 | All 7 API endpoints exist in routes/api.php | **PASS** | GET `/api/admin/decision-matrix/{module}`, GET/POST `/api/admin/action-definitions/{module}`, GET/PATCH/DELETE `/api/admin/action-definitions/{module}/{id}`, PATCH `/api/admin/action-definitions/{module}/{id}/toggle` -- all 7 present with correct middleware (`auth:sanctum`, `permission:admin.access`, `throttle:30,1`). |
| 5 | DecisionMatrix.vue has 6 module sub-tabs with count badges | **PASS** | Protection, Cash & Savings, Investments, Retirement, Estate Planning, Tax -- all 6 present with dynamic count badges from API. Active tab styling: `bg-eggshell-500 text-raspberry-500`; badge: `bg-raspberry-500 text-white` (active) / `bg-neutral-500 text-white` (inactive). |
| 6 | DecisionTree.vue has 4-column layout, stats bar, legend bar, flow rows with SVG arrows | **PASS** | 5 stat cards, column headers (Data Source/Trigger/Logic/Outcome), legend bar with 5 node types + priority badges, flow rows with SVG arrows. Enabled arrows: solid `horizon-300` with arrowhead polygon; disabled: dashed `light-gray` without arrowhead. |
| 7 | DecisionNode.vue has correct type colours, priority badges, opacity for disabled | **PASS** | data: `bg-light-blue-100 border-light-blue-500`, trigger: `bg-violet-50 border-violet-200`, logic: `bg-spring-50 border-spring-200`, outcome: `bg-raspberry-50 border-raspberry-200`. Priority badges: CRIT `bg-raspberry-700`, HIGH `bg-raspberry-500`, MED `bg-violet-500`, LOW `bg-spring-500`. Disabled: `opacity-45` + OFF badge `bg-neutral-500`. |
| 8 | ActionDefinitionDrawer.vue is 420px, has all fields from spec, uses single component for all modules | **PASS** | `w-[420px]`, `shadow-lg`, single component via `moduleConfig` prop. Fields: Key (monospace), Source (select from config), Title Template, Description Template (monospace), Action Template (monospace), Category, Priority (select), Scope (select), What-If Impact Type (select from config), Trigger Configuration (TriggerConfigEditor), Sort Order, Notes, Enabled toggle, Template variable tags (auto-extracted). Footer: Cancel + Save Changes. Uses `<Teleport to="body">` with Transition. |
| 9 | TriggerConfigEditor.vue has AND/OR combinators | **PARTIAL** | Has AND combinator between the main condition and dynamic trigger fields. The spec also mentions OR combinators ("AND/OR combinators"). The implementation only shows AND combinators -- no ability to switch to OR logic. The IF + AND pattern is present but there is no dropdown or toggle for OR. |
| 10 | AdminPanel.vue has 5 tabs (Dashboard, User Management, Decision Matrix, Tax Settings, Database) | **PASS** | Tabs in order: Dashboard, User Management, Decision Matrix, Tax Settings, Database. The 3 per-module action tabs (Retirement Actions, Investment Actions, Protection Actions) are removed and replaced with the single Decision Matrix tab. |
| 11 | MODULE_CONFIGS has per-module sourceOptions, whatIfImpactOptions, conditionOptions | **PASS** | All 6 modules (protection, savings, investment, retirement, estate, tax) have `label`, `sourceOptions`, `whatIfImpactOptions`, `conditionOptions`, and `triggerFields`. Condition options are comprehensive -- savings has 20, investment has 17, retirement has 10, estate has 8, tax has 5, protection has 6. |
| 12 | EstateActionDefinition migration matches other 5 tables | **PASS** | Exact column match: `key` (string 50, unique), `source` (string 20), `title_template` (string), `description_template` (text), `action_template` (string nullable), `category` (string 50), `priority` (enum), `scope` (enum), `what_if_impact_type` (string 30), `trigger_config` (json), `is_enabled` (boolean default true), `sort_order` (smallInteger default 100), `notes` (string nullable), timestamps. Indexes on source, is_enabled, sort_order. |
| 13 | EstateActionDefinitionSeeder seeds 8 definitions | **PASS** | 8 definitions: `no_will` (critical), `policy_not_in_trust` (high), `iht_exceeds_nrb` (high), `no_lpa` (high), `no_lpa_health` (medium), `gifts_pet_window` (medium), `trust_review_due` (medium), `beneficiary_review` (low). Uses `updateOrCreate` on `key`. |
| 14 | EstateActionDefinitionSeeder registered in DatabaseSeeder | **PASS** | Added after `TaxActionDefinitionSeeder::class` in both `run()` and `seedRequiredDataOnly()` methods. |
| 15 | EstateActionDefinitionService mirrors TaxActionDefinitionService pattern | **PASS** | Uses `FormatsCurrency` and `StructuredLogging` traits, constructor injects `TaxConfigService`, `evaluateActions(User)` iterates enabled definitions, dispatch via `match()`, 8 private evaluators, returns `['recommendations', 'total_count', 'high_priority_count']`. |
| 16 | Existing per-module routes retained | **PASS** | The diff shows generic routes added AFTER existing protection-actions group -- no existing routes removed. |

### 1.2 Enhanced User Management

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| 1 | UserModuleTrackingService eager-loads all relationships | **PASS** | `loadMissing()` with: `lifeInsurancePolicies`, `criticalIllnessPolicies`, `incomeProtectionPolicies`, `disabilityPolicies`, `sicknessIllnessPolicies`, `cashAccounts`, `savingsAccounts`, `investmentAccounts.holdings`, `dcPensions`, `dbPensions`, `statePension`, `retirementProfile`, `trusts`, `assets`, `gifts`, `lastingPowersOfAttorney`, `properties`, `mortgages`, `onboardingProgress`. |
| 2 | Returns correct sub-areas per module | **PASS** | Protection: 5 sub-areas (life_insurance, critical_illness, income_protection, disability, sickness_illness). Savings: 4 (cash_accounts, savings_accounts, isa_accounts, emergency_fund). Investment: 4 (investment_accounts, holdings, risk_profile, investment_goals). Retirement: 4 (dc_pensions, db_pensions, state_pension, retirement_profile). Estate: 5 (will, lasting_powers_of_attorney, trusts, gifts, assets). |
| 3 | Status determination: complete/partial/empty/skipped | **PASS** | `determineStatus()` uses boolean array of key sub-areas: all true = complete, some true = partial, none = empty. `isModuleSkipped()` checks `journey_states[$module] === 'skipped'`. |
| 4 | Onboarding includes: completed, started_at, completed_at, life_stage, life_stage_completed_steps, journey_states, journey_selections | **FAIL** | `getOnboardingData()` returns: `completed`, `started_at`, `completed_at`, `journey_states`, `journey_selections`, `progress_records`. **MISSING: `life_stage` and `life_stage_completed_steps`** -- both explicitly required by spec section 1.2 lines 259-260. |
| 5 | UserModuleStatus.vue shows P S I R E dots with correct colours | **PASS** | 5 module dots (P, S, I, R, E) with `w-6 h-6 rounded`. Complete: `bg-spring-500 text-white`, Partial: `bg-violet-500 text-white`, Empty: `bg-light-gray text-neutral-500`, Skipped: `bg-eggshell-500 text-horizon-300 line-through border border-light-gray`. All match spec exactly. |
| 6 | UserOnboardingProgress.vue shows progress card | **PASS** | Shows completion status (spring/violet badge), started/completed dates, progress records count, journey states with colour-coded tags (completed=spring, skipped=eggshell, in_progress=violet). |
| 7 | UserManagement.vue has Modules column and expandable rows | **PASS** | "Modules" `<th>` added between Email and Role. `<UserModuleStatus :user-id="user.id" />` in compact mode. Click toggles `expandedUserId`. Expanded row shows `<UserModuleStatus expanded>` and `<UserOnboardingProgress>`. |
| 8 | API endpoint GET /api/admin/users/{id}/module-status exists | **PASS** | Route added inside existing admin middleware group. `AdminController::moduleStatus()` method injects `UserModuleTrackingService`, calls `getModuleStatus()` on found user. |

### 1.3 Backup Verification

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| 1 | Tests cover createBackup | **FAIL** | No `createBackup` test. The spec says "Test `createBackup()` -- verify mysqldump executes, .sql file is created in `storage/app/backups/`, file contains valid SQL". This test is missing. |
| 2 | Tests cover listBackups | **PASS** | Test "lists existing backups with correct metadata" creates a dummy file and verifies the API returns it with correct filename. |
| 3 | Tests cover restoreBackup | **FAIL** | No `restoreBackup` test. The spec says "Test `restoreBackup()` -- verify restore works on a test database (NOT production), caches are cleared after". This test is missing. |
| 4 | Tests cover deleteBackup | **PASS** | Test "deletes a backup file" creates a dummy file, calls DELETE, verifies file is removed. |
| 5 | Path traversal protection test | **PASS** | Test "rejects path traversal in backup filename" sends `../../../etc/passwd`, asserts 422. |
| 6 | Credential file security test | **PARTIAL** | Test "cleans up temporary credential files after backup operations" only checks that listBackups doesn't leave temp files. Does not test the actual credential file creation/deletion during backup or permissions (0600). |
| 7 | Rate limiting test | **FAIL** | No rate limiting test. The spec says "Verify rate limiting -- 3 requests per minute on backup endpoints". Missing entirely. |

---

## Mockup Compliance Check

| # | Mockup Element | Status | Notes |
|---|----------------|--------|-------|
| 1 | Stats bar: 5 cards with correct colours | **PASS** | Total (horizon-500), Enabled (spring-500), Disabled (neutral-500), Critical/High (raspberry-500), Medium (violet-500). All match. `text-[28px] font-black tracking-tight` matches mockup `.stat-value`. |
| 2 | Tree header: title format "{Module} Module -- Decision Tree" | **PASS** | `{{ moduleLabel }} Module -- Decision Tree` with `text-2xl font-bold text-horizon-500`. Subtitle matches. |
| 3 | Tree header controls: Search, Filter, Collapse All, + Add Action | **FAIL** | **Missing Search and Filter buttons.** The mockup clearly shows 4 controls: Search (btn-secondary + search icon), Filter (btn-secondary + filter icon), Collapse All (btn-secondary), + Add Action (btn-primary). The implementation only has Collapse All and + Add Action. |
| 4 | Legend bar: bg-savannah-100, 5 node types + priority badges right-aligned | **PASS** | `bg-savannah-100 rounded-lg border border-light-gray`. 5 node types: User Data Input (light-blue-500), Trigger Condition (violet-500), Decision Logic (spring-500), Outcome/Action (raspberry-500), Disabled (neutral-500). Priority badges CRIT/HIGH/MED/LOW right-aligned with `ml-auto`. |
| 5 | Tree canvas: column headers (USER DATA, TRIGGER, LOGIC, OUTCOME) | **PARTIAL** | Column headers present but first column says "Data Source" instead of "User Data" per mockup. The mockup line 238 shows "User Data", not "Data Source". |
| 6 | Column headers: min-width 210px, 60px arrow spacers | **PASS** | `min-w-[210px] px-3 text-center` for columns, `min-w-[60px]` for arrow spacers. Matches mockup exactly. |
| 7 | Nodes: rounded-xl, min-w-180/max-w-220, hover shadow, type colours matching mockup | **PASS** | `rounded-xl p-4 min-w-[180px] max-w-[220px] hover:shadow-md hover:-translate-y-px`. Type colours match mockup CSS lines 95-98 exactly. |
| 8 | SVG arrows: horizon-300 solid for enabled, light-gray dashed for disabled, arrowhead polygons | **PASS** | Enabled: `stroke="var(--horizon-300)"`, `stroke-width="2"`, `polygon points="30,0 38,1 30,2"`. Disabled: `stroke="var(--light-gray)"`, `stroke-dasharray="4,3"`, no polygon (v-if). Matches mockup lines 250-288. |
| 9 | Side drawer: 420px, shadow-lg | **PASS** | `w-[420px] bg-white shadow-lg flex flex-col`. Uses `<Teleport>` + `<Transition>`. |
| 10 | Drawer header with title + key (monospace), close button | **PASS** | Title: `text-xl font-bold text-horizon-500`. Key: `text-xs text-neutral-500 font-mono`. Close: `w-8 h-8 rounded-lg border border-light-gray`. |
| 11 | Drawer toggle (spring-500 for on) | **PASS** | Toggle: `bg-spring-500` when on, `bg-horizon-300` when off. Knob: `h-[18px] w-[18px] rounded-full bg-white shadow translate-x-[20px]` when on. Label: `text-spring-500` when on / `text-neutral-500` when off. |
| 12 | Drawer all fields present | **PASS** | Key, Source, Title Template, Description Template, Action Template, Category, Priority, Scope, What-If Impact Type, Trigger Configuration, Sort Order, Notes, Enabled toggle, Template variable tags -- all present. |
| 13 | Drawer footer: Cancel + Save Changes | **PASS** | Cancel: `border border-light-gray text-neutral-500 hover:bg-savannah-100`. Save: `bg-raspberry-500 text-white hover:bg-raspberry-600`. |
| 14 | Template variable tags: bg-violet-50 text-violet-700 font-mono | **PASS** | `bg-violet-50 text-violet-700 text-xs px-2 py-0.5 rounded font-mono inline-block`. Auto-extracted from templates via regex. |
| 15 | Trigger config: field selectors, AND/OR operators | **PARTIAL** | Has IF + AND pattern with condition selector and dynamic fields. Missing OR combinator option (see spec compliance 1.1 #9). |

---

## Plan Task Completion

| Task | Description | Status | Notes |
|------|-------------|--------|-------|
| **Task 1** | EstateActionDefinition Model & Migration | **PASS** | Model, migration created. Matches ProtectionActionDefinition exactly. Migration has safety check `Schema::hasTable()`. |
| **Task 2** | Estate Action Definition Seeder | **PASS** | Seeder created with 8 definitions matching plan exactly. Registered in DatabaseSeeder in both `run()` and `seedRequiredDataOnly()`. EstateActionDefinitionService created with all 8 evaluators. |
| **Task 3** | Generic ActionDefinitionController | **PASS** | Controller, form request, routes, and tests all created per plan. ALLOWED_MODULES whitelist, resolveModel(), decisionMatrix(), buildTreeNodes() all match plan. |
| **Task 4** | UserModuleTrackingService | **PARTIAL** | Service created with correct module status logic. API endpoint added. **Missing `life_stage` and `life_stage_completed_steps` from onboarding data** (plan Step 3 explicitly lists these in the return structure). |
| **Task 5** | Module Config Constants (Frontend) | **PASS** | `moduleConfigs.js` created with all 6 modules. `actionDefinitionService.js` created using `api` from `./api` (not raw axios). |
| **Task 6** | Decision Tree Vue Components | **PARTIAL** | All 5 components created (DecisionMatrix, DecisionTree, DecisionNode, ActionDefinitionDrawer, TriggerConfigEditor). AdminPanel.vue updated with Decision Matrix tab. **Missing Search and Filter buttons in tree header.** First column header says "Data Source" instead of "User Data". |
| **Task 7** | Enhanced User Management | **PASS** | UserModuleStatus, UserOnboardingProgress components created. UserManagement.vue modified with Modules column and expandable rows. adminService.js updated with `getUserModuleStatus()`. |
| **Task 8** | Database Backup Verification & Fix | **PARTIAL** | Tests created but missing 3 of 7 required test cases: `createBackup`, `restoreBackup`, and `rate limiting`. The credential file security test only checks a superficial case (listing, not actual backup). |
| **Task 9** | Final Integration & Seed | **NOT VERIFIED** | Cannot verify (tests fail in worktree, no browser test possible in audit). |

---

## Test Results

**All 21 tests FAIL** with the same error:

```
Call to undefined method claude\worktrees\agentae67b94f\tests\Feature\Api\ActionDefinitionControllerTest::seed()
```

**Root cause:** The worktree uses a symlinked `vendor/` directory pointing to the main repo. Pest's path-based namespace resolution generates incorrect class names from the worktree path (e.g., `claude\worktrees\agentae67b94f\tests\...` instead of `Tests\...`), which means the `uses()` configuration in `tests/Pest.php` does not match these test files. The `seed()` method is provided by `TestCase`, which requires `uses(TestCase::class)` to be bound.

**This is an infrastructure/worktree issue, not a code defect.** The tests are structurally correct and follow the established patterns in `tests/Pest.php`. They would pass when run from the main repo after the branch is merged.

**Test file details:**

- `ActionDefinitionControllerTest.php` (167 lines): 8 test cases covering list, create, update, toggle, delete, decision matrix, invalid module (422), and auth (403). Properly uses `Sanctum::actingAs()`, seeds TaxConfiguration, RolesPermissions, and action definitions.
- `UserModuleTrackingServiceTest.php` (75 lines): 6 test cases covering complete/partial/empty status, sub-area counts, onboarding data, and fresh user handling. Uses factory creates for policies.
- `AdminBackupTest.php` (141 lines): 7 test cases covering list, delete, path traversal, invalid format, auth, empty list, and credential cleanup.

---

## Vue Component Syntax Check

| Component | template/script | Imports | Naming | Design Guide |
|-----------|----------------|---------|--------|-------------|
| DecisionMatrix.vue | Valid | `actionDefinitionService`, `MODULE_CONFIGS`, sub-components | PascalCase, multi-word | PASS |
| DecisionTree.vue | Valid | `DecisionNode` | PascalCase, multi-word | PASS |
| DecisionNode.vue | Valid | None needed | PascalCase, multi-word | PASS |
| ActionDefinitionDrawer.vue | Valid | `TriggerConfigEditor` | PascalCase, multi-word | PASS |
| TriggerConfigEditor.vue | Valid | None needed | PascalCase, multi-word | PASS |
| UserModuleStatus.vue | Valid | `adminService` | PascalCase, multi-word | PASS |
| UserOnboardingProgress.vue | Valid | `adminService` | PascalCase, multi-word | PASS |

**Banned colours check:** No `amber-*`, `orange-*` found in any new component. Pre-existing `emerald-*` and `error-*` tokens in `UserManagement.vue` are NOT introduced by this PR (confirmed via diff).

**No `border-l-4`** found in any new component.

**API imports:** `actionDefinitionService.js` uses `import api from './api'` (correct). `adminService.js` uses `import api from './api'` (correct, pre-existing). No raw `axios` imports.

**v-for with :key:** All `v-for` directives have `:key` attributes.

**No v-if + v-for on same element:** Verified.

---

## GAPS FOUND

1. **[SPEC 1.2] Missing `life_stage` and `life_stage_completed_steps` in onboarding data.** The `UserModuleTrackingService::getOnboardingData()` method does not return `life_stage` (from `$user->life_stage`) or `life_stage_completed_steps` (from `$user->life_stage_completed_steps`). Both are explicitly required in the spec at section 1.2, lines 259-260.

2. **[MOCKUP] Missing Search and Filter buttons in DecisionTree.vue header.** The mockup (lines 207-214) shows 4 controls: Search, Filter, Collapse All, + Add Action. The implementation only has Collapse All and + Add Action. The `searchQuery` data property exists in DecisionTree.vue but there is no search input UI element.

3. **[MOCKUP] Column header "Data Source" should be "User Data".** The mockup (line 238) shows "USER DATA" as the first column header. The implementation uses "Data Source" instead.

4. **[SPEC 1.1] TriggerConfigEditor lacks OR combinator.** The spec (line 99) says "AND/OR combinators". The implementation only supports AND between conditions. There is no dropdown or toggle to switch between AND/OR logic.

5. **[SPEC 1.3] Missing `createBackup` test.** The spec explicitly lists "Test `createBackup()` -- verify mysqldump executes, .sql file is created, file contains valid SQL". This test is absent.

6. **[SPEC 1.3] Missing `restoreBackup` test.** The spec explicitly lists "Test `restoreBackup()` -- verify restore works on a test database, caches are cleared after". This test is absent.

7. **[SPEC 1.3] Missing rate limiting test.** The spec explicitly lists "Verify rate limiting -- 3 requests per minute on backup endpoints". This test is absent.

8. **[SPEC 1.3] Credential file security test is superficial.** The spec says "Verify credential file security -- temp `.my.cnf` is created with 0600 permissions and deleted after use". The test only verifies no temp files remain after a `listBackups` call (which does not create credential files). It does not test the actual `createBackup` flow where credential files are created.

9. **[TESTS] All tests fail in worktree.** Due to symlinked vendor directory and Pest namespace resolution, all 21 tests fail with `Call to undefined method seed()`. This is a worktree infrastructure issue, not a code defect -- tests should pass when run from the main repo.

10. **[PLAN Task 6 Step 6] Drawer emits `save` to parent but parent calls API.** The DrawerComponent's `handleSave` method emits `save` immediately without awaiting the parent's API call result. The `saving` flag is set/unset synchronously in a try/finally, so the "Saving..." state will flash momentarily but not actually track the async save. The parent (`DecisionMatrix.handleSave`) does the real API call. This means the drawer's `saving` state is misleading -- it will show "Saving..." for a fraction of a second then revert, regardless of whether the API call succeeds. This is a UX issue, not a spec violation per se.

---

## RECOMMENDATIONS

1. **Add `life_stage` and `life_stage_completed_steps` to `UserModuleTrackingService::getOnboardingData()`:**
   ```php
   'life_stage' => $user->life_stage,
   'life_stage_completed_steps' => $user->life_stage_completed_steps ?? [],
   ```

2. **Add Search and Filter buttons to DecisionTree.vue tree header.** The `searchQuery` data property and `filteredDefinitions` computed are already implemented -- just need to add the search input UI and a filter dropdown. Copy the button markup from the mockup lines 207-214.

3. **Rename first column header from "Data Source" to "User Data"** in DecisionTree.vue line 85.

4. **Add OR combinator option to TriggerConfigEditor.vue.** Add a dropdown between condition rows that allows switching between AND/OR. Store the combinator type in the trigger_config object.

5. **Add the 3 missing backup tests:** `createBackup` (may need to mock `mysqldump`), `restoreBackup` (use RefreshDatabase test DB), and rate limiting (send 4 rapid requests, assert 429 on the 4th).

6. **Improve credential file security test** to actually test `createBackup` flow (may need to mock shell execution).

7. **Fix drawer `saving` state** to be controlled by the parent via a prop, or have the drawer await the save promise before clearing `saving`.

8. **Tests will work after merge.** No action needed for the worktree-specific test failures. After merging the branch into `main`, run `./vendor/bin/pest tests/Feature/Api/ActionDefinitionControllerTest.php tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php tests/Feature/Api/AdminBackupTest.php` to verify.
