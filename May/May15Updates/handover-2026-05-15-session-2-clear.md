---
type: handover
mode: context-clear
date: 2026-05-15
session: 2
branch: feat/savings-store-pr5f (main checkout) / claude/cranky-lewin-6bc99c (worktree harness root)
trigger: context-handover skill (tripwire ~317k tokens, >97.5% of 200k budget)
previous_session: 2026-05-15 session 1 (EOD wrap of 14 May — shipped 4 PRs)
---

# Context Clear Handover — 2026-05-15, Session 2

## Immediate state

Autonomous mission run (CSJ `.goal`: deliver ALL PRs + fully implement the Savings Canonical Store plan, self-managing context). PR 5f (#316) review fixes just completed by a background agent — **#316 is GREEN and ready for csjones smoke + admin-merge**. That is the literal next action.

## The mission (from `/Users/CSJ/Desktop/fynla/.goal` — READ THIS FILE FIRST)

`.goal` is an untracked file at repo root (intentionally untracked so it survives `/clear` and branch switches — do NOT commit it to a feature branch or it vanishes on `git checkout dev`). It is the standing autonomous contract: deliver all open PRs + fully implement `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` (PRs 5d→5h, 6, 7, 8), e2e test each, cut a dev→main release for the HIGH security fix, and **self-manage context** (tripwire → context-handover → /clear → session-start auto-continues). Do NOT ask permission to continue — continue until the roadmap is delivered or CSJ redirects.

## The thread (this session's arc)

- Bootstrapped session; wrote `.goal`; confirmed plan/spec tracked on dev at `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` (2941 lines) + `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`.
- Established cadence per PR: pre-audit read sites → dispatch general-purpose implementer subagent (TDD parity test first, mechanical migration, arch-allowlist trim, full pest to 17-baseline, commit+push+PR) → dispatch feature-dev:code-reviewer subagent → independently verify → csjones deploy-gate (feature branch via ssh, tinker smoke on chris's real data) → `gh pr merge --merge --admin --delete-branch` → return csjones to dev → next PR.
- **PR 5d (#314)** Tax strategies (7 files, 12 sites) — MERGED dev `5aca9ff`. Reviewer found a "joint-ISA exclusion test" gap; CSJ corrected: **joint ISAs are illegal/don't exist** — rejected that rec; saved memory `feedback_joint_isa_illegal.md` + MEMORY.md index.
- **PR 5e (#315)** Investment ISA consumers (3 files, 5 sites) — MERGED dev `84ff5b0`. Critical site `UserContextBuilder:86` is genuinely joint-aware → `forUser()` 1:1, NO post-filter (csjones smoke proved `46845 == raw forUserOrJoint`).
- **PR #304** (sibling, coordinator forUserOrJoint scope) — MERGED dev `37b5cc5` (csjones parity `[24,121,123,124] MATCH=YES`).
- **PR #303** (sibling, mobile taxconfig) — code-reviewed + approved, **left OPEN, GATED on CSJ iOS sign-off** (Capacitor mobile, can't verify on csjones/by me). Notes: literal fallbacks `||325000/175000/60000` after store getters (LOW); `learnTopics.js` pre-existing emoji icons (grandfathered, untouched, correct).
- **PR 5f (#316)** Coordination + Goals (3 files, 9 sites) — implemented + reviewed. Reviewer CHANGES REQUIRED ×2: Finding 1 (arch comment falsely called HPS `calculateJointAssetsPassingToSurvivor` a non-query — it IS a dynamic-dispatch `$modelClass::where()` savings query; HPS correctly stays allowlisted, comment fixed) → fixed in-PR. Finding 2 (`SavingsStore.php:184` omits `tenants_in_common` from ownership_type enum — real WRITE-PATH bug, OUT OF SCOPE for read-only 5f) → spawned as a separate task (chip), NOT fixed in 5f. Background agent applied both in-scope fixes; **#316 HEAD `da3c265`**, parity 42 passed, arch 97 passed, full suite 17-baseline zero new, pint clean, pushed.

## Files touched this session

All via merged PRs (5d/5e/#304 on dev) + open PR #316 (5f). No loose uncommitted mission code — the 5f agent committed+pushed everything. Only untracked artifact: `/Users/CSJ/Desktop/fynla/.goal` (intentional — see above). CSJ's standing untracked personal files (FCA/, campaigns/, personas/, prompts/, tools/, fyn/, *.docx, May/May1Updates/deployFynFix.md) were deliberately NOT staged.

## WIP commit

- **None made this session.** Justified deviation from the context-handover skill's blanket-WIP rule: all mission work is already committed+pushed via PRs (5d `5aca9ff`, 5e `84ff5b0`, #304 `37b5cc5` on dev; 5f `da3c265` on origin/feat/savings-store-pr5f). `.goal` is deliberately left untracked-on-disk for cross-branch + cross-`/clear` persistence (committing it to feat/savings-store-pr5f would delete it on `git checkout dev`). A blanket `git add -A` was explicitly avoided — it would sweep CSJ's untracked personal files (FCA/, campaigns/, etc.), violating the project's specific-files-only rule.
- Nothing lost. Tree is effectively clean for mission purposes.

## Open decisions

- **dev→main release timing.** dev is now ~119 commits ahead of main and carries the UNPATCHED HIGH data-leak fix (PR #313, in dev since 5c-2). `.goal` default = cut the release at a sensible 5x checkpoint. Recommended default for next session: **after PR 5f merges**, before starting 5g, cut the dev→main release (5d+5e+#304+5f + the prior backlog incl. the security fix). Pattern = PR #280: file list from `git diff origin/main..origin/dev --stat`, build `./deploy/fynla-org/build.sh`, upload, prod cache cycle, smoke fynla.org. CSJ will redirect if they want it later.
- **PR #303** stays open until CSJ does iOS verification. Not a mission blocker.
- **PR #249** PARKED — do not merge/delete (memory `reference_pr249_python_sidecar_parked.md`).

## Pick up from here (auto-continue contract — do these in order, do NOT ask)

1. **Read `/Users/CSJ/Desktop/fynla/.goal` first** (the mission contract).
2. **Finish PR 5f (#316).** It is GREEN (HEAD `da3c265`, parity 42 passed, arch green, full suite 17-baseline zero new). csjones deploy-gate then admin-merge:
   - `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && git fetch origin --quiet && git checkout feat/savings-store-pr5f && git pull origin feat/savings-store-pr5f --quiet && git rev-parse --short HEAD && php artisan cache:clear >/dev/null 2>&1 && composer dump-autoload -o >/dev/null 2>&1 && echo DEPLOYED'`
   - Tinker-smoke on chris (user id 14) the migrated services: `HouseholdPlanningService` (gatherAssetsForUser via reflection / its public wrapper, calculateISAUsage), `CashFlowCoordinator::calculateCommittedContributions($u->id)` + missing-user→0.0, `LifeEventAllocationService::findCashAccountModel` + buildExpenseFundFrom allocation order. Cross-check a joint-aware sum vs raw `forUserOrJoint` (expect MATCH=YES). Also missing-user→empty equivalents.
   - `cd /Users/CSJ/Desktop/fynla && gh pr merge 316 --merge --admin --delete-branch`
   - Return csjones to dev: `ssh ... 'cd ~/www/csjones.co/fynla-app && git checkout dev --quiet && git pull origin dev --quiet && php artisan cache:clear >/dev/null 2>&1 && composer dump-autoload -o >/dev/null 2>&1 && echo CSJONES-ON-DEV'`
3. **Cut dev→main release** (see Open decisions — default is to do it here, after 5f, before 5g; ships the HIGH security fix). If CSJ has redirected, follow that instead.
4. **Dispatch PR 5g** (AI prompt + profile, 4 files) — FULLY PRE-AUDITED, brief below; create branch `feat/savings-store-pr5g` off fresh dev in `/Users/CSJ/Desktop/fynla`, dispatch general-purpose implementer with the brief, then reviewer, then csjones smoke, then admin-merge. Same cadence as 5d–5f.
5. **Then PR 5h** (Agents + savings-internal, 6 files), **PR 6** (derived columns + snapshot, plan Task 6 ~L2124 — much bigger), **PR 7** (tier-cap, plan Task 7 ~L2664), **PR 8** (final sweep, plan Task 8 ~L2816). Then **pass-1 acceptance gate** (plan §"Acceptance gate" ~L2913, 8 checks).
6. Self-manage context: when tripwire fires again → context-handover → /clear → session-start.

## PR 5g pre-audit (USE THIS — do not re-audit; saves a full context cycle)

Cluster table: plan ~L2029. 4 files. SavingsStore API: `forUser(User):Collection` is JOINT-AWARE (`forUserOrJoint`); single-owner sites need Collection `->where('user_id',$X->id)` post-filter; Carbon-cast `created_at` comparisons must use `->filter(fn($a)=>$a->created_at > $cut)` NOT Collection `where('created_at','>',...)`; `->latest('id')` → `->sortByDesc('id')->first()`. Joint ISAs illegal — no joint-ISA fixtures.

- **`app/Services/AI/AdvicePromptBuilder.php:756`** — `SavingsAccount::where('user_id',$userId)->orWhere('joint_owner_id',$userId)->get()` = JOINT-AWARE. Enclosing scope has `$userId` (int) inside the prompt-build method; the `$valueWithShare` closure (L741) uses `$record->user_id === $userId`. Need the User object in scope (find it — method likely has `User $user`; `$userId=$user->id`). → `app(\App\Services\Stores\SavingsStore::class)->forUser($user)` — NO post-filter (joint-aware 1:1). Feeds `->isNotEmpty()` + `->map(...)` prompt text. (Lines 528/540/565 are Property/Goal/LifeEvent — OUT of scope.)
- **`app/Services/AI/DuplicateAcknowledgement.php:220`** in `savingsDescriptors(User $user, array $extracted)` — `SavingsAccount::query()->where('user_id',$user->id)->where('institution',$institution)->where('is_isa',$isIsa)->where('created_at','>',$cutoff)->latest('id')->first()`. SINGLE-OWNER. → `app(SavingsStore::class)->forUser($user)->where('user_id',$user->id)->where('institution',$institution)->where('is_isa',$isIsa)->filter(fn($a)=>$a->created_at > $cutoff)->sortByDesc('id')->first()`. `$cutoff = now()->subHours(24)` (Carbon).
- **`app/Services/UserProfile/ProfileCompletenessChecker.php:203`** in `hasAssets(User $user)` — `SavingsAccount::where('user_id',$user->id)->exists()`. SINGLE-OWNER existence. → `app(SavingsStore::class)->forUser($user)->where('user_id',$user->id)->isNotEmpty()`.
- **`app/Services/Onboarding/AssetCaptureEntityExtractor.php:226`** in `savingsPersistedKeys(User $user, Carbon $cutoff)` — `SavingsAccount::query()->where('user_id',$user->id)->where('created_at','>',$cutoff)->get(['institution','account_name','is_isa'])` foreach. SINGLE-OWNER. → `app(SavingsStore::class)->forUser($user)->where('user_id',$user->id)->filter(fn($a)=>$a->created_at > $cutoff)` then foreach (drop column projection — full models fine). (Plan §5.5 imprecisely says "name match" — the ACTUAL filter is user_id + created_at>cutoff; preserve that, the identity-key/name logic is in `savingsIdentityKey`.)
- **Arch allowlist:** remove all 4 entries (`App\Services\AI\AdvicePromptBuilder`, `App\Services\AI\DuplicateAcknowledgement`, `App\Services\UserProfile\ProfileCompletenessChecker`, `App\Services\Onboarding\AssetCaptureEntityExtractor`). Each file has only import + query (no `::class`/type-hint/instanceof per pre-audit) so all 4 fully clear — but the implementer must grep each post-edit to confirm no residual SavingsAccount ref before removing its allowlist line (5f learned this the hard way: HPS/LEAS had to STAY due to residual refs). Replace imports with `use App\Services\Stores\SavingsStore;` (+ `use App\Models\User;` if a site introduces `User::find`; none of the 4 5g sites take a bare int-userId without a User in scope — AdvicePromptBuilder:756 has a User in the method; verify).
- **Parity tests:** append `// PR 5g parity tests — AI prompt + profile cluster` to `tests/Feature/Stores/SavingsReadConsumerParityTest.php`. Must include: AdvicePromptBuilder:756 joint-INCLUSION (seed joint NON-ISA where user is secondary owner, assert it appears in the SAVINGS prompt line); DuplicateAcknowledgement 24h-cutoff + latest-id parity; ProfileCompletenessChecker true/false; AssetCaptureEntityExtractor cutoff-filtered keys. NO joint-ISA fixtures.

## What the next Claude needs to know

- **`~/.ssh/fynlaDev` is loaded in ssh-agent** (no passphrase prompt). `ssh-add -l` shows "Fynla Dev (ED25519)". csjones alias `u163-ptanegf9edny@ssh.csjones.co:18765`, path `~/www/csjones.co/fynla-app` (real git checkout tracking origin/dev).
- **Deploy gate (memory `feedback_deploy_gate_csjones_before_admin_merge.md`):** deploy FEATURE branch to csjones + smoke BEFORE `gh pr merge --admin`, then return csjones to dev AFTER. Backend-only PRs use tinker smoke on chris (no browser-MFA). `gh pr merge --merge --admin --delete-branch` is the established solo-author pattern (`feedback_admin_merge_pattern_for_solo_reviewer_prs.md`).
- **Working location:** main checkout `/Users/CSJ/Desktop/fynla`; prefix every Bash with `cd /Users/CSJ/Desktop/fynla &&` (the harness cwd is the worktree `.claude/worktrees/cranky-lewin-6bc99c` and resets each call). The worktree needed vendor/node_modules/.env symlinked from main (already done; survives /clear). Work on feature branches off dev in the main checkout (memory `feedback_never_switch_branches`); do NOT use the worktree branch for mission work.
- **Joint ISAs are ILLEGAL** (memory `feedback_joint_isa_illegal.md`, MEMORY.md top-laws). Never seed/test `is_isa=true`/`account_type∈{isa,cash_isa,lifetime_isa}` + `joint_owner_id`/joint ownership. Joint NON-ISA savings ARE valid (use for joint-inclusion tests). Reject any reviewer/plan ask for a joint-ISA fixture.
- **Pest baseline = exactly 17 pre-existing failures**, zero new tolerated: GenerateTitleSanitisationTest ~7 (BindingResolutionException), HasAiChatSummarisationTest ~7 (BindingResolutionException), DirectWriteCoverageTest 1, ProviderSwapLockTest 2. Out of scope for 5x — never bundle.
- **Subagent cadence:** general-purpose for implementer (give the FULL self-contained brief — they don't see this convo), feature-dev:code-reviewer for review. Always independently verify subagent claims (grep the diff, check arch+parity yourself) before merging. Reviewer findings are fixed in-PR via SendMessage back to the SAME implementer agent (it retains context); SendMessage REQUIRES a `summary` field when message is a string (a 5d SendMessage errored without it — harmless, never delivered).
- **Spawned task open (chip):** "Add tenants_in_common to SavingsStore ownership_type validation" — review Finding 2, a real write-path bug at `SavingsStore.php:184` (`'sometimes|in:individual,joint,trust'` missing `tenants_in_common`). Out of scope for 5x read PRs. CSJ can spin it up or it can be a dedicated follow-up PR.
- **Parity test file** `tests/Feature/Stores/SavingsReadConsumerParityTest.php` and the arch allowlist `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` are appended/edited by EVERY 5x sub-PR — this is why 5g/5h/6/7/8 MUST stay sequential (concurrent branches merge-conflict on these two files). Do not parallelise them.
- **Plan key refs:** §5.1 recipe ~L2032, cluster table ~L2019, §5.5 notes ~L2116, Task 6 ~L2124, Task 7 ~L2664, Task 8 ~L2816, acceptance gate ~L2913. Spec at `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`.
- **5x progress:** 5a✅ 5b✅ 5c-1✅ 5c-2✅ 5d✅ 5e✅ | 5f🔄(ready-to-merge #316) | 5g⏳(pre-audited) 5h⏳ → then 6,7,8 → acceptance gate. 6 of 8 5x sub-clusters effectively done.

## Branch / deploy state

- Main checkout branch: `feat/savings-store-pr5f` (clean re mission; `.goal` + CSJ personal files untracked).
- dev tip: `37b5cc5` (5d+5e+#304 merged). origin/dev.
- PR #316 (5f): branch `feat/savings-store-pr5f`, HEAD `da3c265`, pushed, GREEN, **awaiting csjones smoke + admin-merge**.
- dev ahead of main: ~119 commits (incl. unshipped HIGH security fix from #313). main unchanged since PR #280 (11 May).
- csjones.co/fynla: on `dev` at `37b5cc5` (synced after #304). fynla.org: unchanged (PR #280 baseline).
- Open PRs: #316 (5f, ready), #303 (mobile, iOS-gated, open), #249 (PARKED).
- No new memory files beyond `feedback_joint_isa_illegal.md` (written this session, indexed).
