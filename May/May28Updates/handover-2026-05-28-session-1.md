---
type: handover
mode: end-of-day
date: 2026-05-28
session: 1
branch: dev
previous_session: 2026-05-27 session 5 (context-clear at end of Pass 5 + Pass 6 plan; full -clear handover at `May/May27Updates/handover-2026-05-27-session-5-clear.md`)
---

# Handover — 2026-05-28, Session 1

## Where we left off

End of 2026-05-27 with **SP1 Pass 5 (Mortgages) fully closed** at PR 8 merge `e4d8039` and **SP1 Pass 6 (Investments) plan committed** at `1b3a900` (768 lines, 16 PRs covering all 6 Investment models — full scope per CSJ approval). Working tree clean on `dev` at `2f2ba94`. Next session starts Pass 6 PR 1 (InvestmentAccountStore facade) per the explicit CSJ instruction "write plan now, dispatch PR 1 next session". The auto-continue contract at the end of `handover-2026-05-27-session-5-clear.md` is the canonical pickup spec — read it first.

## What shipped today

Across the full 2026-05-27 day (sessions 4 + 5 combined — 81 commits per vault git history):

**Pass 5 PRs (all 12 merges)**:
- #403 (`fe5e1a1`) — MortgageStore facade + boundary + normaliser + events + tier-cap
- #404 (`a78ddd2`) — HTTP form requests through MortgageStore
- #405 (`54f215b`) — Fyn AI write tools through MortgageStore
- #406 (`1e39c45`) — Upload + onboarding + seeders + service-internal
- #407 (`49b0dd2`) — Pass 5 PR 5a Estate/IHT reads + parity test
- #408 (`e653602`) — Pass 5 PR 5b NetWorth/Mobile/CrossModule reads + cross-link regression fix
- #409 (`fc4fe51`) — Pass 5 PR 5c Coordination/AI/UserProfile reads
- #410 (`46dc4f2`) — Pass 5 PR 5d Goals + Protection reads
- #411 (`e50dfde`) — Pass 5 PR 5e GDPR/Protection/RateAlerts reads (final read cluster)
- #412 (`8ec33c6`) — PR 6 canonical derived columns + snapshots + cross-store recalc (3 migrations)
- #413 (`ad5f777`) — PR 7 tier-cap test
- #414 (`e4d8039`) — PR 8 lock-down + audit + parity + Store.md

**Pass 6 planning**:
- `1b3a900` — Pass 6 Investments plan (768 lines, 16 PRs)

**Documentation + handovers**:
- 5 CSJTODO updates (one per PR cluster — sessions 4 + 5)
- Session 5 context-clear handover at `May/May27Updates/handover-2026-05-27-session-5-clear.md`
- This end-of-day handover

**Codebase metrics drift (vault sync flagged)**:
- PHP Services: 331 → 340 (+9 from MortgageStore + Normaliser + Calculator + Listener + Snapshots + Pass 4 Property equivalents)
- Models: 114 → 119 (+5 from MortgageValueSnapshot + PropertyValueSnapshot models)
- CLAUDE.md table updated to reflect.

## What's in flight (NOT done)

**SP1 Pass 6 (Investments) — execution starts session 6**:
- 16 PRs planned, 0 shipped
- Branch convention `feat/investment-store-prN`
- First action: dispatch PR 1 implementer (InvestmentAccountStore facade)
- Full PR list with branch names in `CSJTODO.md` under "Active track: SP1 Pass 6 (Investments)"

**Open questions in plan §19** (resolve at PR 1 dispatch):
- Q1: Tier-cap defaults per entity (proposed values in plan)
- Q2: `HoldingStore::forParent` return shape
- Q6: HoldingStore location (proposed: top-level `app/Services/Stores/`)
- Q8: Currency round-trip — defer or implement (proposed: defer GBP-only)

**Optional cleanups deferred**:
- `GoalsProjectionService:55` dead eager-load (`User::with(['mortgages', 'spouse.mortgages'])` — derived columns now read via MortgageStore, so eager-load is redundant). PR 5d reviewer flagged as out-of-scope follow-up.

## Deploy status

**Ready to deploy to csjones but NOT deployed.** Deploy note at `May/May28Updates/deploy-2026-05-28.md` documents:
- 72 commits ahead of main / dev tip at `2f2ba94`
- csjones at `f2b5bec1` (pre-Pass-4 PR 6)
- 5 pending migrations (Pass 4 PR 6 × 2 + Pass 5 PR 6 × 3)
- No Vue/JS frontend changes — no Vite rebuild required
- Backfill commands to run post-migrate: `properties:backfill-outstanding-mortgage`, `properties:backfill-derived-columns`, `mortgages:backfill-derived-columns`
- Playwright smoke checklist to close §16.1 gate 8 for Pass 4 + Pass 5

**Production (`main`) deploy NOT scheduled.** main stays at `c972fff` (Pass 4 close-out). CSJ to decide when to cut `dev → main` — suggested gate is after Pass 6 closes.

## Tech debt found this session

None new from per-PR Opus reviews. Pre-existing deferred items still on the books:

- `tests/Architecture/Phase03ArchitectureTest.php` — assertions on `NetWorthService` import structure are stale (failing) since Pass 4 PR 5b added PropertyStore. Pre-existing on `dev`, confirmed not introduced by Pass 5. **Fix candidate for Pass 6 PR 4** when MigrateEstateToNetWorth investment branch is audited.
- `GoalsProjectionService:55` dead eager-load — see "What's in flight" above.

