---
title: Freemium Tier Model + Count Caps + Fyn Agent Metering
date: 2026-05-16
sub_project: 2 of 6 (Fynla major-overhaul series)
status: APPROVED — design approved by CSJ 2026-05-16; A8/A9/A5 corrected at review gate; remaining §22 assumptions (A1–A4, A6, A7, A10) approved on defaults by CSJ 2026-05-16. Ready for implementation-plan pass.
author: Claude (Opus 4.7) + CSJ
related_specs: 2026-05-14-module-canonical-store-design (SP1 — foundation, APPROVED); (forthcoming) mobile-first-iframe-shell, campaign-engine, track-onboarding, gamification
---

# Freemium Tier Model + Count Caps + Fyn Agent Metering

## 0. Where this sits in the bigger picture

Fynla is undergoing a major overhaul covering six independent sub-projects:

| # | Sub-project | Status |
|---|-------------|--------|
| 1 | Module canonical store-and-retrieve contract | APPROVED — implementation in progress (Savings pass done) |
| **2** | **Freemium tier model + count caps + Fyn agent metering** *(this doc)* | design approved 2026-05-16 |
| 3 | Mobile-first surface via iframe-framed `/m/*` route | not started |
| 4 | Campaign engine (Save Tax landing pages, future campaigns) | not started |
| 5 | Track-lightweight onboarding (matched pension / spouse transfer / 60% trap) | not started |
| 6 | Gamification (campaign progress + incremental unlocks) | not started |

Sub-project 1 locked the data layer down and shipped the **hooks** sub-project 2 fills in: the `TierGate` interface (`app/Services/Stores/TierGate.php`), the bound-but-permissive `PermissiveTierGate`, the interim hardcoded `StaticTierGate` (not bound), `TierLimitExceededException`, and store-side `canCreate()` enforcement points. SP1 §5.4, §6.3, §9.2, §10.3 and §13 explicitly defer the *numbers* and the *gate implementation* to this sub-project.

This document is the design for sub-project 2 only. It turns the freemium model from "a permissive stub that lets everything through" into "an admin-editable, DB-backed, defence-in-depth tier system that is the single source of truth for pricing, capability gating, count caps, and Fyn metering."

---

## 1. Context and motivation

### 1.1 The problem we're solving

Fynla has billing infrastructure (`Subscription`, `SubscriptionPlan`, `PlanConfiguration`, Revolut integration, `CheckSubscription` middleware) and a `users.plan` enum, but no coherent freemium model. Today:

- **Gating is scattered.** Module access is driven by `SubscriptionPlan.features` JSON; AI limits live in a hardcoded array inside the `HasAiGuardrails` trait; count caps don't exist (the bound `TierGate` is permissive — unlimited everything).
- **There is no single source of truth.** Prices live in Revolut + launch-price config + `SubscriptionPlan`; capability gating lives in JSON; AI caps live in code. Changing a price or a limit means editing three places and hoping they agree. There is no admin screen that drives all of them.
- **Nomenclature is incoherent.** The canonical spec series talks in tiers (`free / tier1 / tier2 / tier3`); the code talks in named *plans* (`free / student / standard / family / pro`) plus `trial` and `preview`. Nothing maps tier ↔ plan.
- **AI metering contradicts the canonical model.** `HasAiGuardrails::DAILY_TOKEN_LIMITS` enforces **daily hard token caps per plan** (preview 100k / trial 1M / student 300k / standard 1M / family 1.5M / pro 2M). The canonical model wants **weekly soft-degrade** with the daily cap demoted to an abuse backstop.

### 1.2 The drift this causes

A user on `free` today has unlimited everything — there is no freemium product. There is no upgrade pressure, no monetisable ceiling, and no admin control surface. Meanwhile the SP1 store layer is *ready* to enforce caps (every store calls `tierGate->canCreate()`), but the bound gate says yes to everything. The product is shipping the plumbing for a paywall with the paywall switched off.

### 1.3 The trigger to act now

SP1 shipped the enforcement seam. Every entity store already consults `TierGate`. The cost of leaving the gate permissive is that the whole freemium business model is inert. SP2 makes the gate real, gives CSJ one admin screen to run pricing and capability gating from, and reconciles the AI metering model with the canonical weekly-soft-degrade design — without breaking existing paying subscribers.

---

## 2. Goals and non-goals

### 2.1 Goals

- **One tier model.** Four tiers — `free`, `tier1`, `tier2`, `tier3` — are the canonical vocabulary. Legacy plan slugs (`student / standard / family / pro / trial / preview`) are reconciled onto tiers, not carried forward as a parallel system.
- **One admin-editable source of truth.** A dedicated `tier_configurations` reference-data store (SP1 Approach A, §12 pattern) holds every tier's prices, capability matrix, count caps, document quotas, Fyn token budgets, currency-display flag, and snapshot-surfacing window. The admin screen edits this store; `PricingPage.vue`, invoices, billing, and the Revolut sync job all read it live.
- **Defence-in-depth count caps.** `DbTierGate` replaces `PermissiveTierGate` as the bound implementation. Every SP1 store already calls it; SP2 makes it return real numbers per tier per entity. UI hides the "add" affordance at the cap; the store refusal is the second line.
- **Grandfathering.** Raising or introducing a cap never deletes or hides a user's existing rows. Over-cap users keep what they have; only *new* creates beyond the cap are blocked, with an upgrade prompt.
- **Weekly Fyn soft-degrade with a daily hard backstop.** Per-tier weekly token budgets (Free 100K / Tier1 250K / Tier2 500K / Tier3 1M). Over weekly budget → graceful soft-degrade (cheaper model + terser system prompt + a gentle in-chat notice), never a hard wall. The existing daily hard cap is retained only as an abuse backstop. Weekly reset.
- **Estate as a teaser-gate, not an add-on.** Free/Tier1 see a cheap IHT-exposure detector that produces a one-line teaser nudge + upgrade CTA. Tier2/Tier3 get the full Estate module. The mechanism is a generic reusable "teaser-gate" pattern; Estate is its first and only user in SP2. No à-la-carte / add-on billing anywhere.
- **No regression for paying subscribers.** Existing subscribers' price is locked until their next renewal; their access does not narrow on migration.

