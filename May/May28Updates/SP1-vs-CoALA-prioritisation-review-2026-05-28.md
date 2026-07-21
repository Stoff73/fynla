---
type: prioritisation-review
date: 2026-05-28
author: Claude (Opus 4.7, 1M) + CSJ
question: "Is SP1 (canonical store) worth continuing, or should we defer it to get to CoALA + testing + other work?"
verdict: DEFER SP1 after the current clean state (Pass 6 PR 5a). Pivot to test-stabilisation, then CoALA.
---

# SP1 (Canonical Store) vs CoALA — Prioritisation Review

## 0. Bottom line (read this first)

**Defer the rest of SP1 now. Pivot to CoALA — but stabilise the test suite first.**

Three findings drive this:

1. **SP1 is a 14-pass marathon and we are 6 passes in. We have already banked the high-value 80%.** The five entities that carry the bulk of a net-worth picture and the most Fyn-capture traffic — Savings, Pensions, Properties, Mortgages, Investments (write-path) — plus all four reference-data tables are done. The ~9 remaining entities (income, expenditure, protection, family, goals/life-events, business, trusts, chattels, wills, LPAs, unsecured liabilities) are lower-volume, lower-Fyn-traffic, and have diminishing marginal value per pass.

2. **CoALA does not depend on SP1.** I checked every CoALA phase PRD and the implementation plan. CoALA's dependencies are entirely (a) internal to CoALA (Phase 5 ← Phases 1-4) and (b) on Fyn infrastructure that **already exists today** (KYC/prerequisite gating, hash-chained audit log, conversation summariser, per-mode tool allowlists, `ai_messages`, the tool catalogues). There is **zero** reference to the SP1 entity stores (`InvestmentAccountStore`, `SavingsStore`, etc.) as a CoALA prerequisite. They operate on different layers: SP1 is the **user-data storage** layer; CoALA is **Fyn's reasoning/memory** layer.

3. **CoALA is fully planned and zero-implemented, with a stronger time-sensitivity than SP1's long tail.** Plan v0.4 + 6 engineering-ready phase PRDs + a stakeholder/investor brief all shipped 2026-05-26 (docs only). The drivers — FCA suitability auditability, AI cost scaling with users, faster product iteration without deploys — are board/regulator-facing and already biting. SP1's remaining passes are internal tech-quality with no external deadline.

