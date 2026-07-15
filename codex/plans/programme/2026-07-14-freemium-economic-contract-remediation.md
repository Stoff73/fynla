# Freemium Economic Contract Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `systematic-debugging` for every failure, `security-and-hardening` for the authentication and payment changes, and `verify-m` at every mobile-web checkpoint. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every active Fynla surface implement the same permanent-Free plus Premium economic contract, repair the broken registration and upgrade journeys, remove misleading trial behaviour and language, and leave a stable backend contract for the later Swift application.

**Architecture:** The canonical tier keys become exactly `free` and `premium`; the unused `tier1`, `tier2` and `tier3` identities are removed rather than hidden behind aliases. `tier_configurations` remains the only runtime authority for capabilities, limits, quotas and web/Revolut prices, with the approved root `Fynla_Pricing_Page.html` supplying the product matrix and launch prices. A new canonical subscription-status service supplies web, `/m`, Fyn and future native clients from one response model. A provisional payment becomes `pending`, never `trialing`; registration always creates a writable Free account before optional checkout. Every entitlement and count cap remains server-enforced.

**Tech Stack:** Laravel 10, PHP 8, MySQL 8, Pest, Vue 3, Vuex, Vite, server-rendered PHP marketing pages, `/m` Vue mobile bundle, Revolut Merchant API.

**Source contract:** `Fynla_Pricing_Page.html` (approved economic matrix), `docs/superpowers/specs/2026-05-29-pure-freemium-signup-design.md` (permanent-Free registration), `database/seeders/TierConfigurationSeeder.php`, `app/Services/Stores/TierConfigurationStore.php`, `app/Services/Tiers/TierResolver.php`, and `app/Services/Tiers/DbTierGate.php`. Where the older documents disagree with the approved HTML, this plan records the two-tier decision explicitly and the approved HTML wins.

## Global Constraints

- New verified users are permanent Free users: `users.tier='free'`, no paid entitlement, no time limit and no credit-card requirement.
- A Free user may write. Count limits are enforced at the Store boundary by `DbTierGate`; limits block creates only and never delete existing data.
- The canonical tiers are exactly `free` and `premium`; `tier1`, `tier2` and `tier3` are not retained as runtime aliases.
- Free count caps are cash/savings accounts 2, investments 2, pensions 2, properties 1, Goals 2 and Life Events 1. The existing mortgage cap of 10 remains because the approved matrix does not replace it.
- Free remains writable. Existing records above a newly lowered cap are grandfathered: never delete or hide them, and block only the next create.
- Free receives the existing Estate teaser but no full Inheritance Tax engine, Estate writes, Holistic Plan, What If, retirement-decumulation planning, document upload, detailed expenditure, combined household view or advanced investment tools.
- Premium receives every implemented Fynla capability, unlimited entity counts, unlimited document count subject to 1 GB total storage, the full retained balance history, and 500,000 Fyn tokens per week.
- Web/Revolut Premium prices are £6.99 monthly and £59.99 annually (`699` and `5999` pence). Runtime rendering and charging still read the live `tier_configurations` row; the seeder establishes these approved values.
- Capabilities not mentioned in `Fynla_Pricing_Page.html` are not silently removed from Free. Existing Free access to Protection, income, liabilities, basic expenditure, personal valuables, risk, tax analysis and base future-value projections remains.
- “Joint household view” is a presentation/combined-planning entitlement, not permission to erase spouse records or disable the Free tax engine. Existing spouse data remains intact and spouse-aware tax calculations advertised by the approved page remain available.
- Paid-churn behaviour is preserved: access lasts to `current_period_end`, then terminal paid subscriptions enter the existing read-only/grace/retention flow. This plan does not silently convert churned paid accounts into writable Free accounts.
- No paid-tier downgrade endpoint is invented in this remediation. Copy must describe the implemented upgrade/cancel behaviour only. Apple downgrade policy is decided in the Swift subscription design.
- Preview users and administrators retain their current bypass behaviour.
- Backend entitlement checks remain authoritative. Frontend gating is guidance, never security.
- Every user-facing change is implemented and verified on desktop web and `/m` unless the surface has no mobile counterpart by design.
- The Capacitor iOS package is not rebuilt in this remediation; the native Swift application is a separate follow-on programme.
- All new user-facing text uses British English, spells out acronyms except ISA, and contains no emoji or decorative icons.
- UI work follows `fynlaDesignGuide.md`; palette tokens only, no new hardcoded colours and no new decorative icons.
- Never run `migrate:fresh` or `migrate:refresh`. Any approved migration is followed by `php artisan db:seed` in that environment.
- Run database-refreshing Pest files serially. Do not launch multiple `RefreshDatabase` suites against the same `laravel_testing` schema.
- Work flows `feature -> dev -> main`; all browser acceptance occurs on `csjones.co/fynla` before production release.

---

## Fixed Product Decisions

1. **Free is an account state, not a subscription.** `has_subscription=false` is valid and writable.
2. **A checkout attempt is not access.** A provisional database row uses `status='pending'`; the user's tier remains Free until verified payment activates the subscription.
3. **One status contract.** `/api/payment/subscription-status` becomes canonical. `/api/payment/trial-status` remains a temporary compatibility alias for one release and returns the identical response.
4. **Premium is canonical, not an alias.** Public pricing, authenticated upgrade UI, checkout, Revolut, `/m`, Fyn and the future Apple client all use the key `premium`.
5. **Registration is not paywalled.** A paid selection produces a checkout intent, but account verification and Free access complete first.
6. **One upgrade destination per web surface.** Desktop routes to `/settings/subscription?openPricing=1`; `/m` performs its authenticated parent-frame breakout to that same destination.
7. **No downgrade promise.** Until a real downgrade lifecycle exists, public and authenticated copy offers upgrades and cancellation only.
8. **Trial schema removal is data-gated.** Trial columns/statuses are removed only after the audit command proves there are no unresolved trial-origin rows on staging and production.
9. **The clean collapse is data-gated.** CSJ confirmed on 2026-07-14 that there are no currently paid accounts. A read-only preflight command must independently prove there is no active paid entitlement before the tier enum migration runs in each environment.
10. **The root HTML is a contract reference, not a deployable artifact.** Production remains `public/pages/pricing.php` with the Fynla design system, accessibility and SEO requirements; do not ship the mock-up's hardcoded colours, glyphs, placeholder links or “demonstration purposes” footer.

## Transition Approach Decision

| Approach | Trade-off | Decision |
|---|---|---|
| Clean collapse to `free,premium` | Requires one guarded enum/data migration, then leaves the simplest contract for web, `/m`, Revolut and Swift | **Selected** because CSJ confirmed there are no currently paid accounts |
| Keep Tier 1/2/3 internally and market one as Premium | Fastest page-only change, but preserves the exact identity/capability mismatch that caused the current inconsistency | Rejected |
| Add Premium while accepting all four paid keys for a transition period | Useful when active payers need cohort migration, but expands every API, gate, test and StoreKit reconciliation path | Rejected unless the preflight audit contradicts the confirmed zero-paid state |

## Canonical Tier Snapshot

| Tier | Account counts | Main capability change | Other differentiators |
|---|---|---|---|
| Free | Cash/savings 2; investments 2; pensions 2; properties 1; Goals 2; Life Events 1; mortgages 10 | Core tracking, UK tax analysis, risk and base projections; Estate teaser | No document uploads; 100,000 weekly Fyn tokens; GBP only; 90-day visible history |
| Premium | Unlimited for every count-gated entity | Every implemented feature, including full Estate and Inheritance Tax planning, combined household view, detailed expenditure, retirement planning, What If, Holistic Plan, statement upload/extraction, plan export and investment fee analysis | Unlimited document count within 1 GB; 500,000 weekly Fyn tokens; currency choice; full retained history; open API affordance; £6.99 monthly or £59.99 annually |

## Findings Coverage

| Finding | Remediation task |
|---|---|
| Historical `/trial-status` name and duplicated status logic | Task 4 |
| Provisional payment rows use `trialing` | Task 5 |
| Backend still has four tier keys despite the approved two-tier product | Tasks 1 and 2 |
| Free limits differ from the approved matrix; Goals and Life Events have no server count gate | Task 2 |
| Free document uploads and Premium history/storage do not match the approved matrix | Task 2 |
| Pricing promises balance history/year-on-year trends but investments and liabilities lack snapshot coverage and no customer history endpoint exists | Task 3 |
| Pricing promises an adviser export pack but only per-plan client-side printing exists | Task 3 |
| Free registration with a paid selection does not continue to checkout | Task 7 |
| Public Premium merges several tiers while charging/selecting a different tier | Task 6 |
| Public pricing claims Stripe and unsupported downgrades | Task 6 |
| Free users do not receive the navbar upgrade action | Task 8 |
| A Premium user can open an empty upgrade modal | Task 8 |
| Limit modals and `/m` upgrade actions land on General settings | Task 8 |
| Desktop pension create lacks a proactive cap check | Task 8 |
| `/m` Estate teaser has no upgrade action | Task 8 |
| Fyn at-cap replies have no structured upgrade action | Task 8 |
| Settings calls Free “Free Trial” and uses a nonexistent checkout route | Task 9 |
| Subscription management and forced modal retain trial branches/copy | Task 9 |
| Notification/admin discount/metrics UI retain trial concepts | Task 10 |
| Guest, campaign, insight and terms pages retain trial copy | Task 11 |
| Trial lifecycle services, commands, seed data and AI billing tools remain | Tasks 10 and 12 |
| Churn classification depends on trial dates rather than completed payment | Task 10 |
| Vault and design-system documentation describe the removed trial and four-tier models | Task 13 |
| `/m` has no native registration/paywall | Deliberately handled by the Swift migration plan, not this remediation |

---

### Task 1: Collapse Tier Identity to Canonical Free and Premium Keys

**Files:**
- Create: `app/Console/Commands/AuditTierCollapse.php`
- Create: `database/migrations/2026_07_15_000000_collapse_tier_identity_to_free_premium.php`
- Modify: `database/migrations/2026_05_17_100000_create_tier_configurations_table.php` only through the new forward migration; do not rewrite applied history
- Modify: `database/seeders/TierConfigurationSeeder.php`
- Modify: `app/Services/Stores/TierConfigurationStore.php`
- Modify: `app/Services/Tiers/TierResolver.php`
- Modify: `app/Services/Tiers/RevolutTierVariationSync.php`
- Modify: `app/Http/Controllers/Api/PaymentController.php`
- Modify: `app/Http/Requests/Admin/UpdateTierConfigurationRequest.php`
- Modify: `app/Http/Resources/TierConfigurationResource.php`
- Modify: `resources/js/components/Admin/TierConfiguration.vue`
- Test: `tests/Feature/Console/AuditTierCollapseTest.php`
- Test: `tests/Feature/Tiers/TwoTierIdentityTest.php`
- Update: tier-identity assertions under `tests/Feature/Tiers/`

