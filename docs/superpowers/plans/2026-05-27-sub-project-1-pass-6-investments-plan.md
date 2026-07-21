---
type: plan
sub-project: 1
pass: 6
entity: Investments (full surface — 6 models)
date: 2026-05-27
status: APPROVED — execution starts next session
sibling: docs/superpowers/plans/2026-05-27-sub-project-1-pass-5-mortgages-plan.md
predecessor: SP1 Pass 5 (Mortgages) FULLY CLOSED at merge `e4d8039`
---

# Sub-Project 1, Pass 6 — Investments Canonical Store Implementation Plan

## 0. Where this sits in the bigger picture

SP1 progress on Pass 6 entry: **8 of 19 entity stores shipped** (Savings + 4 ref-data + Pensions + Properties + Mortgages, all locked). Pass 6 brings InvestmentAccount + Holding + InvestmentGoal + RiskProfile + InvestmentScenario + RebalancingAction up to canonical-store contract — **6 new stores in one pass**. On Pass 6 close-out: **14 of 19 stores shipped**.

This is the **largest entity surface in SP1** by consumer file count: 173 direct references to `InvestmentAccount::` across 67+ files, plus 33 to `Holding::`, 28 to `RiskProfile::`, 13 to `InvestmentGoal::`, 11 to `InvestmentScenario::`, 5 to `RebalancingAction::`. Source: grep of `app/`, `database/` at HEAD `e4d8039`.

### 0.1 Scope decision — full Investment surface (CSJ-confirmed 2026-05-27)

**In scope** — all 6 Investment models get canonical stores:

| Entity | Refs | Table | Joint-aware? | Multi-table relationship | Cross-module? |
|---|---|---|---|---|---|
| `InvestmentAccount` | 173 | `investment_accounts` | YES (`HasJointOwnership` trait — confirmed `joint_owner_id` at `:32`, `jointOwner()` at `:321`) | Parent of `Holding` via `morphMany(holdable)` | No |
| `Holding` | 33 | `holdings` | n/a (inherits from parent) | **Polymorphic** — `morphTo('holdable')` accepting `InvestmentAccount` OR `DCPension` | **YES — Investment AND Retirement** |
| `InvestmentGoal` | 13 | `investment_goals` | TBD per audit | belongsTo InvestmentAccount | No |
| `RiskProfile` | 28 | `risk_profiles` | n/a (per-user) | one-per-user | No |
| `InvestmentScenario` | 11 | `investment_scenarios` | TBD per audit | belongsTo User | No (paid-tier) |
| `RebalancingAction` | 5 | `rebalancing_actions` | n/a | belongsTo InvestmentAccount | No |

**Out of scope** — entities not in this pass:

- **`InvestmentTransaction`** — does NOT exist in codebase (verified: 0 references, no model file at `app/Models/Investment/InvestmentTransaction.php`). Spec §15.3 reference was aspirational. Document as "model not present" in §1.
- **`InvestmentActionDefinition`** — has its own factory at `database/factories/InvestmentActionDefinitionFactory.php` but is a reference-data entity covered by Pass 2 (R1–R4), not user-data.
- **Analytics / fees / performance / rebalancing-analysis services** — operate on entity instances, not DB writes. Per spec §15.4 these are out-of-scope for store migration.

### 0.2 Unique-to-Pass-6 architectural pieces

1. **HoldingStore is cross-module** — accepts writes from BOTH `InvestmentController` (Investment) AND `DCPensionHoldingsController` (Retirement). This is the first cross-module store in SP1. Pension Pass 3 deliberately deferred Holding migration (per `PensionStore.md:40`: "`DCPensionHoldingsController` — `Holding::where('holdable_type', DCPension::class)` polymorphic queries... still direct"). Pass 6 closes that debt.

2. **Multi-table parent-child within Pass 6** — `HoldingStore::create` triggers `InvestmentAccountStore::recalculateDerivedForAccountId` via cross-store recalc listener (mirror Pass 5 PR 6 Mortgage → Property pattern). For DCPension parents, the same listener triggers `PensionStore::recalculateDerivedForPensionId` (which **does not exist yet** — must be added in PR 6 alongside `InvestmentAccountStore::recalculateDerivedForAccountId`).

3. **Three satellite stores (Goal/Risk/Scenario) bundled in PR 8** — small surface area each. Per spec §15.4 deliverables checklist, each entity gets its own `{Entity}Store` + `{Entity}Normaliser` + Store.md. Bundling avoids fragmenting the PR sequence.

4. **RebalancingAction has a single confirmed write site** — `RebalancingActionsController:57` `RebalancingAction::create($actionData)`. Ships as a store in PR 9 (NOT deferred as previously considered). 5 references is low but writes exist, so the boundary needs locking.

5. **Observer entanglement** — two existing observers must keep functional:
   - `InvestmentAccountRiskObserver` — fires on `save`, dispatches risk recalc
   - `InvestmentAccountGoalObserver` — fires on `save`, tracks goal contributions
   Store writes via `save()` must trigger these. The cross-store recalc path (which uses `saveQuietly`) intentionally skips them — same Pass 5 PR 6 nuance.

6. **Holding has currency dimension** — `currency` column on `holdings` table. Pass 5 deferred currency round-trip (GBP-only); Pass 6 inherits that decision unless CSJ overrides. The `current_value_gbp` derived column on Holding implements the round-trip when Pass 14+ revisits.

7. **InvestmentScenario is paid-tier** — tier-cap design must allow tier1+ to create scenarios while free tier is blocked. Likely entity key `investment_scenario` with `free=0, tier1+=null`.

### 0.3 Execution pattern (CSJ-confirmed)

Same as Pass 5 — subagent-driven-development: Sonnet implementer → Opus spec reviewer → Opus code-quality reviewer → CSJ admin-merge per PR. Branch convention: `feat/investment-store-prN` off `dev`.

