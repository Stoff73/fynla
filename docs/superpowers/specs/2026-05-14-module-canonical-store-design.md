---
title: Module Canonical Store-and-Retrieve Contract
date: 2026-05-14
sub_project: 1 of 6 (Fynla major-overhaul series)
status: APPROVED — all 7 open questions resolved 2026-05-14; passes 1–3 DONE, pass 4 (Properties) in flight at 4/8 PRs, passes 5–14 pending
last_updated: 2026-05-27
author: Claude (Opus 4.7) + CSJ
related_specs: (forthcoming) freemium-tier-model, mobile-first-iframe-shell, campaign-engine, track-onboarding, gamification
---

# Module Canonical Store-and-Retrieve Contract

## 0. Where this sits in the bigger picture

Fynla is undergoing a major overhaul covering six independent sub-projects:

| # | Sub-project | Status |
|---|-------------|--------|
| **1** | **Module canonical store-and-retrieve contract** *(this doc)* | APPROVED — pass 1 (Savings) DONE; pass 2 (Reference Data, R1–R4) DONE; pass 3 (Pensions) DONE (8 PRs + close-out #385); pass 4 (Properties) IN FLIGHT — 4/8 PRs merged (#387, #388, #389, #390); passes 5–14 pending |
| 2 | Freemium tier model + count caps + Fyn agent metering | shipped to prod 2026-05-19 (PRs #336 / #337 / #340; supersedes parked #317) |
| 3 | Mobile-first surface via iframe-framed `/m/*` route | in progress — iframe scaffold + drill-down UI shipped to dev (PR #375 open) |
| 4 | Campaign engine (Save Tax landing pages, future campaigns) | not started |
| 5 | Track-lightweight onboarding (matched pension / spouse transfer / 60% trap) | not started |
| 6 | Gamification (campaign progress + incremental unlocks) | not started |

Sub-project 1 is the foundation: every other sub-project assumes the data layer is correct, consistent, and trustworthy. Until that foundation holds, gamification, recommendations, mobile, and tier-gating are building on sand.

This document is the design for sub-project 1 only.

---

## 1. Context and motivation

### 1.1 The problem we're solving

The Fynla codebase has grown large enough that changing a form, an input, or a model field forces rework across modules, services, AI prompts, dashboards, and tests — and unrelated parts of the application break in the process. The root cause is coupling: consumers of user data (recommendations, dashboards, projections, Fyn AI, what-if scenarios, gamification triggers) reach directly into Eloquent models, repeat the same derivations, and depend on the internal shape of those models rather than a stable contract.

### 1.2 The drift we've observed

The most visible symptom is **Fyn AI giving wrong answers** because the database doesn't reflect the user's latest information. Either the data was never written, was written somewhere other than where Fyn AI reads, or was written in a shape Fyn's read tools don't recognise. A separate symptom is `tax_configurations` containing wrong values with no admin UI to fix them — the values are seeded once, drift over time, and downstream tax calculations silently produce wrong results.

### 1.3 The trigger to act now

Three things are converging:

1. The mobile-first overhaul (sub-project 3) will introduce a new surface that reads the same data. If the data layer is fragile, the mobile work compounds the fragility instead of resetting it.
2. The freemium tier model (sub-project 2) introduces count caps, defence-in-depth gating, and Fyn-token metering — all of which need a reliable write path to enforce.
3. The user-acquisition flow (sub-projects 4 and 5) wants minimum-friction onboarding. Onboarding only works if the data captured during a quick track-driven onboarding ends up in the right place, surfaceable by every consumer.

This sub-project locks down the storage layer so that everything built on top of it — mobile, tiers, campaigns, onboarding, gamification — can change freely without breaking storage.

---

## 2. Goals and non-goals

### 2.1 Goals

- **A single canonical store per entity.** Every consumer reads through the same API; every write path goes through the same API; no consumer reaches past it.
- **Three ingest paths produce identical canonical records.** Manual form, Fyn AI chat capture, and document upload all converge on the same store API with the same validation.
- **Calcs live in the backend; results materialize as canonical columns with timestamps.** Frontends and other consumers read values, never compute them.
- **History is preserved.** Snapshots of derived values let us show user progression over time.
- **Reference data is admin-editable.** `tax_configurations`, currency conversion rates, and other reference tables have an admin UI and read consumers re-read on change.
- **The boundary is enforced in CI.** Pest architecture tests fail any PR that bypasses the store.

### 2.2 Non-goals

- We are not rewriting business logic. Calculation rules stay where they are conceptually; we just move *where* and *when* they run.
- We are not changing the Vue or HTTP API contract to consumers in this sub-project. Front-end response shapes stay backward-compatible until consumers are migrated.
- We are not migrating to a different ORM. Eloquent stays.
- We are not building the freemium tier model (sub-project 2). Sub-project 1 adds the *hooks* for count caps and tier-aware retention; sub-project 2 fills them in.
- We are not designing the gamification trigger surface (sub-project 6). Sub-project 1 emits storage events; sub-project 6 decides which events drive which triggers.

### 2.3 Definition of done — sub-project level

Sub-project 1 is complete when all of the following hold:

1. Every in-scope entity has a `Store` service class registered in `App\Services\Stores\`.
2. Every controller, Fyn AI write tool, upload handler, and observer that mutates an in-scope entity does so via the entity's store.
3. Pest architecture tests pass — no code outside `App\Services\Stores\` calls `Model::create`, `Model::update`, `Model::save`, `Model::delete`, or instantiates an in-scope Eloquent model directly.
4. Canonical derived columns are materialized for every entity that has them, with `*_calculated_at` timestamps.
5. Each entity has a snapshot table with documented retention policy.
6. The two specific bug classes named at the start of this work are resolved:
   - **B1** (Fyn AI reads stale state): every entity passes a parity test where user provides data via Fyn AI → next Fyn turn reads back the canonical value.
   - **B2** (`tax_configurations` is wrong and uneditable): admin panel exposes CRUD for every reference-data table, with audit.

---

## 3. Scope

### 3.1 User-data entities — in scope (13)

| # | Entity | Primary table(s) |
|---|--------|------------------|
| 1 | Properties | `properties` |
| 2 | Investments | `investment_accounts`, `holdings`, `investment_transactions` |
| 3 | Bank accounts / cash | `savings_accounts` *(first entity to migrate — prove the pattern)* |
| 4 | Liabilities | `liabilities`, mortgages |
| 5 | Chattels / personal possessions | `chattels` |
| 6 | Pensions | `pension_accounts`, `pension_contributions`, DB scheme tables *(second — prove pattern under complexity)* |
| 7 | Income | `income_sources` |
| 8 | Expenditure | `expenditures` |
| 9 | Protection policies | `protection_policies` |
| 10 | Family members | `family_members` |
| 11 | Goals + life events | `goals`, `goal_contributions`, life-event tables |
| 12 | Business interests | `business_interests` |
| 13 | Trusts | `trusts` |

### 3.2 Document-storage entities — in scope (2, repurposed)

| # | Entity | Notes |
|---|--------|-------|
| 14 | Wills | Repurposed from "Will Builder" — store + view only. User enters their existing will; we don't generate one. |
| 15 | Lasting Powers of Attorney | Same repurposing — store the LPA the user has, don't generate. |

### 3.3 Reference-data entities — in scope (admin-editable)

| # | Entity | Primary table | Edited by | Read by |
|---|--------|--------------|-----------|---------|
| R1 | Tax configuration | `tax_configurations` | admin (CSJ) | all tax / IHT / CGT / income-tax / pension-AA calcs |
| R2 | Currency conversion rates | `currency_rates` (new) | admin or feed | every monetary read across every entity |
| R3 | Actuarial life tables | `actuarial_life_tables` | admin (seeder + UI) | retirement projections, decumulation |
| R4 | Savings market rates | `savings_market_rates` | admin | savings comparisons |

If other reference tables exist (TBD during entity audit), they fall under R1–Rn by the same pattern.

### 3.4 Out of scope for this sub-project

- **Letter to Spouse.** Pure derived surface from existing data — no new data store. Not in this rework.
- **Will Builder logic, LPA Builder logic.** Removed entirely; replaced by store + view (see §3.2).
- **Documents table** as a generic uploaded-file store. The upload *ingest path* is in scope (because uploads produce data), but the generic-file storage shape isn't part of this work.
- **Audit log table itself.** The `Auditable` trait infrastructure stays as-is; we ensure stores invoke it.

---

## 4. Architectural principles

The five rules below are load-bearing. If any decision later in the document contradicts one of these, the rule wins.

### 4.1 One store per entity. One write path per entity.

Every in-scope entity has exactly one service class in `App\Services\Stores\` that is the only legitimate way to mutate the entity. There are no exceptions. Controllers, Fyn AI tools, upload handlers, observers, and seeders all write through the store.

### 4.2 Three ingest paths converge at the store.

```
                     ┌─────────────────────────┐
   Manual form  ─────┤                         │
                     │   Entity Store          │       Canonical
   Fyn AI tool  ─────┤   (validates, persists, │ ───►  record(s)
                     │    audits, derives,     │       + events
   Upload OCR   ─────┤    snapshots, emits)    │
                     └─────────────────────────┘
```

Each ingest path normalises its input into a documented shape (an array of fields), then calls the store. The store does not care which path called it. This is what closes the Fyn-vs-form drift bug class.

### 4.3 Calcs run in the backend; results materialize as columns with timestamps.

When a derived value (e.g. `current_value_gbp`, `equity_gbp`, `years_to_drawdown`) is computed, the result is written to a canonical column on the entity's table along with a `*_calculated_at` timestamp. Consumers — including the frontend — read the column. They do not compute. They do not call a derivation method.

### 4.4 History is preserved; pruning is policy-driven, not hard-coded.

Each derived column declares a **snapshot policy**: trigger predicate (what counts as a meaningful change), retention rule (time-based, count-based, or both), and tier interaction (whether retention varies by user tier). The store enforces the policy uniformly. Adding a new derived value requires declaring its policy, not asking permission. Growth is bounded by pruning, not by capping the count.

### 4.5 The boundary is enforced in CI.

Pest architecture tests run on every PR. They fail the build if any class outside `App\Services\Stores\*` mutates an in-scope Eloquent model. The Pest test is the moat. Convention alone is insufficient; the test is non-negotiable.

---

## 5. The store contract

Every entity store class implements one of two shapes. **User-data stores** (§5.A) handle per-user records with ownership, joint sharing, and soft-delete. **Reference-data stores** (§5.B) handle admin-managed lookup tables with no user scoping. The two shapes share principles (single write path, IngestSource on every mutation, audit, events) but diverge in method signatures.

Per-entity `Store.md` docs in `app/Services/Stores/` are the source of truth for each shipped store's exact API.

### 5.1 Public methods (read) — user-data stores

```php
namespace App\Services\Stores;

class SavingsStore  // canonical example; one per entity
{
    /** Single record visible to $user (respects user_id OR joint_owner_id). */
    public function find(int $id, User $user): ?SavingsAccount;

    /** All records the user can see (own + joint). */
    public function forUser(User $user): Collection;

    /** Same as forUser, with joint-owner relation eager-loaded.
     *  Required where Model::preventLazyLoading is on (e.g. staging AI prompt). */
    public function forUserWithJointOwner(User $user): Collection;

    /** Multi-user joint-aware read for household / multi-owner contexts. */
    public function forUsers(array $userIds): Collection;

    /** User-scoped id-list read. Empty Collection for unknown / other-user ids
     *  — prevents cross-user leakage when callers pass externally-supplied id arrays. */
    public function findMany(array $ids, User $user): Collection;
}
```

Reads return Eloquent model instances or `Collection<Model>`. Consumers may use the model's documented public surface (raw fields, canonical derived columns, declared relationships). They may not call mutation methods (`save`, `update`, `delete`, `forceDelete`). Pest architecture tests enforce this.

Per-entity extension is allowed when the read shape genuinely differs (e.g. `forCohort` on `ActuarialLifeTableStore`). The five methods above are the **minimum** for a user-data store; extensions must remain joint-aware where applicable.

A generic `query(User, EntityQuery)` filter API was specced in the original draft but not built — every shipped store satisfied its consumers with the explicit read methods above plus per-entity extensions. The contract therefore does not require it.

### 5.2 Public methods (read) — reference-data stores

Reference-data stores extend `App\Services\Stores\ReferenceDataStore`, which supplies `find(int $id): array` from a per-request memoised cache. Each entity adds the entity-specific reads it needs:

```php
class TaxConfigStore extends ReferenceDataStore
{
    public function find(int $id): array;                       // inherited
    public function all(): Collection;                          // admin index
    public function findByTaxYear(string $taxYear): ?TaxConfiguration;
    public function activeConfig(): ?array;                     // memoised per-instance
    public function forgetActive(): void;                       // drop the memo
    public function findEloquent(int $id): ?TaxConfiguration;   // for admin controller
}
```

Common patterns across the four shipped ref-data stores:
- `find(int $id): array` (inherited from base)
- `all(): Collection` (admin index)
- one or more entity-specific reads (`forCohort`, `forTaxYear`, `findByX`)
- a `findEloquent(int $id): ?Model` for the admin controller to build Resources

### 5.3 Public methods (write) — user-data stores

```php
public function create(
    array $data,
    User $user,
    IngestSource $source     // FORM | FYN_AI | UPLOAD | SEEDER
): Model;

public function update(
    int $id,
    array $data,
    User $user,
    IngestSource $source
): Model;

public function updateOrCreate(
    array $match,
    array $data,
    User $user,
    IngestSource $source
): Model;

public function delete(int $id, User $user, string $reason): void;

public function restore(int $id, User $user): Model;
```

`IngestSource` is required and audited via `AuditLog::withContext(['ingest_source' => $source->value], …)` (see §8.1). Writes are primary-owner-only — joint owners have read-only access. Soft-delete is the deletion contract; `forceDelete` is not exposed.

### 5.4 Public methods (write) — reference-data stores

```php
public function create(
    array $input,
    IngestSource $source,           // ADMIN | SEEDER
    ?int $actorUserId = null
): int;                              // returns id

public function update(
    int $id,
    array $input,
    IngestSource $source,
    ?int $actorUserId = null
): void;

public function delete(
    int $id,
    IngestSource $source,
    ?int $actorUserId = null
): void;
```

Reference-data stores accept only `IngestSource::ADMIN` or `IngestSource::SEEDER` (enforced by `ReferenceDataStore::guardSource`). There is **no `restore` method** — reference-data rows are not soft-deleted; `delete` is hard and idempotent. The `actorUserId` parameter exists so admin writes attribute correctly even though there's no `User $user` scoping. Per-store extensions (e.g. `TaxConfigStore::setActive`, `TaxConfigStore::duplicate`) are allowed for entity-specific lifecycle operations.

### 5.5 Internal methods (recalc, snapshot, events)

For user-data stores with derived columns, recalc and snapshotting run inside the `create()` / `update()` DB transaction. The shipped pattern is **one private method** that both materialises derived columns and writes per-column snapshots per policy:

```php
private function recalculateDerived(Model $entity, IngestSource $source, string $reason): void;
```

`$reason` is `'create'` or `'update'`, propagated into the snapshot row. Snapshot policies are injected per-column (`SnapshotPolicies::savingsAccountBalance()`, etc.) and decide whether a snapshot row is written for a given old/new value pair.

Events are dispatched inline at the end of each public write method via `event(new EntityXxxxx(...))`. There is no `emitEvent()` helper.

Consumers cannot call any of this directly — the methods are `private`.

### 5.6 Tier-aware count caps (defence in depth)

`create()` refuses to write if the user's tier doesn't permit another row. The check is:

```php
if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $this->countFor($user))) {
    throw new TierLimitExceededException(...);
}
```

UI hides the "add" button — the store check is the second line of defence. Sub-project 2 supplies the `TierGate` implementation (`DbTierGate`); sub-project 1 ensures every store consults it. Reference-data stores skip this check (admin-only; no per-user count).

### 5.7 What the store does NOT expose

- No raw Eloquent query builder. Consumers cannot do `SavingsStore::query()->where(...)->get()`.
- No relationship eager-load hints from the outside. Reads return models with relationships pre-loaded per a documented per-method contract.
- No "save this raw model" escape hatch. There is no `$store->save($model)` shortcut. All writes go through `create` / `update` / `updateOrCreate` / `delete` / `restore`.
- No business-rule helpers ("is this account dormant?"). Those live in consumers.
- No `forceDelete` or `withTrashed`-as-default reads on user-data stores. `restore()` is the only entry point that sees trashed rows, and only for the single id it operates on.

### 5.4 Tier-aware count caps (defence in depth)

`create()` refuses to write if the user's tier doesn't permit another row. The check is:

```php
if (!$this->tierGate->canCreate($user, self::ENTITY_KEY, $this->countFor($user))) {
    throw new TierLimitExceededException(...);
}
```

UI hides the "add" button — the store check is the second line of defence. Sub-project 2 supplies `tierGate`; sub-project 1 ensures every store consults it.

### 5.5 What the store does NOT expose

- No raw Eloquent query builder. Consumers cannot do `SavingsStore::query()->where(...)->get()`.
- No relationship eager-load hints. Reads return models with relationships pre-loaded per a documented per-method contract.
- No "save this raw model" escape hatch. There is no `$store->save($model)` shortcut. All writes go through `create` / `update`.
- No business-rule helpers ("is this account dormant?"). Those live in consumers.

---

## 6. Ingest paths

Three paths, one converging point.

### 6.1 Manual form (existing pattern, adapted)

```
Vue form ──► axios POST ──► Controller ──► FormRequest validates
                                         ──► IngestNormalizer (form) maps to canonical array
                                         ──► SavingsStore->create($data, $user, IngestSource::FORM)
                                         ──► returns 201 + canonical resource
```

FormRequest validation stays where it is. Controllers shrink to ~10 lines: validate, normalise, call store, return resource.

### 6.2 Fyn AI tool call

```
LLM emits tool call ──► OnboardingChatDirector / AdviceFyn delegate
                                         ──► tool-param validator
                                         ──► IngestNormalizer (fyn) maps to canonical array
                                         ──► SavingsStore->create($data, $user, IngestSource::FYN_AI)
                                         ──► returns confirmation back to LLM
```

This is the path that fixes bug B1. Today, Fyn AI write tools may bypass FormRequest-level validation or write fields in slightly different shapes from forms. Routing every Fyn write through the same store closes that gap.

`AdviceFyn` remains read-only — when its model emits `delegate_to_capture`, the dispatch lands at `OnboardingChatDirector::handleInlineCapture`, which then calls the store. This preserves the canonical contract in `April/April24Updates/spec/00-canonical.md`.

### 6.3 Document upload + OCR

```
Vue uploader ──► POST /api/uploads ──► UploadController stores file temporarily
                                    ──► AssetCaptureEntityExtractor runs OCR + LLM extraction
                                    ──► extraction returns structured fields per entity
                                    ──► IngestNormalizer (upload) maps to canonical array
                                    ──► SavingsStore->create(...) (or createMany for multi-entity)
                                    ──► (tier 2+3 only) document retained in DocumentStore
                                    ──► (tier free + 1) temporary file deleted
                                    ──► returns summary of what was captured
```

Document retention is tier-aware:
- **Free + Tier 1:** extraction runs, data lands in stores, original document is deleted.
- **Tier 2 + Tier 3:** extraction runs, data lands in stores, original document is retained linked to the source entity (up to the user's storage quota — see sub-project 2 for quotas).

The retention policy is enforced by `UploadController` after the store calls succeed.

### 6.4 The normaliser layer

`App\Services\Stores\Normalisers\{Entity}Normaliser` per entity, with one method per ingest source:

```php
class SavingsAccountNormaliser
{
    public function fromForm(array $request): array;
    public function fromFyn(array $toolParams): array;
    public function fromUpload(array $extraction): array;
}
```

The output is the canonical-array shape consumed by `SavingsStore::create`. The normaliser handles enum casting, name-field aliases, currency defaulting, and field-name reconciliation between the three sources. If a normaliser cannot map a field, it returns a documented error — the store is never asked to invent values.

---

## 7. Validation

### 7.1 Two layers

- **Outer layer (ingest-path-specific):** FormRequests for forms, tool-param schemas for Fyn AI, extraction-confidence checks for uploads. Catches obviously bad input early.
- **Inner layer (store-internal, canonical):** the store validates the *canonical-array shape* regardless of source. This is the layer that guarantees "no matter where the write came from, the persisted record is valid."

The inner layer is non-optional and applies to seeders and admin writes too. Seeders today often bypass FormRequest; under the new contract, they call the store with `IngestSource::SEEDER` and the store still validates.

### 7.2 What the inner layer checks

- Required fields present.
- Field types and ranges (e.g. `value` is numeric and >= 0; `currency` is a known ISO 4217 code).
- Cross-field invariants (e.g. `interest_only` mortgage requires `interest_rate` not null; `joint_owner_id` requires `ownership_percentage` between 0 and 100 exclusive of 0 and 100 for joint).
- Ownership consistency (the calling user owns or co-owns what they're trying to mutate).
- Tier-aware count caps (§5.4).

### 7.3 Failure mode

Invalid writes throw a `StoreValidationException` with a structured error list. Controllers turn this into a 422; Fyn AI surfaces it back to the LLM as a tool error so the model can correct.

---

## 8. Audit, encryption, security

### 8.1 Audit

Every store write invokes the existing `Auditable` trait pipeline. The audit row includes:
- `user_id`, `entity_type`, `entity_id`, `action` (create/update/delete/restore)
- `ingest_source` (FORM / FYN_AI / UPLOAD / SEEDER / ADMIN)
- `changes` (diff of changed fields)
- `actor_user_id` (for admin and impersonation writes)

Audit happens *inside* the store. Consumers don't need to remember to audit.

### 8.2 Encryption at rest

Sensitive fields (account numbers, sort codes, NI numbers, policy numbers, beneficiary names) are encrypted using Laravel's `Crypt` facade with `EncryptedCast` on the model. The store enforces this — a write that lacks an `EncryptedCast` declaration on a sensitive field fails the Pest architecture test.

### 8.3 Authorisation

The store is the enforcement point for "can this user write this row?". Policies live in `App\Policies\` and are invoked inside the store. Controllers do not need to call `authorize()` separately; the store throws `AuthorizationException` on failure.

### 8.4 Preview-user isolation

Preview users (`is_preview_user = true`) write through the same stores. The `PreviewWriteInterceptor` middleware continues to intercept writes from preview users at the HTTP layer. The store is unaware of preview status — it processes whatever the middleware lets through. This keeps the canonical contract honest.

---

## 9. Currency normalisation

### 9.1 Storage shape

Every monetary column on every entity stores the native value alongside the native currency:

```
properties.purchase_value         (decimal, native currency)
properties.purchase_value_currency (char(3), ISO 4217, default 'GBP')
properties.current_value          (decimal, native currency)
properties.current_value_currency (char(3), default 'GBP')
```

For existing rows, a migration backfills `_currency` columns to `'GBP'`. Existing values are unchanged.

### 9.2 Conversion at read time + display preference

The store exposes a paired `_gbp` field for each monetary column. Conversion uses the latest rate from the `currency_rates` reference-data store (R2).

```
properties.current_value           = 250000 (EUR)
properties.current_value_currency  = 'EUR'
properties.current_value_gbp       = 212500 (computed at read using rate × time)
properties.current_value_gbp_at    = 2026-05-14T10:00:00Z
```

`_gbp_at` is the timestamp at which the conversion was computed. If the rate changes, the next `recalculateDerived()` pass re-derives the `_gbp` column and writes a snapshot.

**Display preference is tier-gated:**

| Tier | Display behaviour |
|------|-------------------|
| Free, Tier 1 | Always GBP. Native currency preserved in DB but only `_gbp` is surfaced. |
| Tier 2, Tier 3 | User picks a display currency from preferences (default GBP, options include EUR / USD / others as we onboard them). The store derives `_display` columns at read time using the same rate-conversion path. |

The store API for tier 2+3 reads:
```
properties.current_value_display       = (native → user's chosen display currency)
properties.current_value_display_ccy   = (user's chosen display currency code)
```

The `_gbp` column always exists for cross-tier consumers (recommendations, IHT calc, etc.) that need a single canonical figure. Display currency is *additive* — never replaces `_gbp`.

### 9.3 Why not convert at write?

Write-time conversion would lose the user's native-currency intent. A user who entered "€250,000" should always be able to see €250,000; only the GBP equivalent moves with rates. Read-time conversion preserves both views.

### 9.4 Single source of truth for rates

The `currency_rates` ref-data store is admin-editable from the admin panel and (future) updated automatically via a scheduled job. Changing one rate row triggers recompute of every consumer's `_gbp` derived column on the next nightly job — or sooner if a snapshot trigger fires.

---

## 10. Canonical derived columns and snapshot policy

### 10.1 What is "canonical"

A *canonical derived column* is a value computed from the entity's raw fields (plus ref-data) that is:
- materialised on the entity's primary table with a `*_calculated_at` timestamp
- exposed by the store's read API
- consumed without recompute by all consumers (frontend, dashboards, recommendations, Fyn AI)

### 10.2 Examples (representative, not exhaustive)

| Entity | Canonical derived columns |
|--------|---------------------------|
| Properties | `current_value_gbp`, `equity_gbp`, `ltv_pct`, `iht_estate_share_gbp` |
| Investments | `current_value_gbp`, `gain_loss_pct`, `gain_loss_gbp`, `asset_allocation_summary` |
| Savings | `balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct` |
| Pensions | `current_value_gbp`, `years_to_drawdown`, `projected_value_at_drawdown_gbp`, `annual_allowance_used_pct` |
| Liabilities | `outstanding_balance_gbp`, `monthly_payment_gbp`, `years_remaining` |

Each entity's full list is declared in the entity's implementation plan (not this design doc) and is treated as part of the entity's stable contract.

### 10.3 Snapshot policy — per derived column

**Retention vs. surfacing are two different concerns:**

- **Retention (storage):** ALL snapshots are retained for **7 years** (2555 days) for every user regardless of tier. This is a regulatory / compliance baseline — financial data retention. Pruning beyond 7 years runs as a nightly job.
- **Surfacing (API):** the *visible window* exposed through the store's read API and to consumers (charts, progression views, recommendations) is tier-gated. Snapshots older than the user's tier window are present in the database but not returned by default. Upgrading a user's tier instantly widens their visible window with no recomputation needed. **At Tier 3 the surfacing window equals the retention floor (2555 days / 7 years) — there is no API-level gating at the top tier; the user sees everything that's retained.**

Each derived column declares a `SnapshotPolicy` object:

```php
new SnapshotPolicy(
    triggerPredicate: fn($old, $new) =>
        abs($new - $old) > 100         // > £100 change
        || abs($new - $old) / $old > 0.01,  // OR > 1% change
    retentionDays: 2555,                // 7 years for ALL tiers (regulatory floor)
    surfacingWindowDays: [
        'free'  => 90,
        'tier1' => 365,
        'tier2' => 1825,    // 5 years
        'tier3' => 2555,    // 7 years = full retained history (no API gating)
    ],
    maxRowsHardCap: 5000,    // per user per column, to prevent runaway
    recalcCadence: 'on_change',  // or 'daily' for time-dependent values
);
```

The store exposes both surfaces:
```php
$store->snapshots($id, $user);             // returns within user's tier window
$store->snapshots($id, $user, fullHistory: true);  // returns all retained — used by admin / GDPR export only
```

### 10.4 Snapshot tables

Each entity has a paired snapshot table:

```
property_value_snapshots
  id, property_id, column_name, value, currency, value_gbp,
  taken_at, trigger_reason, ingest_source
```

A single snapshot table per entity covers all derived columns on that entity. Tier-based pruning runs nightly. Count-based pruning runs on insert.

### 10.5 Recalc lifecycle

```
write to store ──► persist raw fields
              ──► recalculateDerived() runs all derived-column policies
              ──► for each derived column:
                    ──► compute new value
                    ──► if policy.triggerPredicate(old, new) → snapshot row + update *_calculated_at
                    ──► else → quietly update column, no snapshot
              ──► emit storage event (§11)
```

Recalc is bounded — only the entity being written is touched. Cross-entity recalculation (e.g. liability change → net-worth recompute) happens via events, not in-line.

---

## 11. Storage events

### 11.1 Shape

Event shape depends on the store type.

**User-data stores** emit exactly four event classes per entity (one per write method):

```php
class SavingsAccountCreated { public readonly SavingsAccount $entity; public readonly User $user; public readonly IngestSource $source; }
class SavingsAccountUpdated { public readonly SavingsAccount $entity; public readonly array $changes; public readonly User $user; public readonly IngestSource $source; }
class SavingsAccountDeleted { public readonly int $entityId; public readonly User $user; public readonly string $reason; }
class SavingsAccountRestored { public readonly SavingsAccount $entity; public readonly User $user; }
```

**Reference-data stores** emit a single discriminator event with `entity_type` payload (since there is no `User`, no soft-delete, and the listener set is small enough that a typed-payload event is cheaper than four classes per entity):

```php
class ReferenceDataUpdated {
    public function __construct(
        public readonly string $entityType,   // 'tax_configuration' | 'actuarial_life_table' | …
        public readonly int $entityId,
        public readonly array $changedKeys,   // array_keys($canonical) on create/update; ['is_active'] on TaxConfig::setActive; ['__deleted'] on delete
        public readonly ?int $actorUserId,
    ) {}
}
```

Both shapes flow through Laravel's `event()` dispatch and are observed via `EventServiceProvider` listener registration.

### 11.2 Consumers

Consumers register listeners in `EventServiceProvider`. Examples:
- **NetWorthRecalculator** listens to all financial-entity events to invalidate cached aggregations.
- **GamificationTriggerService** (sub-project 6) listens to created/updated to fire achievements.
- **RecommendationEngine** listens to schedule recommendation refresh.
- **FynReadCacheInvalidator** listens to invalidate cached read-models for Fyn AI.

### 11.3 What events are NOT for

Events are not a back-channel to mutate the same entity again. Listeners do not call store write methods on the entity that triggered them. Cross-entity writes are allowed if they make sense (e.g. updating a goal's progress after an investment contribution lands), but must go through the relevant store.

---

## 12. Reference-data stores

### 12.1 The same pattern, applied to admin-managed data

Reference-data tables (R1–R4 in §3.3) use the same store/ingest/audit pattern, with one difference: the only `IngestSource` for writes is `ADMIN` (or `SEEDER` for bootstrapping).

```
TaxConfigStore  (read-heavy, admin-write)
CurrencyRateStore
ActuarialLifeTableStore
SavingsMarketRateStore
```

**Existing admin UI to be fixed, not rebuilt:**
- **Tax configuration** — already has [`TaxSettings.vue`](resources/js/components/Admin/TaxSettings.vue) + [`TaxSettingsController.php`](app/Http/Controllers/Api/TaxSettingsController.php) + `StoreTaxConfigurationRequest` + `TaxConfigurationAudit` model. **CSJ-reported broken state: views exist but are not wired correctly — values can't actually be edited.** Sub-project 1 audits and fixes the wiring rather than building from scratch.
- **Currency rates, actuarial tables, savings market rates** — no existing admin UI visible; new admin views needed, following the same shape as the (fixed) tax-config admin pattern.

The admin UI replaces the seeder-only workflow that caused B2. The location for the new screens follows the existing admin-panel pattern (tabs/sections of `AdminPanel.vue`), not a separate `/admin/reference-data/*` namespace.

### 12.2 Read caching

Reference data is read on nearly every request, so each store maintains a per-request memoised cache. On admin write, the store invalidates the cache and emits a `ReferenceDataUpdated` event so consumers can drop their own caches.

### 12.3 Bootstrapping

Seeders continue to seed reference data on a fresh install, calling the store with `IngestSource::SEEDER`. The seeder is no longer the only path — it's just one path.

---

## 13. Tier-aware count caps (hooks for sub-project 2)

This sub-project introduces a thin `TierGate` interface that stores call. Sub-project 2 provides the real implementation.

```php
interface TierGate
{
    public function canCreate(User $user, string $entityKey, int $currentCount): bool;
    public function softLimit(User $user, string $entityKey): ?int; // null = unlimited
    public function hardLimit(User $user, string $entityKey): ?int;
}
```

Initial entity keys (sub-project 1 audits and documents the list):
- `savings_account` (free: 3, tier1+: unlimited)
- `investment` (free: 2, tier1+: unlimited)
- `pension_account` (free: 5, tier1+: unlimited)
- *(other entities are unlimited at free tier per current matrix; this list grows in sub-project 2 if needed)*

Sub-project 1's job is to make sure every store calls `canCreate` before persisting. Sub-project 2 owns the actual limit numbers and how upgrade prompts surface.

---

## 14. Boundary enforcement (Pest architecture tests)

### 14.1 The test set

The following Pest architecture tests run in CI on every PR. **Enforcement mode is hard CI failure from PR 1 of every entity, including the first** (CSJ decision). No soft-warn ramp-up. The test is the gate, not a guideline.

```php
// tests/Architecture/StoreBoundaryTest.php

arch('only stores mutate in-scope models')
    ->expect(['App\Models\SavingsAccount', 'App\Models\Property', /* …all in-scope models */])
    ->toOnlyBeUsedIn(['App\Services\Stores', 'App\Observers', 'App\Console\Commands']);

arch('controllers may not call model mutation methods')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Eloquent\Model::create', 'Eloquent\Model::save', 'Eloquent\Model::update']);

arch('Fyn AI tools route through stores')
    ->expect('App\Services\AI\Tools')
    ->not->toUse(/* in-scope models directly */);

arch('every in-scope entity has a Store class')
    ->expect('App\Services\Stores')
    ->toContainClassesNamedLike('*Store')
    ->and(/* one-per-entity assertion */);

arch('Store methods accept IngestSource')
    ->expect('App\Services\Stores\*\create')
    ->toHaveParameterOfType(IngestSource::class);
```

The exact test syntax follows Pest's `arch()` API; the *intent* above is what's load-bearing.

### 14.2 Allowlist for legitimate exceptions

A small allowlist documents code paths that legitimately touch models directly:
- Observers in `App\Observers\` (they react to model events; can't go through store without recursion).
- Console commands in `App\Console\Commands\` (admin-grade tooling).
- Migrations.
- Seeders during install (they call the store; the model is reached through the store).

The allowlist lives in the architecture test file with a comment explaining each entry. Adding to it requires a PR review.

---

## 15. Migration strategy

### 15.1 Entity-by-entity, complete before moving on

The decision is **option (b) from the brainstorming session**: pick one entity, build its store, migrate all consumers, lock it down, *then* move to the next entity. No entity is half-done. No two entities are in flight at the same time.

Within an entity, the work splits into a sequence of small PRs (typically 6–8 per entity):

| PR | Title pattern | What it does | Risk |
|----|---------------|--------------|------|
| 1 | `feat({entity}): introduce {Entity}Store facade + validation + audit` | Add store class, normalizer, ingest-source enum, Pest architecture test stub. No consumers wired. | Very low — pure addition. |
| 2 | `refactor({entity}): point form requests at {Entity}Store` | Controllers + FormRequests now call store. | Low — covered by feature tests. |
| 3 | `refactor({entity}): point Fyn AI write tools at {Entity}Store` | Onboarding Fyn capture + AdviceFyn delegate route through store. | Medium — Fyn integration must be re-verified end-to-end. |
| 4 | `refactor({entity}): point upload-extraction at {Entity}Store` | OCR + extraction path uses store. Skip if entity has no upload path yet. | Low–medium. |
| 5 | `refactor({entity}): point read consumers at {Entity}Store` | Dashboards, what-if, IHT calc, recommendations, observers consume canonical derived columns. **Auto-split when diff exceeds ~500 lines** — split by consumer cluster (dashboard / IHT / what-if / recommendations / Fyn-read / projections), each becoming its own PR (5a, 5b, …) in sequence. No consult needed per split. | Medium — broadest blast radius; biggest PR in the sequence. |
| 6 | `feat({entity}): materialize canonical derived columns + snapshot table` | Add columns, snapshot table, recalc, policies. Backfill existing data. | Medium — migration must be reversible. |
| 7 | `feat({entity}): admin tier-cap enforcement` | Wire `TierGate::canCreate` into store. (Cap values come from sub-project 2.) | Low. |
| 8 | `lock-down({entity}): enable Pest architecture test` | Architecture test goes from soft (warning) to hard (failing). Remove from allowlist. | Low — by this point all consumers route through store. |

Each PR ships to `dev`, deploys to csjones, gets browser-tested, then merges to main. Standard Fynla flow.

### 15.2 No two entities in flight simultaneously

A new entity's PR 1 only opens after the previous entity's PR 8 has shipped to main. This is what keeps the migration legible — at every point in time, every entity is either "not started" or "fully strangled", never "halfway".

### 15.3 Order

| Pass | Entity | Status | Why this position |
|------|--------|--------|-------------------|
| 1 | **Savings (bank/cash accounts)** | **DONE** (8 PRs, #305–#323, locked) | Simple, frequent Fyn-capture target. Modest consumer surface. Proved the pattern. |
| 2 | **Reference data (R1–R4)** | **DONE** (26 PRs across R1–R4 tracks, all locked) | Pulled forward from pass 14 to close B2 (`tax_configurations` wrong + admin views not wired) early, while the store template from pass 1 was still fresh. Every subsequent entity migrates against a clean tax-config foundation. |
| 3 | **Pensions** (DC + DB + State + InputHistory) | **DONE** (8 PRs + close-out #385, locked) | Most complex single entity. Pattern survived multi-table cross-record (contributions, InputHistory) handling. Three-ingest parity test (`PensionThreeIngestParityTest`) shipped with close-out. No joint-ownership (UK pensions are single-owner) — Properties (pass 4) restores joint-aware reads from the Savings template. |
| 4 | **Properties** | **IN FLIGHT** — 4/8 PRs merged (PR 1 #387 facade + boundary + normaliser + 4 events; PR 2 #388 HTTP form requests + cross-store Option A tier-limit shape; PR 3 #389 Fyn AI write tools + DB::transaction atomicity; PR 4 #390 upload + onboarding + seeders, incl. `PropertyNormaliser::fromForm` seam in OnboardingService). PR 5 (read consumers, sub-clustered ~21 files — biggest PR of this pass) next. PR 6 derived columns + snapshot table; PR 7 tier-cap test; PR 8 lock-down + parity + audit + `PropertyStore.md`. | Heavy consumer surface (dashboard, IHT, what-if, mortgage). High user value. Second joint-aware entity (after Savings). |
| 5 | **Liabilities** | pending — no plan | Pairs with properties (mortgages — `properties.outstanding_mortgage` is currently a denormalised cache; Pass 5 reconciles against the `mortgages` table as the canonical source). Logical next after Properties. |
| 6 | **Investments** | pending — no plan | Multi-table (`investment_accounts` + `holdings` + `investment_transactions`). |
| 7 | **Income** + **Expenditure** | pending — no plan | Cross-cutting financial inputs; near-twin entities. |
| 8 | **Protection** | pending — no plan | Insurance policies. |
| 9 | **Family members** | pending — no plan | Foundational but lightly consumed. |
| 10 | **Goals + life events** | pending — no plan | Already partly modernised. |
| 11 | **Chattels** | pending — no plan | Simple, low priority. Inherits same `current_valuation` → `current_value` bug pattern surfaced in Pass 4 PR 4 (`MigrateEstateToNetWorth::migrateChattel:223` still writes `current_valuation`) — apply same fix during this pass. |
| 12 | **Business interests** | pending — no plan | Small surface. Same `current_valuation` bug-pattern as Chattels (`MigrateEstateToNetWorth::migrateBusiness:201`) — apply same fix during this pass. |
| 13 | **Trusts** | pending — no plan | Paid-tier feature; small surface. |
| 14 | **Wills** + **LPAs** | pending — no plan | Repurposed from builders — biggest *behaviour* change but smallest *data* change. |

Order between passes 5 and 14 can flex based on what surfaces during the work; passes 1, 2, 3, and 4 are fixed (passes 1–3 shipped to main; pass 4 plan is canonical at `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md`).

### 15.4 What we ship per entity

For each entity, the end-state deliverables are:
- `App\Services\Stores\{Entity}Store` class
- `App\Services\Stores\Normalisers\{Entity}Normaliser` class
- `IngestSource` enum (one shared file, written once)
- Per-entity snapshot table migration
- Per-entity derived-column migration
- Pest architecture test enabling for this entity
- Per-entity feature-test parity suite (form, Fyn, upload all write identical records)
- Admin UI updates (where the entity has admin surface)

---

## 16. Acceptance criteria

### 16.1 Per-entity acceptance (every entity must pass before moving on)

1. **Single write path.** No code outside the store mutates the entity's model. Pest architecture test passes.
2. **Three-ingest parity.** A test suite asserts that creating the same logical record via form, Fyn AI, and upload (where applicable) produces byte-identical canonical rows (excluding `id`, timestamps, and `ingest_source`).
3. **Audit completeness.** Every write produces an audit row with `ingest_source` populated.
4. **Derived-column correctness.** Materialised columns match an independent recomputation in tests.
5. **Snapshot policy applied.** Triggers fire when policy says so; retention pruning works.
6. **Currency round-trip.** Native value + currency persisted; `_gbp` derived correctly; rate change re-derives.
7. **Tier-cap enforcement.** Refusal happens at the store level with a clear exception, not just in the UI.
8. **Browser-tested via Playwright.** Form, Fyn AI, and upload paths each verified end-to-end on csjones before merge to main. (Per Fynla browser-testing law.)

### 16.2 Sub-project-wide acceptance

1. All 13 user-data entities + 2 document-storage entities + 4 reference-data entities have stores meeting §16.1. **Progress: 6 of 19 fully shipped (Savings + 4 ref-data + Pensions). Properties in flight — store class + boundary + normaliser shipped (PR 1 #387), all three ingest paths routed (PRs 2–4), but §16.1 acceptance (derived columns + snapshot policy + tier-cap test + three-ingest parity + Store.md) lands across PRs 5–8.**
2. Pest architecture test suite is green for the full set. **Progress: 7 boundary tests passing (`SavingsStoreBoundaryTest`, `TaxConfigStoreBoundaryTest`, `ActuarialLifeTableStoreBoundaryTest`, `CurrencyRateStoreBoundaryTest`, `SavingsMarketRateStoreBoundaryTest`, `PensionStoreBoundaryTest`, `PropertyStoreBoundaryTest`). All 7 enforce as hard-fail from their respective PR 1; the "lock-down" PR 8 trims the allowlist to its final form rather than flipping a soft→hard switch.**
3. `tax_configurations` is fully editable from the admin panel (B2 closed). **DONE — admin CRUD via `TaxSettingsController` → `TaxConfigStore` (PRs R1.2 #365, R1.5 #372).**
4. Fyn AI three-turn capture-and-read parity test passes for every entity (B1 closed). **Progress: 2 of 13 user-data entities have parity tests shipped (`SavingsThreeIngestParityTest` #324, `PensionThreeIngestParityTest` PR #385). Properties parity test lands in PR 8 of Pass 4 (incl. `tenants_in_common` case per CLAUDE.md Rule #5).**
5. Documentation: each entity has an `app/Services/Stores/{Entity}Store.md` explaining its derived columns, snapshot policy, and tier caps. **Progress: 6 of 19 docs landed (`SavingsStore.md`, `TaxConfigStore.md`, `ActuarialLifeTableStore.md`, `CurrencyRateStore.md`, `SavingsMarketRateStore.md`, `PensionStore.md`). `PropertyStore.md` ships in Pass 4 PR 8.**

---

## 17. Out of scope (explicit)

The following are explicitly *not* in sub-project 1 (most are in later sub-projects):

- Freemium tier definitions and pricing (sub-project 2).
- Document storage quotas in GB (sub-project 2 — sub-project 1 defines the *path*, sub-project 2 sets the *quota*).
- Fyn AI token metering and weekly soft-degrade (sub-project 2).
- Mobile-first `/m/*` shell or phone-frame iframe (sub-project 3).
- Save-Tax campaign landing pages (sub-project 4).
- Track-lightweight onboarding flows (sub-project 5).
- Gamification triggers, achievements, streaks (sub-project 6).
- Changes to the Vue/HTTP API shape consumers receive — backward compatibility is maintained throughout this sub-project.
- Migration from Eloquent to a different ORM.
- Replacing or restructuring the audit log table itself.
- A full DDD/hexagonal rewrite. We picked Approach A (service facade over Eloquent), not Approach C.

---

## 18. Risks and mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Pest architecture tests are too strict and block legitimate work | Medium | High | Allowlist with documented entries; soft-warn before hard-fail on first entity; iterate. |
| Derived-column backfill is slow on large tables | Low (Fynla data volumes are small per user) | Medium | Backfill via chunked artisan command; never block migrations. |
| Fyn AI tool migration breaks the SSE conversation contract | Medium | High | Per-entity feature-test parity suite covers Fyn path; csjones browser-test mandatory before main. |
| Currency conversion adds latency on every read | Low (cached ref-data + memoised) | Low | Memoise rate within request; bench before lock-down. |
| Snapshot tables grow unboundedly despite policy | Low | Medium | Nightly pruning job with metrics + alerts; max-rows hard cap. |
| Consumer migration (PR 5 per entity) is too big to review | High | Medium | Sub-divide PR 5 by consumer cluster (dashboard / IHT / what-if / recommendations) if it exceeds ~500 lines diff. |
| Existing observers create recursion with new event emitters | Medium | Medium | Audit observers per entity in PR 1; convert to event listeners where appropriate. |

---

## 19. Dependencies on other sub-projects

| Depends on | What we need from them | How sub-project 1 unblocks |
|------------|------------------------|----------------------------|
| Sub-project 2 (freemium) | Final tier-cap numbers for each entity; document storage quotas; Fyn weekly token limits | Sub-project 1 ships the `TierGate` interface and store-side enforcement points. Sub-project 2 fills in numbers. |
| Sub-project 3 (mobile) | None — sub-project 3 consumes the same stores as desktop. | Mobile gets a clean data layer to build on. |
| Sub-project 4 (campaigns) | None — campaign engine reads through stores. | Campaign data writes (e.g. selected track) become a small entity using the same pattern. |
| Sub-project 5 (track onboarding) | None — onboarding writes through stores like every other Fyn capture. | Onboarding Fyn becomes one of three ingest paths converging on the store. |
| Sub-project 6 (gamification) | List of which storage events drive which achievements. | Storage events are already emitted by every store. Sub-project 6 wires listeners. |

Sub-project 1 produces the foundation; everything else consumes it.

---

## 20. Open questions — all resolved 2026-05-14

| # | Question | Decision | Recorded in |
|---|----------|----------|-------------|
| 1 | Snapshot retention | Retain ALL snapshots 7 years (2555d) for every user; surfacing window tier-gated. | §10.3 |
| 2 | Currency display | Free + Tier 1 = GBP only. Tier 2 + 3 = user-chosen display currency. | §9.2 |
| 3 | Reference-data admin location | Fix existing TaxSettings.vue / TaxSettingsController wiring; other ref-data follows same shape inside existing AdminPanel pattern. | §12.1 |
| 4 | Pest architecture test ramp-up | Hard CI failure from PR 1 of pass 1. No soft-warn. | §14.1 |
| 5 | PR 5 split rule | Auto-split by consumer cluster when diff > 500 lines. No consult needed. | §15.1 (PR 5 row) |
| 6 | Tier 2 / Tier 3 snapshot surfacing windows | Tier 2 = 1825d (5 years). Tier 3 = 2555d (full retained history, equals retention — no API gating at top tier). | §10.3 |
| 7 | Migration ordering — pull reference data forward? | Yes. Reference data moves from pass 14 to **pass 2** to close B2 early while the store template is still fresh; every subsequent entity migrates against a clean tax-config foundation. | §15.3 |

**No outstanding questions. Spec is ready for the implementation-plan pass.**

---

## 21. Sign-off

Approved 2026-05-14 — all seven open questions resolved by CSJ (see §20).

### 21.1 Implementation rollout

| Pass | Entity | Plan | Status | Acceptance |
|------|--------|------|--------|------------|
| 1 | Savings | `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` | DONE — 8 PRs (#305–#323), boundary locked | Three-ingest parity #324; `SavingsStore.md`; derived columns + snapshots #321; tier-cap hook #322 |
| 2 | Reference data (R1–R4) | `docs/superpowers/plans/2026-05-21-sub-project-1-pass-2-reference-data-plan.md` | DONE — 26 PRs across R1/R2/R3/R4 tracks, all locked | Four `*Store.md` docs (#373); admin UI for tax / actuarial / FX / savings-rates; B2 closed (#372) |
| 3 | Pensions (DC + DB + State + InputHistory) | `docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md` (4200 lines) | DONE — 8 PRs + close-out PR #385, boundary locked | `PensionStore.md` + `PensionThreeIngestParityTest` (#385); derived columns + snapshots; tier-cap hook; multi-table cross-record (contributions + InputHistory) pattern proven |
| 4 | Properties | `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md` (2743 lines) | IN FLIGHT — 4/8 PRs merged. PR 1 #387 (facade + boundary + `PropertyNormaliser` + 4 events). PR 2 #388 (HTTP form requests + Option A cross-store tier-limit response shape). PR 3 #389 (Fyn AI write tools + `DB::transaction` atomicity). PR 4 #390 (upload + onboarding + seeders + `PropertyStore::updateOrCreate` consumed by `ChrisUserSeeder`; `PropertyNormaliser::fromForm` seam in `OnboardingService`; 2 pre-existing bug fixes disclosed: `current_valuation`→`current_value`, `annual_rental_income` column-mismatch drop). | PR 5 read consumers (sub-clustered ~21 files), PR 6 derived columns + snapshot table, PR 7 tier-cap test, PR 8 lock-down + audit + `PropertyStore.md` + `PropertyThreeIngestParityTest` (incl. `tenants_in_common` case). Per §16.1. |
| 5 | Liabilities (incl. mortgages) | not yet written | pending — §15.2 forbids more than one entity in flight at a time | per §16.1 |
| 6 | Investments | not yet written | pending | per §16.1 |
| 7 | Income + Expenditure | not yet written | pending | per §16.1 |
| 8 | Protection | not yet written | pending | per §16.1 |
| 9 | Family members | not yet written | pending | per §16.1 |
| 10 | Goals + life events | not yet written | pending | per §16.1 |
| 11 | Chattels | not yet written | pending — sweep `current_valuation`→`current_value` fix in `MigrateEstateToNetWorth::migrateChattel:223` during this pass | per §16.1 |
| 12 | Business interests | not yet written | pending — sweep `current_valuation`→`current_value` fix in `MigrateEstateToNetWorth::migrateBusiness:201` during this pass | per §16.1 |
| 13 | Trusts | not yet written | pending | per §16.1 |
| 14 | Wills + LPAs | not yet written | pending | per §16.1 |

### 21.2 Contract status

**APPROVED as a living contract.** The spec is the authoritative description of the canonical store/retrieve pattern. Per-entity `app/Services/Stores/{Entity}Store.md` docs are the source of truth for each shipped store's exact API surface, allowlist, and migration history.

When implementation evolves the contract (e.g. extending `forUser` with joint-owner eager-loading, splitting the original `recalculateDerived/snapshotIfPolicySays/emitEvent` triple into a single recalc + inline `event()` dispatch, adding `updateOrCreate` for seeder-idempotency callers as in Pass 4 PR 4), this spec is updated to match — not the other way round. The contract follows what consumers actually need; what consumers don't need does not get built.

### 21.3 Living progress log

| Date | Update |
|------|--------|
| 2026-05-14 | Spec approved; all 7 open questions resolved. |
| 2026-05-23 (approx) | Pass 1 (Savings) DONE — 8 PRs + parity test. Pattern established. |
| 2026-05-24 (approx) | Pass 2 (Reference data R1–R4) DONE — 26 PRs, B2 closed, admin UI live. |
| 2026-05-26 | Pass 3 (Pensions) DONE — 8 PRs + close-out PR #385 (`PensionStore.md` + `PensionThreeIngestParityTest`). Multi-table cross-record pattern proven. |
| 2026-05-26 | Pass 4 (Properties) plan written (`docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md`, 2743 lines). PR 1 #387 + PR 2 #388 + PR 3 #389 merged same day via subagent-driven-development workflow. |
| 2026-05-27 | Pass 4 PR 4 #390 merged (`df357e9`). 4/8 PRs done. Outstanding for Pass 4: PR 5 (read consumers, sub-clustered ~21 files), PR 6 (derived columns + snapshot table), PR 7 (tier-cap test), PR 8 (lock-down + audit + Store.md + parity). Two cross-pass debts surfaced: `current_valuation`→`current_value` sibling bug in `MigrateEstateToNetWorth` chattel/business branches (route through Pass 11/12). |

---

*End of design document.*