Per-PR code-quality reviews caught and fixed:
- PR 5b cross-link regression (Mortgage-on-property-but-not-owned-by-user edge case)
- PR 6 `final` modifier inconsistency (dropped to match Pass 4 sibling)
- PR 6 observer-dedup docblock (NetWorthCacheObserver firing semantics)
- PR 8 migration history SHAs (used merge SHAs not feature commits)
- PR 8 audit context-leak test missing (added 6th case)
- PR 8 derived-column parity gap (extended snapshot to include `_gbp` + `_ltv_pct`)

All fixed inline. Zero deferred from Pass 5.

## Known issues / blockers

None blocking. Pass 5 closed cleanly with all tests green.

- **Pre-existing `Phase03ArchitectureTest` failures** — NOT introduced by Pass 5; documented in Pass 4 PR 5b precedent. Will continue to surface in every Pass until fixed.
- **csjones deploy gate** outstanding for §16.1 gate 8 (Playwright smoke) on Pass 4 + Pass 5. Browser smoke can't be run until deploy happens.

## Rules reinforced this session

No new memory files created — all session learnings fit existing memory patterns. Existing relevant rules:
- `feedback_loop_until_correct.md` — applied in PR 5b cross-link regression fix
- `feedback_pages_must_use_app_layout.md` — N/A (no UI changes)
- `feedback_never_hardcode_tax_values.md` — N/A (no tax work)
- `critical_browser_testing_law.md` — N/A (no UI changes; gate 8 still open pending csjones deploy)

The plan template bugs caught during execution (wrong exception class, global function helpers, MySQL 64-char index limit, signature mismatches) are documented in the Pass 6 plan §4 "Critical convention drift to address" section so they don't recur.

## Next session should

1. **Run session-start** — it will auto-load `CSJTODO.md`, find `May/May28Updates/handover-2026-05-28-session-1.md` (this file) as the most-recent handover, and pick up from "Pick up from here" below.

2. **Auto-continue from Pass 5 session-5 handover's "Pick up from here"** — dispatch Pass 6 PR 1 implementer (InvestmentAccountStore facade + boundary + normaliser + events + tier-cap). Branch `feat/investment-store-pr1` off `dev` at `2f2ba94`.

3. **Implementer brief** should follow Pass 5 PR 1 sibling pattern. Key references:
   - Pass 6 plan §5 (Steps 1.1–1.9): `docs/superpowers/plans/2026-05-27-sub-project-1-pass-6-investments-plan.md`
   - Pass 5 PR 1 sibling commit: `git show fe5e1a1`
   - Pint stall mitigation: combine import + constructor reference in same edit
   - Resolve plan §19 Q1 (tier-cap defaults) inline at dispatch

4. **Dispatch pattern**: Sonnet implementer foreground → background-dispatched Opus spec reviewer + Opus code-quality reviewer → admin-merge after both APPROVE. Same as Pass 5.

5. **Pace the work** — Pass 6 has 16 PRs vs Pass 5's 8. Investments is 2-3x larger by consumer surface. Do not try to ship all 16 in one session.

6. **Mid-Pass deploy candidate** — consider triggering csjones re-deploy after Pass 6 PR 5e (when InvestmentAccount routing is complete). Reduces accumulated migration debt and gets gate 8 closed earlier.

## Context hints

- **Active branch type:** mainline (Pass 5 + Pass 6 plan in `dev`, will be promoted to `main` after Pass 6 close-out)
- **Behind origin/main by:** 2 commits (suggests `main` had some minor activity — investigate at session 6 start; pull if clean)
- **Ahead of origin/main by:** 72 commits (entire Pass 4 close-out + all of Pass 5 + Pass 6 plan)
- **Uncommitted:** none, working tree clean (only untracked is `docs/mobile/designer-brief.pdf` which is CSJ's own work — leave alone, same as sessions 3, 4, 5)
- **Last commit:** `2f2ba94 docs(session-end): session 5 handover — Pass 5 closed, Pass 6 plan written`
- **csjones (dev staging):** at `f2b5bec1` — 5 migrations pending. Deploy note at `May/May28Updates/deploy-2026-05-28.md`.
- **Production (`main`):** unchanged. Last release 22 May. CSJ to schedule next cut.
- **SP1 progress:** 8/19 stores shipped. Pass 6 brings it to 14/19 on close-out.
- **Test sweep at session-5 end:** all green. No flaky tests identified.

## Pass 6 plan summary (for reference)

Plan file: `docs/superpowers/plans/2026-05-27-sub-project-1-pass-6-investments-plan.md` (768 lines).

**16 PRs covering 6 entities**: InvestmentAccount (173 refs) + Holding (33 refs, cross-module polymorphic) + InvestmentGoal (13 refs) + RiskProfile (28 refs) + InvestmentScenario (11 refs) + RebalancingAction (5 refs).

**Unique architectural pieces**:
- HoldingStore is **first cross-module store in SP1** — accepts writes from both InvestmentController AND DCPensionHoldingsController (closes Pass 3 deferral)
- **2 cross-store recalc listeners** (Account ← Holdings AND Pension ← Holdings)
- **3 satellite stores bundled in PR 8** (Goal + Risk + Scenario)
- Observer entanglement (`InvestmentAccountRiskObserver`, `InvestmentAccountGoalObserver`)
- Polymorphic Holdings via `morphTo('holdable')`