### 2.2 Non-goals

- **Not building Open Banking / investment-feed aggregation.** Tiers 2/3 show an "unlimited + Open API" affordance and a feature flag, but the real aggregation integration is a separate future sub-project. SP2 ships the flag and the UI affordance only.
- **Not redesigning billing or Revolut integration.** SP2 makes `tier_configurations` the source the existing billing + Revolut sync read from. It does not rebuild `RevolutSubscriptionService`, `SubscriptionRenewalService`, or the subscription lifecycle.
- **Not overloading `PlanConfiguration`.** `PlanConfiguration` is financial-calc defaults (growth/withdrawal assumptions). The freemium matrix gets its own `tier_configurations` home. These do not merge.
- **Not à-la-carte add-on billing.** Rejected in brainstorming. Estate is teaser-gated, not sold as an add-on. There is no per-feature purchase path.
- **Not the mobile shell, campaigns, onboarding tracks, or gamification.** Those are SP3–SP6. SP2's tier model is consumed by them; it does not implement them.
- **Not changing the SP1 store contract.** SP2 fills SP1's deferred numbers; it does not alter the store API, the ingest paths, or the snapshot architecture.

### 2.3 Definition of done — sub-project level

SP2 is complete when all of the following hold:

1. `users.tier` exists and every user (including legacy plan-holders, trial, preview) resolves to exactly one of `free / tier1 / tier2 / tier3`.
2. `tier_configurations` reference-data store exists, follows the SP1 §12 store pattern (admin-write only, audited, cached, emits `ReferenceDataUpdated`), and is the single source of truth for prices, capability matrix, count caps, doc quotas, Fyn budgets, currency-display flag, and snapshot window.
3. `DbTierGate` is the bound `TierGate` implementation; `PermissiveTierGate` is unbound; `StaticTierGate` is deleted. Every SP1 store enforces real per-tier caps with grandfathering.
4. The admin tier-config screen edits the store and its changes propagate verifiably to `PricingPage.vue`, invoices, the `CheckSubscription` access path, and the Revolut plan-variation sync.
5. Fyn metering is weekly soft-degrade + daily hard backstop, reading per-tier weekly budgets from the store. The legacy per-plan daily-cap array is removed from `HasAiGuardrails` (or repointed at the store as the backstop).
6. The generic teaser-gate pattern exists and Estate uses it: Free/Tier1 → IHT-exposure teaser + upgrade CTA; Tier2/Tier3 → full module.
7. Migration is non-destructive: no existing subscriber loses data or access; existing prices are locked until renewal; over-cap users are grandfathered.
8. Every gating path is browser-tested on csjones per the Fynla browser-testing law before merge to main.

---

## 3. Scope

### 3.1 In scope

| Area | What SP2 delivers |
|------|-------------------|
| Tier vocabulary | `users.tier` column; tier ↔ legacy-plan reconciliation map; tier resolution for trial/preview/admin |
| `tier_configurations` store | SP1 §12-pattern reference-data store; admin-write; audited; cached; the single source of truth |
| Capability matrix | The transcribed whiteboard matrix (§7) materialised in the store and enforced |
| Count caps | `DbTierGate` (bound), per-tier per-entity caps, grandfathering, upgrade-prompt surface |
| Fyn metering | Weekly per-tier token budget + soft-degrade behaviour + daily hard backstop |
| Teaser-gate | Generic reusable gate; Estate is its only consumer in SP2 |
| Document allowance | Per-tier rolling upload-count ladder + per-tier storage GB quota |
| Currency display gating | Confirm the SP1 §9.2 free/tier1 = GBP-only, tier2/tier3 = chosen-display mapping in the store |
| Snapshot surfacing window | Confirm the SP1 §10.3 per-tier window (free 90 / tier1 365 / tier2 1825 / tier3 2555 days) in the store |
| Open-API affordance | Feature flag + UI affordance on Tier2/Tier3 entities (no real integration) |
| Admin pricing/discount screen | One screen → store → propagates to PricingPage + invoices + billing + Revolut sync; includes discount codes |
| Migration | Non-destructive plan→tier migration; price-lock on existing subs; grandfather over-cap rows |

### 3.2 Out of scope for this sub-project

- Open Banking / investment-feed real aggregation (flag + affordance only).
- Mobile `/m/*` shell (SP3) — it consumes the tier model unchanged.
- Campaign engine (SP4), track onboarding (SP5), gamification (SP6).
- Rebuilding billing, Revolut services, subscription lifecycle, or `PlanConfiguration`.
- À-la-carte / add-on purchase flows (explicitly rejected).
- Changes to the SP1 store contract, ingest paths, or snapshot architecture.

---

## 4. Architectural principles

The five rules below are load-bearing. If a later decision contradicts one, the rule wins.

### 4.1 One store is the single source of truth.

