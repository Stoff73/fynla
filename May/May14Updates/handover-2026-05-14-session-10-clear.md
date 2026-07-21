---
type: handover
mode: context-clear
date: 2026-05-14
session: 10
branch: claude/cranky-lewin-6bc99c
trigger: context-handover skill (tripwire — ~240k tokens, second tripwire of this session)
previous_session_this_worktree: 2026-05-14 session 8 (wrote spec amendment + 2,934-line implementation plan, see handover-2026-05-14-session-8-clear.md)
parallel_session: 2026-05-14 session 9 (different worktree — single-file 7-line refactor, unrelated; see handover-2026-05-14-session-9-clear.md)
---

# Context Clear Handover — 2026-05-14, Session 10

## Immediate state

**No new code work this turn.** Session 8 wrapped plan + spec writing and tripwired. CSJ then said: **"check out a branch on dev, so we can implement this locally and test locally, then implement. /goal is to have the plan implemented, tested and working as intended"** — making the next-session goal explicit. Second tripwire fired immediately on that user prompt, before any work could start. **This handover exists purely to capture CSJ's directive so the next session executes against it.**

## CSJ's explicit instruction for the next session

> "check out a branch on dev, so we can implement this locally and test locally, then implement. /goal is to have the plan implemented, tested and working as intended"

Three reads:

1. **Branch off `dev`, not `main`** — standard Fynla flow (CLAUDE.md "Branch workflow"). The Savings store work does NOT continue on `claude/cranky-lewin-6bc99c` (that worktree holds the spec + plan artefacts; its job is done).
2. **Implement locally first, test locally, THEN ship.** Run the plan's TDD micro-cycle + Pest suites in the local environment before touching csjones. Local-green before csjones-deploy.
3. **The goal is GREEN per the plan, not "an attempt".** CLAUDE.md Rule #15 (LOOP UNTIL CORRECT): "FOR ALL TESTS AND WHEN CSJ POINTS AT A SPECIFIC PLAN AND SAYS 'MAKE THIS WORK', I LOOP UNTIL IT IS GREEN PER THAT PLAN. I DO NOT STOP." The plan's "Acceptance gate for pass 1 closure" section is the contract.

## The thread (sessions 5 → 8 → 10)

- **Session 5** (this worktree, earlier today): brainstorming session producing the 774-line design doc at `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`. Tripwired before final 2 questions folded in.
- **Session 8** (this worktree, just before): auto-resumed from session 5. Folded CSJ's two final answers into the spec (`2cc5f82`). Invoked `superpowers:writing-plans`. Explored Savings code surface (model, controller, FormRequests, AI direct-write test, every direct `SavingsAccount::create/update/save/delete` site across the codebase, every read consumer across `app/Services/**`). Wrote a 2,934-line, 8-PR implementation plan to `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` (`c16b803`). Self-review caught storage events §11 missing from tasks — fixed inline. First tripwire fired. Wrote handover-8.
- **Session 10 (this)**: CSJ replied with the implementation directive. Second tripwire fired before any new work. This handover captures the directive.

## Files touched this session (10)

```
May/May14Updates/handover-2026-05-14-session-10-clear.md   (this file — new)
```

No code changes. No new commits to `claude/cranky-lewin-6bc99c`.

## Authoritative artefacts from session 8 (READ FIRST before starting work)

```
docs/superpowers/specs/2026-05-14-module-canonical-store-design.md   (APPROVED, all 7 questions resolved)
docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md   (2,934 lines, 8 tasks, ready to execute)
May/May14Updates/handover-2026-05-14-session-8-clear.md   (full session-8 narrative)
```

All on `origin/claude/cranky-lewin-6bc99c` at `c16b803`.

## WIP commit

None — tree is clean.

## Open decisions

**None outstanding from CSJ.** The directive is unambiguous: branch off `dev`, implement locally, test locally, ship. The plan recommends `superpowers:subagent-driven-development`; the next session should invoke it.

**One PR-7-specific decision baked into the plan** (not a CSJ-blocker, but flag at PR-7 review time): bind `StaticTierGate` immediately, or keep `PermissiveTierGate` bound until sub-project 2 ships? Plan default: ship `StaticTierGate`; surface alt in PR description.

## Pick up from here (auto-continue contract)

**The next session executes PR 1 of the Savings store plan. Concretely:**

1. **Read the plan in full** before any code: [docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md](docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md). 2,934 lines, pre-digested into 8 tasks with TDD micro-steps. The plan is the contract.

2. **Switch to the main fynla repo** (`/Users/CSJ/Desktop/fynla`) — **NOT this worktree**. The main repo has `vendor/`, `node_modules/`, dev server running; this worktree does not.

   ```bash
   cd /Users/CSJ/Desktop/fynla
   git fetch origin
   git status   # confirm tree clean — main repo currently on coordinatingagent-foruserorjoint-scope
                # per session-8's handover-commit landing
   # If dirty, COMMIT or STASH the in-flight work (do NOT lose it):
   #   git stash push -u -m "pre-savings-store-pr1 stash"
   git checkout dev
   git pull origin dev
   git checkout -b feat/savings-store-pr1
   ```

3. **Invoke `superpowers:subagent-driven-development`** with the plan path. Fresh subagent per task + review checkpoint between tasks. Start at **Task 1 / PR 1** (introduce `SavingsStore` facade + `IngestSource` + normaliser + `TierGate` + four storage events + boundary arch test). The full Task 1 = Steps 1.1 through 1.16.

