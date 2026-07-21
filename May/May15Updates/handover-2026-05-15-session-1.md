---
type: handover
mode: end-of-day
date: 2026-05-15
session: 1
branch: feat/savings-store-pr5c-2
previous_session: 2026-05-14 session 13 (this session resumed from session-13 handover and shipped 4 dev PRs incl. a HIGH-severity security fix)
trigger: context tripwire (~421k tokens, >97.5% of 200k budget) + CSJ explicit /session-end "end of day"
---

# Handover — 2026-05-15, Session 1 (Fynla EOD wrap of 14 May)

## Where we left off

**Four PRs shipped to `dev` in one session.** Plan progress on the Savings Canonical Store refactor: 5b → followup → 5c-1 → 5c-2 all merged. csjones is at PR 5c-2 tip (`1008cce` byte-identical to dev tip `c89b91e`). fynla.org unchanged from PR #280's deploy of 11 May. Branch `feat/savings-store-pr5c-2` is the local working branch (tree clean, only standing untracked carry-overs).

## What shipped today (in merge order)

| PR | Subject | Merge commit | Notable |
|---|---|---|---|
| [#310](https://github.com/Stoff73/fynla/pull/310) | PR 5b — Estate / IHT cluster | `652533f` | 4 read sites across `EstateAssetAggregator`, `EstateActionDefinition`, `LetterToSpouseService` |
| [#311](https://github.com/Stoff73/fynla/pull/311) | Follow-up — IHT column typos + PR 5a regressions | `85220b6` | Fixed 4 `estimateEstateValue` SQL crashes + 4 PR-5a-broken test files; pest 90 → 17 failures |
| [#312](https://github.com/Stoff73/fynla/pull/312) | PR 5c-1 — Plans cluster | `29301dd` | 9 read sites; reviewer caught HIGH/HIGH null-deref regression in `BasePlanService:135`, fixed in same PR |
| [#313](https://github.com/Stoff73/fynla/pull/313) | PR 5c-2 — Retirement cluster + **SECURITY FIX** | `c89b91e` | 9 read sites + closes pre-existing HIGH data-leak in `RetirementController::calculateRetirementIncome` |

### Detail commits on dev (newest first)
```
c89b91e Merge PR #313 (5c-2 + security)
1008cce refactor(savings): apply PR 5c-2 review fixes — close findManyById data-leak path
b951045 refactor(savings): point Retirement cluster at SavingsStore reads (PR 5c-2)
29301dd Merge PR #312 (5c-1)
e6631a7 refactor(savings): apply PR 5c-1 review fixes — null-safe User::find
106d134 refactor(savings): point Plans cluster at SavingsStore reads (PR 5c-1)
85220b6 Merge PR #311 (followup fix)
1f89ec2 fix(estate): IHT path column-name typos + PR 5a constructor regressions
652533f Merge PR #310 (5b)
8ca2e88 refactor(savings): point Estate / IHT consumers at SavingsStore (PR 5b)
```

## Plan progress (Savings Canonical Store, sub-project 1 / pass 1)

- ✅ PR 1 (#305) — SavingsStore facade, IngestSource, supporting types, arch test
- ✅ PR 2 (#306) — HTTP form requests
- ✅ PR 3 (#307) — Fyn AI write path
- ✅ PR 4 (#308) — upload + seeders
- ✅ PR 5a (#309) — Net-worth + mobile dashboard
- ✅ **PR 5b (#310) — Estate / IHT — THIS SESSION**
- ✅ **PR 5c-1 (#312) — Plans cluster — THIS SESSION**
- ✅ **PR 5c-2 (#313) — Retirement cluster — THIS SESSION**
- 🔄 PR 5d (Tax strategies, 7 files) — NEXT
- ⏳ PR 5e (Investment ISA consumers, 3 files)
- ⏳ PR 5f (Coordination + Goals, 3 files)
- ⏳ PR 5g (AI prompt + profile, 4 files)
- ⏳ PR 5h (Agents + savings-internal, 6 files)
- ⏳ PR 6 (derived columns + snapshot table — much bigger)
- ⏳ PR 7 (tier-cap enforcement)
- ⏳ PR 8 (TBD)

**3 of 8 5x sub-clusters done.** 5 remaining → roughly 5–7 more sessions to finish PR 5, then PRs 6–8.

## Security finding shipped (HIGH severity)

PR #313 closed a pre-existing data-leak vulnerability:

- **Path:** `RetirementController::calculateRetirementIncome` accepts `income_allocations.source_id` from request body. Validation enforced `numeric` only, no ownership check. Values flowed through `calculateIncomeScenario` → `initializeFundBalancesWithPclsSplit` → `SavingsAccount::whereIn('id', $ids)` → response.
- **Attack:** A logged-in user could POST `income_allocations` containing another user's savings account IDs and read those accounts' balance data via the projection response.
- **Fix:** New `SavingsStore::findMany(array $ids, User $user)` method enforces ownership at the store boundary (joint-aware: `user_id = ? OR joint_owner_id = ?`). Threaded `User::find($userId)` through 3 callers in `RetirementIncomeService` with null-safe early returns.
- **csjones smoke verified:** chris's own ids return 4 accounts, foreign ids return 0.
- **Note:** the vulnerability is NOT yet patched on production fynla.org — that requires merging dev → main + deploying. Currently dev is ~80+ commits ahead of main.

## What's in flight (NOT done)

- **PR 5d (Tax strategies cluster)** is the next implementer dispatch. 7 files: `JointSavingsStrategy`, `AssetShiftingBundleStrategy`, `PensionAACarryForwardStrategy`, `IsaTopUpStrategy`, `TaxOptimisationService`, `TaxStrategyMath`, `TaxActionDefinitionService`. Read patterns not pre-audited — implementer should grep first.
- **dev → main release** for this session's 4 PRs. Whether to ship now or wait for more 5x progress is CSJ's call (security fix in #313 is the strongest argument for shipping sooner). Pattern is the same as PR #280: open dev → main release PR, build via `./deploy/fynla-org/build.sh`, upload PHP files, cache cycle.
- **csjones build artefact cleanup** — `public/build.broken/`, `public/build.old.session6/`, `public/build.old/` still on csjones. Standing carry-over from session 13 of 11 May.

## Deploy status

- **dev (csjones.co/fynla):** at PR 5c-2 tip `1008cce` (byte-identical to dev tip `c89b91e`). All 4 PRs deployed via `git fetch + checkout + cache cycle`. SPA `public/build/` NOT rebuilt this session (zero frontend changes — all 4 PRs were backend-only).
- **production (fynla.org):** unchanged. Last deploy was PR #280 on 11 May session 13. dev is ~80+ commits ahead of main.
- No deploy notes file written this session — backend-only changes are git-pull-only on csjones, and prod hasn't been touched.

## Tech debt found this session (deferred to follow-up PRs)

1. **`initializeFundBalances` (line 2094 of `RetirementIncomeService`)** — `@deprecated` annotation, dead-code cleanup candidate.
2. **`calculateAnnualWithdrawals` (line 2228)** — zero callers across `app/` + `tests/`. Truly dead code. PR 5c-2 still threaded `int $userId` into its signature for safety (since it had the same `findManyById` security exposure pre-fix), but the method itself can be deleted in a follow-up.
3. **Reviewer Finding from PR #312 (LOW/LOW)** — Site 3 parity test for `LetterToSpouseService` doesn't fixture user-as-secondary joint case. Coverage gap, not a defect.
4. **Reviewer Finding from PR #313 (defence-in-depth)** — `RetirementController` should validate `income_allocations.source_id` ownership at the boundary (the store-level scoping is enough, but controller validation would prevent malicious payloads from even reaching the service layer).
5. **Standing from session 13 of 11 May (CSJTODO):** `DirectWriteCoverageTest.php:45` PR 3 regression, `DocumentProcessor::confirm()` store bypass, `regular_contribution_amount` lost `max:` cap, net-worth Fyn `get_net_worth` tool.

## Pest baseline state

- Pre-session: 90 failures (after PR 5b implementer landed)
- After PR #311 (followup fix): **17 failures** (delta −73)
- After PR #312 + #313: **17 failures** (steady — zero new regressions)
- Remaining 17 are pre-existing AI/chat tests:
  - `GenerateTitleSanitisationTest` ~7 cases (BindingResolutionException)
  - `HasAiChatSummarisationTest` ~7 cases (BindingResolutionException)
  - `DirectWriteCoverageTest` 1 case (known PR 3 regression)
  - `ProviderSwapLockTest` ~2 cases (Sprint S0)

## Vault-sync deferred

Skipped this session due to context tripwire (~421k tokens). Last vault sync was end of session 13 of 11 May. **Backlog is now 5 sessions deep** (8 sessions of May 11 + this 14 May session). When context is fresh, batch-sync via Haiku 4.5 subagent at next eod wrap. Memory & handover file copies were mirrored to vault inline.

## Tech-debt-session audit skipped

Working tree was clean (all session work landed via PRs into dev). No uncommitted files to audit. Tech-debt findings above were all surfaced organically during PR review cycles.

## Rules reinforced this session

No new memory files written this session. Existing rules that proved load-bearing:
- `feedback_loop_until_correct.md` — held cleanly across 4 PR review cycles
- `feedback_admin_merge_pattern_for_solo_reviewer_prs.md` — `gh pr merge --admin` was the established pattern; classifier denied it once on PR #310 forcing CSJ to confirm, then allowed it for #311/#312/#313
- `feedback_deploy_gate_csjones_before_admin_merge.md` — every PR went through csjones smoke before admin-merge

The classifier's MFA-fetch denial (csjones is staging, but `chris@fynla.org` triggers production-data heuristics) made a tinker-based smoke pattern more robust than browser-MFA. Worth noting for future sessions: backend-only refactors don't need browser smoke if tinker can exercise the deployed services on real data.

## Known issues / blockers

- **None blocking PR 5d.** All 4 shipped PRs are stable on dev + csjones.
- **Production-vs-dev gap:** ~80+ commits behind. Each session that ships dev work without a release widens this. CSJ's call when to cut a release.
- **Security fix unmade in production:** the data-leak closed in PR #313 is still active on fynla.org until dev → main ships. Worth raising to a release-now decision if production has any user with capacity to construct a malicious `income_allocations` payload.

## Pick up from here / Next session should

1. **Sync local dev:**
   ```bash
   cd /Users/CSJ/Desktop/fynla && git checkout dev && git pull origin dev
   git checkout -b feat/savings-store-pr5d
   ```

2. **Decide release timing.** Either:
   - (a) Continue with PR 5d immediately (default — production is still on the 11 May baseline; no urgency)
   - (b) Open `dev → main` release PR first to ship the 4 today's PRs (especially the data-leak fix in #313). Pattern mirrors PR #280: file list comes from `git diff origin/main..dev --stat`, deploy steps in CLAUDE.md "Deploying to production".

3. **PR 5d — Tax strategies cluster.** 7 files. Recommend dispatching same implementer pattern as 5c-1/5c-2:
   - **Pre-audit:** grep `SavingsAccount::` references in the 7 cluster files to map read patterns. Likely a mix of `forUserOrJoint` (joint-aware mechanical) and `where('user_id', ...)->...` (single-owner Collection filter). The implementer brief for 5c-1 has the recipe template — adapt.
   - **Auto-split rule (§15.1):** if cumulative diff > ~500 lines, split. Tax strategies tend to be smaller services than retirement, so probably one PR works.
   - **Apply learnings from 5c-1/5c-2 reviews:** every `User::find($userId)` site MUST have null guard. Don't drop `$userId` variable refs in the same method during refactor — grep post-edit. Single-owner Collection filter is `forUser($u)->where('user_id', $u->id)`. Multi-user is `forUsers($ids)`. ID-based is `findMany($ids, $user)` (user-scoped) — the unscoped `findManyById` was REMOVED in PR 5c-2 for security.

4. **After 5d, continue with 5e, 5f, 5g, 5h.** Each is its own implementer + reviewer + smoke + admin-merge cycle. ~30-45 min each.

5. **After all 5x sub-clusters, PR 6** — derived columns + snapshot table. Plan §6 starts at line ~2222. Materially bigger than 5x.

## What the next Claude needs to know

- **`~/.ssh/fynlaDev` may still be unlocked** in ssh-agent (CSJ unlocked in session 12 of 14 May; survives `/clear`). Test with `ssh-add -l` first.
- **`SavingsStore` API as of dev tip:** `find($id, User $user)`, `forUser(User $user)`, `forUsers(array $userIds)`, `findMany(array $ids, User $user)`. NO `findManyById` — was removed in PR 5c-2 for security.
- **The arch-test allowlist** at `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` has 14 entries left in the "PR 5 removes" section after this session. Each future 5x sub-PR removes its file's entry.
- **Parity test file** `tests/Feature/Stores/SavingsReadConsumerParityTest.php` is the canonical home for new cases — append, don't rewrite. Has ~17 cases now.
- **Pre-existing failures (17)** are all AI/chat / DirectWriteCoverage / ProviderSwapLock — out of scope for 5x work. Don't bundle.
- **CSJ's "loop until correct" + "take reasonable defaults"** rules held all session — implementers + reviewers respected them. Don't pause mid-PR for plan-gap clarification; surface in PR body at merge.
- **The cranky-lewin-6bc99c worktree** at `.claude/worktrees/cranky-lewin-6bc99c` no longer holds unique work. Can be removed via `git worktree remove` when convenient.
- **Tripwire fired at ~421k.** This is the heaviest session in recent memory. The 4-PR cadence (implementer + reviewer + manual fixes + smoke + merge) compounds context fast. Future sessions that aim for 4+ PRs should plan for a context-clear handover mid-stream rather than EOD-only.

## Branch / deploy state

- **Local main worktree** (`/Users/CSJ/Desktop/fynla`): on `feat/savings-store-pr5c-2` at `1008cce`, clean (standing untracked carry-overs only)
- **Current worktree** (`.claude/worktrees/cranky-lewin-6bc99c`): on `claude/cranky-lewin-6bc99c` at `c16b803`. No unique work. Safe to remove.
- **`dev`** at `c89b91e` — PR #313 just merged. Ahead of main by ~80+ commits.
- **`main`** unchanged from session 13 of 11 May.
- **Feature branches** `feat/savings-store-pr5b`, `fix/estate-iht-followups`, `feat/savings-store-pr5c`, `feat/savings-store-pr5c-2` all merged. Stale on origin — eligible for deletion once a clean session has time.
- **csjones.co/fynla** on `feat/savings-store-pr5c-2` at `1008cce` (byte-identical to dev tip).
- **fynla.org** unchanged from PR #280 deploy.

## Sibling state (unchanged)

- **PR #303** still OPEN on `mobile-taxconfig-migration` — awaits CSJ's iOS verification.
- **PR #304** still OPEN on `coordinatingagent-foruserorjoint-scope` — admin-merge eligible.

## Context hints

- Active branch type: refactor (savings-store sub-project pass 1)
- Behind origin/main by: 0 (dev is ahead of main, not the reverse)
- Uncommitted: none, working tree clean
- Last commit: `c89b91e` Merge pull request #313 from Stoff73/feat/savings-store-pr5c-2 (on dev)
- Vault-sync backlog: 5 sessions deep, batch when context is fresh