`tier_configurations` is the only place tier prices, the capability matrix, count caps, document quotas, Fyn budgets, the currency-display flag, and the snapshot-surfacing window live. `PricingPage.vue`, invoices, the access-control path, `HasAiGuardrails`, the teaser-gate, and the Revolut sync read it. Nothing hardcodes a tier number anywhere else. This is the SP1 §12 reference-data pattern applied to freemium config.

### 4.2 Tiers are the vocabulary; plans are a legacy detail.

Code, config, prompts, and UI speak `free / tier1 / tier2 / tier3`. Legacy plan slugs survive only inside a reconciliation map used by migration and Revolut compatibility. No new code branches on `plan`.

### 4.3 Defence in depth, never UI-only.

Every gate has two layers: the UI hides/teasers the affordance, and the backend (store `canCreate`, teaser-gate, metering) independently refuses. A user who bypasses the UI hits the same wall. SP1 already wired the store-side seam; SP2 makes it return real answers.

### 4.4 Grandfather, never confiscate.

Introducing or lowering a cap never deletes, hides, or downgrades a user's existing data. Over-cap users keep everything and are blocked only from *new* creates beyond the cap, with an upgrade prompt. Existing subscribers' prices are locked until renewal.

### 4.5 Soft-degrade, don't hard-wall, the user-facing AI.

The weekly Fyn budget produces graceful degradation (cheaper model, terser prompt, gentle notice), not a locked chat. A hard stop exists only as an abuse backstop (the demoted daily cap), not as the normal monetisation lever.

---

## 5. The tier model

### 5.1 Canonical tiers

| Tier | Slug | Role |
|------|------|------|
| Free | `free` | Acquisition tier. Capped counts, GBP-only, teaser-gated Estate, smallest Fyn budget. |
| Tier 1 | `tier1` | Entry paid. Unlimited core counts, still GBP-only, Estate still teaser-gated. |
| Tier 2 | `tier2` | Full product. Estate module, decumulation, exotic investments, display-currency, Open-API affordance. |
| Tier 3 | `tier3` | Top. Everything Tier 2 has plus the widest doc/storage/Fyn/snapshot allowances. |

The whiteboard had **four columns only** (Free | T1 | T2 | T3). These four tiers are a **new product model** — they are **not** a relabel of, and do **not** map by equivalence to, the legacy sub-plans (`student / standard / family / pro`). Each new tier has its own exposures, surfaces, caps, and price, defined **solely** by the capability matrix (§7) + the `tier_configurations` admin store (§6). The legacy plans' value ladder carries no meaning in the new model.

### 5.2 Legacy plans are NOT mapped — existing subscribers are grandfathered

`users.plan` is currently `enum('free','student','standard','family','pro')` (default `free`), plus the runtime states `trial` and `preview`. SP2 adds `users.tier` as the new authoritative gating column. **There is no mechanical plan→tier equivalence map** (CSJ 2026-05-16: the freemium tiers are new with different exposures/surfaces; the old sub-tiers do not correspond to them).

| Legacy plan / state | New-model treatment |
|---------------------|---------------------|
| `free` (no subscription) | `users.tier = free` directly — the only clean correspondence (no paid value to preserve). |
| Any paid legacy plan (`student / standard / family / pro`) | **Grandfathered.** The subscriber keeps their current access surface **and** current price (principle 4.4) until their next renewal. Their `users.tier` is set so gating never *narrows* their existing access on migration. At renewal/conversion they move into the new tier model; **which new tier each legacy cohort lands in is a per-cohort CSJ decision (§22 A9), not a mechanical map.** |
| `trial` | Trial of a *new tier* resolves to that tier for its 7-day duration, then falls back to `free` on expiry unless converted. (Trials of legacy plans expire normally and the account follows the grandfather rule above.) |
| `preview` | `free`-equivalent for gating; `PreviewWriteInterceptor` still governs writes (SP1 §8.4 unchanged). |
| admin / impersonation | Resolves to the impersonated user's tier; admin's own account is unbounded by an `is_admin` bypass in `DbTierGate` (SP1 allowlist). |

`users.tier` is authoritative going forward. `users.plan` is retained read-only for Revolut/billing compatibility during the grandfather window and is not branched on by new code. New signups only ever see the four new tiers.

### 5.3 Tier resolution

A single `TierResolver` (consumed by `DbTierGate`, `HasAiGuardrails`, the teaser-gate, and read-model builders) resolves any `User` to a canonical tier, applying: explicit `users.tier` → active-trial override → preview/admin rules → `free` default. SP1's `StaticTierGate::resolveTier()` (`$user->tier ?? 'free'`) is the seed of this; SP2 promotes it to the real resolver and deletes `StaticTierGate`.

---

## 6. The `tier_configurations` reference-data store

### 6.1 Pattern

`tier_configurations` is a reference-data entity following the SP1 §12 store pattern exactly:

- `App\Services\Stores\TierConfigurationStore` — read-heavy, admin-write only (`IngestSource::ADMIN`, `IngestSource::SEEDER` for bootstrap).
- Per-request memoised cache; admin write invalidates and emits `ReferenceDataUpdated` (SP1 §12.2) so consumers drop their caches.
- Every write audited via the `Auditable` pipeline with `actor_user_id`.
- Pest architecture test: nothing outside the store mutates the table (SP1 §14 boundary).

### 6.2 Shape

One row per tier (`free / tier1 / tier2 / tier3`), each row carrying the full configuration as structured columns + JSON where the shape is matrix-like:

```
tier_configurations
  id, tier (unique: free|tier1|tier2|tier3),
  display_name,                       -- "Free", "Tier 1", …
  price_monthly_pence, price_annual_pence,
  revolut_plan_variation_id,          -- written back by the sync job
  capability_matrix (json),           -- entity_key => access verb (✓ / ✗ / LIMITED / teaser)
  count_caps (json),                  -- entity_key => int|null (null = unlimited)
  document_upload_allowance (int),    -- rolling upload count
  document_storage_gb (decimal|null), -- null = none
  fyn_weekly_token_budget (int),
  fyn_daily_hard_backstop (int),
  currency_display_mode (enum: gbp_only | user_choice),
  snapshot_surfacing_window_days (int),
  open_api_affordance (bool),
  is_active (bool), updated_at, updated_by
```

`capability_matrix` and `count_caps` are JSON because they are sparse entity-keyed maps; everything billing/metering-numeric is a typed column so the admin form and invoices bind cleanly.

### 6.3 Why a dedicated store (Approach A)

Brainstorming rejected: storing the matrix in `SubscriptionPlan.features` JSON (overloads billing, no audit, no admin form), hardcoding (the current pain), and à-la-carte add-on billing. **Approach A** — a dedicated admin-editable reference-data store with `DbTierGate` reading it — was chosen for parity with SP1 §12 and because it gives CSJ one screen that demonstrably drives every downstream consumer. Do not re-litigate.

---

## 7. The capability matrix

Transcribed verbatim from CSJ's whiteboard photo, 2026-05-16. Columns: **Free | Tier1 | Tier2 | Tier3**. Cells marked `?` were glare-obscured in the photo and are carried as **flagged assumptions** (§22) — proceed with the stated default, confirm at the review gate.

| Capability | Free | Tier1 | Tier2 | Tier3 |
|---|---|---|---|---|
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Letter to Spouse | ✗ | ✓ | ✓ | ✓ |
| Documents — uploads | LIMITED | LIMITED+1 | LIMITED+2 | LIMITED+3 |
| Documents — storage | none | none *(assume ✗)* | up to X GB | up to X GB+ |
| Bank accounts / cash | up to 3 | unlimited | unlimited + Open API | unlimited + Open API |
| Investments | up to 2 | unlimited | unlimited + API | unlimited + API |
| Investments — exotic | ✗ | ✗ *(assume ✗)* | ✓ | ✓ |
| Retirement — pension pots | up to 5 | unlimited | unlimited | unlimited |
| Retirement — decumulation | ✗ | ✗ | ✓ | ✓ |
| Goals + life events | ✓ | ✓ | ✓ | ✓ |
| Protection (send to IFA) | ✓ | ✓ | ✓ | ✓ |
| Property | ✓ | ✓ | ✓ | ✓ |
| Liabilities | ✓ | ✓ | ✓ | ✓ |
| Estate planning | teaser-gate | teaser-gate | ✓ | ✓ |
| Chattels / possessions | ✓ | ✓ *(assume ✓)* | ✓ | ✓ |
| Income | ✓ | ✓ | ✓ | ✓ |
| Expenditure | ✓ | ✓ | ✓ | ✓ |
| Benefits (child) | ✓ *(assume ✓)* | ✓ | ✓ | ✓ |
| Fyn agent (weekly token budget) | 100K | 250K | 500K | 1M |
| Family module | ✓ | ✓ | ✓ | ✓ |

Access verbs in `capability_matrix` JSON: `full` (✓), `none` (✗), `limited` (count-capped — caps in `count_caps`), `teaser` (teaser-gate, §10). "+ Open API" / "+ API" sets `open_api_affordance = true` for that tier (§14). "unlimited" = `count_caps[entity] = null`.