**Scope-induced PR count**: 12 PRs (vs Pass 5's 8). Estimated 7-10 day execution.

---

## 1. Pre-pass audit (PR 0 baseline)

### 1.1 Files referencing each entity

Per `grep -rnE "{Entity}::"` against `app/` and `database/`:

| Entity | Write refs | Read refs | Total | Primary consumers |
|---|---|---|---|---|
| InvestmentAccount | ~30 | ~143 | 173 | InvestmentController, 55 services across 11 subdirs, agents, GoalsAgent, RetirementAgent, NetWorthService, MobileDashboardAggregator, CrossModuleAssetAggregator, AdvicePromptBuilder, UserProfileService, PersonalAccountsService, HouseholdPlanningService, DocumentProcessor, MigrateEstateToNetWorth, multiple seeders |
| Holding | ~8 | ~25 | 33 | InvestmentController (storeHolding/updateHolding/destroyHolding), DCPensionHoldingsController (CROSS-MODULE), analytics services, document upload mapper |
| InvestmentGoal | ~4 | ~9 | 13 | InvestmentController, GoalsAgent, investment goal services |
| RiskProfile | ~3 | ~25 | 28 | InvestmentController (storeOrUpdateRiskProfile), recommendation engines, risk recalc job |
| InvestmentScenario | ~5 | ~6 | 11 | InvestmentScenarioController, scenario services, MonteCarlo job |
| RebalancingAction | ~2 | ~3 | 5 | RebalancingActionsController, RebalancingService |

Full per-file lists are gathered in PR 0 audit step (per Pass 5 PR 1 sibling pattern).

### 1.2 Read patterns

- **InvestmentAccount** — `forUserOrJoint($userId)`, `Property::where('user_id', $userId)`-style direct queries, `$user->investmentAccounts` HasMany (per Pass 4 pattern). Verify exact distribution in PR 0 audit. **Critical**: parity test in PR 5a will lock joint-aware vs primary-only contract (same regression class as Pass 4 PR 5a + Pass 5 PR 5a).
- **Holding** — primarily accessed via `$parent->holdings` MorphMany or `Holding::where('holdable_type', X::class)->where('holdable_id', $id)` polymorphic queries.
- **RiskProfile** — per-user singleton, accessed via `$user->riskProfile` HasOne.
- **InvestmentScenario** — `$user->investmentScenarios` HasMany.
- **InvestmentGoal** — `$account->investmentGoals` HasMany via account_id FK.
- **RebalancingAction** — `$account->rebalancingActions` HasMany.

### 1.3 Joint ownership shape

**InvestmentAccount uses `HasJointOwnership` trait** (verified: `app/Models/Investment/InvestmentAccount.php:25`). `joint_owner_id` is fillable (`:32`), `jointOwner()` relationship at `:321` with `withTrashed`. Same pattern as Property/Mortgage.

Holding inherits joint ownership transitively via its `holdable` polymorph parent (no `joint_owner_id` on holdings table itself).

The other 4 entities (Goal/Risk/Scenario/RebalancingAction) are NOT joint-aware (single-user).

**Parity contract** (locked by `InvestmentReadConsumerParityTest` in PR 5a):
- `InvestmentAccountStore::forUser` is joint-aware (returns `user_id = X OR joint_owner_id = X`)
- `InvestmentAccountStore::forUserPrimaryOnly` is primary-only (`user_id = X` only)
- `User->investmentAccounts` HasMany ≡ `forUserPrimaryOnly` (per Pass 5 PR 5a precedent)

### 1.4 Tier-cap keys

Verify in PR 0 audit by reading `database/seeders/TierConfigurationSeeder.php`. Current expected state:
- `investment_account => 2` (free tier), `null` (tier1+) — already in seeder per Pass 5 PR 1 audit at line 44
- New keys needed in PR 1:
  - `holding => null` (tier1+) / `5` (free)? CSJ to confirm
  - `investment_goal => null` (tier1+) / `2` (free)? CSJ to confirm
  - `risk_profile => 1` (one-per-user — enforced by unique index, not tier-cap)
  - `investment_scenario => 0` (free, blocked) / `null` (tier1+)
  - `rebalancing_action => null` (no cap — generated by engine)

Resolve at PR 1 dispatch.

### 1.5 Existing factories (all 6 present)

- `database/factories/Investment/InvestmentAccountFactory.php`
- `database/factories/Investment/HoldingFactory.php`
- `database/factories/Investment/InvestmentGoalFactory.php`
- `database/factories/Investment/InvestmentScenarioFactory.php`
- `database/factories/Investment/RebalancingActionFactory.php`
- `database/factories/Investment/RiskProfileFactory.php`

### 1.6 Cross-record relationships (multi-table dimension)

```
                      InvestmentAccount
                       /              \
                  Holding[]        InvestmentGoal[]
                  (morphMany)       (hasMany)
                       |
                  RebalancingAction[]
                  (via account_id)
```

Separate from the Account hierarchy:
- `RiskProfile` — one per User (HasOne)
- `InvestmentScenario` — many per User (HasMany)

**Cross-store recalc invariant (load-bearing)**: `HoldingStore::create/update/delete` triggers `InvestmentAccountStore::recalculateDerivedForAccountId` (one-way; never the reverse). The Account's `current_value_gbp` derived column is the canonical sum of Holdings.

### 1.7 Investment-specific calc helpers (out of scope for store)

Per spec §15.4 + Pass 5 PR 5e Category D precedent: services that take entity instances as parameters and compute (not read from DB) are out of scope. This includes:
- `app/Services/Investment/Analytics/*` — diversification, sector breakdown, etc.
- `app/Services/Investment/Fees/*` — OCF / TER calculations
- `app/Services/Investment/Performance/*` — XIRR, TWR
- `app/Services/Investment/Rebalancing/*` — drift analysis (NOT writes — writes go through RebalancingActionStore)
- `app/Services/Investment/Recommendation/*` — engine outputs
- `app/Services/Investment/Tax/*` — tax-drag, allowance-utilisation helpers
- `app/Services/Investment/AssetLocation/*` — placement recommendations

All operate on `InvestmentAccount $account` parameters, not `InvestmentAccount::find()`. Store migration does not affect them.

### 1.8 Existing observers + events

Two observers must keep functional through Pass 6:

| Observer | Trigger | Action |
|---|---|---|
| `InvestmentAccountRiskObserver` | `InvestmentAccount::save()` | Dispatches `RecalculateRiskProfileJob` (debounced 5s) |
| `InvestmentAccountGoalObserver` | `InvestmentAccount::save()` | Tracks goal contributions for linked goals |

**No existing `App\Events\Investment\*` event classes** — verified via `find app/Events -path "*Investment*"`. PR 1 creates all 4: `InvestmentAccountCreated`, `InvestmentAccountUpdated`, `InvestmentAccountDeleted`, `InvestmentAccountRestored`. PR 6 creates the 4 Holding events. PR 8 creates events for Goal/Risk/Scenario. PR 9 creates events for RebalancingAction.

### 1.9 The cross-module Holding handoff (Pass 3 deferral)

Per `app/Services/Stores/PensionStore.md:40`:
> `App\Http\Controllers\Api\Retirement\DCPensionHoldingsController` — `Holding::where('holdable_type', DCPension::class)` polymorphic queries + a `?DCPension` return-type hint on `pensionForUserOr404`

Pass 3 documented this as a known boundary exception. **Pass 6 closes it** by routing `DCPensionHoldingsController` writes through the new `HoldingStore`. This requires:
- `PensionStore::recalculateDerivedForPensionId($id)` — analogous to PropertyStore equivalent (does not currently exist)
- HoldingStore listener handling both polymorphic parents

---

## 2. Scope

### 2.1 Files in scope (production code)

Detailed lists per-PR in §5 onwards. Summary:

**PR 1-5** (InvestmentAccount):
- `app/Services/Stores/InvestmentAccountStore.php` (NEW)
- `app/Services/Stores/Normalisers/InvestmentAccountNormaliser.php` (NEW)
- `app/Events/Investment/InvestmentAccount{Created,Updated,Deleted,Restored}.php` (NEW × 4)
- `app/Http/Controllers/Api/InvestmentController.php` (store/update/destroy account write paths + toggleRetirementInclusion)
- `app/Http/Controllers/Api/PreviewController.php` (seedInvestmentAccounts)
- `app/Agents/CoordinatingAgent.php` (handleCreateInvestmentAccount, handleUpdateInvestmentAccount, handleDeleteInvestmentAccount)
- `app/Services/Onboarding/OnboardingService.php` (investment branch)
- `app/Services/Documents/DocumentProcessor.php` (investment branch)
- `database/seeders/{ChrisUserSeeder,PreviewUserSeeder}.php` (investment seeds)
- **55 service files across 11 Investment subdirs** — read consumers (PR 5)
- Cross-module read consumers: NetWorthService, MobileDashboardAggregator, CrossModuleAssetAggregator, AdvicePromptBuilder, UserProfileService, PersonalAccountsService, HouseholdPlanningService, MigrateEstateToNetWorth, GoalsAgent, RetirementAgent

**PR 6-7** (Holding — cross-module):
- `app/Services/Stores/HoldingStore.php` (NEW)
- `app/Services/Stores/Normalisers/HoldingNormaliser.php` (NEW)
- `app/Events/Holding/Holding{Created,Updated,Deleted,Restored}.php` (NEW × 4)
- `app/Http/Controllers/Api/InvestmentController.php` (storeHolding/updateHolding/destroyHolding)
- `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` (cross-module migration)
- `app/Listeners/Holding/RecalculateAccountHoldingsValue.php` (NEW — cross-store recalc for InvestmentAccount parent)
- `app/Listeners/Holding/RecalculatePensionHoldingsValue.php` (NEW — cross-store recalc for DCPension parent)
- `app/Services/Stores/PensionStore.php` (add `recalculateDerivedForPensionId` public method — mirrors Pass 5 PR 6 PropertyStore equivalent)

**PR 8** (Goal + Risk + Scenario satellite stores):
- `app/Services/Stores/InvestmentGoalStore.php` (NEW)
- `app/Services/Stores/RiskProfileStore.php` (NEW)
- `app/Services/Stores/InvestmentScenarioStore.php` (NEW)
- 3 normalisers + 12 event classes
- Controller routing for storeGoal/updateGoal/destroyGoal + storeOrUpdateRiskProfile + InvestmentScenarioController writes

**PR 9** (RebalancingAction):
- `app/Services/Stores/RebalancingActionStore.php` (NEW)
- `app/Services/Stores/Normalisers/RebalancingActionNormaliser.php` (NEW)
- 4 event classes
- `app/Http/Controllers/Api/Investment/RebalancingActionsController.php` write paths

**PR 10** (derived columns + snapshots + cross-store recalc):
- 6 migrations (InvestmentAccount derived columns, Holding derived columns, snapshot tables × 2-3, calculated_at timestamps on parents)
- Snapshot models for InvestmentAccount + Holding
- `app/Services/Stores/Recalc/InvestmentAccountDerivedColumnCalculator.php`
- `app/Services/Stores/Recalc/HoldingDerivedColumnCalculator.php`
- Backfill commands × 2-3
- 4+ snapshot policies

### 2.2 Files out of scope

- Analytics / fees / performance / rebalancing-analysis / recommendation / tax / asset-location services (operate on instances)
- `InvestmentActionDefinition` (Pass 2 reference data)
- Frontend Vue components (no UI changes per Pass 5 precedent)
- Migration of legacy `InvestmentService` or similar facades — Investment doesn't have a single "service facade" like Property's `MortgageService`; the migration is direct controller → store

---

## 3. Dependencies + bindings

Container bindings for all 6 stores wired in `app/Providers/AppServiceProvider.php` (or RouteServiceProvider). Pattern mirrors Pass 5 — singleton scope per request.

Listener bindings in `app/Providers/EventServiceProvider.php`:
- 4 InvestmentAccount events × any new InvestmentAccount listeners
- 4 Holding events × RecalculateAccountHoldingsValue + RecalculatePensionHoldingsValue
- 4 events each for Goal/Risk/Scenario/RebalancingAction (per PR 8/9 design)

---

## 4. Cross-PR conventions

### 4.1 IngestSource enum reuse
Use existing `App\Services\Stores\IngestSource` enum (FORM/FYN_AI/UPLOAD/SEEDER/ADMIN). No new values.

### 4.2 Audit context
Every write wrapped in `AuditLog::withContext(['ingest_source' => $source->value], fn () => ...)` — established pattern from Pass 5.

### 4.3 Joint ownership invariant (InvestmentAccount only)
`InvestmentAccount` is joint-aware via `HasJointOwnership`. `joint_owner_id` is a User reference. Holdings inherit ownership transitively. Goal/Risk/Scenario/RebalancingAction are NOT joint-aware.

### 4.4 `tenants_in_common` is NOT a valid Investment ownership_type
Per CLAUDE.md Rule #5: TIC is property-only. InvestmentAccount uses `individual` | `joint`. Normaliser coerces TIC → joint at the boundary (defensive — UI shouldn't ever send TIC for investments).

### 4.5 Pint hook strips unused imports
Same lesson from every prior pass — add `use` and the constructor reference in the SAME edit. If stripped, re-add. Documented in MEMORY/feedback rules.

### 4.6 Commit cadence
One commit per PR. Review-fix commits land separately (`chore(investments): PR N review fixes — ...`). Admin-merge after both reviewers approve.

### 4.7 Cross-store recalc invariant (one-way Holdings → Account/Pension)
HoldingStore writes trigger recalc on the polymorphic parent:
- `holdable_type = InvestmentAccount` → `InvestmentAccountStore::recalculateDerivedForAccountId(holdable_id)`
- `holdable_type = DCPension` → `PensionStore::recalculateDerivedForPensionId(holdable_id)`

Parent stores must NOT call HoldingStore writes (loop prevention by design). Locked by tests in PR 10:
- `HoldingAccountReconciliationTest`
- `HoldingPensionReconciliationTest`

### 4.8 Subagent dispatch pattern (Pass 5 lessons)
- Pint strips unused imports — combine import + constructor reference in same edit.
- ~50% of sub-agent dispatches stall mid-Pint-diagnosis. SendMessage nudge: "do not stop again, just complete the work". After 2 stalls, take over manually.
- Reviewers must check sibling commit (`git show <merge SHA>`) for the latest convention drift learnings.

### 4.9 Observer compatibility invariant
InvestmentAccountRiskObserver + InvestmentAccountGoalObserver must keep firing on user-driven writes. Store `create/update/delete` use `$model->save()` (fires events), NOT `saveQuietly`. Only the cross-store recalc path inside `recalculateDerived` uses `saveQuietly` to prevent loops — per Pass 5 PR 6 nuance.

---

## 5. PR 1 — InvestmentAccountStore facade + boundary + normaliser + events

**PR title**: `feat(investments): InvestmentAccountStore facade + boundary + normaliser + events + tier-cap (SP1 Pass 6 PR 1)`

**Branch**: `feat/investment-store-pr1` off `dev` post-Pass-5 (current `dev` at `bc95d55` after CSJTODO update).

**Pattern**: Follow Pass 5 PR 1 step-by-step. The structure is identical with `Investment` substituted for `Mortgage`. Reference plan §5 of `2026-05-27-sub-project-1-pass-5-mortgages-plan.md` for line-by-line code samples.

**Deliverables**:
- [ ] **Step 1.1** — Create 4 event classes at `app/Events/Investment/InvestmentAccount{Created,Updated,Deleted,Restored}.php`. Each takes `InvestmentAccount $investmentAccount`, `int $userId` constructor args, with `Updated` also taking `array $changes` (diff map). Mirror `app/Events/Mortgage/Mortgage{Created,...}.php` exactly.
- [ ] **Step 1.2** — Create `app/Services/Stores/Normalisers/InvestmentAccountNormaliser.php`. Three static methods: `fromForm`, `fromFyn`, `fromUpload`. Each accepts the raw payload + `User $user`, returns canonical array. Coerce `tenants_in_common` → `joint`. Default `ownership_percentage` to 100 for `individual`, 50 for `joint`. Drop deprecated input field aliases (verify against StoreInvestmentAccountRequest validation rules).
- [ ] **Step 1.3** — Write failing `tests/Unit/Services/Stores/Normalisers/InvestmentAccountNormaliserTest.php` covering field coercion + TIC handling + alias mapping.
- [ ] **Step 1.4** — Create `app/Services/Stores/InvestmentAccountStore.php`. Constructor injects `TierGate $tierGate`. Public API (mirror MortgageStore.php):
  - Reads: `find(int $id, User $user): ?InvestmentAccount`, `forUser(User $user): Collection` (joint-aware), `forUserPrimaryOnly(User $user): Collection`, `forUserWithJointOwner(User $user): Collection`, `forUserByType(User $user, string $accountType): Collection`, `findMany(array $ids, User $user): Collection`
  - Writes: `create(array $canonical, User $user, IngestSource $source): InvestmentAccount`, `update(int $id, array $changes, User $user, IngestSource $source): InvestmentAccount`, `updateOrCreate(array $matchKeys, array $values, User $user, IngestSource $source): InvestmentAccount`, `delete(int $id, User $user, IngestSource $source): void`, `restore(int $id, User $user, IngestSource $source): InvestmentAccount`
- [ ] **Step 1.5** — Write `tests/Unit/Services/Stores/InvestmentAccountStoreTest.php` (smoke covering each public method's happy path).
- [ ] **Step 1.6** — Write `tests/Unit/Services/Stores/InvestmentAccountStoreEventsTest.php` (asserts events dispatch correctly with the right payload shape).
- [ ] **Step 1.7** — Seed the `investment_account` tier-cap key (verify existing entry; if missing, add to `database/seeders/TierConfigurationSeeder.php`: `'investment_account' => 2` for free, `null` for tier1+).
- [ ] **Step 1.8** — Create `tests/Architecture/StoreBoundary/InvestmentAccountStoreBoundaryTest.php` with FULL SOFT transition allowlist (all 30 write sites identified in audit). Each entry comments which PR removes it.
- [ ] **Step 1.9** — Commit + open PR 1.

```bash
git checkout -b feat/investment-store-pr1
# ... implementation steps 1.1-1.8 ...
git add app/Services/Stores/InvestmentAccountStore.php app/Services/Stores/Normalisers/InvestmentAccountNormaliser.php app/Events/Investment/ tests/Unit/Services/Stores/InvestmentAccountStoreTest.php tests/Unit/Services/Stores/InvestmentAccountStoreEventsTest.php tests/Unit/Services/Stores/Normalisers/InvestmentAccountNormaliserTest.php tests/Architecture/StoreBoundary/InvestmentAccountStoreBoundaryTest.php database/seeders/TierConfigurationSeeder.php
git commit -m "feat(investments): InvestmentAccountStore facade + boundary + normaliser + events + tier-cap (SP1 Pass 6 PR 1)"
git push -u origin feat/investment-store-pr1
gh pr create --base dev --head feat/investment-store-pr1 --title "feat(investments): InvestmentAccountStore facade + boundary + normaliser + events + tier-cap (SP1 Pass 6 PR 1)"
```

---

## 6. PR 2 — Point HTTP form requests at InvestmentAccountStore

**PR title**: `feat(investments): HTTP form requests through InvestmentAccountStore (SP1 Pass 6 PR 2)`

**Branch**: `feat/investment-store-pr2` off `dev` post-PR-1-merge.

**Sibling**: Pass 5 PR 2 (Mortgage HTTP routing). Mechanical substitution.

**Deliverables**:
- [ ] **Step 2.1** — Audit `InvestmentController` write sites. Confirmed write methods: `storeAccount`, `updateAccount`, `destroyAccount`, `toggleRetirementInclusion`. (Holdings/Goals/RiskProfile/etc are PR 6-9 territory.)
- [ ] **Step 2.2** — Inject `InvestmentAccountNormaliser` + `InvestmentAccountStore` into `InvestmentController` constructor.
- [ ] **Step 2.3** — Replace `storeAccount()` write site:
  ```php
  // Pre:
  $account = InvestmentAccount::create($validated);
  // Post:
  $canonical = InvestmentAccountNormaliser::fromForm($validated, $user);
  $account = $this->investmentAccountStore->create($canonical, $user, IngestSource::FORM);
  ```
  Add `TierLimitExceededException` catch with structured 403 response.
- [ ] **Step 2.4** — Replace `updateAccount()` write site (use `InvestmentAccountStore::update($id, $changes, $user, IngestSource::FORM)`).
- [ ] **Step 2.5** — Replace `destroyAccount()` write site (`InvestmentAccountStore::delete`).
- [ ] **Step 2.6** — Replace `toggleRetirementInclusion()` — this is a single-field toggle. Use `InvestmentAccountStore::update($id, ['include_in_retirement_planning' => ...], $user, IngestSource::FORM)`.
- [ ] **Step 2.7** — Audit `PreviewController` for `seedInvestmentAccounts` — route through `InvestmentAccountStore::create` with `IngestSource::SEEDER`.
- [ ] **Step 2.8** — Remove migrated sites from `InvestmentAccountStoreBoundaryTest` allowlist.
- [ ] **Step 2.9** — Write `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` covering happy paths + TierLimitExceededException → 403.
- [ ] **Step 2.10** — Run boundary test + targeted suites. All green.
- [ ] **Step 2.11** — Commit + open PR 2.

---

## 7. PR 3 — Point Fyn AI write tools at InvestmentAccountStore

**PR title**: `feat(investments): Fyn AI write tools through InvestmentAccountStore (SP1 Pass 6 PR 3)`

**Branch**: `feat/investment-store-pr3` off `dev` post-PR-2-merge.

**Sibling**: Pass 5 PR 3.

**Deliverables**:
- [ ] **Step 3.1** — Locate Fyn investment handlers in `CoordinatingAgent`. Expect: `handleCreateInvestmentAccount`, `handleUpdateInvestmentAccount`, `handleDeleteInvestmentAccount`.
- [ ] **Step 3.2** — Route `handleCreateInvestmentAccount` through `InvestmentAccountStore::create` with `IngestSource::FYN_AI`. Use `InvestmentAccountNormaliser::fromFyn` for payload prep.
- [ ] **Step 3.3** — Route update/delete handlers similarly.
- [ ] **Step 3.4** — Trim `InvestmentAccountStoreBoundaryTest` allowlist.
- [ ] **Step 3.5** — Write `tests/Feature/Stores/InvestmentAccountFynCaptureIntegrationTest.php`.
- [ ] **Step 3.6** — Commit + open PR 3.

---

## 8. PR 4 — Point upload + onboarding + seeders + service-internal at InvestmentAccountStore

**PR title**: `feat(investments): upload + onboarding + seeders through InvestmentAccountStore (SP1 Pass 6 PR 4)`

**Branch**: `feat/investment-store-pr4` off `dev` post-PR-3-merge.

**Sibling**: Pass 5 PR 4.

**Deliverables**:
- [ ] **Step 4.1** — Route `DocumentProcessor`'s investment upload branch through `InvestmentAccountStore` with `IngestSource::UPLOAD`.
- [ ] **Step 4.2** — Route `OnboardingService` investment writes through the store.
- [ ] **Step 4.3** — Investigate whether there's an analogue to `MortgageService::createFromPropertyData` (likely NOT — investments don't have a sibling-bootstrap pattern). Confirm in audit.
- [ ] **Step 4.4** — Route persona seeders (`ChrisUserSeeder`, `PreviewUserSeeder`) through the store with `IngestSource::SEEDER`. Use `updateOrCreate` for seeder idempotency.
- [ ] **Step 4.5** — Write `tests/Feature/Stores/InvestmentAccountUploadIngestTest.php`.
- [ ] **Step 4.6** — Trim boundary allowlist (now should have only `EncryptExistingData` + `ResetPreviewData` + `PreviewUserSeeder` if Pass 5 precedent applies).
- [ ] **Step 4.7** — Audit `MigrateEstateToNetWorth` for investment branches — investments are a NetWorth-Asset migration target. Check the migration command for direct InvestmentAccount writes. If present, route via store. Audit data should clarify this.
- [ ] **Step 4.8** — Commit + open PR 4.

---

## 9. PR 5 — Point read consumers at InvestmentAccountStore (sub-clustered 5a-5e)

InvestmentAccount has **~143 read references across 67+ files**, larger than Pass 4 Properties (47 across 21) and Pass 5 Mortgages (~24 across 11). Sub-clustering is mandatory.

**Branch**: `feat/investment-store-pr5{a,b,c,d,e}` per cluster off `dev` post-PR-N-merge.

### 9.1 PR 5a — Investment-internal reads + InvestmentReadConsumerParityTest

**PR title**: `feat(investments): Investment service reads (Analytics/AssetLocation/Fees) + ReadConsumerParityTest (SP1 Pass 6 PR 5a)`

Files (verify in PR 5a audit):
- `app/Services/Investment/Analytics/*` — diversification analyzer, sector breakdown, portfolio analyzer (reads only — the analyzers READ InvestmentAccount/Holding but don't write)
- `app/Services/Investment/AssetLocation/*` — placement service, tax-drag calculator
- `app/Services/Investment/Fees/*` — fee analyzer, OCF/TER calculators

Sibling: Pass 5 PR 5a (Estate/IHT reads). Mirror the 7-case parity test pattern + sibling-conventionLearnings.

**Critical**: PR 5a establishes the `InvestmentReadConsumerParityTest` — 7+ cases locking joint-aware vs primary-only contract. Every subsequent cluster (5b-5e) must consume this contract.

### 9.2 PR 5b — Goals + ModelPortfolio + Performance reads

**PR title**: `feat(investments): Goals/ModelPortfolio/Performance reads through InvestmentAccountStore (SP1 Pass 6 PR 5b)`

Files:
- `app/Services/Investment/Goals/*` — investment goal projector, contribution tracker
- `app/Services/Investment/ModelPortfolio/*` — model portfolio matcher, glidepath calculator
- `app/Services/Investment/Performance/*` — XIRR, TWR, time-weighted return

### 9.3 PR 5c — Rebalancing + Recommendation + Tax reads

**PR title**: `feat(investments): Rebalancing/Recommendation/Tax reads through InvestmentAccountStore (SP1 Pass 6 PR 5c)`

Files:
- `app/Services/Investment/Rebalancing/*` — drift analyzer (read-only; writes are in PR 9 RebalancingActionStore)
- `app/Services/Investment/Recommendation/*` — recommendation engine, UserContextBuilder, ProductSelector
- `app/Services/Investment/Tax/*` — CGT calculator, ISA allowance, capital-gains-harvesting

### 9.4 PR 5d — Utilities + root + Agents

**PR title**: `feat(investments): Utilities + Agents reads through InvestmentAccountStore (SP1 Pass 6 PR 5d)`

Files:
- `app/Services/Investment/Utilities/*` — helpers
- `app/Services/Investment/*.php` (root files)
- `app/Agents/InvestmentAgent.php` — primary agent
- `app/Agents/GoalsAgent.php` — reads investments for goal feasibility
- `app/Agents/RetirementAgent.php` — reads investments for retirement total (if `include_in_retirement_planning`)
- `app/Agents/EstateAgent.php` — reads investments for IHT

### 9.5 PR 5e — Cross-module reads + parity close-out

**PR title**: `feat(investments): cross-module reads (NetWorth/Mobile/CrossModule/AI/Profile/Plans/GDPR) through InvestmentAccountStore (SP1 Pass 6 PR 5e — final read cluster)`

Files:
- `app/Services/NetWorth/NetWorthService.php`
- `app/Services/Mobile/MobileDashboardAggregator.php`
- `app/Services/Shared/CrossModuleAssetAggregator.php` (already routes Property/Savings/Mortgage — add Investment)
- `app/Services/AI/AdvicePromptBuilder.php`
- `app/Services/UserProfile/UserProfileService.php`
- `app/Services/UserProfile/PersonalAccountsService.php`
- `app/Services/UserProfile/LetterToSpouseService.php`
- `app/Services/Coordination/HouseholdPlanningService.php`
- `app/Services/Plans/InvestmentPlanService.php`
- `app/Services/GDPR/DataExportService.php`
- `app/Services/Protection/ProtectionDataReadinessService.php`

Apply Pass 5 PR 5b's cross-link KEEP precedent if any unscoped-by-user queries surface (e.g. an admin/system read that needs all investments regardless of user).

**§9 close-out**: After PR 5e merges, InvestmentAccount boundary should be ready to LOCK in PR 12. PR 6 (HoldingStore) starts independently.

---

## 10. PR 6 — HoldingStore facade + cross-module + 4 events + boundary

**PR title**: `feat(holdings): HoldingStore facade + cross-module boundary + normaliser + events (SP1 Pass 6 PR 6)`

**Branch**: `feat/investment-store-pr6` off `dev` post-PR-5e-merge.

**Architectural significance**: HoldingStore is the **first cross-module store in SP1**. Accepts writes from both `InvestmentController` (storeHolding) AND `DCPensionHoldingsController` (which Pass 3 deferred). The store enforces polymorphic parent invariants.

**Deliverables**:
- [ ] **Step 6.1** — Create `app/Events/Holding/Holding{Created,Updated,Deleted,Restored}.php`. Each carries `Holding $holding`, `int $userId`, and the polymorphic parent type for downstream routing.
- [ ] **Step 6.2** — Create `app/Services/Stores/Normalisers/HoldingNormaliser.php` with three ingest paths. Critical: normaliser must accept and validate `holdable_type` + `holdable_id`. Reject invalid polymorph types.
- [ ] **Step 6.3** — Create `app/Services/Stores/HoldingStore.php`. Public API:
  - Reads: `find($id, $user)`, `forUser($user)` (queries via parent ownership — joint-aware for InvestmentAccount parents, primary-only for DCPension), `forParent($holdable, $user)`, `forParentByType($holdable, $user, $assetClass)`, `findMany($ids, $user)`
  - Writes: `create`, `update`, `updateOrCreate`, `delete`, `restore`
- [ ] **Step 6.4** — Ownership check: every write verifies the user owns the polymorphic parent. For InvestmentAccount parents, joint-owner counts. For DCPension parents, primary-owner only.
- [ ] **Step 6.5** — Write unit tests covering both polymorph parent types.
- [ ] **Step 6.6** — Create `app/Listeners/Holding/RecalculateAccountHoldingsValue.php` — listens to 4 Holding events; if `holdable_type === InvestmentAccount`, calls `InvestmentAccountStore::recalculateDerivedForAccountId($holdable_id)`. Mirror Pass 5 PR 6 listener pattern.
- [ ] **Step 6.7** — Create `app/Listeners/Holding/RecalculatePensionHoldingsValue.php` — same shape, for DCPension parents.
- [ ] **Step 6.8** — Add `PensionStore::recalculateDerivedForPensionId(int $pensionId): void` public method (analogous to PropertyStore's equivalent in Pass 5 PR 6). Required because Pass 3 Pensions didn't ship this method — Pass 6 needs it for the cross-store recalc.
- [ ] **Step 6.9** — Wire 4 Holding event mappings in `EventServiceProvider`.
- [ ] **Step 6.10** — Create `tests/Architecture/StoreBoundary/HoldingStoreBoundaryTest.php` with FULL SOFT allowlist.
- [ ] **Step 6.11** — Commit + open PR 6.

---

## 11. PR 7 — Holding routing (HTTP + Fyn + upload + cross-module)

**PR title**: `feat(holdings): route HTTP + Fyn + upload + cross-module DCPensionHoldings through HoldingStore (SP1 Pass 6 PR 7)`

**Branch**: `feat/investment-store-pr7` off `dev` post-PR-6-merge.

**Bundles the routing for ALL Holding write paths** — InvestmentController + DCPensionHoldingsController + Fyn (if any) + Document upload.

**Deliverables**:
- [ ] **Step 7.1** — Route `InvestmentController::storeHolding`, `::updateHolding`, `::destroyHolding` through `HoldingStore`.
- [ ] **Step 7.2** — Route `DCPensionHoldingsController` write paths through `HoldingStore` — this closes the Pass 3 deferral. Update `PensionStore.md` to remove the deferred-debt note at `:40`.
- [ ] **Step 7.3** — Audit `CoordinatingAgent` for Fyn-driven Holding writes — likely a `handleCreateHolding` exists or needs creating. Route through `HoldingStore`.
- [ ] **Step 7.4** — Route `DocumentProcessor` holding-upload branch (e.g. statement parsing) through `HoldingStore` with `IngestSource::UPLOAD`.
- [ ] **Step 7.5** — Update seeders + onboarding (verify which create Holdings — `ChrisUserSeeder`, `PreviewUserSeeder` likely).
- [ ] **Step 7.6** — Trim `HoldingStoreBoundaryTest` allowlist.
- [ ] **Step 7.7** — Write `tests/Feature/Stores/HoldingHttpIntegrationTest.php` covering both polymorphic parents.
- [ ] **Step 7.8** — Commit + open PR 7.

---

## 12. PR 8 — Goal + Risk + Scenario satellite stores

**PR title**: `feat(investments): InvestmentGoalStore + RiskProfileStore + InvestmentScenarioStore + routing (SP1 Pass 6 PR 8)`

**Branch**: `feat/investment-store-pr8` off `dev` post-PR-7-merge.

**Bundle pattern**: Three small entities ship in a single PR. Per spec §15.4, each gets its own Store + Normaliser + Store.md (Store.md docs land in PR 12).

**InvestmentGoal (13 refs)**:
- [ ] **Step 8.1** — Create `InvestmentGoalStore` + normaliser + 4 events.
- [ ] **Step 8.2** — Route `InvestmentController::storeGoal/updateGoal/destroyGoal` through store.
- [ ] **Step 8.3** — Boundary test + parity coverage.

**RiskProfile (28 refs, observer-driven)**:
- [ ] **Step 8.4** — Create `RiskProfileStore` + normaliser + 4 events.
- [ ] **Step 8.5** — Route `InvestmentController::storeOrUpdateRiskProfile` through store. Use `updateOrCreate` (one-per-user pattern enforced by unique index).
- [ ] **Step 8.6** — Observer compatibility: `RecalculateRiskProfileJob` already dispatches from observer; verify store writes still fire `RiskProfile::save()` which triggers the observer. Document in Store.md.
- [ ] **Step 8.7** — Boundary test.

**InvestmentScenario (11 refs, paid-tier)**:
- [ ] **Step 8.8** — Create `InvestmentScenarioStore` + normaliser + 4 events.
- [ ] **Step 8.9** — Route `InvestmentScenarioController` write paths.
- [ ] **Step 8.10** — Tier-cap: `investment_scenario` key. Free tier = 0 (blocked entirely), tier1+ = null (unlimited). Add to `TierConfigurationSeeder`.
- [ ] **Step 8.11** — Boundary test.

**§12 close-out**: 3 satellite stores shipped, 3 boundary tests, 3 normalisers, 12 event classes.

- [ ] **Step 8.12** — Commit + open PR 8.

---

## 13. PR 9 — RebalancingActionStore

**PR title**: `feat(investments): RebalancingActionStore + routing (SP1 Pass 6 PR 9)`

**Branch**: `feat/investment-store-pr9` off `dev` post-PR-8-merge.

**Audit (resolves §0.1 scope question)**: `RebalancingActionsController:57` calls `RebalancingAction::create($actionData)` — confirmed write site. Ships as a full store.

**Smallest store in Pass 6** — 5 refs total. May complete in a single small PR.

**Deliverables**:
- [ ] **Step 9.1** — Create `RebalancingActionStore` + normaliser + 4 events.
- [ ] **Step 9.2** — Route `RebalancingActionsController::store` (and any update/destroy methods) through store.
- [ ] **Step 9.3** — Audit `app/Services/Investment/Rebalancing/*` for any rebalancing-service write to `RebalancingAction::create` (the analysis engine creates action rows when it detects drift). If found, route through store with `IngestSource::ADMIN` or `IngestSource::FYN_AI` depending on caller.
- [ ] **Step 9.4** — Tier-cap: `rebalancing_action => null` (no cap — engine-generated).
- [ ] **Step 9.5** — Boundary test + integration test.
- [ ] **Step 9.6** — Commit + open PR 9.

---

## 14. PR 10 — Canonical derived columns + snapshots + cross-store recalc

**PR title**: `feat(investments): canonical derived columns + snapshots + Holdings→Account/Pension recalc (SP1 Pass 6 PR 10)`

**Branch**: `feat/investment-store-pr10` off `dev` post-PR-9-merge.

**Architecturally significant** — mirrors Pass 5 PR 6 (cross-store recalc) but with TWO consumer stores (InvestmentAccount + DCPension), TWO derived-column calculators, TWO snapshot tables, MULTIPLE migrations.

**Migrations (estimated 5-6)**:
1. Add derived columns to `investment_accounts`: `current_value_gbp`, `current_value_gbp_calculated_at`, `holdings_count`, `holdings_count_calculated_at` (the canonical holdings count, replacing any denormalised cache)
2. Add derived columns to `holdings`: `value_gbp`, `value_gbp_calculated_at` (current value × FX-rate; GBP-only today)
3. Create `investment_account_value_snapshots` table
4. Create `holding_value_snapshots` table
5. Add `holdings_value_calculated_at` to `dc_pensions` (for the cross-module recalc to DCPension parent)
6. Optional: add `current_value_calculated_at` to `dc_pensions` if Pass 3 didn't already

**Calculators** (mirror Pass 5 PR 6):
- [ ] **Step 10.1** — `InvestmentAccountDerivedColumnCalculator` — sums Holdings values into `current_value_gbp`, counts active holdings, etc.
- [ ] **Step 10.2** — `HoldingDerivedColumnCalculator` — computes `value_gbp` (units × price × FX-rate).

**Listeners (already created in PR 6)**:
- `RecalculateAccountHoldingsValue` — wired in PR 6 to InvestmentAccount parents
- `RecalculatePensionHoldingsValue` — wired in PR 6 to DCPension parents

**Snapshot policies** (add to `SnapshotPolicies.php`):
- `investmentAccountValue` (≥£1k OR ≥0.5% relative)
- `holdingValue` (≥£100 OR ≥0.5% relative — smaller threshold for individual holdings)

**Backfill commands**:
- `investments:backfill-account-derived-columns`
- `holdings:backfill-derived-columns`
- `investments:backfill-account-holdings-sum` (reconciles `investment_accounts.current_value` from canonical Holdings sum)
- `pensions:backfill-holdings-sum` (cross-module — reconciles `dc_pensions.current_value` from canonical Holdings sum where holdable_type=DCPension)

**Tests**:
- `tests/Unit/Services/Stores/Recalc/InvestmentAccountDerivedColumnCalculatorTest.php`
- `tests/Unit/Services/Stores/Recalc/HoldingDerivedColumnCalculatorTest.php`
- `tests/Feature/Stores/HoldingAccountReconciliationTest.php` (mirror Pass 5 MortgagePropertyReconciliationTest — 5+ cases incl. loop prevention)
- `tests/Feature/Stores/HoldingPensionReconciliationTest.php` (cross-module variant — verifies Holding writes to DCPension parents trigger correct PensionStore recalc, NOT InvestmentAccountStore)
- `tests/Feature/Stores/InvestmentDerivedColumnsBackfillTest.php`

**Critical**: the loop-prevention test must cover BOTH polymorph parent types. Two `Event::fake` blocks asserting no Holding events fire when either parent is recalc'd.

- [ ] **Step 10.3** through **10.15** — implementation per Pass 5 PR 6 template, doubled for the two parent stores.
- [ ] **Step 10.16** — Commit + open PR 10.

**Deploy gate**: 5-6 new migrations. csjones needs `php artisan migrate --force` on deploy.

---

## 15. PR 11 — Tier-cap test

**PR title**: `feat(investments): tier-cap enforcement tests (SP1 Pass 6 PR 11)`

**Branch**: `feat/investment-store-pr11` off `dev` post-PR-10-merge.

**Sibling**: Pass 5 PR 7.

**One test file per tier-capped store** (4 files):
- `tests/Feature/Stores/InvestmentAccountTierCapTest.php` (5 cases per Pass 5 sibling)
- `tests/Feature/Stores/HoldingTierCapTest.php` (5 cases — verifies the polymorph parent's tier-cap is respected; cross-store-cap nuance)
- `tests/Feature/Stores/InvestmentGoalTierCapTest.php` (5 cases)
- `tests/Feature/Stores/InvestmentScenarioTierCapTest.php` (5 cases — paid-tier-only, free tier returns 0 cap = blocked)

RiskProfile + RebalancingAction have no tier cap (unique-per-user / engine-generated). Skip.

- [ ] **Step 11.1** — Write each test file. Use inline canonical arrays (Pass 5 PR 7 convention; plan template had global-function bug).
- [ ] **Step 11.2** — Commit + open PR 11.

---

## 16. PR 12 — Lock-down + parity + audit + Store.md ×6 (SP1 §16 close-out IN-LINE)

**PR title**: `lock-down(investments): allowlist LOCKED + audit + parity + Store.md (SP1 Pass 6 PR 12 — final closure)`

**Branch**: `feat/investment-store-pr12` off `dev` post-PR-11-merge.

**Deliverables**:
- [ ] **Step 12.1** — Rewrite all 6 boundary tests to LOCKED framing (mirror Pass 5 PR 8). Final allowlists per Pass 5 precedent (`EncryptExistingData` + `ResetPreviewData` + `PreviewUserSeeder` where applicable per entity).
- [ ] **Step 12.2** — Write audit ingest-source tests:
  - `InvestmentAccountAuditIngestSourceTest` (6 cases — 5 IngestSource + leak prevention)
  - `HoldingAuditIngestSourceTest` (6 cases)
  - `InvestmentGoalAuditIngestSourceTest` (6 cases)
  - `RiskProfileAuditIngestSourceTest` (6 cases)
  - `InvestmentScenarioAuditIngestSourceTest` (6 cases)
  - `RebalancingActionAuditIngestSourceTest` (6 cases)
- [ ] **Step 12.3** — Write three-ingest parity tests:
  - `InvestmentAccountThreeIngestParityTest` (2 cases incl. TIC → joint coercion)
  - `HoldingThreeIngestParityTest` (2 cases incl. polymorph parent verification)
  - Goal/Risk/Scenario/RebalancingAction parity tests as applicable (some only have 1-2 ingest paths)
- [ ] **Step 12.4** — Write 6 Store.md files (one per entity). Target ~200 lines each:
  - `app/Services/Stores/InvestmentAccountStore.md`
  - `app/Services/Stores/HoldingStore.md` — must call out the cross-module polymorphism + the PensionStore.md update closing the Pass 3 deferral
  - `app/Services/Stores/InvestmentGoalStore.md`
  - `app/Services/Stores/RiskProfileStore.md` (note observer-driven nature)
  - `app/Services/Stores/InvestmentScenarioStore.md` (note paid-tier-only)
  - `app/Services/Stores/RebalancingActionStore.md` (note engine-generated nature)
- [ ] **Step 12.5** — Update `app/Services/Stores/PensionStore.md` to remove the Pass 3 deferral note at `:40` (the `DCPensionHoldingsController` direct-write debt is now closed).
- [ ] **Step 12.6** — Run the full Pass 6 sweep (every Pass 6 test file). All green.
- [ ] **Step 12.7** — Commit + open PR 12 — final PR of Pass 6.

**§16.1 acceptance gates closed inline**:
- Gate 1 (single write path) — 6 LOCKED boundary tests
- Gate 2 (three-ingest parity) — parity tests across applicable entities
- Gate 3 (audit completeness) — 6 audit ingest tests
- Gate 4 (derived columns) — PR 10 covers
- Gate 5 (snapshot policy) — PR 10 covers
- Gate 6 (currency round-trip) — DEFERRED (GBP-only per Pass 5 precedent; revisit in Pass 14+ if needed)
- Gate 7 (tier-cap) — PR 11 covers
- Gate 8 (Playwright browser-smoke) — opens for csjones post-merge

---

## 17. Acceptance criteria mapping (spec §16)

| §16.1 gate | Met by |
|---|---|
| 1. Single write path (Pest boundary × 6 entities) | PR 12 — all 6 LOCKED |
| 2. Three-ingest parity | PR 12 — 4+ parity tests |
| 3. Audit completeness — ingest_source | PR 1-9 (each store wraps via `AuditLog::withContext`) + PR 12 (6 verification tests) |
| 4. Derived-column correctness | PR 10 — 2 calculators + tests |
| 5. Snapshot policy applied | PR 10 — `investmentAccountValue` + `holdingValue` policies |
| 6. Currency round-trip | n/a — GBP-only in Pass 6 (deferred per Pass 5 precedent; flagged in Store.md as future work) |
| 7. Tier-cap enforcement | PR 1, 8, 9 (seams) + PR 11 (4 tier-cap tests) |
| 8. Browser-tested via Playwright | csjones smoke after PR 12 merge — CSJ-driven |

**§16.2 progress after Pass 6**:
- 14 of 19 stores fully shipped (Savings + 4 ref-data + Pensions + Properties + Mortgages + InvestmentAccount + Holding + InvestmentGoal + RiskProfile + InvestmentScenario + RebalancingAction)
- 15 boundary tests passing (Pass 6 adds 6)
- 9+ parity tests shipped
- 14 Store.md docs landed (adds 6)

---

## 18. Risks + mitigations

| Risk | Mitigation |
|---|---|
| **Cross-module HoldingStore complexity** — polymorphic parent breaks Pass 3 Pensions if mis-routed | PR 6 explicit tests for both `InvestmentAccount` AND `DCPension` parents. `HoldingPensionReconciliationTest` verifies cross-store recalc reaches PensionStore correctly. Pass 3 PensionStore.md updated in PR 12 only after PR 7 routing verified. |
| **Loop prevention via 2 parent stores** — Holding write → Account or Pension recalc, where the parent's `saveQuietly` must skip back-firing Holding events | Mirror Pass 5 PR 6 saveQuietly invariant. PropertyStore + PensionStore.recalculateDerivedForXxxId methods both use `forceFill + saveQuietly`. Loop-prevention tests in PR 10 for both branches. |
| **InvestmentAccount has 173 refs — PR 5 sub-clustering bloat** | 5 sub-clusters (5a-5e) split by service domain. Each sub-cluster reviewed independently. Pass 4 Property + Pass 5 Mortgage proved this pattern. |
| **Observer entanglement** — InvestmentAccountRiskObserver + GoalObserver must keep firing on store writes | Store uses plain `->save()` for user-driven writes (fires events). Only cross-store recalc uses `saveQuietly`. Documented in Pass 6 Store.md per Pass 5 PR 6 precedent. |
| **RiskProfile one-per-user** | Use `updateOrCreate` keyed on `user_id`. Existing unique index in DB enforces. |
| **InvestmentScenario paid-tier blocking** — free users must get a clean tier-cap-exceeded error, not a fatal | PR 11 tier-cap test covers. Free-tier cap = 0 throws TierLimitExceededException at create. |
| **MigrateEstateToNetWorth investment branch** — pre-existing direct writes during migration | Audit in PR 4 (mirror Pass 4 PR 4 + Pass 5 PR 4 pattern). Route through store or document as allowlisted command. |
| **5-6 migrations in PR 10** — csjones drift risk | Plan a single csjones re-deploy window after PR 10 + PR 12 merge. Document deploy gate explicitly. |
| **InvestmentAccount schema is FAT** (~50 columns) — normaliser complexity | Normaliser handles aliasing + defaults per ingest path. Pass 5 MortgageNormaliser is the sibling. |
| **Plan/spec drift** — Pass 5 plan §11/12 had several bugs caught in implementation (wrong exception class, global function helpers, etc.) | This plan references `App\Services\Stores\Exceptions\TierLimitExceededException` correctly throughout. Uses inline canonical arrays in test samples. Migration index names kept under 64 chars. |

---

## 19. Open questions — resolve at PR 0 / PR 1 dispatch

| Q | Notes / proposed resolution |
|---|---|
| **Q1** — Tier-cap defaults for the 6 entities | Proposed: `investment_account => 2` (free) / `null` (tier1+) — already in seeder; `holding => null` (no cap; bounded by parent ownership); `investment_goal => 2` / `null`; `risk_profile => 1` (unique index); `investment_scenario => 0` (free blocked) / `null`; `rebalancing_action => null` (engine-generated). CSJ confirms at PR 1. |
| **Q2** — `HoldingStore::forParent` return shape — keyed by parent_id or flat? | Proposed: flat `Collection<int, Holding>` (mirrors `MortgageStore::forProperty`). Keyed variant via `forUserByParent` (mirrors `forUserByProperty`). |
| **Q3** — RebalancingAction full store vs documented exception? | RESOLVED: full store (PR 9). `RebalancingActionsController:57` is a confirmed write site. |
| **Q4** — RiskProfile observer compatibility — store-driven writes must still fire `RecalculateRiskProfileJob` | RESOLVED: store uses `->save()` (fires events). Observer chain preserved. Documented in Store.md. |
| **Q5** — Keep or drop `investment_accounts.current_value` denormalised column after Pass 6? | Proposed: KEEP as derived (write-only-by-recalc) for backward compatibility with read consumers. Same as Pass 5 PR 6 `properties.outstanding_mortgage` decision. |
| **Q6** — Cross-module HoldingStore — should it live under `app/Services/Stores/` or split by module? | Proposed: `app/Services/Stores/HoldingStore.php` at the root level (alongside MortgageStore, PropertyStore). It's a SHARED store across two modules — its location reflects that. Document the cross-module nature in Store.md. |
| **Q7** — InvestmentTransaction model — confirm not needed | RESOLVED: doesn't exist; aspirational spec reference. Confirm in §0.1. |
| **Q8** — Currency round-trip — defer or implement in Pass 6? | Proposed: DEFER (GBP-only). Pass 5 deferred; Pass 6 inherits. Flag for Pass 14+ revisit. |

---

## 20. Pass 6 progress tracker

| PR | Title | Branch | Status |
|----|-------|--------|--------|
| 1 | InvestmentAccountStore facade + boundary + normaliser + events + tier-cap | `feat/investment-store-pr1` | pending |
| 2 | HTTP form requests through InvestmentAccountStore | `feat/investment-store-pr2` | pending |
| 3 | Fyn AI write tools through InvestmentAccountStore | `feat/investment-store-pr3` | pending |
| 4 | Upload + onboarding + seeders + MigrateEstateToNetWorth | `feat/investment-store-pr4` | pending |
| 5a | Investment-internal Analytics/AssetLocation/Fees + parity test | `feat/investment-store-pr5a` | pending |
| 5b | Goals/ModelPortfolio/Performance reads | `feat/investment-store-pr5b` | pending |
| 5c | Rebalancing/Recommendation/Tax reads | `feat/investment-store-pr5c` | pending |
| 5d | Utilities + Agents reads | `feat/investment-store-pr5d` | pending |
| 5e | Cross-module reads (NetWorth/Mobile/CrossModule/AI/Profile/Plans/GDPR) | `feat/investment-store-pr5e` | pending |
| 6 | HoldingStore cross-module facade + listeners + PensionStore.recalculate | `feat/investment-store-pr6` | pending |
| 7 | Holding routing (HTTP InvestmentController + DCPensionHoldingsController + Fyn + upload) | `feat/investment-store-pr7` | pending |
| 8 | InvestmentGoalStore + RiskProfileStore + InvestmentScenarioStore + routing | `feat/investment-store-pr8` | pending |
| 9 | RebalancingActionStore + routing | `feat/investment-store-pr9` | pending |
| 10 | Derived columns + snapshots + cross-store recalc (×2 parents) | `feat/investment-store-pr10` | pending |
| 11 | Tier-cap tests (×4 entities) | `feat/investment-store-pr11` | pending |
| 12 | Lock-down + parity + audit + Store.md (×6) + PensionStore.md cleanup | `feat/investment-store-pr12` | pending |

**Total**: 16 PRs (12 numbered + 4 sub-clusters for PR 5). Estimated 7-10 day execution at Pass 5 cadence.

**Sub-Project 1 progress when Pass 6 closes**: **14 of 19** entity stores fully shipped (Savings + 4 ref-data + Pensions + Properties + Mortgages + InvestmentAccount + Holding + InvestmentGoal + RiskProfile + InvestmentScenario + RebalancingAction).

---

## Implementer notes for next session

1. **Start with PR 0 audit** (verify §1.1 file lists, confirm every entity's joint-aware status, audit `MigrateEstateToNetWorth` investment branch, audit `RebalancingAction` writes, confirm DCPension cross-module Holdings shape). Audit can be inline in PR 1 dispatch brief (no separate PR for the audit — verification happens in PR 1 itself).

2. **Reference Pass 5 PR 1** (`git show fe5e1a1`) for the latest store conventions — constructor shape, public API ordering, validation pattern, audit context wrapping.

3. **Reference Pass 5 PR 6** (`git show 8ec33c6`) for the cross-store recalc pattern — listener shape, EventServiceProvider wiring, calculator interface, snapshot policy thresholds.

4. **Reference Pass 5 PR 8** (`git show e4d8039`) for the LOCKED boundary framing, audit test shape, and Store.md structure.

5. **The Pint formatter strips unused imports** — every implementer dispatch should be pre-warned. If 2 SendMessage nudges don't resolve the stall, main thread takes over manually.

6. **csjones deploy gate** accumulates 5-6 more migrations across PR 10 alone. Plan a re-deploy window after PR 12.

---

*Plan author: Claude Opus 4.7 (1M context), 2026-05-27 session 5, after Pass 5 closure at `e4d8039`.*
*Reviewed for sibling-pattern parity against Pass 5 Mortgages + Pass 4 Properties + Pass 3 Pensions plans.*
