---
type: handover
mode: context-clear
date: 2026-05-14
session: 8
branch: claude/cranky-lewin-6bc99c
trigger: context-handover skill (tripwire — ~217k tokens)
previous_session: 2026-05-14 session 5 (this worktree's brainstorming session that wrote the design doc; session 6+7 were on different branches/worktrees)
---

# Context Clear Handover — 2026-05-14, Session 8

## Immediate state

Just finished writing the **implementation plan for sub-project 1 / pass 1 (Savings canonical store)** — 2,934 lines covering 8 PRs with TDD micro-cycle, real file paths, real test code, and Playwright smoke gates per CLAUDE.md. Both spec amendment and plan are committed and pushed. Tripwire fired right after answering CSJ's question about where the files were (he was looking under the wrong path AND on the main worktree which is on a different branch).

## The thread

1. **Session opened from `handover-2026-05-14-session-5-clear.md`.** Session-start's Phase 5 auto-resumed with: apply CSJ's two final answers to the design doc → commit → invoke `superpowers:writing-plans` for pass 1 (Savings). Explicit instruction in that handover: **do NOT start implementation in the same session that wrote the plan.**
2. **Spec amended (`2cc5f82`)** — three changes plus frontmatter status flip:
   - §10.3 filled in `tier2 => 1825` (5yr) and `tier3 => 2555` (7yr); noted Tier 3 surfacing equals retention floor (no API gating at top tier); aligned `retentionDays` constant
   - §15.3 reordered the migration passes — Reference data moved from pass 14 to **pass 2**, all subsequent passes bumped by one (to close B2 `tax_configurations` admin wiring while the store template from pass 1 is still fresh)
   - §20 collapsed "still open" → "all resolved" with a 7-row resolutions table
   - §21 + frontmatter flipped from DRAFT to APPROVED
3. **`superpowers:writing-plans` invoked.** Before writing, did a focused exploration of the Savings surface — model, controller, both FormRequests, AI direct-write test, an existing arch test as template, lookup of every direct `SavingsAccount::create/update/delete/save` call site, and every read consumer across `app/Services/**`. Used `Read`/`Bash`/`grep` directly (no subagents — wanted single-context concrete code).
4. **Plan written (`c16b803`)** to `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md`. Structure:
   - Plan header per writing-plans skill (Goal, Architecture, Tech Stack)
   - File Structure section listing every new + modified file with one-line responsibility
   - Task 1 = PR 1: store facade + `IngestSource` enum + normaliser + `TierGate` interface + four storage events + boundary arch test. Hard-fails CI from this PR with an explicit transition allowlist that names every file each subsequent PR removes.
   - Task 2 = PR 2: HTTP form path → store (controller shrinks)
   - Task 3 = PR 3: Fyn AI write tools → store (`CoordinatingAgent::handleCreateSavingsAccount` shrinks; AI-enum-to-DB mapping + ISA inference move to `SavingsAccountNormaliser::fromFyn`; existing 6-case `CreateSavingsAccountTest` stays green)
   - Task 4 = PR 4: upload extraction + 2 seeders + PreviewController → store; adds `SavingsStore::updateOrCreate` to match `ChrisUserSeeder` semantics
   - Task 5 = PR 5: read consumers → store with explicit 8-cluster split (5a net-worth/mobile, 5b estate/IHT, 5c plans/retirement, 5d tax strategies, 5e investment ISA, 5f coordination/goals, 5g AI prompt/profile, 5h agents/savings-internal). Auto-splits at 500 lines per spec §15.1 — no consult needed.
   - Task 6 = PR 6: derived columns (`balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct`) + `savings_account_value_snapshots` table + `SnapshotPolicy` per column + backfill artisan command
   - Task 7 = PR 7: tier-cap enforcement (`StaticTierGate` with hardcoded `savings_account` free=3 cap from spec §13; fallback to keep `PermissiveTierGate` bound until sub-project 2 lands if CSJ wants to defer)
   - Task 8 = PR 8: final allowlist lock-down + capture `ingest_source` in audit row metadata
   - Per-PR Playwright csjones smoke section
   - Acceptance gate at the end mapping to spec §16.1 (8 boxes)
5. **Self-review caught one gap** — storage events (spec §11) were in the file structure but had no implementation task. Added a Step 1.13 to PR 1 that creates the four event classes (`SavingsAccountCreated/Updated/Deleted/Restored`), writes failing event tests, wires `event(...)` calls into `create`/`update`/`delete`/`restore`. Step numbers renumbered cleanly.
6. **CSJ said "I don't see the plan or spec in fynla/superpowers/docs/spec or plans folders".** Answered:
   - Path mismatch: actual paths are `docs/superpowers/specs` and `docs/superpowers/plans` (under top-level `docs/`, plural `specs`)
   - More likely: branch mismatch — this worktree is `claude/cranky-lewin-6bc99c`; main repo at `/Users/CSJ/Desktop/fynla/` is on `mobile-taxconfig-migration`. Files exist on disk inside the worktree path and on origin, but won't appear in main repo view until he checks out `claude/cranky-lewin-6bc99c` or opens the worktree path directly.
7. **Tripwire fired at ~217k tokens** before any further work. Started this handover.

## Files touched this session

```
docs/superpowers/specs/2026-05-14-module-canonical-store-design.md  (modified, +33/-36 net)
docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md  (new, 2,934 lines)
```

Both committed and pushed.

## Commits this session (all on `claude/cranky-lewin-6bc99c`)

| SHA | Subject |
|-----|---------|
| `2cc5f82` | docs(spec): finalise sub-project 1 design with CSJ's final answers |
| `c16b803` | docs(plan): sub-project 1 / pass 1 — Savings canonical store implementation plan |

Both pushed to `origin/claude/cranky-lewin-6bc99c`. No WIP commit this handover — tree is clean.

## Open decisions

**None outstanding from CSJ for this work stream.** The implementation plan is approved by virtue of CSJ pointing the session-start auto-resume at writing-plans (the handover-5 contract). The next-session question is execution mode:

- **Recommended default: subagent-driven-development** (one fresh subagent per task, two-stage review between tasks, fast iteration) — this is what the writing-plans skill recommends.
- **Alternative: `superpowers:executing-plans` inline** — batch execution with checkpoints.

Session-start's auto-resume should propose subagent-driven and proceed unless CSJ redirects. The plan itself is mode-agnostic; both skills consume the same checklist.

One PR-7-specific decision flagged INSIDE the plan (not a CSJ-blocker): whether to bind `StaticTierGate` immediately or keep `PermissiveTierGate` bound until sub-project 2 ships. Default per plan: ship `StaticTierGate` bound; flag the alternative in the PR description.

## Pick up from here (auto-continue contract)

1. **Read the plan in full:** [docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md](docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md)
2. **Invoke `superpowers:subagent-driven-development`** (recommended). Plan path: above. Start at Task 1 / PR 1 (introduce `SavingsStore` facade).
3. **First subagent task — PR 1 Steps 1.1 through 1.16** (the whole of Task 1). Stop after csjones smoke green + admin-merge, before starting Task 2. Review with CSJ between tasks per the subagent-driven-development skill's two-stage gate.
4. **Branch strategy per CLAUDE.md:** PR 1 branches off `dev` (not off this `claude/cranky-lewin-6bc99c` worktree). The worktree exists only to hold the spec + plan; implementation PRs target `dev` directly. Pattern:
   ```
   git checkout dev && git pull
   git checkout -b feat/savings-store-pr1
   # ...implement...
   gh pr create --base dev --title "feat(savings): introduce SavingsStore facade + boundary arch test"
   ```
5. **Each PR: csjones browser smoke is mandatory** per Rule #15 (LOOP UNTIL CORRECT) + browser testing law + `feedback_deploy_gate_csjones_before_admin_merge.md`. Then admin-merge per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`.

## What the next Claude needs to know

- **This worktree (`cranky-lewin-6bc99c`) has no `vendor/` directory.** Pest / artisan / composer must run from `/Users/CSJ/Desktop/fynla` (which is on `mobile-taxconfig-migration` branch — **do NOT inadvertently commit Savings-store code to that branch**). For execution, the cleaner pattern is to do all implementation work directly on `dev` (or a `feat/...` branch off `dev`) in the main repo, NOT in this worktree. The worktree is just storage for the design artefacts; it has served its purpose.
- **Alternatively, `composer install` inside this worktree** if you want to run pest locally here — but the canonical path is to branch off `dev` in the main repo for implementation.
- **The arch test is hard-fail from PR 1.** No soft-warn ramp-up. Spec §14.1 + CSJ-confirmed. The test in PR 1 ships with an explicit transition allowlist; subsequent PRs remove entries one by one. PR 8 confirms only permanent entries remain.
- **PR 5 auto-splits at 500 lines** — no consult needed per spec §15.1. The plan names 8 cluster sub-PRs (5a–5h) in suggested merge order; the engineer chooses which clusters to bundle vs split based on actual diff size.
- **Currency native-vs-GBP storage (spec §9) is partially deferred** for pass 1. Pass-1 assumes all balances are GBP; `balance_gbp = current_balance`. Multi-currency lands when pass 2 (reference data) ships the `currency_rates` table.
- **Calcs INSIDE the store, not consumer-side.** CSJ corrected this in brainstorming session 5; derived values materialise as columns with `*_calculated_at` timestamps. The store is the only writer for these columns.
- **Observers stay on the allowlist forever** per spec §14.2 — `SavingsAccountGoalObserver` and `SavingsAccountRiskObserver` are the canonical permanent exceptions to the boundary.
- **The `tier` column on User might not exist yet.** PR 7's `StaticTierGate::resolveTier()` defaults to `'free'` when `$user->tier` is null. If running PR 7 reveals no tier column, the fallback option (keep `PermissiveTierGate` bound) is in the plan.
- **Vault-sync deferred for the 5th session running** (May 14 sessions 2 + 3 + 4 + 6 + this one). Next EOD session-end must catch up.
- **Sibling work on `dev` (sessions 6 + 7):** taxConfig rail completion. Independent of this work stream. Outstanding from that thread: 4 mobile/Vuex importers of `@/constants/taxConfig` still to migrate (per session-6 handover) + tax-config drop + dev → main release with 21 PRs / 69 commits. None of that blocks Savings store work.

## Branch / deploy state

- **Branch:** `claude/cranky-lewin-6bc99c` (this worktree)
- **Behind origin:** 0
- **Ahead of origin:** 0 (just pushed `2cc5f82` + `c16b803`)
- **Behind `main`:** uncounted — this is a feature branch, not in the dev/main release line
- **Behind `dev`:** uncounted — sibling work has shipped on dev (PRs #300, #301 for taxConfig rail); this branch holds spec + plan only
- **Deploy status:** Not deployed (design + plan only, no code changes yet)
- **PR open:** No (no need — implementation PRs will branch off `dev` per the Pick-up-from-here section)
- **Sibling main-line state:** `main` 21 PRs behind `dev` (sessions 6 + 7 / parallel taxConfig work). Release PR `dev → main` not yet opened. Unrelated to Savings store but worth knowing.

## CSJTODO touch

CSJTODO not updated this session — the standing entries on the document (PR #280 deploy follow-up, mobile taxConfig importers, Path B advisor-impersonation, net-worth Fyn `get_net_worth` tool, vault-sync catch-up, stale feature branch cleanup) are all on the `dev` workstream, not this one. The Savings store work is a brand-new tracked initiative; if it should be added to CSJTODO, that's a session-end (not context-handover) job per the skill rules.

## File locations summary (for CSJ to verify before /clear)

- **Spec:** `/Users/CSJ/Desktop/fynla/.claude/worktrees/cranky-lewin-6bc99c/docs/superpowers/specs/2026-05-14-module-canonical-store-design.md` (also visible after `git checkout claude/cranky-lewin-6bc99c` in the main repo)
- **Plan:** `/Users/CSJ/Desktop/fynla/.claude/worktrees/cranky-lewin-6bc99c/docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` (same)
- **Both on `origin/claude/cranky-lewin-6bc99c`** — visible on GitHub at `https://github.com/Stoff73/fynla/tree/claude/cranky-lewin-6bc99c/docs/superpowers`