**Interfaces:**
- Produces: `TierConfigurationStore::TIERS === ['free', 'premium']`.
- Produces: `TierResolver::resolve(User): 'free'|'premium'` for all canonical users.
- Produces: selectable billing plan key `premium`; legacy `student`, `standard`, `family`, `pro`, `tier1`, `tier2` and `tier3` are never returned by active pricing APIs.
- Produces: `php artisan subscriptions:audit-tier-collapse --json`, a read-only deployment gate.

- [ ] **Step 1: Write the failing identity and audit tests**

Assert the store exposes only the canonical keys and the public price endpoint does not leak retired tiers:

```php
expect(TierConfigurationStore::TIERS)->toBe(['free', 'premium']);

$this->getJson('/api/pricing-config')
    ->assertOk()
    ->assertJsonCount(2, 'data')
    ->assertJsonPath('data.0.tier', 'free')
    ->assertJsonPath('data.1.tier', 'premium');
```

For the audit command, seed one case at a time and assert stable JSON fields:

```json
{
  "active_paid_subscriptions": 0,
  "active_paid_users": 0,
  "completed_payments": 0,
  "retired_tier_user_rows": 0,
  "retired_tier_subscription_rows": 0,
  "safe_to_collapse": true
}
```

`safe_to_collapse` must become `false` when an active or still-entitled paid subscription exists. Historical completed payments are reported but preserved; they do not make the command destructive or rewrite invoice/payment amounts.

- [ ] **Step 2: Run the new tests and confirm they fail**

```bash
./vendor/bin/pest tests/Feature/Console/AuditTierCollapseTest.php
./vendor/bin/pest tests/Feature/Tiers/TwoTierIdentityTest.php
```

Expected: fail because the command and migration do not exist and the store still exposes four tiers.

- [ ] **Step 3: Implement the read-only preflight command**

`AuditTierCollapse` queries `subscriptions`, `payments` and `users` without updates. Treat `active`, `cancelled` with a future `current_period_end`, and `past_due` with a future `current_period_end` as currently entitled. Real-account safety counts exclude preview and explicitly flagged lifecycle test users, while retired-row counts still report them so the migration mapping is observable. `--json` prints only the stable object above and exits `1` when `safe_to_collapse=false`; the human format prints the same counts and a clear migration block reason.

Before any migration on local, csjones or production, run:

```bash
php artisan subscriptions:audit-tier-collapse --json
```

Expected from the CSJ-confirmed current state: `active_paid_subscriptions=0`, `active_paid_users=0`, `safe_to_collapse=true`. A non-zero result stops deployment and requires a CSJ data decision; do not silently coerce an active payer.

- [ ] **Step 4: Implement the forward-only identity collapse migration**

The migration first repeats the active-entitlement precondition and throws a clear exception if the count is non-zero, so a skipped command cannot silently collapse a payer. It then performs these operations inside the safest transaction/DDL boundaries supported by MySQL:

1. Expand `users.tier`, `users.plan`, `subscriptions.plan` and `tier_configurations.tier` enums to accept `premium` while retaining old values temporarily.
2. Insert a `premium` configuration by copying the current Tier 2 row if it is absent. Tier 2 is only a zero-downtime bridge; `TierConfigurationSeeder` replaces every economic field in Task 2.
3. Map `users.tier IN ('tier1','tier2','tier3')`, `users.plan IN ('tier1','tier2','tier3')` and `subscriptions.plan IN ('tier1','tier2','tier3')` to `premium`, preserving IDs, period dates, amounts, payments and invoices.
4. Delete the now-unreferenced Tier 1, Tier 2 and Tier 3 configuration rows.
5. Narrow `tier_configurations.tier` and `users.tier` to `free,premium`. Keep historical plan slugs in `users.plan` and `subscriptions.plan` only so old rows remain readable; application validation must reject them for new checkout.

The down migration expands the retired enum values and maps `premium` to `tier2`; it explicitly cannot reconstruct the former tier distinction. State this in the migration docblock.

- [ ] **Step 5: Replace hardcoded four-tier orders and validation**

Use one canonical constant:

```php
public const TIERS = ['free', 'premium'];
```

`TierResolver`, `TierConfigurationStore::allActiveOrdered()`, `allOrdered()`, `lowestTierWithCapability()`, `PaymentController::TIER_KEYS`, tier order comparisons, Revolut variation sync and the admin configuration surface must all consume this order. Active `/api/payment/plans` and `/api/pricing-config` responses return Free and Premium only. Checkout validation accepts only `premium` as a paid plan.

- [ ] **Step 6: Make the seeder idempotently remove retired configuration rows**

After upserting `free` and `premium`, delete configuration rows whose key is not canonical:

```php
TierConfiguration::whereNotIn('tier', TierConfigurationStore::TIERS)->delete();
```

Do not delete `subscriptions`, `payments`, invoices or audit logs.

- [ ] **Step 7: Update tier-identity test coverage**

Replace four-tier expectations in:

```text
tests/Feature/Tiers/TierConfigurationsTableTest.php
tests/Feature/Tiers/TierConfigurationSeederTest.php
tests/Feature/Tiers/RevolutTierVariationSyncTest.php
tests/Feature/Tiers/TierLifecycleTest.php
tests/Feature/Payment/TierKeyAcceptanceTest.php
tests/Feature/Tiers/Sp1WiringBehaviouralTest.php
```

Add negative assertions proving `tier1`, `tier2` and `tier3` return validation errors for new pricing, registration and checkout requests, while historical mapped rows remain readable as Premium.

- [ ] **Step 8: Run focused verification, format and commit**

```bash
./vendor/bin/pest tests/Feature/Console/AuditTierCollapseTest.php
./vendor/bin/pest tests/Feature/Tiers/TwoTierIdentityTest.php
./vendor/bin/pest tests/Feature/Tiers
./vendor/bin/pest tests/Feature/Payment/TierKeyAcceptanceTest.php
./vendor/bin/pint app/Console/Commands/AuditTierCollapse.php app/Services/Stores/TierConfigurationStore.php app/Services/Tiers/TierResolver.php app/Services/Tiers/RevolutTierVariationSync.php app/Http/Controllers/Api/PaymentController.php database/seeders/TierConfigurationSeeder.php database/migrations/2026_07_15_000000_collapse_tier_identity_to_free_premium.php
git add app database resources/js/components/Admin tests/Feature/Console/AuditTierCollapseTest.php tests/Feature/Tiers tests/Feature/Payment/TierKeyAcceptanceTest.php
git commit -m "refactor(tiers): collapse pricing model to Free and Premium"
```

---

### Task 2: Implement the Approved Two-Tier Capability, Limit and Quota Matrix

**Files:**
- Create: `database/migrations/2026_07_15_000001_support_unbounded_premium_quotas.php`
- Create: `app/Services/Stores/GoalStore.php`
- Create: `app/Services/Stores/LifeEventStore.php`
- Modify: `database/seeders/TierConfigurationSeeder.php`
- Modify: `app/Models/TierConfiguration.php`
- Modify: `app/Services/Tiers/DbTierGate.php`
- Modify: `app/Services/Documents/DocumentAllowanceGate.php`
- Modify: `app/Services/Stores/Snapshots/SnapshotPolicy.php`
- Modify: `app/Services/Stores/Snapshots/SnapshotPolicies.php`
- Modify: `app/Http/Controllers/Api/GoalsController.php`
- Modify: `app/Http/Controllers/Api/LifeEventController.php`
- Modify: `app/Services/Goals/LifeEventService.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Modify: `app/Http/Middleware/CheckSubscription.php`
- Modify: `resources/js/constants/tierAccess.js`
- Test: `tests/Feature/Tiers/ApprovedTwoTierMatrixTest.php`
- Test: `tests/Feature/Tiers/GoalLifeEventTierCapTest.php`
- Test: `tests/Feature/Tiers/PremiumCapabilityEnforcementTest.php`
- Test: `tests/Feature/Documents/TwoTierDocumentAllowanceTest.php`
- Test: `tests/Unit/Services/Stores/Snapshots/TwoTierSnapshotPolicyTest.php`

**Interfaces:**
- Produces: count keys `savings_account`, `investment`, `pension_account`, `property`, `mortgage`, `goal`, `life_event`.
- Produces: `GoalStore::create(array $canonical, User $user, IngestSource $source): Goal` and `LifeEventStore::create(array $canonical, User $user, IngestSource $source): LifeEvent` as the only application create boundaries for their entities.
- Produces: nullable `document_upload_allowance` and `snapshot_surfacing_window_days`; `null` means unbounded within storage/retention policy.

- [ ] **Step 1: Write an exact matrix contract test**

Assert the seeded rows exactly match the approved commercial contract:

```php
$free = app(TierConfigurationStore::class)->forTier('free');
$premium = app(TierConfigurationStore::class)->forTier('premium');

expect($free->count_caps)->toMatchArray([
    'savings_account' => 2,
    'investment' => 2,
    'pension_account' => 2,
    'property' => 1,
    'mortgage' => 10,
    'goal' => 2,
    'life_event' => 1,
])->and($free->document_upload_allowance)->toBe(0)
  ->and($free->fyn_weekly_token_budget)->toBe(100_000)
  ->and($free->snapshot_surfacing_window_days)->toBe(90)
  ->and($premium->price_monthly_pence)->toBe(699)
  ->and($premium->price_annual_pence)->toBe(5999)
  ->and($premium->count_caps)->toMatchArray([
      'savings_account' => null,
      'investment' => null,
      'pension_account' => null,
      'property' => null,
      'mortgage' => null,
      'goal' => null,
      'life_event' => null,
  ])
  ->and($premium->document_upload_allowance)->toBeNull()
  ->and((float) $premium->document_storage_gb)->toBe(1.00)
  ->and($premium->fyn_weekly_token_budget)->toBe(500_000)
  ->and($premium->snapshot_surfacing_window_days)->toBeNull();