**Recommended sequence:** (1) stabilise the test suite to green (small, high-value, and CoALA's regression strategy depends on a clean baseline), (2) start CoALA Phase 5's cost-telemetry PR + Phase 1 semantic memory (the brief's recommended foundation-first pairing), (3) keep the deferred SP1 work as a resumable backlog (CSJTODO already tracks the exact resume point: Pass 6 PR 5b).

This is **not** "SP1 was a mistake." SP1 delivered real, durable value. It is "the marginal next SP1 pass is now worth less than the first CoALA phase, and nothing forces us to finish SP1 before starting CoALA."

---

## 1. What SP1 actually is, and how far we've got

**Source:** `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`

SP1 ("Module Canonical Store-and-Retrieve Contract") is sub-project 1 of a 6-sub-project major overhaul. Its premise (spec §0): *"Sub-project 1 is the foundation: every other sub-project assumes the data layer is correct, consistent, and trustworthy. Until that foundation holds, gamification, recommendations, mobile, and tier-gating are building on sand."*

The headline problems it solves (spec §1.2):
- **B1 — Fyn AI gives wrong answers because the DB doesn't reflect the user's latest info** (data written where Fyn doesn't read, or in a shape Fyn's read tools don't recognise).
- **B2 — `tax_configurations` holds wrong values with no admin UI to fix them.**

It covers **15 user-data entities + 4 reference tables**, migrated one "pass" at a time. Each pass introduces a `Store` facade, routes every write path (HTTP / Fyn / upload / seeders) through it, locks the write boundary with a Pest architecture test, then routes reads and adds derived columns.

### Progress to date (as of 2026-05-28)

| Pass | Entity | Status | PRs |
|---|---|---|---|
| 1 | Savings | DONE | — |
| 2 | Reference data (R1-R4: tax config, currency, life tables, market rates) | DONE | 26 PRs |
| 3 | Pensions | DONE | 8 PRs + close-out |
| 4 | Properties | DONE (`c972fff`) | 12 PRs |
| 5 | Mortgages | DONE (`e4d8039`) | 8 PRs (#403-#414) |
| 6 | **Investments** | **IN PROGRESS — write-path complete** | 5/16 PRs this session (#415-#419) |
| 7-14 | Unsecured liabilities, chattels, income, expenditure, protection, family, goals/life-events, business, trusts, wills, LPAs | **NOT STARTED** | — |

**Entities with a primary write store today:** Savings, Pensions, Properties, Mortgages, InvestmentAccount + 4 reference tables. **B1 is addressed for the highest-traffic asset entities.**

**Entities still entirely unmigrated (~9):** income, expenditure, protection policies, family members, goals + life events, business interests, trusts, chattels, unsecured liabilities, wills, LPAs — plus the Investment satellites (Holding, InvestmentGoal, RiskProfile, InvestmentScenario, RebalancingAction) mid-Pass-6.

**The 80/20:** the done entities are where the money and the Fyn-capture traffic concentrate (net-worth core). The remaining entities are real but lower-volume. We have done the 20% of entities that carry ~80% of the value.

### What "continuing SP1" actually costs

At the current cadence (Pass 6 = 16 PRs, Pass 4 = 12, Pass 2 = 26), finishing **Pass 6 alone** is ~11 more PRs. Passes 7-14 are roughly **8 more entity passes** at 8-16 PRs each — on the order of **80-120+ more PRs / multiple weeks** of subagent-driven implement-review-merge cycles. That is the thing eating the calendar.

---

## 2. What CoALA is, and its status

**Sources:** `fynla-coala-implementation-plan.md` (v0.4, 734 lines), `fynla-coala-stakeholder-brief.md`, `May/May27Updates/PRD-coala-phase-{1..6}-*.md`

CoALA ("Cognitive Architectures for Language Agents", Sumers et al. 2023, arXiv:2309.02427) is the framework for **reshaping Fyn's brain** — *how* Fyn organises knowledge, what it can do, and how it decides. Explicitly **not** retraining a model, not replacing Anthropic/xAI, not changing the user-facing chat, not multi-agent.

Six phases:

| Phase | What | Headline value |
|---|---|---|
| 1 | Semantic memory — versioned `.md` corpus (tax/FCA/product/house-view) + effective-date retrieval + embeddings | Resolves FCA narrative-content-in-code risk |
| 2 | Episodic memory — SQL + `.md` hybrid; adds `reasoning_trace`, `procedural_version`, `semantic_snapshot_id` | Auditable, reconstructable advice; resolves the deferred forensic-column purge debt |
| 3 | Working memory — one typed per-turn VO replacing the ad-hoc context mosaic | Cleaner state; cheaper to reason about |
| 4 | Procedural memory — overlays/workflows/tool-schemas as versioned `.md` | Product edits behaviour without code deploys |
| 5 | Decision loop (plan→execute) + **cost telemetry per action type** | Cost attributable per behaviour; FCA/board cost story |
| 6 | Gated learning — human-reviewed promotion of facts to semantic memory | Improves over time, never auto-merges regulatory content |

**Status: fully planned, zero implemented.** The plans, PRDs, and a non-technical stakeholder/investor brief all shipped as docs on 2026-05-26. No CoALA code exists yet.

**Business drivers (stakeholder brief):** regulator-auditable by design, cheaper to operate, faster to improve without deploys — framed as three P&L lines (compliance cost, AI infra cost, iteration speed). The brief's explicit ask: *"Commitment to fund Phase 1 + Phase 5 as a paired investment."* These are external-facing, time-sensitive concerns; SP1's long tail is not.

Critically, the plan is **"extend, don't rebuild"** — it lists the CoALA-shaped infrastructure that already exists (working-memory templating, hash-chained audit, summariser, KYC/prerequisite gating, per-mode tool allowlists, atomic cost gating). So CoALA is additive layering on a substrate that already exists **today, independent of SP1**.

---

## 3. The dependency question — does CoALA need SP1? (No.)

This is the crux. I audited it rather than assuming.

**Method:** grepped all 6 CoALA phase PRDs + the implementation plan for any dependency on the SP1 canonical stores (`InvestmentAccountStore`, `SavingsStore`, `App\Services\Stores`, "canonical store", "entity store", "blocked by", "prerequisite").

**Result:** every dependency CoALA names is one of:
- **Internal to CoALA** — "Phase 5 blocked by Phases 1-4", "Phase 6 blocked by 1-5", "Phase 4 blocked by Phase 2 (procedural_version column)", "Phase 1 telemetry baseline from Phase 5's first PR".
- **On existing Fyn infra** — `KycGateChecker`, `PrerequisiteGateService`, `ai_messages`, `ai_audit_events`, `AiToolDefinitions`, `FynSystemPrompt`, the conversation summariser. All present today.

**There is no reference to any SP1 entity store as a CoALA dependency.** The word "store" in CoALA refers to its own `.md` memory stores (semantic/procedural), which have nothing to do with SP1's `investment_accounts`/`properties`/etc. stores.

**Why they're independent, conceptually:**
- SP1 fixes **where/how user data is written and read** (the data layer). Its B1 bug is about data *freshness*.
- CoALA fixes **how Fyn reasons, remembers, decides, and is audited** (the cognition layer). Its concerns are knowledge organisation, cost, and regulatory traceability.
- CoALA Phase 1 operates on FCA/tax *narrative* `.md` files — not user data. Phase 5 operates on turn orchestration + cost. **Neither touches user-data freshness (B1).** SP1 doesn't make Fyn reason better; CoALA doesn't make the data fresher. They compound positively but neither blocks the other.

**Honest caveat:** SP1's B1 motivation *is* the one most adjacent to Fyn quality. The unmigrated entities (income, expenditure, protection, goals…) still carry a residual B1 risk — Fyn could read stale data for those. But that risk exists with or without CoALA, and CoALA doesn't fix it either way. The high-traffic entities are already protected. So "build CoALA on a partially-migrated data layer" is acceptable: the foundation under the *busy* entities holds; the long tail is a separate, resumable quality stream.

---

## 4. Where exactly to defer SP1 (the clean stop point)

We are mid-Pass-6. The safe, non-half-finished stop point is **right where we are now: Pass 6 PR 5a merged.** Here's why this is a stable boundary and not abandoned-mid-surgery:

- **InvestmentAccount write-path is COMPLETE and locked.** All writes (HTTP, Fyn, upload, onboarding, both seeders) route through `InvestmentAccountStore`; the boundary test passes with zero direct writes outside the store. B1 is addressed for investments. ✓
- **Reads are mostly direct still (5 of ~143 routed in 5a).** This is **not a correctness bug** — those reads return correct data; they just don't use the store's joint-aware read API yet. The boundary test only polices writes, so CI stays green. This is *consistency debt*, not breakage.
- **Holding store / derived columns / satellites (PRs 6-12) not started** — these were **new** work; not doing them is **no regression** (Holding is exactly where it was pre-Pass-6; the Pass-3 `DCPensionHoldingsController` deferral simply stays open as it has since Pass 3).

So stopping here leaves the tree **stable, green, shippable** — the high-value write-path migration banked, the lower-value read/completeness work deferred. CSJTODO already records the exact resume point (Pass 6 PR 5b) and the I-1 read-resolution convention to follow when we return.

**What we defer:** Pass 6 PRs 5b-5e (reads), 6-7 (Holding cross-module store), 8 (satellite stores), 9 (RebalancingAction), 10 (derived columns), 11 (tier-cap tests), 12 (lock-down); and Passes 7-14 entirely.

> Alternative considered: finish Pass 6 cleanly first (11 more PRs) then defer. Rejected — that's another ~half-week of the exact work we're trying to step away from, for completeness value, when the write-path (the valuable part) is already done.

---

## 5. Testing — the thing to do *before* CoALA

The user named "testing" as a priority. There are two distinct testing concerns, and one of them is a genuine blocker for doing CoALA well:

**A. The suite is NOT fully green on `dev` right now.** Known failures surfaced this session:
- `tests/Feature/Api/MortgageControllerTest.php` — **7 failing** (verified). Pre-existing latent regression from Pass 5 PR 2: create/update now route through the tier gate, but the test never seeds `TierConfigurationSeeder`, so POSTs 404. Trivial fix (seed it in `beforeEach`), logged in CSJTODO.
- `MortgageTierCapTest` — `loan_to_value_pct` out-of-range (surfaced in PR 5a review).
- `tests/Architecture/Phase03ArchitectureTest.php` — 2 `NetWorthService` assertion failures, pre-existing since Pass 4.

**B. CoALA's own risk mitigation assumes a regression harness that doesn't exist yet.** The stakeholder brief's headline cutover mitigation is *"75 golden-conversation regression tests."* Those are not built. CoALA Phase 5 (the decision-loop cutover) is the highest-regression-risk phase, and it leans on this harness.

**Recommendation:** spend a short, bounded block getting the suite green (fix the 3 known red areas — likely <1 day) **before** starting CoALA. A clean baseline is cheap now and is the prerequisite for trusting any CoALA regression signal later. The golden-conversation harness can be built as the first CoALA-adjacent task (it's useful regardless of phase order).

---

## 6. "Other bits and pieces" — what I can see (please confirm your list)

I don't have your full list, so this is what's visibly outstanding from the repo/vault/CSJTODO. **Please tell me which of these you meant, and what else is on your mind** — I've flagged my read of priority but you own the call:

- **csjones (dev staging) deploy gate** — still pre-Pass-4-PR6; ~5+ pending migrations + a Playwright smoke to close Pass 4/5 §16.1 gate 8. Low effort, unblocks staging verification. *(Worth doing soon regardless.)*
- **5 logged follow-ups from this session** (all pre-existing, all in CSJTODO): the red `MortgageControllerTest`; a dormant preview-spouse tier-cap risk; two `stocks_shares`→`stocks_and_shares` canonical-drift sites; and the LISA ISA-bucketing mis-categorisation in `ISATracker`/`ISAAllowanceOptimizer`. Small, batchable into the test-stabilisation block.
- **The other major sub-projects, per the SP1 spec §0:** SP4 Campaign engine (not started), SP5 Track-onboarding (not started), SP6 Gamification (not started). SP2 Freemium (shipped to prod) and SP3 Mobile iframe (on dev) are done/landed.
- **Deferred SP1 Pass 6 remainder + Passes 7-14** (the subject of this review).

---

## 7. Recommended sequencing

```
NOW ──► 1. Test-stabilisation block (small, ~1 day)
            • Fix MortgageControllerTest (seed TierConfigurationSeeder), MortgageTierCapTest, Phase03ArchitectureTest
            • Batch the 5 CSJTODO follow-ups
            • Optional: csjones deploy to close the staging gate
            • Outcome: green suite baseline

        2. CoALA Phase 5 cost-telemetry PR (ships first, standalone — per PRD §276)
            • Establishes prompt-cache hit/miss + per-action cost baseline BEFORE anything else moves
            • Outcome: the cost-attribution story the brief promises stakeholders

        3. CoALA Phase 1 semantic memory (foundation; brief's recommended start)
            • FCA/tax narrative .md corpus + retrieval
            • Outcome: resolves the biggest regulatory exposure

        4. CoALA Phases 2 → 3 → 4 → (5 full) → 6, per the PRD dependency order
            • Re-review with stakeholders at end of Phase 5 (the brief's explicit checkpoint)

LATER ─► Resume deferred SP1 (Pass 6 PR 5b onward) opportunistically, or fold the
         remaining entities into a leaner "stores for the long-tail entities" mini-pass
         when an entity's B1 risk actually bites (e.g. if Fyn keeps misreading income).
```

Note the brief itself recommends **Phase 5 telemetry-first, then Phase 1 foundation-first** — measure before you commit, then build the foundation that unlocks the rest. That matches the sequencing above.

---

## 8. Risks of deferring SP1 (and mitigations)

| Risk | Reality / mitigation |
|---|---|
| "Building CoALA on sand" — the original SP1 thesis | Partially true, but the *busy* entities are already on solid ground; CoALA doesn't touch data freshness anyway. The long-tail entities' B1 risk is unchanged by deferring. |
| InvestmentAccount read layer left half-migrated | Consistency debt, not a bug — reads return correct data; CI green; resume point recorded (PR 5b). No user-facing impact. |
| Pass-3 `DCPensionHoldingsController` deferral stays open | It has been open since Pass 3; deferring Pass 6 doesn't worsen it. No regression. |
| Losing the SP1 context/momentum | CSJTODO + the 6 pass plans + the spec capture everything; subagent-driven cadence is repeatable. Resuming is cheap. |
| Stakeholder expectation that SP1 finishes | Reframe: SP1 delivered the high-value core; remaining passes are scheduled-not-cancelled. The brief already positions CoALA as the next-funded initiative. |

---

## 9. Concrete next actions (proposed — your call)

1. **Approve the defer.** Stop SP1 at Pass 6 PR 5a (current state). Mark Pass 6 "paused — write-path complete" in CSJTODO and the SP1 spec.
2. **Test-stabilisation block.** Fix the 3 red test areas + batch the 5 follow-ups. Target: green `dev` suite.
3. **Confirm the "other bits" list** so I can slot them in (csjones deploy? SP4/5/6? something not in the repo yet?).
4. **Kick off CoALA** with the Phase 5 telemetry PR, then Phase 1 — using the same subagent-driven implement→spec-review→code-quality-review→merge pipeline that shipped the 5 SP1 PRs cleanly this session.

---

*Prepared 2026-05-28 by Claude (Opus 4.7, 1M context). Grounded in: SP1 spec `2026-05-14-module-canonical-store-design.md`; CoALA plan `fynla-coala-implementation-plan.md` v0.4; stakeholder brief; 6 CoALA phase PRDs; `progress.md`; `CSJTODO.md`; and direct codebase/test inspection this session.*