4. **Local-first execution (CSJ's directive):**
   - Pest after every TDD step (red → green): `./vendor/bin/pest tests/Unit/Services/Stores/`
   - Architecture suite once the arch test lands: `./vendor/bin/pest --testsuite=Architecture`
   - Full suite before the end-of-task commit: `./vendor/bin/pest`
   - **Only after local-green:** push, open PR → `dev`, deploy csjones, Playwright smoke, admin-merge.
   - Deploy gate: per `feedback_deploy_gate_csjones_before_admin_merge.md` and `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`.

5. **LOOP UNTIL CORRECT (Rule #15)** — for every task, if any pest case or browser-test step fails, diagnose with file:line evidence, fix the root cause, re-run, repeat. No stopping early. No apologies-without-fixes. Plan's acceptance criteria (every checkbox across Tasks 1–8 + §"Acceptance gate") is the only exit.

6. **Per-task review checkpoint with CSJ.** Subagent-driven-development prescribes a two-stage review between tasks. Present subagent output, get CSJ's nod, then dispatch the next subagent. DO NOT batch all 8 tasks into one subagent run.

## What the next Claude needs to know

- **Spec is APPROVED.** Don't re-litigate the design. All 7 open questions resolved (§20). If something seems off during implementation, fix the implementation — unless you find a true contradiction with reality, in which case raise it as a blocker.
- **Arch test is hard-fail from PR 1 with a transition allowlist** (spec §14.1). The plan's Step 1.11 ships the test with an allowlist naming every file each subsequent PR removes. Subsequent PRs (2, 3, 4, 5, 8) edit the allowlist as they migrate each site.
- **PR 5 auto-splits at 500 lines** (spec §15.1) — no consult needed. The plan names 8 cluster sub-PRs (5a–5h).
- **Calcs INSIDE the store** — CSJ corrected this in session 5. Don't propose consumer-side derivation. `balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct` materialise as columns with `*_calculated_at` timestamps.
- **Observers stay on the allowlist forever** (spec §14.2). `SavingsAccountGoalObserver` and `SavingsAccountRiskObserver` are permanent exceptions.
- **The User `tier` column may not exist yet.** PR 7's `StaticTierGate::resolveTier()` defaults to `'free'` if null. Fallback (keep `PermissiveTierGate` bound) documented in the plan.
- **Currency native-vs-GBP storage (spec §9) is partially deferred** for pass 1 — assumed GBP, so `balance_gbp == current_balance`. Multi-currency lands when pass 2 ships `currency_rates`.
- **Fyn AI direct-write test must stay green.** `tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php` has 6 cases pinning envelope shape. PR 3 refactors through the store but envelope is byte-identical. If any case fails after PR 3, bug is in the refactor.
- **Don't `migrate:fresh` / `migrate:refresh`.** After PR 6 adds the migration, `php artisan migrate && php artisan db:seed`.
- **Sibling work on `dev`:** session-9 (parallel worktree) shipped a single-file 7-line refactor; taxConfig rail PRs #300, #301 already merged. Mobile-importer migration still outstanding (session-6 handover). All independent of Savings store — don't bundle.
- **Vault-sync deferred for 6+ consecutive sessions** on May 14. Next EOD `session-end` MUST catch up.
- **The worktree `claude/cranky-lewin-6bc99c` is done.** Its purpose was to hold the spec + plan. After PR 1 ships, implementation lives entirely on `dev`-branched feature branches in the main repo. The worktree can be cleaned up by CSJ at any time without losing anything (spec + plan are on origin).

## Branch / deploy state

- **This worktree's branch:** `claude/cranky-lewin-6bc99c` — clean, ahead/behind origin 0/0
- **Main repo branch** (where implementation happens next session): currently on `coordinatingagent-foruserorjoint-scope` (per session-8 handover-commit landing). Next session: checkout `dev`, pull, branch `feat/savings-store-pr1`
- **Deploy status:** Not deployed (no code changes — design + plan only)
- **PR open on this branch:** No (none needed — design artefact branch)
- **Sibling main-line state:** `main` ~21 PRs behind `dev` per session-6/7 handovers; release PR `dev → main` not yet opened. Unrelated to Savings store.

## File locations summary (so CSJ can sanity-check after /clear)

- **Spec (APPROVED):** [docs/superpowers/specs/2026-05-14-module-canonical-store-design.md](docs/superpowers/specs/2026-05-14-module-canonical-store-design.md) on `origin/claude/cranky-lewin-6bc99c`
- **Plan (8 PRs, 2,934 lines):** [docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md](docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md) on `origin/claude/cranky-lewin-6bc99c`
- **Session-8 handover (full session-8 context):** `/Users/CSJ/Desktop/fynla/May/May14Updates/handover-2026-05-14-session-8-clear.md`
- **Session-9 handover (parallel worktree, unrelated):** `/Users/CSJ/Desktop/fynla/May/May14Updates/handover-2026-05-14-session-9-clear.md`
- **This handover (the implementation directive):** `/Users/CSJ/Desktop/fynla/May/May14Updates/handover-2026-05-14-session-10-clear.md`

Both spec and plan visible on GitHub at `https://github.com/Stoff73/fynla/tree/claude/cranky-lewin-6bc99c/docs/superpowers`.
