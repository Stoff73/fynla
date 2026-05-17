---
type: handover
mode: context-clear
date: 2026-05-16
session: 1
branch: fix/baseline-remaining-failures (harness worktree cranky-lewin-6bc99c, off origin/dev ce9fc35)
trigger: context-handover skill (context tripwire ~199k)
---

# Context Clear Handover — 2026-05-16, Session 1

## Immediate state

`composer install` JUST COMPLETED (exit 0) in the harness worktree, replacing the
`vendor` symlink with a real local `vendor/` dir. The very next action is to re-run
the pest suite under PHP 8.3 + the isolated test DB to measure the TRUE baseline —
the previous "2636 failed" was a worktree-symlink artifact, now fixed. NO code
changes have been made on this branch; all work so far is diagnosis + the env fix.

## The thread

- CSJ instruction: "merge 320, forget iOS/#303 entirely, fix the 313 17 failures, then review the plan."
- **PR #320 admin-merged** to dev (`ce9fc35`) — the 3 baseline test-defect fixes (TierGate bootstrap / stale DB::transaction assertion / Faker-flaky investment fixture). Confirmed on origin/dev.
- **iOS / PR #303 — DROPPED per CSJ** ("different OS, code & deployment cycle"). Do not touch it this thread.
- Created branch `fix/baseline-remaining-failures` off `origin/dev` IN the harness worktree (main checkout is held by a parallel instance on `fix/advice-prompt-jointowner-lazyload` per session-7 handover — DO NOT touch it).
- "313 17 failures" = the documented baseline from PR #313's body and memory `feedback_never_artisan_env_testing.md`: **17 failed / 25 skipped / ~3696 passed** = `GenerateTitleSanitisationTest` ×7 + `HasAiChatSummarisationTest` ×7 + `DirectWriteCoverageTest` ×1 + `ProviderSwapLockTest` ×2 (all AI/chat, pre-existing). PR #320 fixed a *different* 3, so the genuine remaining set should be ≈ those 17 (NOT minus 3 — PR #320's 3 were separate defects; the true remaining is to be re-measured).
- Ran full pest in the worktree → **2636 failed / 1107 passed**. Spent the session diagnosing this (it is NOT a code regression):
  - REJECTED theory: PHP 8.5 incompat — disproved (raw bootstrap probe works under 8.5; artisan db:seed/migrate work under 8.5).
  - REJECTED theory: PHP version generally — disproved (installed php@8.3 = 8.3.31, still 24/24 fail on the 4 suspect files).
  - REJECTED theory: stale autoload classmap — disproved (`composer dump-autoload -o` added PermissiveTierGate, still failed).
  - **CONFIRMED root cause:** worktree `vendor` was a **symlink** → `/Users/CSJ/Desktop/fynla/vendor` (main repo). Pest resolves the autoload root as the main repo, so it generates test classes in namespace `P\claude\worktrees\crankylewin6bc99c\tests\...` and `tests/Pest.php`'s directory-scoped `uses(TestCase::class)->in('Feature'|'Unit/Traits'|...)` bindings NEVER match the `.claude/worktrees/...` paths. So container/DB tests don't extend `Tests\TestCase` → no `$this->app` → "facade root not set" / "TierGate not instantiable" cascade. Pure-logic tests (e.g. `IngestSourceTest`) still pass — hence 1107 pass / 2636 fail. Hard evidence in full-run log: `Undefined property: P\claude\worktrees\crankylewin6bc99c\tests\Feature\Mobile\InsightsTest::$app`.
  - **FIX APPLIED:** `rm vendor && /usr/local/opt/php@8.3/bin/php composer install` → real worktree-local `vendor/` (done, exit 0). Isolated: the symlink was worktree-local; main repo + parallel-instance worktrees untouched.