```

Also assert Free keeps core Protection, income, liabilities, base expenditure, personal valuables, risk, tax and base-projection access, while Premium has `full` for every implemented capability.

- [ ] **Step 2: Run the matrix test and confirm it fails**

```bash
./vendor/bin/pest tests/Feature/Tiers/ApprovedTwoTierMatrixTest.php
```

Expected: fail on old caps, old prices, non-null paid document counts and four-tier capability rows.

- [ ] **Step 3: Add nullable quota schema semantics**

Make `tier_configurations.document_upload_allowance` and `snapshot_surfacing_window_days` nullable. Update model casts, admin validation and resources. The semantic contract is:

```text
document_upload_allowance = 0    -> uploads unavailable
document_upload_allowance = N    -> at most N retained documents
document_upload_allowance = null -> unlimited document count; storage ceiling still applies
snapshot_surfacing_window_days=N -> only N most recent days visible
snapshot_surfacing_window_days=null -> all snapshots retained by platform policy visible
```

`SnapshotPolicy::surfacingWindow(string $tier): ?int` and its PHPDoc must reflect the nullable return.

- [ ] **Step 4: Seed the exact Free and Premium rows**

Use the snapshot above plus these explicit capability decisions:

```php
'free' => [
    'dashboard' => 'full', 'protection' => 'full',
    'income' => 'full', 'liabilities' => 'full', 'expenditure' => 'full',
    'expenditure_detailed' => 'none', 'tax_strategy' => 'full',
    'risk_profile' => 'full', 'future_value_projections' => 'full',
    'savings_account' => 'limited', 'investment' => 'limited',
    'pension_account' => 'limited', 'property' => 'limited',
    'goals' => 'limited', 'life_events' => 'limited',
    'chattels' => 'full', 'estate' => 'teaser',
    'joint_household_view' => 'none', 'letter_to_spouse' => 'none',
    'investments_exotic' => 'none', 'property_buy_to_let_analysis' => 'none',
    'retirement_decumulation' => 'none', 'what_if' => 'none',
    'holistic_plan' => 'none', 'document_upload' => 'none',
    'statement_upload' => 'none', 'advisor_export' => 'none',
    'investment_cost_analysis' => 'none',
],
'premium' => 'full for every key above',
```

Retain existing `benefits_child` and base family-data capture access on Free. The new `joint_household_view` key gates combined household presentation/planning without deleting spouse data or disabling spouse-aware Save Tax calculations.

- [ ] **Step 5: Write failing create-boundary cap tests**

Cover HTTP and Fyn/direct-write paths. A Free user with one existing Goal can create the second and receives a structured `tier_limit_reached` response on the third. A Free user with one Life Event is blocked on the second. Premium is unlimited. Existing Free users already above a new cap can read, edit and delete every existing row but cannot create another.

- [ ] **Step 6: Route Goal and Life Event creation through stores**

`GoalStore` and `LifeEventStore` count `forUserOrJoint($user->id)` records so joint records are counted once for the viewing household. Before persistence they call:

```php
if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $currentCount)) {
    throw new TierLimitExceededException(self::ENTITY_KEY, $this->tierGate->hardLimit($user, self::ENTITY_KEY));
}
```

Replace `Goal::create()` and `LifeEvent::create()` in `GoalsController`, `LifeEventService` and `CoordinatingAgent` with the stores. Preserve current validation, ownership, audit context, cache invalidation and API resources. Do not gate updates, deletes or reads.

- [ ] **Step 7: Correct document and history enforcement**

`DocumentAllowanceGate` must block Free immediately at allowance `0`; when allowance is `null`, skip the count comparison and enforce Premium's 1 GB storage ceiling. At the terminal Premium tier, storage-limit errors return `target_tier=null` and instruct the user to remove documents rather than offering a nonexistent higher tier.

Snapshot consumers treat a null surfacing window as no tier filter while the existing seven-year retention and hard-row ceilings remain operational safety limits.

- [ ] **Step 8: Add server capability enforcement for Premium-only tools**

Map the implemented endpoints for full Estate/Inheritance Tax, Holistic Plan, What If, document/statement upload and investment fee analysis to their matrix keys in `CheckSubscription` or focused middleware. Replace legacy `feature:standard` and `feature:pro` route middleware on these paths with capability checks. Frontend route gating in `tierAccess.js` mirrors the same keys but never replaces the server checks.

For capabilities represented inside a shared endpoint rather than a separate route—combined household view and detailed expenditure—shape the response according to the resolved tier and test that Free cannot obtain Premium-only fields by calling the API directly.

- [ ] **Step 9: Run focused verification, format and commit**

```bash
./vendor/bin/pest tests/Feature/Tiers/ApprovedTwoTierMatrixTest.php
./vendor/bin/pest tests/Feature/Tiers/GoalLifeEventTierCapTest.php
./vendor/bin/pest tests/Feature/Tiers/PremiumCapabilityEnforcementTest.php
./vendor/bin/pest tests/Feature/Documents/TwoTierDocumentAllowanceTest.php
./vendor/bin/pest tests/Unit/Services/Stores/Snapshots/TwoTierSnapshotPolicyTest.php
./vendor/bin/pint app/Services/Stores/GoalStore.php app/Services/Stores/LifeEventStore.php app/Services/Documents/DocumentAllowanceGate.php app/Services/Stores/Snapshots app/Http/Controllers/Api/GoalsController.php app/Http/Controllers/Api/LifeEventController.php app/Services/Goals/LifeEventService.php app/Agents/CoordinatingAgent.php app/Http/Middleware/CheckSubscription.php database/seeders/TierConfigurationSeeder.php database/migrations/2026_07_15_000001_support_unbounded_premium_quotas.php
git add app database resources/js/constants/tierAccess.js tests/Feature/Tiers tests/Feature/Documents tests/Unit/Services/Stores/Snapshots
git commit -m "feat(tiers): enforce approved Free and Premium matrix"
```

---

### Task 3: Deliver the Premium Balance-History and Adviser-Export Claims

**Files:**
- Create: `database/migrations/2026_07_15_000002_create_investment_and_liability_value_snapshots.php`
- Create: `app/Models/InvestmentAccountValueSnapshot.php`
- Create: `app/Models/LiabilityValueSnapshot.php`
- Create: `app/Services/Stores/LiabilityStore.php`
- Create: `app/Services/History/BalanceHistoryService.php`
- Create: `app/Http/Controllers/Api/BalanceHistoryController.php`
- Create: `app/Services/Plans/AdviserExportPackService.php`
- Create: `app/Http/Controllers/Api/AdviserExportPackController.php`
- Create: `resources/views/plans/adviser-export-pack.blade.php`
- Create: `resources/js/views/BalanceHistory.vue`
- Create: `resources/mobile/views/BalanceHistory.vue`
- Modify: `app/Services/Stores/InvestmentAccountStore.php`
- Modify: `app/Services/Stores/Snapshots/SnapshotPolicies.php`
- Modify: `app/Http/Controllers/Api/EstateController.php`
- Modify: `app/Services/Onboarding/OnboardingService.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Modify: `routes/api.php`
- Modify: `resources/js/router/index.js`
- Modify: `resources/mobile/router.js`
- Modify: relevant Net Worth history entry points under `resources/js/views/NetWorth/` and `resources/mobile/views/modules/`
- Test: `tests/Feature/History/BalanceHistoryEntitlementTest.php`
- Test: `tests/Feature/History/BalanceHistorySnapshotCoverageTest.php`
- Test: `tests/Feature/Plans/AdviserExportPackTest.php`
- Test: `tests/E2E/freemium/premium-history-export.spec.js`

**Interfaces:**
- Produces: authenticated `GET /api/balance-history?from=YYYY-MM-DD&to=YYYY-MM-DD` with a tier-clamped date range, series and year-on-year deltas.
- Produces: authenticated `GET /api/plans/adviser-export-pack` returning a generated PDF for Premium and `403 capability_denied` for Free.
- Consumes: `TierResolver`, `TierConfigurationStore`, existing value-snapshot models and the existing plan/holistic services.

- [ ] **Step 1: Write failing entitlement and snapshot-coverage tests**

Assert Free requests are clamped to the latest 90 days even if an older `from` date is supplied, while Premium receives all retained snapshots. Seed values 13 months apart and assert the Premium response includes an exact currency delta and percentage change; with less than 12 months, the response returns `year_on_year=null` rather than fabricating a trend.

Write store tests proving investment `current_value_gbp` and liability `current_balance` changes emit snapshots through every application write path: normal web CRUD, onboarding and Fyn inline capture. Preview seeding may remain a direct fixture path.

- [ ] **Step 2: Run the new tests and confirm they fail**

```bash
./vendor/bin/pest tests/Feature/History/BalanceHistoryEntitlementTest.php
./vendor/bin/pest tests/Feature/History/BalanceHistorySnapshotCoverageTest.php
./vendor/bin/pest tests/Feature/Plans/AdviserExportPackTest.php
```

Expected: fail because the snapshot tables, history endpoint and export service do not exist.

- [ ] **Step 3: Add the missing snapshot tables and models**

Mirror the established `*_value_snapshots` schema: parent foreign key, `column_name`, `value`, `currency`, `value_gbp`, `taken_at`, `trigger_reason`, `ingest_source`, timestamps and a short explicit `(parent_id,column_name,taken_at)` index name. Investment snapshots track `current_value_gbp`; liability snapshots track `current_balance`. Do not use floats for stored money.

- [ ] **Step 4: Route all application investment/liability writes through snapshot-aware stores**

Extend `InvestmentAccountStore` to write a snapshot on create and when the configured absolute/relative threshold is crossed. Add `LiabilityStore` with canonical create/update/delete/read methods, audit context and snapshot emission. Replace direct application writes in `EstateController`, `OnboardingService` and `CoordinatingAgent`; preserve preview-fixture direct writes. Add policies to `SnapshotPolicies` and retain the existing seven-year operational ceiling.

- [ ] **Step 5: Implement tier-clamped balance history**

`BalanceHistoryService` authorises ownership/joint ownership, combines savings, investments, pensions, properties, mortgages and other liabilities into dated series, and applies:

```php
$windowDays = $policy->surfacingWindow($tier);
$effectiveFrom = $windowDays === null
    ? $requestedFrom
    : max($requestedFrom, now()->subDays($windowDays));
```

Return plain financial metrics—dates, balances, currency changes and percentage changes—never a numerical quality score. Empty series return `[]`; they are not errors.

- [ ] **Step 6: Build the web and `/m` history views**

Both surfaces consume the same endpoint and display the user-selected period, available account series and year-on-year changes. Free clearly states that 90 days are visible and offers the shared Premium action; Premium says “Full available history”. Use the chart design constants and `ui-graph` skill during implementation. Do not add decorative icons.

- [ ] **Step 7: Build a real Premium adviser export pack**

