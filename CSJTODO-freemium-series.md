# Fynla Major-Overhaul Series — Spec & Plan Campaign

**Branch:** `freemium` (off `origin/dev` @ 9de1d04, upstream unset)
**Goal:** Produce a separate spec + implementation plan for **each** of sub-projects 2→6.
Work them sequentially; do not stop until all five spec+plan pairs exist and have passed
their review gates. CSJ instruction 2026-05-16: "full sp2, sp3, sp4, sp5, sp6 spec and
plans … continue to each one until we are finished with them all".

**Foundation doc (already approved):** `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md` (SP1).
SP1 pass-1 plan: `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md`.

## Series status

| SP | Title | Spec | Plan | State |
|----|-------|------|------|-------|
| 2 | Freemium tier model + count caps + Fyn metering | ☑ | ☐ | spec written — awaiting CSJ review gate |
| 3 | Mobile-first iframe-framed `/m/*` shell | ☐ | ☐ | not started |
| 4 | Campaign engine (Save-Tax landing pages) | ☐ | ☐ | not started |
| 5 | Track-lightweight onboarding | ☐ | ☐ | not started |
| 6 | Gamification (campaign progress + unlocks) | ☐ | ☐ | not started |

Spec path convention: `docs/superpowers/specs/2026-05-16-sub-project-N-<slug>-design.md`
Plan path convention: `docs/superpowers/plans/2026-05-16-sub-project-N-<slug>-plan.md`

## Per-sub-project loop (brainstorming skill)

explore context → clarify open decisions → propose approaches → present design
→ write spec → self-review → CSJ review gate → writing-plans → next SP.

## SP2 working notes — existing infrastructure found

- **TierGate seam shipped by SP1:** `app/Services/Stores/TierGate.php` (iface:
  `canCreate/softLimit/hardLimit`), `PermissiveTierGate` (bound globally now —
  unlimited), `StaticTierGate` (interim, hardcoded `savings_account` free=3, NOT
  bound — `users.tier` does not exist). `SavingsStore` already calls the gate.
  SP2 must supply the DB-backed impl + the full entity×tier matrix + bind it.
- **Billing exists:** `Subscription`, `SubscriptionPlan`, `PlanConfiguration`,
  `PlanActionFundingSelection`; Revolut (`RevolutSubscriptionService`,
  `SubscriptionRenewalService`, `RevolutService`, `SyncRevolutPlans`,
  `CheckOverdueSubscriptions`); `CheckSubscription` middleware;
  `subscriptions.data_retention_starts_at`; discount codes; launch prices.
- **AI metering exists but conflicts with SP1 spec §17:** `HasAiGuardrails` +
  `ai_daily_usage` enforce **daily HARD token caps per plan** (preview 100k /
  trial 1M / student 300k / standard 1M / family 1.5M / pro 2M). Canonical spec
  wants **WEEKLY SOFT-DEGRADE**. Reconcile in SP2 brainstorm.
- **Nomenclature gap:** spec = `free/tier1/tier2/tier3`; code = named *plans*
  (`free/student/standard/pro/family` + `trial`/`preview`), `users.plan` enum,
  no `users.tier`. tier↔plan mapping is a core SP2 decision.
- SP1 spec hooks SP2 must fill: §5.4 count caps, §6.3 doc-retention quotas (GB),
  §9.2 currency-display gating, §10.3 snapshot surfacing windows
  (free 90 / tier1 365 / tier2 1825 / tier3 2555 days), §13 TierGate impl.
- Parked: PR #317 (dev→main release) is gated on SP2 landing on dev.

## Decisions log (append as CSJ answers)

- 2026-05-16: Scope = full separate spec+plan for SP2..SP6, sequential, run to completion.
- 2026-05-16 SP2 Q1: Tier model = **rename plans to literal tiers** (free/tier1/tier2/tier3).
- 2026-05-16 SP2 Q2: Product ladder = **Free + 3 paid** (spec shape); reconcile legacy slugs.
- 2026-05-16 SP2 Q3: Fyn metering = **weekly soft-degrade + daily hard backstop**.
- 2026-05-16 SP2 Q4: Caps = **grandfather; block only NEW creates** over cap, upgrade prompt.
- Current paid plans (pence/mo, launch): student 499/399, standard 1499/1099,
  family 2199/1499, pro 2999/1999. 7-day trial any plan. Gating today = module
  access via SubscriptionPlan.features JSON. `free` in enum, no plan row.