- Plan review delivered to CSJ in-chat (sub-project 1 pass-1: all 8 tasks/PRs merged, acceptance gate certified 8/8 per session-7 handover, pass 2 unplanned = spec §15.3, release PR #317 dev→main is the key CSJ-gated item carrying the HIGH data-leak fix #313).

## Files touched this session

NONE tracked. No code edits. Only: PR #320 merged via `gh` (lands on origin/dev, not this branch); `vendor/` symlink replaced with real install (gitignored — invisible to git). `git status --short` is clean. Isolated test DB `laravel_testing_blf` created + migrated.

## WIP commit

- None — there are zero code changes to commit on this branch (clean tree). Nothing lost; all state is in this handover.

## Open decisions

- None requiring CSJ. CSJ's directives are unambiguous: fix the genuine remaining test failures (loop until green per CLAUDE.md Rule #15), then the plan review (already delivered). Auto-resume should just continue the loop.

## Pick up from here (auto-continue contract)

Work in the harness worktree `/Users/CSJ/Desktop/fynla/.claude/worktrees/cranky-lewin-6bc99c`, branch `fix/baseline-remaining-failures`. `vendor/` is now a REAL dir (do not re-symlink).

1. **Re-measure the true baseline** (vendor is now real, so Pest path resolution is correct):
   ```bash
   cd /Users/CSJ/Desktop/fynla/.claude/worktrees/cranky-lewin-6bc99c
   DB_DATABASE=laravel_testing_blf /usr/local/opt/php@8.3/bin/php ./vendor/bin/pest --compact 2>&1 | tee /tmp/pest-baseline-v2.log | tail -40
   ```
   Expectation: failures collapse from 2636 toward the documented ~17 (AI/chat). If it's now ~17, the symlink WAS the whole 2636 story and the genuine work is just those 17.
2. **Sanity-check the 4 suspect files first** (faster than the full suite):
   ```bash
   DB_DATABASE=laravel_testing_blf /usr/local/opt/php@8.3/bin/php ./vendor/bin/pest tests/Unit/Traits/GenerateTitleSanitisationTest.php tests/Unit/Traits/HasAiChatSummarisationTest.php tests/Feature/AI/DirectWriteCoverageTest.php tests/Feature/AI/ProviderSwapLockTest.php 2>&1 | grep -vE "PHP Deprecated|setAccessible" | tail -30
   ```
   If these now PASS → the genuine baseline may already be green and the "17" were ALSO symlink artifacts. If they still fail with real assertion errors (not "facade root"/"not instantiable") → those are the genuine bugs to fix. The implementations already look correct on inspection: `HasAiChat::generateTitle` (HasAiChat.php:864 — strip_tags + 100-cap + ellipsis), `HasAiChat::summariseToolResult` (HasAiChat.php:1005 — entity_id/entity_type priority + 5-key cap), `HasAiGuardrails::getAiProviderForLoop` (HasAiGuardrails.php:55 — versioned key + legacy fallback). So genuine failures, if any, are subtle (e.g. test expects `app(CoordinatingAgent)` resolves — needs container; or admin `/api/admin/ai-provider` endpoint behavior for ProviderSwapLockTest's HTTP cases).
3. **Loop per CLAUDE.md Rule #15**: diagnose with file:line evidence → fix root cause → re-verify under php@8.3 + `laravel_testing_blf` → repeat until the suite is at/under the documented clean baseline (17 or fewer, ideally 0 for the AI/chat set if they turn out to be fixable real bugs).
4. When green: open a PR `fix/baseline-remaining-failures` → `dev` (admin-merge pattern per memory `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`; test-only changes need no csjones smoke).

## What the next Claude needs to know

- **Environment quirks (critical):**
  - PHP 8.3 was installed this session: `/usr/local/opt/php@8.3/bin/php` (8.3.31). composer.lock platform = PHP 8.3.30. Default `php` = 8.5.2 (works for artisan but emits deprecation noise). Use the 8.3 binary for pest.
  - Isolated test DB `laravel_testing_blf` is created + migrated (`DB_DATABASE=laravel_testing_blf ... migrate:fresh --force` already run). Reuse it; do NOT run migrate:fresh on the `laravel` dev DB (see memory `feedback_never_artisan_env_testing.md`).
  - `vendor/` in THIS worktree is now a real `composer install` (php@8.3), NOT a symlink. Keep it real — re-symlinking reintroduces the 2636-failure artifact.
  - Never `php artisan --env=testing` for DB ops (memory law). Never migrate:fresh the dev DB.
- **Do NOT touch the main checkout** (`/Users/CSJ/Desktop/fynla`, branch `fix/advice-prompt-jointowner-lazyload` — a parallel instance). The handover file was written to `/Users/CSJ/Desktop/fynla/May/May16Updates/` by filesystem write only; it is NOT git-committed (committing would pollute the parallel instance's branch). session-start finds it by path regardless.
- The "2636" log is at `/tmp/pest-baseline-run.log` (pre-fix, for reference only).
- Memory `feedback_never_artisan_env_testing.md` documents the exact clean baseline composition (17 = the 4 AI/chat files). Read it.
- PR #320 is merged; do NOT re-do it. iOS/#303 is dropped; do NOT touch it.

## Branch / deploy state

- Branch: `fix/baseline-remaining-failures` (local only, off origin/dev `ce9fc35`; never pushed — no commits on it yet).
- origin/dev tip: `ce9fc35` (PR #320 merged). origin/main behind dev by ~141 commits (release PR #317 OPEN, CSJ-gated — carries HIGH data-leak fix #313; production deploy is CSJ's manual SiteGround upload).
- Deploy status: nothing deployed this session. csjones still on dev `9b82a52` (now one merge behind dev tip ce9fc35 — #320 is test-only, no csjones action needed).
- Open PRs: #317 (release, CSJ-gated), #303 (iOS — DROPPED per CSJ), #249 (PARKED).