**Firm rule — household/spouse linking is never tier-gated (CSJ 2026-05-16).** Every tier, **including Free**, can enter family members and link a spouse and the spouse's accounts whenever the user has a spouse. The Family module is `✓` at all tiers (no longer an assumption). Spouse/joint-owned records use Fynla's single-record joint-ownership pattern (CLAUDE.md Rule #7); a joint record counts **once** against the owning user's entity count cap (it is not double-counted against the spouse), so household linking does not silently consume a Free user's cap twice.

---

## 8. Count caps + grandfathering — `DbTierGate`

### 8.1 `DbTierGate`

`DbTierGate implements TierGate` replaces `PermissiveTierGate` as the globally bound implementation in `AppServiceProvider` (currently bound at `AppServiceProvider.php:60-63`). It reads `count_caps` from `TierConfigurationStore` for the user's resolved tier:

```php
public function canCreate(User $user, string $entityKey, int $currentCount): bool
{
    if ($user->is_admin) return true;                       // admin bypass (SP1 allowlist)
    $hard = $this->hardLimit($user, $entityKey);            // null = unlimited
    if ($hard === null) return true;
    return $currentCount < $hard;                           // strictly under cap
}

public function hardLimit(User $user, string $entityKey): ?int
{
    return $this->store->capFor($this->tierResolver->resolve($user), $entityKey); // int|null
}

public function softLimit(User $user, string $entityKey): ?int
{
    return $this->hardLimit($user, $entityKey); // soft == hard for count caps; the
    // "soft" concept is reserved for Fyn metering (§9), not row counts
}
```

Initial caps from the matrix (§7): `savings_account` free 3 / tier1+ null; `investment` free 2 / tier1+ null; `pension_account` free 5 / tier1+ null. Every other entity is `null` (unlimited) at every tier per the matrix.

### 8.2 Grandfathering (principle 4.4)

`canCreate` only ever blocks a **new** create when `currentCount >= hardLimit`. It never inspects, deletes, or hides existing rows. A `free` user who already has 7 pension pots (created before the cap, or after a downgrade) keeps all 7; they cannot create an 8th until they upgrade. The SP1 store throws `TierLimitExceededException` (`app/Services/Stores/Exceptions/TierLimitExceededException.php`); SP2 ensures the exception carries the entity key, the user's tier, the cap, and the upgrade-target tier so the UI can render a precise upgrade prompt.

### 8.3 Upgrade-prompt surface

When the store throws `TierLimitExceededException`, the API returns a structured 4xx the frontend renders as an inline upgrade CTA (not a generic error). The "add" affordance is hidden in the UI when `currentCount >= hardLimit` (defence-in-depth principle 4.3); the store refusal is the second line for anyone who bypasses the UI. The CTA's copy and target tier come from `tier_configurations` (single source of truth).

---

## 9. Fyn agent metering — weekly soft-degrade + daily hard backstop

### 9.1 The change from today

`HasAiGuardrails::DAILY_TOKEN_LIMITS` currently enforces **daily hard caps per legacy plan** (`preview 100_000, trial 1_000_000, student 300_000, standard 1_000_000, family 1_500_000, pro 2_000_000`). The canonical model (SP1 §17 hook) wants **weekly soft-degrade per tier**.

### 9.2 New model

| Tier | Weekly token budget (soft) | Daily hard backstop (abuse only) |
|------|----------------------------|----------------------------------|
| Free | 100K | retained, generous — abuse trip only |
| Tier1 | 250K | retained, generous |
| Tier2 | 500K | retained, generous |
| Tier3 | 1M | retained, generous |

Both numbers come from `tier_configurations` (`fyn_weekly_token_budget`, `fyn_daily_hard_backstop`). The weekly budget is the monetisation lever; the daily backstop only exists to stop a runaway loop or scripted abuse, set well above normal weekly-paced usage.

### 9.3 Soft-degrade behaviour

When a user's rolling 7-day token consumption exceeds `fyn_weekly_token_budget`:

1. **Cheaper model.** The in-flight `getAiModel()` selection drops to the cheaper model tier (the model-selection seam already exists in `HasAiGuardrails::getAiModel()`).
2. **Terser system prompt.** A degraded system-prompt variant (shorter, fewer tools, no long-form synthesis) is selected.
3. **Gentle notice.** A one-line, plain-text in-chat notice ("Fyn is running in a lighter mode this week — upgrade for full responses") — **no icon, no emoji, plain text** (CLAUDE.md Rule #16, Fyn chat is a banned surface).
4. **Never a wall.** The chat stays usable. Degradation is graceful and reverses automatically at the weekly reset.

The daily hard backstop, if hit, returns the existing guardrail refusal (abuse path only — not the normal experience).

### 9.4 Weekly reset

Rolling 7-day window (not calendar week) per user, tracked off the existing `ai_daily_usage` accounting (aggregated to 7 days) so no new heavy accounting table is needed. Reset is implicit (the trailing window slides); no cron needed for the reset itself, only for the existing daily accounting roll-up.

---

## 10. The teaser-gate pattern (Estate is its only SP2 consumer)

### 10.1 Generic mechanism

A reusable `TeaserGate` decides, for a teaser-gated capability, whether the user gets the **full module** or a **teaser**: a cheap computed signal + a one-line nudge + an upgrade CTA. It reads the capability verb (`teaser` vs `full`) from `tier_configurations`.

```php
$teaserGate->resolve($user, 'estate');
// → FULL  (tier2/tier3) : render the full Estate module
// → TEASER (free/tier1)  : run the cheap IHT-exposure detector, return
//                          { exposed: bool, headline: "Your estate may face £X IHT",
//                            cta: { label, target_tier } }
```

### 10.2 Estate behaviour

- **Free / Tier1:** the full Estate calculation engine and strategies are **not** run. A cheap IHT-exposure detector (NRB/RNRB threshold check against the user's already-canonical net-worth figure from SP1 stores — no heavy Estate calc) produces a single teaser line + upgrade CTA. Defence-in-depth: the Estate module routes/components are gated server-side too, not just hidden.
- **Tier2 / Tier3:** the full Estate module (calcs + strategies) as today.

Estate is **not an add-on**. There is no à-la-carte purchase. The only route to the full module is a Tier2+ subscription. The teaser-gate is written generically so SP4/SP6 can reuse it, but Estate is its only consumer in SP2 — do not build speculative consumers.

---

## 11. Document upload allowance + storage quota

### 11.1 Upload allowance ladder

`document_upload_allowance` is a **rolling upload count** (not lifetime, not per-day): the number of source documents a user may have ingested-and-retained at once, laddered LIMITED / +1 / +2 / +3 across tiers.

| Tier | Upload allowance | Storage quota |
|------|------------------|---------------|
| Free | LIMITED (proposed: 3) | none |
| Tier1 | LIMITED+1 (proposed: 4) | none *(assumption — §22)* |
| Tier2 | LIMITED+2 (proposed: 5) | up to X GB (proposed: 5 GB) |
| Tier3 | LIMITED+3 (proposed: 6) | up to X GB+ (proposed: 20 GB) |

The concrete LIMITED base, the increments, the GB numbers, and the per-tier £ prices are **proposed values flagged for confirmation at the spec-review gate** (§22). They live in `tier_configurations` so CSJ can tune them from the admin screen without a deploy.

### 11.2 Interaction with SP1 §6.3

SP1 §6.3 already defines: Free/Tier1 → extraction runs, data lands in stores, **original document deleted**; Tier2/Tier3 → original **retained** linked to the source entity up to the storage quota. SP2 supplies the quota numbers (`document_storage_gb`) and the rolling upload-count allowance (`document_upload_allowance`). The retention *mechanism* is SP1's; SP2 only fills the *numbers*.

---

## 12. Currency display gating

Confirms (does not redesign) SP1 §9.2. The store's `currency_display_mode` column is the single source of truth:

| Tier | `currency_display_mode` | Behaviour (SP1 §9.2) |
|------|-------------------------|----------------------|
| Free, Tier1 | `gbp_only` | DB keeps native currency; only `_gbp` is surfaced. |
| Tier2, Tier3 | `user_choice` | User picks a display currency; store derives `_display` columns at read time. `_gbp` always exists for cross-tier consumers. |

SP2 adds nothing to the conversion mechanism — it only ensures the tier→mode mapping is read from `tier_configurations` rather than hardcoded, so the line moves with the admin screen.

---

## 13. Snapshot surfacing window

Confirms (does not redesign) SP1 §10.3. `snapshot_surfacing_window_days` per tier is the single source of truth:

| Tier | Surfacing window |
|------|------------------|
| Free | 90 days |
| Tier1 | 365 days |
| Tier2 | 1825 days (5 years) |
| Tier3 | 2555 days (7 years = full retained history; no API gating at top tier) |

Retention is always 7 years for everyone (SP1 regulatory floor — unchanged). SP2 only ensures the *surfacing* window per tier is read from `tier_configurations`. Upgrading a tier instantly widens the visible window with no recompute (SP1 §10.3 guarantee preserved).

---

## 14. Open-API affordance (flag only)

Tier2/Tier3 rows set `open_api_affordance = true`. SP2 ships:

- A feature flag (`open_api_affordance`) read from `tier_configurations`.
- A UI affordance on Bank-accounts and Investments entities for Tier2/Tier3 ("Connect via Open Banking — coming soon" / a disabled-but-present connect surface).

SP2 ships **no real aggregation integration**. Open Banking / investment-feed ingestion is a separate future sub-project. The affordance exists so the tier value proposition is visible and the flag is wired for the future project to switch on.

---

## 15. Admin pricing/discount screen — single source of truth

### 15.1 Requirement (new, CSJ 2026-05-16)

One admin screen edits `tier_configurations` and its changes **propagate verifiably** to every downstream consumer:

```
                    ┌──────────────────────────┐
  Admin screen ────►│  TierConfigurationStore   │
  (tabs in          │  (audited, cached,        │
   AdminPanel)      │   ReferenceDataUpdated)   │
                    └────────────┬──────────────┘
                                 │ read live / on event
        ┌────────────┬───────────┼───────────────┬─────────────────┐
        ▼            ▼           ▼                ▼                 ▼
  PricingPage.vue  Invoices  CheckSubscription  HasAiGuardrails  Revolut sync job
  (public prices)  (line £)  (access gating)    (Fyn budgets)    (plan variations)
```

- **Discounts included.** Discount codes are edited on the same screen and read by billing live.
- **Revolut sync.** A sync job pushes tier prices to Revolut as plan variations and writes the resulting `revolut_plan_variation_id` back into the store row (the store stays the source of truth; Revolut is a downstream mirror).
- **Price-lock.** Existing subscribers' price is locked until their next renewal. A price change in the store affects new subscriptions and renewals only — never an in-flight billing period (principle 4.4).
- **Location.** Follows the existing admin-panel pattern (a tab/section of `AdminPanel.vue`), per SP1 §12.1 — not a new `/admin/*` namespace.

### 15.2 Propagation contract (acceptance-relevant)

Changing one value in the admin screen must demonstrably change: the public `PricingPage.vue` price; a freshly generated invoice line; the access decision in `CheckSubscription`; the Fyn weekly budget in `HasAiGuardrails`; and (on sync) the Revolut plan variation. This is browser-tested end-to-end on csjones before merge (§18).

---

## 16. Migration strategy

### 16.1 Non-destructive, reversible, no two states in flight

| PR | Title pattern | What it does | Risk |
|----|---------------|--------------|------|
| 1 | `feat(tier): tier_configurations store + seeder + admin-write + Pest boundary` | New table, store (SP1 §12 pattern), seeder loads the §7 matrix, architecture test. No consumers wired. | Very low — pure addition. |
| 2 | `feat(tier): users.tier column + TierResolver + grandfather backfill` | Add `users.tier`; `free`/preview → `free`; **paid legacy subscribers grandfathered** — `users.tier` set so gating never narrows their current access, `users.plan` + price retained until renewal (§5.2, no mechanical map); `TierResolver`. | Low — additive + non-narrowing backfill. |
| 3 | `feat(tier): DbTierGate replaces PermissiveTierGate; delete StaticTierGate` | Bind `DbTierGate`; grandfathering logic; `TierLimitExceededException` enriched. SP1 stores already call the seam. | Medium — caps go live; grandfather test mandatory. |
| 4 | `feat(tier): admin tier-config screen + propagation` | Admin tab; PricingPage + invoices + CheckSubscription read the store live. | Medium — broad read surface. |
| 5 | `feat(tier): Revolut plan-variation sync + price-lock` | Sync job; `revolut_plan_variation_id` write-back; existing-sub price-lock. | Medium — billing-adjacent; sandbox-tested on csjones first. |
| 6 | `feat(tier): Fyn weekly soft-degrade + daily backstop` | `HasAiGuardrails` reads weekly budget from store; soft-degrade path; daily cap demoted to backstop. | Medium — Fyn behaviour change; parity-tested. |
| 7 | `feat(tier): generic teaser-gate + Estate consumer` | `TeaserGate`; Estate Free/Tier1 → IHT teaser, Tier2/3 → full module; server-side route gating. | Medium — Estate access change. |
| 8 | `feat(tier): doc allowance + storage quota + currency/snapshot/open-API flags` | Wire `document_upload_allowance`, `document_storage_gb`, `currency_display_mode`, `snapshot_surfacing_window_days`, `open_api_affordance` to their SP1 consumers. | Low–medium — mostly reads from store. |
| 9 | `lock-down(tier): enable Pest architecture test + remove legacy hardcoded caps` | Remove `HasAiGuardrails::DAILY_TOKEN_LIMITS` legacy array; architecture test hard-fails any hardcoded tier number. | Low — by now everything reads the store. |

Each PR ships to `dev`, deploys to csjones, gets browser-tested, then the periodic `dev → main` release ships it (PR #317 remains parked until SP2 lands on dev — see memory `project_pr317_gated_on_freemium_refactor`). Standard Fynla flow.

### 16.2 Grandfathering on migration

There is **no mechanical plan→tier map** (§5.2). PR 2's backfill never deletes, hides, or downgrades rows or access. A legacy `free` power-user whose existing row count exceeds a new Free cap is grandfathered — existing rows untouched, only new over-cap creates blocked. A legacy *paid* subscriber keeps their current access surface **and** price until renewal; their `users.tier` is set so gating never narrows what they already have. The new tier each legacy paid cohort is offered at renewal/conversion is a per-cohort CSJ decision (§22 A9), settled before PR 5 (price-lock / Revolut sync) ships.

---

## 17. Boundary enforcement (Pest architecture tests)

Following SP1 §14, hard CI failure (no soft-warn ramp):

- `tier_configurations` is mutated only by `TierConfigurationStore` (+ SP1 allowlist: observers, console commands, migrations, seeders-via-store).
- No tier number, price, cap, Fyn budget, doc quota, currency-mode, or snapshot window is hardcoded outside the store/seeder — the test fails any literal tier constant in `app/` outside `App\Services\Stores\` and the seeder.
- `DbTierGate` is the bound `TierGate`; `PermissiveTierGate` unbound; `StaticTierGate` deleted (test asserts class absence).
- Every SP1 store still calls `tierGate->canCreate()` before persist (SP1 §16.1.7 carried forward).

---

## 18. Acceptance criteria

### 18.1 Per-PR

Each PR ships to dev, deploys to csjones, and is **browser-tested via Playwright** (Fynla browser-testing law): the relevant gate is exercised end-to-end (click/fill/submit/observe DB + UI), not merely code-reviewed.

### 18.2 Sub-project-wide

1. **One vocabulary.** `users.tier` authoritative; every user (incl. legacy/trial/preview/admin) resolves to one canonical tier; no new code branches on `plan`.
2. **One source of truth.** `tier_configurations` drives PricingPage, invoices, access gating, Fyn budgets, doc allowances, currency mode, snapshot window, Open-API flag — verified by changing one admin value and observing all five downstream effects on csjones.
3. **Caps + grandfather.** `DbTierGate` bound; `StaticTierGate` deleted; an over-cap user keeps all existing rows and is blocked only from new over-cap creates with a precise upgrade CTA; cap numbers come from the store.
4. **Fyn metering.** Exceeding the weekly budget soft-degrades (cheaper model + terser prompt + plain-text notice, chat still works); daily backstop only trips on abuse; legacy hardcoded daily array removed.
5. **Estate teaser-gate.** Free/Tier1 see the IHT-exposure teaser + upgrade CTA and cannot reach the full Estate module server-side; Tier2/Tier3 get the full module; no add-on purchase path exists anywhere.
6. **Non-destructive migration.** No subscriber loses data or access; existing prices locked until renewal; backfill reversible.
7. **Open-API affordance.** Visible on Tier2/Tier3 bank/investment entities; no real aggregation integration shipped.
8. **Boundary green.** Pest architecture suite green; no hardcoded tier numbers remain.

---

## 19. Out of scope (explicit)

- Open Banking / investment-feed real aggregation (flag + affordance only — §14).
- Mobile `/m/*` shell, campaign engine, track onboarding, gamification (SP3–SP6).
- Rebuilding billing / Revolut services / subscription lifecycle / `PlanConfiguration`.
- À-la-carte / add-on purchase flows (rejected — Estate is teaser-gated).
- Changes to the SP1 store contract, ingest paths, or snapshot architecture.
- New currency-conversion or snapshot mechanics (SP2 only supplies the per-tier *numbers*; mechanics are SP1's).
- A standalone `/admin/*` namespace (admin screen is a tab in the existing `AdminPanel.vue`).

---

## 20. Risks and mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| A grandfathered paid subscriber's access or price narrows on migration | Medium | High | No mechanical plan→tier map; `users.tier` set non-narrowing; access + price held until renewal (§5.2, §16.2); conversion tier is a per-cohort CSJ decision before PR 5; backfill reversible. |
| Caps go live and an existing power-user loses access | Low | High | Principle 4.4 — grandfather: existing rows never blocked, only new over-cap creates; explicit test in PR 3. |
| Revolut sync drifts from the store | Medium | High | Store is source of truth; sync writes `revolut_plan_variation_id` back; sandbox-tested on csjones (REVOLUT_SANDBOX=true) before main. |
| Fyn soft-degrade feels like a downgrade users didn't expect | Medium | Medium | Plain-text in-chat notice + upgrade CTA; chat never walls; auto-reverses weekly; copy reviewed with CSJ. |
| Glare-obscured matrix cells guessed wrong | Medium | Medium | Carried as flagged assumptions (§22), surfaced at the review gate before any code. |
| Hardcoded tier numbers leak back in over time | Medium | Medium | Pest architecture test fails any literal tier constant outside the store/seeder (§17). |
| Admin screen change has an invisible downstream miss | Medium | High | Acceptance §18.2.2 requires demonstrating all five downstream effects from one admin edit on csjones. |

---

## 21. Dependencies on other sub-projects

| Relationship | Detail |
|--------------|--------|
| **Consumes SP1** | `TierGate` seam, `TierLimitExceededException`, store `canCreate` calls, §9.2 currency mechanics, §10.3 snapshot mechanics, §12 reference-data store pattern, §6.3 doc-retention mechanism. SP2 fills SP1's deferred numbers. |
| **Unblocks SP3 (mobile)** | Mobile consumes the same tier model unchanged — no mobile-specific tier logic. |
| **Unblocks SP4 (campaigns)** | Campaign engine can reuse the generic teaser-gate and read tier config. |
| **Unblocks SP5 (track onboarding)** | Lightweight onboarding assigns a starting tier (default `free`) through the same model. |
| **Unblocks SP6 (gamification)** | Incremental unlocks can be expressed as tier-config / teaser-gate changes; gamification reads the same store. |
| **Gates release** | PR #317 (dev→main release) stays parked until SP2 lands on dev (memory `project_pr317_gated_on_freemium_refactor`). |

---

## 22. Flagged assumptions — confirm at the spec-review gate

These are **not blockers**. Proceed with the stated default; CSJ corrects at the written-spec review gate (before `writing-plans`).

| # | Item | Default assumed | Why flagged |
|---|------|-----------------|-------------|
| A1 | Tier1 — Investments (exotic) | ✗ (not available) | Glare-obscured `?` on whiteboard. |
| A2 | Tier1 — Chattels / possessions | ✓ (available) | Glare-obscured `?`. |
| A3 | Tier1 — Documents storage | none (✗) | Glare-obscured `–?`. |
| A4 | Free — Benefits (child) | ✓ (available) | Glare-obscured `?`. |
| A5 | Free — Family module | **RESOLVED 2026-05-16: ✓ at all tiers (firm, no longer an assumption).** Household/spouse linking is never tier-gated — see §7 firm rule. | CSJ confirmed; closed. |
| A6 | Document upload allowance base + ladder | LIMITED=3, then +1/+2/+3 → 3/4/5/6 | Whiteboard gave the ladder shape, not the base number. |
| A7 | Per-tier storage GB | Tier2 = 5 GB, Tier3 = 20 GB; Free/Tier1 = none | Whiteboard said "up to X GB" / "X GB+" — numbers not given. |
| A8 | Per-tier monthly/annual £ price | **No legacy-price seed.** The four tiers are a new product with new prices that **only CSJ sets**, entered in the `tier_configurations` admin store. Seeder ships placeholder prices (e.g. tier1 £4.99/mo, tier2 £14.99/mo, tier3 £29.99/mo) purely so the screen renders; these are not proposals and carry no relation to legacy plan prices. | CSJ 2026-05-16: freemium tiers are new with different exposures/surfaces; legacy prices do not transfer. CSJ to set real prices. |
| A9 | Conversion tier for grandfathered legacy paid subscribers | **No mechanical plan→tier map** (CSJ 2026-05-16). Existing paid subscribers are grandfathered on current access + price until renewal (§5.2, §16.2). Which new tier each legacy paid cohort is offered/placed into at renewal/conversion is a **per-cohort CSJ decision**, made before PR 5 (the price-lock/Revolut PR) ships. Default until then: grandfather only — no automatic placement. | §5.2 — legacy sub-tiers do not correspond to the new tiers; CSJ decides conversion per cohort. |
| A10 | Fyn daily hard backstop value | Per tier, set generously above normal weekly-paced usage so it only trips on runaway/scripted abuse; exact value CSJ-set in `tier_configurations`. | Backstop is abuse-only; no legacy figure to anchor to (no plan mapping). |

A5 and A9 reflect CSJ corrections on 2026-05-16. All open A-items live in `tier_configurations` so post-confirmation tuning needs no code change.

---

## 23. Sign-off

10-section design approved by CSJ 2026-05-16 ("looks good"). Architecture = Approach A (dedicated admin-editable `tier_configurations` reference-data store + `DbTierGate` replacing `PermissiveTierGate`), matching SP1 §12. Rejected and not to be re-litigated: `SubscriptionPlan.features` JSON as matrix home; hardcoded config; à-la-carte add-on billing; building Open Banking in SP2; keeping 4 paid plans.

**CSJ review-gate corrections applied 2026-05-16:** the four freemium tiers are a **new product model** with their own exposures/surfaces/prices — they are **not** a relabel of, and carry **no mechanical map to**, the legacy sub-plans (corrects former A8/A9). Existing paid subscribers are grandfathered (access + price) until renewal; conversion tier is a per-cohort CSJ decision (§5.2, §16.2, §22 A9). Household/spouse linking is never tier-gated — the Family module is `✓` at every tier including Free (closes A5, §7 firm rule).

The remaining flagged assumptions in §22 (A1–A4, A6, A7, A10) are surfaced for confirmation at the written-spec review gate. On approval, the next step is `superpowers:writing-plans` to produce the SP2 implementation plan at `docs/superpowers/plans/2026-05-16-sub-project-2-freemium-tier-model-plan.md`. Subsequent sub-projects (SP3 mobile shell, SP4 campaign engine, SP5 track onboarding, SP6 gamification) each get their own brainstorm → spec → plan cycle per `CSJTODO-freemium-series.md`.

---

*End of design document.*