`AdviserExportPackService` creates one dated PDF containing the user's profile summary, assets, liabilities, income/expenditure totals, risk profile, Goals/Life Events, module-plan summaries, assumptions and data-as-at timestamps. Reuse existing services/resources; do not duplicate financial calculations or include Fyn chat transcripts. The Blade template spells “financial adviser” correctly, includes the existing non-advice disclaimer and omits sections with no data rather than inventing zeros.

The controller is protected by `advisor_export=full`, ownership and the existing PDF download security conventions. Free receives a structured Premium action and cannot generate the file by calling the endpoint directly.

- [ ] **Step 8: Prove the other advertised Premium claims exist and are gated**

Add coverage showing statement upload/extraction routes are protected by `statement_upload`, investment fee endpoints by `investment_cost_analysis`, and consolidated/printable plan routes by `advisor_export` or `holistic_plan`. The pricing page must not land until these tests, the new history tests and the export test pass.

- [ ] **Step 9: Run browser acceptance and commit**

```bash
./vendor/bin/pest tests/Feature/History
./vendor/bin/pest tests/Feature/Plans/AdviserExportPackTest.php
npx playwright test tests/E2E/freemium/premium-history-export.spec.js --project=desktop-chromium --project=mobile-webkit
./vendor/bin/pint app/Models/InvestmentAccountValueSnapshot.php app/Models/LiabilityValueSnapshot.php app/Services/Stores/InvestmentAccountStore.php app/Services/Stores/LiabilityStore.php app/Services/History/BalanceHistoryService.php app/Http/Controllers/Api/BalanceHistoryController.php app/Services/Plans/AdviserExportPackService.php app/Http/Controllers/Api/AdviserExportPackController.php database/migrations/2026_07_15_000002_create_investment_and_liability_value_snapshots.php
git add app database routes/api.php resources/views/plans resources/js resources/mobile tests/Feature/History tests/Feature/Plans/AdviserExportPackTest.php tests/E2E/freemium/premium-history-export.spec.js
git commit -m "feat(premium): add balance history and adviser export pack"
```

---

### Task 4: Establish a Single Canonical Subscription and Entitlement Contract

**Files:**
- Create: `app/Services/Payment/SubscriptionStatusService.php`
- Modify: `app/Http/Controllers/Api/PaymentController.php:890-940`
- Modify: `routes/api.php:1123`
- Modify: `app/Agents/CoordinatingAgent.php:2381-2416`
- Modify: `app/Services/AI/AdvicePromptBuilder.php:309-327`
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Services/AI/XaiToolDefinitions.php`
- Modify: `resources/js/components/Payment/DataRetentionOverlay.vue`
- Modify: `resources/js/components/Settings/SettingsTabBar.vue`
- Modify: `resources/js/components/UserProfile/SubscriptionManagement.vue`
- Modify: `resources/js/constants/tierAccess.js`
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/mixins/tierLimitMixin.js`
- Modify: `resources/js/router/index.js`
- Modify: `resources/js/views/Settings.vue`
- Modify: `resources/js/views/Settings/FamilySettings.vue`
- Modify: `resources/js/views/UserProfile.vue`
- Test: `tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php`
- Test: `tests/Feature/Payment/SubscriptionStatusTest.php`
- Test: `tests/Feature/AI/BillingToolsTest.php`

**Interfaces:**
- Consumes: `TierResolver::resolve(User): string`, `TierConfigurationStore::forTier(string): TierConfiguration`, and the user's latest `Subscription`.
- Produces: `SubscriptionStatusService::forUser(User $user): array` and canonical `GET /api/payment/subscription-status`.

- [ ] **Step 1: Write the service contract tests**

Cover Free, pending purchase, active paid, cancelled-inside-period, grace and expired states. The Free assertion must be exact:

```php
$status = app(SubscriptionStatusService::class)->forUser($freeUser);

expect($status)
    ->toMatchArray([
        'has_subscription' => false,
        'tier' => 'free',
        'tier_display_name' => 'Free',
        'subscription_status' => null,
        'count_caps' => [
            'savings_account' => 2,
            'investment' => 2,
            'pension_account' => 2,
            'property' => 1,
            'mortgage' => 10,
            'goal' => 2,
            'life_event' => 1,
        ],
        'payment_enabled' => true,
    ])
    ->not->toHaveKeys(['trial_started_at', 'trial_ends_at', 'days_remaining']);
```

For every state assert `capability_matrix`, document allowances, Fyn budgets, currency mode, snapshot window and open API affordance come from the resolved `tier_configurations` row. The active paid case must resolve `tier='premium'`, `tier_display_name='Premium'` and never emit a retired tier key.

- [ ] **Step 2: Run the new unit test and confirm it fails**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php
```

Expected: fail because `SubscriptionStatusService` does not exist.

- [ ] **Step 3: Implement `SubscriptionStatusService`**

Return this stable top-level shape:

```php
$publicStatus = $subscription?->status === 'trialing'
    ? 'pending'
    : $subscription?->status;

[
    'has_subscription' => $subscription !== null,
    'tier' => $tier,
    'tier_display_name' => $tierConfig->display_name,
    'subscription_status' => $publicStatus,
    'status' => $publicStatus, // one-release client compatibility key; remove in Task 12
    'plan' => $subscription?->plan,
    'billing_cycle' => $subscription?->billing_cycle,
    'amount' => $subscription?->amount,
    'current_period_start' => $subscription?->current_period_start?->toISOString(),
    'current_period_end' => $subscription?->current_period_end?->toISOString(),
    'cancelled_at' => $subscription?->cancelled_at?->toISOString(),
    'data_retention_starts_at' => $paymentEnabled ? $subscription?->data_retention_starts_at?->toISOString() : null,
    'grace_period_ends_at' => $paymentEnabled ? $subscription?->gracePeriodEndsAt()?->toISOString() : null,
    'is_in_grace_period' => $paymentEnabled && ($subscription?->isInGracePeriod() ?? false),
    'is_terminal_paid' => $subscription !== null && ! $subscription->isActive() && ! in_array($subscription->status, ['pending', 'trialing'], true),
    'auto_renew' => $subscription?->auto_renew ?? false,
    'next_renewal_date' => $subscription?->status === 'active' && $subscription->auto_renew
        ? $subscription->current_period_end?->toISOString()
        : null,
    'count_caps' => $tierConfig->count_caps ?? [],
    'capability_matrix' => $tierConfig->capability_matrix ?? [],
    'document_upload_allowance' => $tierConfig->document_upload_allowance,
    'document_storage_gb' => $tierConfig->document_storage_gb,
    'fyn_weekly_token_budget' => $tierConfig->fyn_weekly_token_budget,
    'fyn_daily_hard_backstop' => $tierConfig->fyn_daily_hard_backstop,
    'currency_display_mode' => $tierConfig->currency_display_mode,
    'snapshot_surfacing_window_days' => $tierConfig->snapshot_surfacing_window_days,
    'open_api_affordance' => $tierConfig->open_api_affordance,
    'payment_enabled' => $paymentEnabled,
]
```

During the compatibility release, map an internal `trialing` value to public `pending`; never expose it as a product state. Do not expose trial date/countdown keys. Do not derive tier access from subscription status.

- [ ] **Step 4: Replace `PaymentController::trialStatus` with `subscriptionStatus`**

The controller delegates entirely to the service. Register both routes temporarily:

```php
Route::get('/subscription-status', [PaymentController::class, 'subscriptionStatus']);
Route::get('/trial-status', [PaymentController::class, 'subscriptionStatus']); // compatibility alias; remove in Task 12
```

Both endpoints must return byte-equivalent JSON in the feature test. Update every repository client listed in this task to call `/payment/subscription-status`; keep the `status` response key for one release so this PR remains independently deployable before the presentation refactor.

- [ ] **Step 5: Make Fyn consume the same service**

`get_subscription_status` must return Free as `status='free'`, use `tier_display_name`, omit all trial fields and retain the existing navigation action to `/settings/subscription`. Update billing guidance to mention only `free`, `pending`, `active`, `past due`, `cancelled` and `expired` states. Use the live tier display name rather than `SubscriptionPlan::findBySlug()`.

- [ ] **Step 6: Regenerate AI tool-schema fixtures through their capture tests**

Run:

```bash
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```

Then run both files again without the environment variable and expect all byte-identity assertions to pass.

- [ ] **Step 7: Run focused verification**

```bash
./vendor/bin/pest tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php
./vendor/bin/pest tests/Feature/Payment/SubscriptionStatusTest.php
./vendor/bin/pest tests/Feature/AI/BillingToolsTest.php
```

Expected: all pass serially.

- [ ] **Step 8: Commit the canonical contract**

```bash
git add app/Services/Payment/SubscriptionStatusService.php app/Http/Controllers/Api/PaymentController.php routes/api.php app/Agents/CoordinatingAgent.php app/Services/AI tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php tests/Feature/Payment/SubscriptionStatusTest.php tests/Feature/AI/BillingToolsTest.php tests/fixtures/ToolSchema tests/fixtures/XaiToolSchema
git commit -m "refactor(billing): establish canonical subscription status contract"
```

---

### Task 5: Replace Provisional `trialing` Rows with a Non-Entitling `pending` State

**Files:**
- Create: `database/migrations/2026_07_15_000003_add_pending_subscription_status.php`
- Create: `app/Console/Commands/AuditTrialSubscriptionRemnants.php`
- Modify: `app/Models/Subscription.php`
- Modify: `database/factories/SubscriptionFactory.php`
- Modify: `app/Http/Controllers/Api/PaymentController.php:235-270,381-440`
- Modify: `app/Http/Middleware/CheckSubscription.php:98-132`
- Modify: `app/Services/Payment/RevolutSubscriptionService.php`
- Test: `tests/Feature/Console/AuditTrialSubscriptionRemnantsTest.php`
- Test: `tests/Feature/Payment/TierKeyAcceptanceTest.php`
- Test: `tests/Feature/Middleware/CheckSubscriptionTest.php`

**Interfaces:**
- Produces: subscription status `pending` for abandoned/in-progress checkout; `pending` never grants a paid tier.
- Produces: `php artisan subscriptions:audit-trial-remnants` with `--json` and a non-zero exit when unsafe historical rows remain.

- [ ] **Step 1: Write failing pending-state tests**

Assert all of the following:

```php
expect($provisional->status)->toBe('pending');
expect($user->fresh()->tier)->toBe('free');

$write = $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', $validSavingsPayload);
expect($write->json('error'))->not->toBe('subscription_required');

$status = $this->actingAs($user, 'sanctum')->getJson('/api/payment/subscription-status');
$status->assertJsonPath('tier', 'free')
    ->assertJsonPath('subscription_status', 'pending');
