---
type: handover
mode: context-clear
date: 2026-05-15
session: 3
branch: feat/savings-store-pr5g (main checkout) / claude/cranky-lewin-6bc99c (worktree harness root)
trigger: context-handover skill (tripwire ~391k tokens)
previous_session: 2026-05-15 session 2 (shipped 5d/5e/#304, opened release #317)
---

# Context Clear Handover — 2026-05-15, Session 3

## Immediate state

Autonomous mission run (CSJ `.goal`). PR 5g (#318) review-tightening just completed by a background agent — **#318 is GREEN (HEAD `d314d2d`, parity 47 passed, full suite 17-baseline zero new) and ready for csjones smoke + admin-merge.** That is the literal next action.

## The mission (READ `/Users/CSJ/Desktop/fynla/.goal` FIRST)

`.goal` (untracked file at repo root — DON'T commit it to a feature branch or it vanishes on `git checkout dev`; it persists on disk across /clear) = deliver all open PRs + fully implement `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` (PRs 5d→5h, 6, 7, 8), e2e test each, cut dev→main release, self-manage context (tripwire→handover→/clear→session-start). Continue WITHOUT asking until roadmap delivered or CSJ redirects. Note on the /clear mechanism: the model CANNOT self-issue /clear (no such tool); the handover is the durable safety net and the conversation auto-compacts, so the next instance just continues from "Pick up from here" — momentum over idling.

## The thread (this session's arc)

- Resumed from session-2 handover. PR 5f (#316): csjones smoke green (calculateISAUsage=5460, missing-user→0, findCashAccountModel real/null guards correct) → admin-merged dev `42423da`.
- Opened **dev→main release PR #317** (127 commits, ships unpatched HIGH data-leak fix #313). Did NOT admin-merge it: production go-live = CSJ manual SiteGround upload (Key Rule #1) + irreversible prod action. Left ready-for-CSJ with full deploy plan in PR body.
- PR 5g (#318) AI prompt+profile (4 files): implemented (general-purpose agent) → reviewed (feature-dev:code-reviewer) → APPROVE. Reviewer resolved the `preventLazyLoading(false)` test deviation as parity-neutral (prod has preventLazyLoading FALSE; both old/new paths equally lazy on jointOwner; no $with diff). One non-blocking test-strength tweak (highest-id test didn't isolate id from created_at) → sent back to same implementer agent → applied; tightened test PASSES (confirms `sortByDesc('id')` correct, no prod fix needed). #318 HEAD `d314d2d`.
- Spawned 2 out-of-scope tasks (chips): (1) SavingsStore.php:184 `tenants_in_common` missing from ownership_type enum (write-path bug, PR 5f review Finding 2); (2) AdvicePromptBuilder `ownershipLabel` jointOwner lazy-load throws LazyLoadingViolationException on staging/csjones for joint accounts w/ null joint_owner_name (pre-existing, surfaced PR 5g review — affects AdvicePromptBuilder csjones smokes).

## Files touched this session

Via merged PR #316 (5f) on dev, open PR #318 (5g). No loose uncommitted mission code (agents commit+push). Only untracked artifact: `/Users/CSJ/Desktop/fynla/.goal` (intentional). CSJ personal untracked files (FCA/, campaigns/, personas/, prompts/, tools/, fyn/, *.docx) NOT staged.

## WIP commit

- **None** (justified, same as session-2): all mission work committed+pushed via PRs (5f `42423da` dev; 5g `d314d2d` on origin/feat/savings-store-pr5g). `.goal` deliberately untracked-on-disk for cross-branch + cross-/clear persistence. Blanket `git add -A` avoided (would sweep CSJ personal files — project rule). Nothing lost.

## Open decisions

- **Release PR #317 (dev→main)**: OPEN, ready. Production deploy is CSJ-gated (manual SiteGround build upload, Key Rule #1) — NOT auto-mergeable by the model into an undeployed gap. Default: leave for CSJ; do NOT admin-merge #317 autonomously. Ships the HIGH security fix when CSJ deploys.
- **PR #303** (mobile taxconfig): code-approved, iOS-gated — leave OPEN for CSJ iOS verification. Not mission-blocking.
- **PR #249** PARKED — never merge/delete.

## Pick up from here (auto-continue contract — in order, do NOT ask)

1. **Read `/Users/CSJ/Desktop/fynla/.goal`.**
2. **Finish PR 5g (#318)** — GREEN (HEAD `d314d2d`, parity 47 passed, arch green, suite 17-baseline). csjones deploy-gate then admin-merge:
   - `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && git fetch origin --quiet && git checkout feat/savings-store-pr5g && git pull origin feat/savings-store-pr5g --quiet && git rev-parse --short HEAD && php artisan cache:clear >/dev/null 2>&1 && composer dump-autoload -o >/dev/null 2>&1 && echo DEPLOYED'`
   - Tinker-smoke on chris (id 14): `ProfileCompletenessChecker::hasAssets` (reflection), `DuplicateAcknowledgement::savingsDescriptors($u,[])` , `AssetCaptureEntityExtractor::savingsPersistedKeys($u, now()->subDays(30))` (reflection), and a joint-aware parity check for AdvicePromptBuilder. **CAUTION:** `app(AdvicePromptBuilder::class)->buildExistingRecordsSummary($u)` may throw `LazyLoadingViolationException` on csjones (staging, preventLazyLoading=TRUE) IF chris has a joint savings acct with null joint_owner_name — this is the PRE-EXISTING defect (spawned task #2), NOT a 5g regression. If it throws, verify via a non-prompt parity check (raw `forUserOrJoint` sum == store `forUser` sum) instead, note the pre-existing issue, and proceed — do NOT block 5g merge on the pre-existing lazy-load.
   - `cd /Users/CSJ/Desktop/fynla && gh pr merge 318 --merge --admin --delete-branch`
   - Return csjones to dev: `ssh ... 'cd ~/www/csjones.co/fynla-app && git checkout dev --quiet && git pull origin dev --quiet && php artisan cache:clear >/dev/null 2>&1 && composer dump-autoload -o >/dev/null 2>&1 && echo CSJONES-ON-DEV'`
3. **PR 5h** — Agents + savings-internal, 6 files: `app/Agents/SavingsAgent.php`, `app/Agents/InvestmentAgent.php`, `app/Agents/CoordinatingAgent.php` (read calls only — NOTE its `checkForDuplicate(SavingsAccount::class,...)` ~L2069 is a `::class` ref + it may retain other residual refs → likely STAYS allowlisted like HPS/LEAS; pre-audit carefully), `app/Services/Savings/ISATracker.php`, `app/Services/Savings/SavingsActionDefinitionService.php`, `app/Models/Goal.php` (relationship reads only — plan §5.5: leave `$goal->savingsAccounts` relationship reads as-is, only direct `SavingsAccount::query()` moves; Goal likely STAYS allowlisted for the relationship method). Create `feat/savings-store-pr5h` off fresh dev in `/Users/CSJ/Desktop/fynla`; pre-audit each site (grep `SavingsAccount::`/`::class`/`?SavingsAccount`/`instanceof`), then dispatch general-purpose implementer with the full recipe (see "cadence" below), reviewer, csjones smoke, admin-merge.
4. **PR 6** (plan Task 6 ~L2124 — derived columns + snapshot table; materially BIGGER, has migrations — read the whole Task 6 first), **PR 7** (Task 7 ~L2664 tier-cap), **PR 8** (Task 8 ~L2816 final sweep + audit ingest_source).
5. **Pass-1 acceptance gate** (plan ~L2913, 8 checks).
6. Self-manage context: tripwire → context-handover → (auto-compact) → continue.

## What the next Claude needs to know

- **Cadence per 5x PR (proven across 5d–5g):** pre-audit read sites (read the enclosing method — is it joint-aware `forUserOrJoint`/`where->orWhere(joint_owner_id)` → `forUser($user)` NO post-filter; or single-owner `where('user_id')` → `forUser($user)->where('user_id',$X->id)` post-filter; int-userId → null-guarded `User::find` + `collect()` empty-equiv; Carbon `created_at` → `->filter(fn)` NOT Collection where; `->latest('id')` → `->sortByDesc('id')->first()`; `->orderByDesc` → `->sortByDesc()->values()`). Dispatch general-purpose implementer with a FULL self-contained brief (TDD parity test first in `tests/Feature/Stores/SavingsReadConsumerParityTest.php` appended `// PR 5x` section, mechanical migration, arch-allowlist trim, full pest to 17-baseline, pint, commit+push+`gh pr create --base dev`). Then feature-dev:code-reviewer. Independently verify (grep diff, arch+parity yourself). Reviewer findings fixed in-PR via SendMessage to the SAME implementer agent (`summary` field REQUIRED when message is a string). Then csjones deploy-gate (feature branch ssh, tinker smoke chris) → `gh pr merge --merge --admin --delete-branch` → return csjones to dev.
- **Residual-reference rule (learned 5f):** if a file retains a non-migratable `SavingsAccount` ref (`::class` in polymorphic array, `?SavingsAccount` return type, `instanceof`, dynamic `$modelClass::where`), it STAYS arch-allowlisted with an accurate documented comment (DocumentProcessor precedent). Don't force-remove its allowlist entry. CoordinatingAgent + Goal in 5h likely fall here — pre-audit before assuming full removal.
- **Joint ISAs are ILLEGAL** (memory `feedback_joint_isa_illegal.md`). Never seed/test is_isa=true (or account_type∈{isa,cash_isa,lifetime_isa}) + joint_owner_id/joint ownership. Joint NON-ISA valid (use for joint-inclusion tests).
- **Pest baseline = exactly 17 pre-existing** (GenerateTitleSanitisationTest 7, HasAiChatSummarisationTest 7, DirectWriteCoverageTest 1, ProviderSwapLockTest 2). Zero new tolerated. Never bundle.
- **`~/.ssh/fynlaDev` loaded in ssh-agent.** csjones `u163-ptanegf9edny@ssh.csjones.co:18765`, path `~/www/csjones.co/fynla-app`, real git checkout tracking origin/dev, APP_ENV=staging (preventLazyLoading=TRUE — lazy-load violations throw there). Deploy-gate: feature branch → smoke → admin-merge → return to dev (memory `feedback_deploy_gate_csjones_before_admin_merge.md`). `gh pr merge --merge --admin --delete-branch` is the solo-author pattern.
- **Working dir:** main checkout `/Users/CSJ/Desktop/fynla`; prefix every Bash `cd /Users/CSJ/Desktop/fynla &&` (harness cwd is the worktree, resets each call). Feature branches off dev in main checkout. Worktree branch is NOT for mission work.
- **Parity test + arch allowlist are shared, appended by every 5x PR → 5h/6/7/8 MUST stay sequential** (concurrent branches merge-conflict). Do not parallelise.
- **Two spawned task chips open** (out of scope, real): SavingsStore tenants_in_common enum; AdvicePromptBuilder jointOwner lazy-load. CSJ may spin up or they're follow-up PRs.
- **Plan refs:** §5.1 recipe ~L2032, cluster table ~L2019, §5.5 ~L2116, Task 6 ~L2124, Task 7 ~L2664, Task 8 ~L2816, acceptance gate ~L2913.

## Branch / deploy state

- Main checkout branch: `feat/savings-store-pr5g` (clean re mission; `.goal` + CSJ personal files untracked).
- dev tip `42423da` (5d+5e+5f+#304). PR #318 (5g) branch `d314d2d` pushed, GREEN, **awaiting csjones smoke + admin-merge**.
- dev ahead of main: 127 commits (incl. unshipped HIGH security fix #313). main unchanged since PR #280.
- csjones.co/fynla: on `dev` `42423da`. fynla.org: unchanged (PR #280 baseline).
- Open PRs: #318 (5g, ready-to-merge), #317 (release dev→main, CSJ-gated prod deploy), #303 (mobile, iOS-gated), #249 (PARKED).
- 5x progress: 5a–5f ✅ merged; 5g ✅ ready-to-merge (#318); 5h ⏳ next; then 6,7,8 → acceptance gate. New memory this run: `feedback_joint_isa_illegal.md`.