- PlanConfiguration = financial-calc defaults (growth/withdrawal), NOT the
  freemium matrix — do not overload it. Freemium matrix needs its own home.
- 2026-05-16 SP2 arch: Approach A — dedicated admin-editable tier_configurations
  reference-data store + DbTierGate (replaces PermissiveTierGate). Recommended.
- 2026-05-16 SP2 Section 1: family merged (whiteboard has 4 cols only);
  Free module set defined by whiteboard photo (transcribed below).
- 2026-05-16 SP2 NEW requirement (CSJ): single admin pricing/discount screen
  that propagates to billing + invoices + Revolut + public PricingPage.vue
  (one source of truth). Discounts included.

### SP2 tier matrix — transcribed from CSJ whiteboard 2026-05-16 (cols: FREE | T1 | T2 | T3)

| Capability | Free | Tier1 | Tier2 | Tier3 |
|---|---|---|---|---|
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Letter to Spouse | – | ✓ | ✓ | ✓ |
| Documents — uploads | LIMITED | LTD+1 | LTD+2 | LTD+3 |
| Documents — storage | – | –? | up to X GB | up to X GB+ |
| Bank accounts / cash | up to 3 | unlimited | unlimited + Open API | unlimited + Open API |
| Investments | up to 2 | unlimited | unlimited + API | unlimited + API |
| Investments — exotic | – | –? | ✓ | ✓ |
| Retirement — pension pots | up to 5 | unlimited | unlimited | unlimited |
| Retirement — decumulation | – | – | ✓ | ✓ |
| Goals + life events | ✓ | ✓ | ✓ | ✓ |
| Protection (send to IFA) | ✓ | ✓ | ✓ | ✓ |
| Property | ✓ | ✓ | ✓ | ✓ |
| Liabilities | ✓ | ✓ | ✓ | ✓ |
| Estate planning | £ add-on if wanted | £ add-on if wanted | ✓ | ✓ |
| Chattels / possessions | ✓ | ✓? | ✓ | ✓ |
| Income | ✓ | ✓ | ✓ | ✓ |
| Expenditure | ✓ | ✓ | ✓ | ✓ |
| Benefits (child) | ✓? | ✓ | ✓ | ✓ |
| Fyn agent (token budget) | 100K | 250K | 500K | 1M |
| Family module | ✓? | ✓ | ✓ | ✓ |

`?` cells = glare-obscured. Carried as flagged ASSUMPTIONS into the spec
(corrected at the spec review gate), not silently guessed.

- 2026-05-16 SP2 Section-2 decisions:
  - Estate Planning: **NOT an add-on**. Free/T1 = cheap IHT-exposure detector →
    one-line teaser nudge + upgrade CTA. T2/T3 = full Estate module (calcs +
    strategies). Generic "teaser-gate" pattern; Estate is its only user now.
    No à-la-carte / add-on billing anywhere in SP2.
  - Fyn budgets 100K/250K/500K/1M = **WEEKLY** token budgets. Over weekly →
    soft-degrade (cheaper model + terser + gentle notice). Daily hard cap kept
    as abuse backstop only. Weekly reset.
  - Open API / investment API = **flag + UI affordance only** in SP2. Real
    aggregation integration is a separate future sub-project, out of scope.
  - Pricing/discounts: **admin tier-config store is the single source of
    truth**. PricingPage + invoices read it live; a sync job pushes Revolut
    plan variations and updates stored revolut_plan_variation_id. Existing
    subscribers' price locked until their renewal.
- 2026-05-16 SP2 spec-review-gate correction (CSJ): the four freemium tiers
  are a **NEW product model** — NOT a relabel of legacy sub-plans
  (student/standard/family/pro), and there is **NO mechanical plan→tier
  map**. New tiers have their own exposures/surfaces/prices. Existing paid
  subscribers grandfathered (access + price) until renewal; conversion tier
  is a per-cohort CSJ decision (settled before the price-lock/Revolut PR).
  Prices are new, CSJ-set in the admin store, no legacy-price seed. Corrects
  former spec assumptions A8 (legacy-price seed) + A9 (plan→tier map) —
  both rewritten. **Household/spouse linking is never tier-gated:** every
  tier incl. Free can enter family + link spouse accounts when the user has
  a spouse; Family module = ✓ all tiers (closes assumption A5). Spec §5.1,
  §5.2, §7 firm rule, §16.2, §20, §22, §23 amended; committed.