```

Also assert only payment confirmation or a verified webhook can change `users.tier` to the purchased tier.

- [ ] **Step 2: Add `pending` without removing `trialing` yet**

The migration expands the MySQL enum to:

```sql
ENUM('pending','trialing','active','cancelled','expired','past_due') DEFAULT 'pending'
```

`trialing` remains temporarily so deployment cannot corrupt historical rows. In the same transaction, backfill only the unambiguous provisional shape (`status='trialing'`, both trial dates null, explicit Free user, no completed Payment) to `pending`. The down migration restores the old enum only after converting `pending` rows to `trialing`, making rollback explicit and data-safe.

- [ ] **Step 3: Update both order-creation paths**

Change the legacy and tier order helpers in `PaymentController` to create `status='pending'`, null paid period dates and no trial dates. An abandoned checkout leaves the user on Free. Payment confirmation updates the same row to `active` inside the existing locked transaction.

- [ ] **Step 4: Update middleware semantics**

Allow writes when the subscription is null, pending, or active:

```php
if ($subscription === null
    || in_array($subscription->status, ['pending', 'trialing'], true) // compatibility removed in Task 12
    || $subscription->isActive()) {
    return $next($request);
}
```

This does not grant paid access because `TierResolver` still resolves `users.tier='free'` and `DbTierGate` still applies Free limits.

- [ ] **Step 5: Remove Revolut trial configuration**

`RevolutSubscriptionService::upsertTierPlan` must omit `trial_duration` entirely. Add or update `RevolutTierVariationSyncTest` to assert the request contains no `trial_duration` key.

- [ ] **Step 6: Implement the read-only audit command**

Report these groups separately:

```text
provisional_shape: status=trialing, both trial dates null, user tier=free, no completed payment
historical_trial_shape: status=trialing or trial dates present
paid_shape: at least one completed payment
```

The command changes no data. It exits `1` when `historical_trial_shape > 0`, and `0` otherwise. `--json` prints stable machine-readable counts for deployment evidence.

- [ ] **Step 7: Run focused verification and format**

```bash
./vendor/bin/pest tests/Feature/Console/AuditTrialSubscriptionRemnantsTest.php
./vendor/bin/pest tests/Feature/Payment/TierKeyAcceptanceTest.php
./vendor/bin/pest tests/Feature/Middleware/CheckSubscriptionTest.php
./vendor/bin/pest tests/Feature/Tiers/RevolutTierVariationSyncTest.php
./vendor/bin/pint app/Models/Subscription.php app/Console/Commands/AuditTrialSubscriptionRemnants.php app/Http/Controllers/Api/PaymentController.php app/Http/Middleware/CheckSubscription.php app/Services/Payment/RevolutSubscriptionService.php database/migrations/2026_07_15_000003_add_pending_subscription_status.php
```

- [ ] **Step 8: Commit the payment-state correction**

```bash
git add app/Console/Commands/AuditTrialSubscriptionRemnants.php app/Models/Subscription.php app/Http/Controllers/Api/PaymentController.php app/Http/Middleware/CheckSubscription.php app/Services/Payment/RevolutSubscriptionService.php database/factories/SubscriptionFactory.php database/migrations/2026_07_15_000003_add_pending_subscription_status.php tests/Feature
git commit -m "fix(billing): replace provisional trial status with pending"
```

---

### Task 6: Render the Approved Free + Premium Contract on the Public Pricing Page

**Files:**
- Modify: `public/pages/pricing.php`
- Modify: `public/pages/js/pricing.js`
- Modify: `public/pages/css/pricing.css`
- Test: `tests/Feature/Public/PricingContractTest.php`
- Test: `tests/E2E/public/pricing-contract.spec.js`

**Interfaces:**
- Consumes: public `GET /api/pricing-config`.
- Produces: one Free card, one Premium card and an accessible Free-versus-Premium comparison sourced from the two canonical configuration rows.
- Produces: Premium CTA `/register?plan=premium&billing=<monthly|yearly>`.

- [ ] **Step 1: Add a server-rendered pricing contract test**

The test requests `/pricing` and asserts:

```php
$response->assertOk()
    ->assertSee('Free')
    ->assertSee('Premium')
    ->assertDontSee('plan-tier1')
    ->assertDontSee('plan-tier2')
    ->assertDontSee('plan-tier3')
    ->assertDontSee('Payments are processed securely through Stripe')
    ->assertDontSee('downgrade at any time')
    ->assertDontSee('free trial', false);
```

Add structural assertions for card IDs `plan-free` and `plan-premium`, plus `href="/register?plan=premium&amp;billing=yearly"` on the default Premium CTA. Assert the page contains the approved Free caps, Premium 1 GB storage, 100,000/500,000 weekly Fyn limits and no mock-up footer or placeholder `href="#"` CTA.

- [ ] **Step 2: Rebuild the production page from the approved content, not the mock-up implementation**

Keep the production navigation, footer, metadata, Fynla palette and design-system components in `pricing.php`/`pricing.css`. Use `Fynla_Pricing_Page.html` for information hierarchy, price copy and the comparison rows only. Do not copy its hardcoded colours, decorative check glyphs, placeholder links, inline CSS or “For demonstration purposes only” footer. Expand user-facing acronyms: use “Inheritance Tax”, “artificial intelligence” or plain “Fyn”, and generic “advanced investments” rather than unexplained product acronyms.

- [ ] **Step 3: Replace union logic with direct Premium rendering**

`pricing.js` must consume only the canonical rows:

```javascript
renderFeatures('free', byKey.free, null, null);
renderFeatures('premium', byKey.premium, 'Free', byKey.free);
TIERS.premium = {
  monthly: byKey.premium.price_monthly_pence,
  yearly: byKey.premium.price_annual_pence,
};
```

Premium feature and quota text must be generated only from the live Premium row: capability matrix, count caps, document allowance/storage, Fyn budget and snapshot window. Do not merge rows or append capabilities absent from the API. The CTA always uses `plan=premium` and the selected billing cycle. Build dynamic labels with DOM nodes and `textContent`; do not inject admin-controlled strings through `innerHTML`.

- [ ] **Step 4: Implement the complete comparison contract**

Render the approved rows for cash accounts, investments, property, pensions, personal valuables, net worth, combined household view, projections, Save Tax, Inheritance Tax planning, risk, Goals, Life Events, Fyn, balance history, basic/detailed expenditure, document storage, Letter to Spouse, retirement planning, What If, plan export, statement upload and investment fee analysis. Every quantity comes from the API row. “Unlimited history” is rendered as “Full available history” so the page does not contradict the platform retention policy.

Rows are keyboard-readable without requiring an icon. If detail disclosure is retained, use native `<details><summary>` with a visible text label and no new decorative glyph.

- [ ] **Step 5: Correct commercial copy**

Use these statements:

```text
There is no time-limited trial. The Free tier remains available for as long as you want.
Upgrade whenever you need more capacity or planning features.
Cancel a paid subscription at any time; paid access continues until the end of the current billing period.
Payments are processed securely through Revolut.
```

Do not claim that paid users can downgrade until a downgrade lifecycle exists.

- [ ] **Step 6: Test dynamic rendering and accessibility**

The Playwright test intercepts `/api/pricing-config` with deliberately changed Premium prices and limits, loads `/pricing`, toggles monthly/yearly and proves every visible value and `plan=premium` CTA follows the response. It asserts the default approved values render as £6.99 monthly and £59.99 yearly, the annual saving is calculated rather than hardcoded, tab/keyboard operation works, no horizontal overflow occurs at iPhone width, and the page has no serious accessibility violations.

- [ ] **Step 7: Run, visually compare and commit**

```bash
./vendor/bin/pest tests/Feature/Public/PricingContractTest.php
npx playwright test tests/E2E/public/pricing-contract.spec.js --project=desktop-chromium
git add public/pages/pricing.php public/pages/js/pricing.js public/pages/css/pricing.css tests/Feature/Public/PricingContractTest.php tests/E2E/public/pricing-contract.spec.js
git commit -m "fix(pricing): publish canonical Free and Premium contract"
```

---

### CHECKPOINT 1: Contract and Public Pricing

Before Task 7:

1. Run Tasks 1-6 focused suites serially.
2. Start the app with `./dev.sh`.
3. Verify `/pricing` at desktop and narrow mobile widths.
4. Compare each card against a fresh `/api/pricing-config` response.
5. Verify Free has no payment requirement and Premium uses the canonical Premium price, capabilities, quotas and registration target.
6. Verify no trial, Stripe or downgrade claim appears.
7. Compare the information hierarchy against `Fynla_Pricing_Page.html` while confirming the production page follows the Fynla design system and icon rules.
8. Record screenshots and network evidence.

If any assertion fails, use `systematic-debugging`, correct the root cause and repeat the whole checkpoint.

---

### Task 7: Preserve Premium Checkout Intent Through Registration

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php:100-130,620-700`
- Modify: `app/Models/PendingRegistration.php`
- Modify: `resources/js/views/Register.vue:285-305,480-510`
- Modify: `resources/js/services/authService.js`
- Test: `tests/Feature/Auth/RegistrationTest.php`
- Test: `tests/E2E/auth/registration.spec.js`

**Interfaces:**
- Produces in the verified-registration response:

```json
"checkout_intent": {
  "tier": "premium",
  "billing_cycle": "yearly"
}
```

or `"checkout_intent": null` for Free/campaign registrations.

- [ ] **Step 1: Add validation and persistence tests**

Accept only `premium` and `monthly`, `yearly`. Reject `tier1`, `tier2`, `tier3`, legacy slugs, `free` as a paid checkout intent, unknown tiers and mismatched cycles with `422`. Continue asserting the verified user is created as Free with no Subscription row.

- [ ] **Step 2: Add the response contract**

Build `checkout_intent` from the verified `PendingRegistration`, not untrusted current query parameters. Return it with the access token. Clear or consume the pending registration as the existing verification flow requires.

- [ ] **Step 3: Route after verification**

In `Register.vue`, apply precedence:

```javascript
if (data.checkout_intent) {
  router.push(`/checkout?plan=${data.checkout_intent.tier}&cycle=${data.checkout_intent.billing_cycle}`);
} else if (fromParam) {
  // existing campaign dashboard route
} else if (stageParam) {
  // existing staged onboarding route
} else {
  // existing onboarding route
}
```

Do not create a subscription during registration. Checkout remains optional and can be abandoned without losing Free access.

- [ ] **Step 4: Add end-to-end cases**

Cover:

```text
/register -> verify -> onboarding as Free
/register?plan=premium&billing=monthly -> verify -> /checkout?plan=premium&cycle=monthly
/register?plan=premium&billing=yearly -> verify -> /checkout?plan=premium&cycle=yearly
campaign handoff without a paid intent -> existing campaign destination
```

- [ ] **Step 5: Run and commit**

```bash
./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php
npx playwright test tests/E2E/auth/registration.spec.js --project=desktop-chromium
./vendor/bin/pint app/Http/Controllers/Api/AuthController.php app/Models/PendingRegistration.php
git add app/Http/Controllers/Api/AuthController.php app/Models/PendingRegistration.php resources/js/views/Register.vue resources/js/services/authService.js tests/Feature/Auth/RegistrationTest.php tests/E2E/auth/registration.spec.js
git commit -m "fix(registration): continue Premium intent to checkout"
```

---

### Task 8: Repair Upgrade Entry Points and Count-Cap Guidance on Web and `/m`

**Files:**
- Create: `resources/js/utils/subscriptionNavigation.js`
- Modify: `resources/js/components/AppNavbar.vue:359-375`
- Modify: `resources/js/components/Payment/PlanSelectionModal.vue:177-285`
- Modify: `resources/js/components/Shared/LimitReachedModal.vue`
- Modify: `resources/js/views/Settings.vue`
- Modify: `resources/js/views/NetWorth/CashOverview.vue`
- Modify: `resources/js/components/NetWorth/InvestmentList.vue`
- Modify: `resources/js/components/NetWorth/PropertyList.vue`
- Modify: `resources/js/components/NetWorth/PensionList.vue`
- Modify: `resources/js/views/Goals/GoalsDashboard.vue`
- Modify: `resources/js/components/Goals/EventsTab.vue`
- Modify: `resources/mobile/mixins/upgrade.js`
- Modify: `resources/mobile/views/modules/Estate.vue`
- Modify: `resources/mobile/views/HolisticPlan.vue`
- Modify: `app/Agents/CoordinatingAgent.php`
- Modify: `app/Traits/HasAiChat.php`
- Modify: `resources/js/components/Shared/AiChatPanel.vue`
- Modify: `resources/js/components/Shared/AiMessageContent.vue`
- Modify: `resources/mobile/mixins/onboardingChat.js`
- Test: `tests/frontend/utils/subscriptionNavigation.test.js`
- Test: `tests/Feature/Mobile/FreemiumCapsTest.php`
- Test: `tests/Feature/Fyn/FynTierLimitActionTest.php`
- Test: `tests/E2E/freemium/upgrade-entry-points.spec.js`

**Interfaces:**
- Produces: `subscriptionOptionsLocation(): { path: '/settings/subscription', query: { openPricing: '1' } }`.
- Produces: Fyn action `{ action: 'subscription_options', reason: 'tier_limit_reached', entity_key, current_count, limit, tier }`.

- [ ] **Step 1: Write navigation and visibility tests**

Assert:

```text
Free: Premium upgrade entry is visible
Premium: upgrade entry is absent and no empty modal can open
Preview: no upgrade entry
Payment disabled: no paid CTA
LimitReachedModal: opens canonical subscription options
/m breakout: /settings/subscription?openPricing=1 with auth token bridged
```

- [ ] **Step 2: Centralise desktop navigation**

Use a route object, never string variants such as `/settings?tab=subscription` or `/payment/checkout`. `Settings.vue` uses `/checkout?plan=<tier>&cycle=<cycle>` when a plan is selected.

- [ ] **Step 3: Use the canonical two-tier order**

In the navbar and modal, rank only `free`, `premium`. Remove trial-status, Student-email, legacy Pro and Tier 1/2/3 assumptions from active flows. For Free, `PlanSelectionModal` shows one Premium option. It must render a defensive “You are on Premium” text state if opened for Premium, with no selectable empty grid.

- [ ] **Step 4: Add all missing proactive count-cap guards**

Before opening a Defined Contribution or Defined Benefit pension create form, compare the combined pension count with the backend-provided `pension_account` cap. State Pension does not count. Apply the same shared check before opening Goal and Life Event create forms using `goal` and `life_event`. At cap, open `LimitReachedModal`; editing existing records remains allowed. The backend stores from Task 2 remain authoritative if the UI is bypassed.

- [ ] **Step 5: Repair `/m` actions**

Change the parent-frame destination to the canonical subscription path. Add a plain-text “Compare plans” action to the existing Estate teaser. Holistic Plan, Savings, Investment and Retirement reuse the same mixin. Do not add icons.

- [ ] **Step 6: Add structured Fyn at-cap behaviour**

When a create tool catches `TierLimitExceededException`, retain the accurate plain-language reply and include the structured `subscription_options` action. Desktop opens `PlanSelectionModal`; `/m` performs the parent-frame breakout. The action is presentation-neutral so Swift can map it to a native paywall later.

- [ ] **Step 7: Run focused suites and browser tests**

```bash
./vendor/bin/pest tests/Feature/Mobile/FreemiumCapsTest.php
./vendor/bin/pest tests/Feature/Fyn/FynTierLimitActionTest.php
npx vitest run tests/frontend/utils/subscriptionNavigation.test.js
npx playwright test tests/E2E/freemium/upgrade-entry-points.spec.js --project=desktop-chromium --project=mobile-webkit
```

- [ ] **Step 8: Commit cross-surface upgrade repairs**

```bash
git add resources/js/utils/subscriptionNavigation.js resources/js/components/AppNavbar.vue resources/js/components/Payment/PlanSelectionModal.vue resources/js/components/Shared/LimitReachedModal.vue resources/js/views/Settings.vue resources/js/views/NetWorth/CashOverview.vue resources/js/components/NetWorth resources/mobile/mixins/upgrade.js resources/mobile/views app/Agents/CoordinatingAgent.php tests
git commit -m "fix(freemium): repair upgrade and limit journeys on web and mobile"
```

---

### CHECKPOINT 2: Registration, Upgrade and `/m` Parity

Before Task 9:

1. Register a Free user and verify onboarding begins without checkout.
2. Register from both Premium billing-cycle CTAs and verify `premium` plus the correct cycle reach checkout after account verification.
3. As Free, hit Savings, Investment, Pension, Property, Goal and Life Event caps; verify no record is deleted and each upgrade action opens Premium.
4. Verify Premium shows no upgrade action and cannot open an empty plan modal.
5. Verify `/m` Savings, Investment, Retirement, Estate and Holistic upgrade actions reach the authenticated desktop subscription options.
6. Trigger a Fyn at-cap create on desktop and `/m`; verify the same structured action is rendered correctly.
7. Run the two reliable `verify-m` authentication paths; do not cold-navigate and mistake a missing token bridge for an application failure.

Record browser screenshots, network responses and the unchanged database counts. Any failure restarts the checkpoint.

---

### Task 9: Replace Trial-Oriented Authenticated Subscription UI

**Files:**
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/components/Payment/PlanSelectionModal.vue`
- Modify: `resources/js/views/Settings.vue`
- Modify: `resources/js/views/Settings/SubscriptionSettings.vue`
- Modify: `resources/js/components/UserProfile/SubscriptionManagement.vue`
- Modify: `resources/js/components/Settings/SettingsTabBar.vue`
- Modify: `resources/js/views/UserProfile.vue`
- Modify: `resources/js/views/Settings/FamilySettings.vue`
- Test: `tests/frontend/utils/subscriptionPresentation.test.js`
- Test: `tests/E2E/freemium/subscription-states.spec.js`

- [ ] **Step 1: Define a presentation-state mapper**

The mapper consumes the canonical status response and produces:

```javascript
free      -> { label: 'Free', canWrite: true, showUpgrade: true }
pending   -> { label: 'Free — payment pending', canWrite: true, showUpgrade: true }
active    -> { label: tier_display_name, canWrite: true, showUpgrade: false }
cancelled inside period -> { label: `${tier_display_name} — ends <date>`, canWrite: true }
past_due  -> { label: `${tier_display_name} — payment issue`, canWrite: existing middleware result }
grace     -> { label: 'Subscription ended — read-only access', canWrite: false }
expired   -> { label: 'Subscription ended', canWrite: false }
```

No mapping returns “Free Trial” or a countdown.

- [ ] **Step 2: Rename trial-oriented component state**

Rename `checkTrialStatus`, `showTrialExpiredModal`, `handleTrialModalClose` and related variables to subscription-neutral names. The forced modal heading becomes “Your subscription has ended”. Free and pending users never trigger it.

- [ ] **Step 3: Remove countdown UI**

Delete the trial panel, countdown calculations and `trial_ends_at` watchers from `SubscriptionManagement.vue`. Preserve active billing, cancellation, invoices, grace retention and deletion controls.

- [ ] **Step 4: Consume `tier` and `tier_display_name` everywhere**

Remove legacy `status === 'trialing' ? 'pro'` fallbacks from Settings tabs, User Profile and Family Settings. Use the status service response; unknown states fail closed to Free presentation without granting entitlements.

- [ ] **Step 5: Exercise every state in Playwright**

The test seeds or stubs Free, pending Premium checkout, active Premium, cancelled-inside-period, grace and expired responses. It asserts headings, available actions, `plan=premium` checkout routes, the absence of upgrade controls for Premium and that no trial or retired-tier text appears.

- [ ] **Step 6: Run and commit**

```bash
npx vitest run tests/frontend/utils/subscriptionPresentation.test.js
npx playwright test tests/E2E/freemium/subscription-states.spec.js --project=desktop-chromium
git add resources/js/layouts/AppLayout.vue resources/js/components/Payment/PlanSelectionModal.vue resources/js/views/Settings.vue resources/js/views/Settings/SubscriptionSettings.vue resources/js/components/UserProfile/SubscriptionManagement.vue resources/js/components/Settings/SettingsTabBar.vue resources/js/views/UserProfile.vue resources/js/views/Settings/FamilySettings.vue tests
git commit -m "fix(subscription): replace trial UI with freemium states"
```

---

### Task 10: Correct Lifecycle, Admin, Discount and Retention Semantics

**Files:**
- Create: `app/Services/Payment/SubscriptionExpiryService.php`
- Create: `app/Console/Commands/ExpireSubscriptions.php`
- Create: `database/migrations/2026_07_15_000004_deactivate_trial_extension_discount_codes.php`
- Modify: `app/Console/Kernel.php`
- Modify for one-release compatibility: `app/Services/Payment/TrialService.php`
- Modify for one-release compatibility: `app/Console/Commands/ExpireTrials.php`
- Modify: `app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php`
- Modify: `app/Services/Lifecycle/LifecycleEngine.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/lifecycle.php`
- Modify: `app/Console/Commands/ExecuteGraceDeletions.php`
- Modify: `app/Http/Controllers/Api/AdminController.php`
- Modify: `app/Models/DiscountCode.php`
- Modify: `app/Services/Payment/DiscountCodeService.php`
- Modify: `app/Services/Payment/InvoiceService.php`
- Modify: `resources/js/components/UserProfile/NotificationPreferences.vue`
- Modify: `resources/js/components/Admin/DiscountCodeModal.vue`
- Modify: `resources/js/components/Admin/DiscountCodes.vue`
- Modify: `resources/js/components/Admin/metrics/UserMetrics.vue`
- Modify: `database/seeders/DiscountCodeSeeder.php`
- Modify: `database/factories/DiscountCodeFactory.php`
- Test: `tests/Feature/Lifecycle/Campaigns/ChurnedSubscriberCampaignTest.php`
- Test: `tests/Feature/Console/ExpireSubscriptionsTest.php`
- Test: `tests/Unit/Services/Payment/DiscountCodeServiceTest.php`

- [ ] **Step 1: Reclassify churn using payment evidence**

`ChurnedSubscriberCampaign` selects cancelled subscriptions with at least one completed `Payment`; it must not compare `cancelled_at` to `trial_ends_at`. Add a negative case for a cancelled row with no completed payment and a positive case for a completed payer.

- [ ] **Step 2: Rename the paid-expiry service and command**

Move `expireCancelledSubscriptions()` into `SubscriptionExpiryService`. Schedule `subscriptions:expire` at `00:05`. Keep `TrialService` and `trials:expire` as deprecated delegates for one release; each points to the replacement service/command and contains no trial behaviour. Remove both only in Task 12 after the deployment audit.

- [ ] **Step 3: Remove orphan lifecycle branches**

Delete `trialAfterEndCandidates`, trialer preference keys and trialer lifecycle template registrations. Keep `ChurnedSubscriberCampaign` and `LapsedSubscriberCampaign`.

- [ ] **Step 4: Remove trial-extension products**

Admin validation accepts only `percentage` and `fixed_amount`. The data migration sets `is_active=false` for every existing `trial_extension` row; it does not reinterpret or delete historical codes. Remove trial-extension calculation and invoice labels. Historical invoice JSON remains readable.

- [ ] **Step 5: Correct retention reason semantics**

`ExecuteGraceDeletions` records `subscription_cancelled_grace_ended` for paid churn. It must not infer `trial_expired` from historical timestamp presence.

- [ ] **Step 6: Remove trial notification and admin copy**

Delete trial lifecycle preferences from notification settings, “Trial Extension” from discount forms and trial language from metrics headings. Do not change unrelated metrics.

- [ ] **Step 7: Run and commit**

```bash
./vendor/bin/pest tests/Feature/Lifecycle/Campaigns/ChurnedSubscriberCampaignTest.php
./vendor/bin/pest tests/Feature/Console/ExpireSubscriptionsTest.php
./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php
./vendor/bin/pint app/Services/Payment app/Console/Commands app/Services/Lifecycle app/Http/Controllers/Api/AdminController.php app/Models/DiscountCode.php
git add app config resources/js/components database/seeders database/factories tests
git commit -m "refactor(subscription): align lifecycle and admin with pure freemium"
```

---

### Task 11: Remove Reachable Trial Copy from Public and Dormant Surfaces

**Files:**
- Modify: `public/pages/about.php`
- Modify: `public/pages/calculators.php`
- Modify: `public/pages/faq.php`
- Modify: `public/pages/features.php`
- Modify: `public/pages/help.php`
- Modify: `public/pages/how-it-works.php`
- Modify: `public/pages/features/ice-letters.php`
- Modify: `public/pages/features/iht-planning.php`
- Modify: `public/pages/features/monte-carlo.php`
- Modify: `public/pages/features/net-worth-dashboard.php`
- Modify: `public/pages/features/pension-tracker.php`
- Modify: `public/pages/features/protection-gap.php`
- Modify: `public/pages/features/when-can-i-retire.php`
- Modify: `public/pages/why-fynla/alternatives.php`
- Modify: `public/pages/why-fynla/independent.php`
- Modify: `public/pages/why-fynla/one-platform.php`
- Modify: `public/pages/why-fynla/our-approach.php`
- Modify: `public/pages/partials/modules/cta-band.php`
- Modify: `public/pages/css/calculators.css`
- Modify: `resources/js/views/Public/QuickStartPage.vue`
- Modify: `resources/js/views/Public/CampaignPage.vue`
- Modify: `resources/js/views/Public/TermsOfServicePage.vue`
- Modify: `resources/js/views/Public/NewsArticlePage.vue`
- Modify: `resources/js/views/Public/insights/HowMuchToRetireUkPage.vue`
- Modify: `resources/js/views/Public/insights/IsaGuideUkPage.vue`
- Modify: `resources/js/views/Public/insights/RetirementPlanningUkPage.vue`
- Modify: `resources/js/views/Public/insights/StocksSharesIsaUkPage.vue`
- Modify: `resources/js/views/Public/AboutPage.vue`
- Modify: `resources/js/views/Public/CalculatorsPage.vue`
- Modify: `resources/js/views/Public/FeaturesPage.vue`
- Modify: `resources/js/views/Public/HowItWorksPage.vue`
- Modify: `resources/js/constants/faqData.js`
- Modify: `resources/js/components/Public/CalculatorCard.vue`
- Modify: `resources/js/components/Public/FeaturePageLayout.vue`
- Delete after the Step 4 reference proof: `resources/views/emails/lifecycle/countdown.blade.php`
- Delete after the Step 4 reference proof: `resources/views/emails/lifecycle/subscribe-in-progress.blade.php`
- Delete after the Step 4 reference proof: `resources/views/emails/lifecycle/subscribe-max-discount.blade.php`
- Modify: `resources/views/emails/modules/counter.blade.php`
- Modify: `resources/views/emails/modules/notice.blade.php`
- Test: `tests/Feature/Public/FreemiumCopyContractTest.php`

- [ ] **Step 1: Capture an exact active-surface inventory**

Run:

```bash
rg -n -i '\bfree trial\b|7-day trial|trial has ended|trial ends|trial expires|status === .trialing.' public/pages resources/js resources/mobile resources/views/emails fynlaDesignGuide.md --glob '!*.bak'
```

Copy the result into the test dataset so every active file is deliberately classified as changed, deleted or historical-only.

- [ ] **Step 2: Replace public registration language**

Use “Create your free account”, “Get started free”, or “Continue with Free” according to context. Calculator gates must describe the real required tier/capability rather than “requires free trial”. Terms must describe permanent Free, paid renewal, cancellation, read-only grace and retention accurately.

- [ ] **Step 3: Clean dormant duplicates and examples**

Although server-rendered pages are active, correct the duplicate Vue public pages so they cannot reintroduce trial copy if routing changes. Replace trial examples in reusable email-module comments with subscription-neutral examples.

- [ ] **Step 4: Delete orphaned trial email templates only after reference proof**

Before deletion:

```bash
rg -n "lifecycle\.(countdown|subscribe-in-progress|subscribe-max-discount)|emails\.lifecycle\.(countdown|subscribe-in-progress|subscribe-max-discount)" app config routes tests
```

Expected: no runtime references. This expectation is the deletion gate; a non-empty result is a failure to diagnose before continuing.

- [ ] **Step 5: Add a copy contract test**

Scan active PHP, Vue, JavaScript and Blade files and fail on the banned phrases. Allow only named historical migrations, the compatibility alias comment and the data-audit command until Task 12 removes them.

- [ ] **Step 6: Run and commit**

```bash
./vendor/bin/pest tests/Feature/Public/FreemiumCopyContractTest.php
npx playwright test tests/E2E/public --project=desktop-chromium
git add public/pages resources/js/views/Public resources/js/constants/faqData.js resources/js/components/Public resources/views/emails tests/Feature/Public/FreemiumCopyContractTest.php
git commit -m "fix(copy): remove obsolete trial claims across Fynla"
```

---

### CHECKPOINT 3: Authenticated States and Copy Sweep

Before Task 12:

1. Verify Free, pending, active, cancelled, grace and expired subscription screens.
2. Ask Fyn “Where is my invoice?” for Free and active users; verify correct tier/status language and navigation.
3. Browse every server-rendered public route listed in Task 11.
4. Search rendered text and source responses for trial claims.
5. Verify notification preferences, discount administration and metrics contain no trial controls.
6. Verify the paid-churn grace and deletion path still works and Free users never enter it.

Any failure restarts the checkpoint after a root-cause fix.

---

### Task 12: Data-Gated Removal of Trial Schema and Compatibility Code

**Precondition:** Do not begin schema removal until `subscriptions:audit-trial-remnants --json` returns exit `0` on local, csjones and production, and the evidence is saved. Production execution is read-only but still requires the normal production-access approval.

**Files:**
- Create: `database/migrations/2026_07_15_000005_remove_trial_subscription_schema.php`
- Modify: `database/seeders/SubscriptionPlanSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `database/seeders/TestUsersSeeder.php`
- Modify: `database/seeders/LifecycleTestSeeder.php`
- Modify: `database/seeders/ChrisUserSeeder.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Subscription.php`
- Modify: `database/factories/SubscriptionFactory.php`
- Modify: `app/Services/Account/AccountDeletionService.php`
- Modify: `app/Services/Account/RetentionPurgeService.php`
- Delete: `app/Console/Commands/ConvertTrialUsersToFree.php`
- Delete: `tests/Feature/Console/ConvertTrialUsersToFreeTest.php`
- Delete: `app/Services/Payment/TrialService.php`
- Delete: `app/Console/Commands/ExpireTrials.php`
- Update: all remaining trial-specific tests, browser scenario comments and Version page copy
- Test: `tests/Feature/Database/TrialSchemaRemovalTest.php`

- [ ] **Step 1: Save pre-migration audit evidence**

```bash
php artisan subscriptions:audit-trial-remnants --json
```

Expected in each environment:

```json
{"provisional_shape":0,"historical_trial_shape":0,"paid_shape":0,"safe_to_remove":true}
```

`paid_shape` here counts paid subscriptions still using trial-only columns/status, not all paid subscriptions.

- [ ] **Step 2: Write the schema-removal test**

After migration, assert:

```php
expect(Schema::hasColumn('subscriptions', 'trial_started_at'))->toBeFalse()
    ->and(Schema::hasColumn('subscriptions', 'trial_ends_at'))->toBeFalse()
    ->and(Schema::hasColumn('users', 'trial_ends_at'))->toBeFalse()
    ->and(Schema::hasColumn('subscription_plans', 'trial_days'))->toBeFalse()
    ->and(Schema::hasTable('trial_reminder_log'))->toBeFalse();
```

Also query `information_schema.COLUMNS` and assert the subscription status enum is exactly `pending,active,cancelled,expired,past_due`.

- [ ] **Step 3: Implement the safe migration**

The migration aborts with a clear exception if any `subscriptions.status='trialing'` row exists. It then removes trial columns/table, removes `trialing` from the enum and keeps `pending` as default. The down migration recreates nullable compatibility columns/table and expands the enum; it does not fabricate trial data.

- [ ] **Step 4: Remove trial-only runtime and fixture code**

Seed users as Free, pending, active, cancelled or expired according to the scenario. Delete the trial factory state, conversion command, stale browser comments and trial-only model casts/fillables. Update the Version page to describe permanent Free and tier enforcement.

- [ ] **Step 5: Remove the old status route alias**

After all repository consumers use `/payment/subscription-status` and checkpoint evidence shows no calls to `/payment/trial-status`, delete the route alias and the one-release `status` response key, leaving `subscription_status` as the only status field. Add a route test asserting the old endpoint returns `404`.

- [ ] **Step 6: Run migration verification and reseed**

Only in an approved environment:

```bash
php artisan migrate
php artisan db:seed
./vendor/bin/pest tests/Feature/Database/TrialSchemaRemovalTest.php
./vendor/bin/pest tests/Feature/Payment tests/Feature/Tiers tests/Feature/Middleware tests/Feature/Lifecycle
```

- [ ] **Step 7: Commit schema cleanup**

```bash
git add database app tests resources/js/views/Version.vue routes/api.php
git commit -m "refactor(subscription): remove retired trial schema and compatibility"
```

---

### Task 13: Replace Stale Documentation with the Canonical Economic Contract

**Files:**
- Create: `codex/plans/canonical/01-freemium-economic-contract.md`
- Modify: `docs/superpowers/specs/2026-05-29-pure-freemium-signup-design.md`
- Modify: `fynlaDesignGuide.md`
- Modify: `AGENTS.md` only if a clarification is required; do not weaken existing rules
- Update through `vault-sync`: `/Users/CSJ/Desktop/fynlaBrain/Current State/PaymentSubscription.md`
- Update through `vault-sync`: relevant payment section of `/Users/CSJ/Desktop/fynlaBrain/Architecture/v083/10-NEW-SYSTEMS.md`
- Update through `vault-sync`: current Auth and mobile state notes that describe registration/upgrade

- [ ] **Step 1: Write the concise canonical document**

It must state:

```text
Registration -> Free, no Subscription
Free -> writable, Store-boundary caps
Checkout pending -> still Free
Verified payment -> active paid tier
Cancellation -> access to period end
Terminal paid -> read-only/grace/retention
Canonical tiers -> free/premium
Canonical API -> /api/payment/subscription-status
Premium web/Revolut price -> 699 monthly / 5999 annual pence from the live tier store
Future StoreKit products -> map to the same premium entitlement; localized App Store price remains StoreKit-authoritative
Free caps -> savings 2, investments 2, pensions 2, properties 1, Goals 2, Life Events 1, mortgages 10
Premium quotas -> unlimited counts, unlimited document count within 1 GB, 500000 weekly Fyn tokens, full retained history
```

- [ ] **Step 2: Remove the design-system trial component**

Delete the “Trial Countdown Banner” section and table-of-contents entry. Add subscription-state presentation rules for Free, active paid, cancellation and grace using existing palette/component patterns. Do not add icons.

- [ ] **Step 3: Update the vault from verified code**

Use `vault-sync` only after Tasks 1-12 pass. The rewritten payment map must explicitly mark Student/Standard/Family/Pro, Tier 1/2/3 and the seven-day-trial narrative as historical, not current.

- [ ] **Step 4: Run documentation consistency scans**

```bash
rg -n -i '7-day free trial|start your free trial|trial countdown|Student, Standard, Pro|free/tier1/tier2/tier3|Tier 1|Tier 2|Tier 3' AGENTS.md fynlaDesignGuide.md docs/superpowers/specs codex/plans/canonical '/Users/CSJ/Desktop/fynlaBrain/Current State/PaymentSubscription.md' '/Users/CSJ/Desktop/fynlaBrain/Architecture/v083/10-NEW-SYSTEMS.md'
```

Expected: no current-contract matches. Historical migration notes must be explicitly labelled historical.

- [ ] **Step 5: Commit repository documentation**

```bash
git add codex/plans/canonical/01-freemium-economic-contract.md docs/superpowers/specs/2026-05-29-pure-freemium-signup-design.md fynlaDesignGuide.md AGENTS.md
git commit -m "docs(freemium): publish the canonical economic contract"
```

---

### Task 14: Full Regression, Browser Acceptance and Swift Readiness Gate

**Files:**
- Create: `codex/evidence/freemium-remediation-acceptance.md`
- Modify: this plan only to check completed steps and record commit identifiers

- [ ] **Step 1: Run static sweeps**

```bash
rg -n -i '\bfree trial\b|7-day trial|trial has ended|trial ends|trial expires|trialing|trial_started_at|trial_ends_at|trial_extension|/payment/trial-status|settings\?tab=subscription|/payment/checkout|processed securely through Stripe|downgrade at any time|tier1|tier2|tier3|Tier 1|Tier 2|Tier 3' app config database routes resources public/pages tests fynlaDesignGuide.md --glob '!*.bak'
```

Expected after Task 12: no active runtime matches. Applied historical migrations and deliberate migration/audit tests may retain old keys; each retained match must be listed with its reason in the acceptance evidence.

- [ ] **Step 2: Run focused suites serially**

```bash
./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php
./vendor/bin/pest tests/Feature/Payment
./vendor/bin/pest tests/Feature/Tiers
./vendor/bin/pest tests/Feature/History
./vendor/bin/pest tests/Feature/Plans/AdviserExportPackTest.php
./vendor/bin/pest tests/Feature/Middleware/CheckSubscriptionTest.php
./vendor/bin/pest tests/Feature/Mobile/FreemiumCapsTest.php
./vendor/bin/pest tests/Feature/Fyn/FynTierLimitActionTest.php
./vendor/bin/pest tests/Feature/AI/BillingToolsTest.php
./vendor/bin/pest tests/Feature/Lifecycle
./vendor/bin/pest tests/Feature/Public
```

Expected: all pass; do not parallelise database-refreshing files.

- [ ] **Step 3: Run frontend tests and production builds**

```bash
npm run test:frontend
npm run build
```

Use the environment-specific dev build procedure before upload to csjones. Do not run the Capacitor iOS build because this remediation targets web and `/m`, not the native package.

- [ ] **Step 4: Perform local end-to-end acceptance**

Test the complete journeys:

```text
Free registration -> onboarding -> writable Free use -> cap reached -> upgrade options
Premium monthly CTA -> registration -> Premium checkout -> confirmed sandbox payment -> users.tier=Premium
Premium annual CTA -> registration -> Premium checkout -> confirmed sandbox payment -> users.tier=Premium
Premium -> no upgrade action and no empty modal
Free caps -> savings 2, investments 2, pensions 2, property 1, Goals 2, Life Events 1
Free Premium-only API probes -> server denial or correctly shaped Free response
Cancellation -> access retained to period end
Terminal paid -> read-only grace and retention controls
Fyn billing question -> correct tier/status and invoices
Desktop and /m cap/teaser/Holistic paths
```

Verify database rows, network payloads and UI, not UI alone.

- [ ] **Step 5: Deploy the feature branch to csjones and repeat acceptance**

Follow `deploy/DEPLOY.md`, use the dev build only, run approved migrations, reseed, clear caches and use `verify-m`. Save screenshots, relevant JSON responses, subscription/payment row evidence and the deployed commit in `codex/evidence/freemium-remediation-acceptance.md`.

- [ ] **Step 6: Run the complete test suite once**

```bash
./vendor/bin/pest
```

Expected: green. Any failure enters the Rule 14 diagnose-fix-browser-reverify loop; do not hand back partial success.

- [ ] **Step 7: Run plan-compliance and session tech-debt audits**

Check every finding in the coverage table against code and evidence. Then run the `tech-debt-session` skill over changed files. Fix in-scope issues and report adjacent findings without expanding scope.

- [ ] **Step 8: Mark Swift readiness**

Swift migration planning may resume only when all are true:

```text
Canonical status endpoint is stable and documented
No trial state exists in active client/server contracts
Every tier/capability is sourced from tier_configurations
Only free and premium are selectable or returned by active APIs
Registration returns a validated optional checkout intent
Store-boundary enforcement tests are green
Web and /m upgrade journeys are green on csjones
Paid-churn behaviour is documented and tested
No unresolved pricing or payment-provider claim remains
```

- [ ] **Step 9: Commit acceptance evidence**

```bash
git add codex/evidence/freemium-remediation-acceptance.md codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md
git commit -m "test(freemium): record cross-surface remediation acceptance"
```

---

## Release Order

1. PR A: tier-collapse audit, schema identity and canonical `free,premium` keys.
2. PR B: approved capability/count/quota matrix and missing server enforcement.
3. PR C: Premium balance history, missing snapshot coverage and adviser export pack.
4. PR D: canonical subscription-status contract.
5. PR E: pending checkout state and trial-remnant audit command.
6. PR F: public pricing and Premium registration checkout intent.
7. PR G: desktop and `/m` upgrade/cap journeys.
8. PR H: authenticated subscription presentation.
9. PR I: lifecycle/admin corrections and active-copy sweep.
10. Dev deployment and both read-only audits.
11. PR J: trial schema/compatibility removal after the audit gate.
12. PR K: canonical docs and final acceptance evidence.

Every PR targets `dev`. Do not combine PR J with earlier work: the data audit is a mandatory boundary, not ceremony.

## Explicit Deferrals to the Swift Plan

- StoreKit product identifiers for monthly and annual Premium products and the subscription-group configuration.
- App Store Server API and server-notification processing.
- Apple transaction verification and cross-platform entitlement reconciliation.
- Native registration, Face ID credential release and Keychain storage.
- Native paywall design and StoreKit localized pricing.
- Apple-specific upgrade/downgrade timing and billing recovery.
- iPad layout, which remains version 2.

The Swift plan must consume the stable `subscription-status` and structured upgrade-action contracts created here; it must not reproduce web trial remnants or trust local UI state for entitlements.
