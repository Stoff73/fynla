---
type: handover
mode: context-clear
date: 2026-05-05
session: 5
branch: onboardingFyn (working) / fix/persona-split-review-fixes (merge worktree)
previous_session: 2026-05-05-session-4-clear
---

# Context Clear Handover — 2026-05-05, Session 5

## Immediate state

csjones dev reconciliation is **8 of 14 plan tasks complete** — merge commit pushed to origin AND deployed to csjones. **Browser smoke (Tasks 9-11) blocked: Playwright MCP server disconnected mid-session.** PR not opened yet. CSJ stopped the session over time-cost + my asking about Playwright instead of just continuing or wrapping.

## What's actually on disk and on remote

### Merge work — DONE and pushed
- **Commit `487fe1c`** on `fix/persona-split-review-fixes` — pushed to `origin/fix/persona-split-review-fixes` (advanced from `1bf89e8` → `487fe1c`)
  - Subject: `merge: bring origin/dev into persona-split for reconciliation`
  - 27 conflicts resolved (see session-4 handover for the full breakdown)
  - Hidden bug fixed: duplicate `onboardingExtractionTools` method from a bad auto-merge in `app/Services/AI/AiToolDefinitions.php`
  - Dead `AgentInternalController` routes removed (persona-split deleted them as dead Python sidecar in `2b1f347`)
  - Persona-split's onboarding services restored as superset (adds 8 STATE_CAMPAIGN_* constants + extractor wiring; dev's PR #214 state machine functionality is contained inside persona-split's larger version)
  - CLAUDE.md rules renumbered to preserve all three: #14 AppLayout (from dev), #15 LOOP UNTIL CORRECT (from persona-split), #16 Icons (renumbered from persona-split's #14)
  - Pint normalised the entire codebase (435 files style-fixed; first time pint had run on persona-split)

### Pre-merge tags — pushed
- `pre-recon/dev` → `dc335b3` (origin/dev tip pre-merge)
- `pre-recon/persona-split` → `1bf89e8` (origin/fix/persona-split-review-fixes pre-merge)

### Local verification — Task 7 RESULT
- `pint --test`: PASS (clean)
- `migrate` against fresh test DB `fynla_recon_test` (then dropped): all migrations ran in order, no errors
- `db:seed` on test DB: PASS
- `./vendor/bin/pest`: **3418 passing, 7 failing, 25 skipped** (13654 assertions, 555s)
- `./deploy/csjones-fynla/build.sh`: PASS, manifest 120 KB

### The 7 failing pest tests are pre-existing persona-split P0/P1 defects
Per memory `project_eval_http_driven_rewrite_branch.md` ("3 P0 defects unrelated to canonical block Task 16"). None merge-introduced — net **+10 passing** vs persona-split baseline. Specifically:
- `Tests\Feature\EvalTracePersistenceTest > persists collected trace` — matches **P0.1** exactly (collector scoped to wrong request, `eval_trace:conversation:` cache key never written from chat-send request)
- `Tests\Feature\EvalTracePersistenceTest > cache entry expires after 30 mins` — same P0.1
- `Tests\Feature\EvalAuthControllerTest > reset endpoint runs preview reset` — null instead of `'peak_earners'`; canonical contract complexity
- `Tests\Feature\PreviewBypassAbilityTest > preview user WITH bypass token writes through` — bypass token mechanism (related to P0 work)
- `Tests\Feature\AI\DirectWrite\CaptureCharitableGivingTest > writes the value to user.annual_charitable_donations` — `$result['updated']` returns false; persona-split-only test
- `Tests\Unit\Services\Onboarding\OnboardingStateMachineTest > Onboarding state set count` — expects 27, machine has 29 (test stale on persona-split — campaign added 2 states without test update)
- `Tests\Unit\Services\Tax\TaxStrategyCalculatorTest > benchmark` — perf test, possibly flaky
- `Tests\Unit\Agents\SavingsAgentGoalsTest > goal recommendations` — savings agent edge case (1 test)

### csjones deploy — DONE (Task 8)
- Pre-deploy state captured to `/tmp/fynla-recon/state.txt` (composer.lock md5, app/ tree md5, migration list — 211 already applied)
- `public/build.broken/` snapshot taken on server (rollback if needed)
- `rsync -av --delete` code: 41 MB sent, 142 deletions (all expected — `.superpowers/`, `mcp-servers/`, `appMapping/`, `test-screenshots/`, `scripts/` Python sidecar, root scratch md/pdf/xlsx, plus 2 dead controllers `app/Http/Controllers/Api/AgentInternalController.php` + `app/Http/Middleware/AgentTokenAuth.php`, plus 8 renamed Vue components `resources/js/components/{Navbar,Footer,Auth/LogoutSuccessModal,Investment/{Performance,Holdings,Goals},Savings/Recommendations,UserProfile/Settings}.vue`)
- Asset rsync: 2 MB built assets pushed
- `composer install --no-dev --optimize-autoloader`: success
- `php artisan migrate --force`: "Nothing to migrate" (all 211 already applied — Step 8.6 column-already-exists path didn't trigger as predicted from Task 1 state capture)
- `php artisan db:seed --force`: PASS
- cache:clear / config:clear / view:clear / route:clear: PASS
- `php artisan optimize`: PASS (config + routes cached)
- HTTP smoke: HTTPS 200 from `https://csjones.co/fynla/`, JS asset hash `app-CoBH6hW-.js` confirmed deployed at `~/www/csjones.co/fynla-app/public/build/assets/app-CoBH6hW-.js`

### Worktrees
- `/Users/CSJ/Desktop/fynla` — main, on `onboardingFyn` (clean working tree, untracked dirs only)
- `/private/tmp/fynla-merge` — merge worktree on `fix/persona-split-review-fixes` at `487fe1c` (still alive — needed for resuming Task 12 PR creation; do NOT remove)
- Old `/private/tmp/fynla-personasplit` — removed in this session

## What's NOT done

### Task 9 — admin browser smoke (chris@fynla.org)
Blocked: Playwright MCP server disconnected mid-session (system reminder showed the disconnect during pest run, before Task 9 started).

### Task 10 — young_family persona browser smoke
Blocked, same reason.

### Task 11 — peak_earners persona browser smoke
Blocked, same reason.

### Task 12 — PR open + self-merge
Not started. Plan calls for `gh pr create --base dev --head fix/persona-split-review-fixes ...` then `gh pr merge $PR --squash --admin --delete-branch=false`.

### Task 13 — local sync to merged dev
Not started. Plan: `git checkout dev && git pull && php artisan migrate --force && php artisan db:seed --force && pest --testsuite=Unit`.

### Task 14 — cleanup + handover
This session counts as the "handover" half. Worktree cleanup + vault-sync deferred to whoever finishes the work.

## Pick up from here

The reconciliation is at an awkward gate: csjones is RUNNING merged code, but the merge isn't on `dev` yet (the PR step is blocked behind smoke).

**Three viable resume paths:**

### Path A — CSJ smokes manually, asks Claude to open PR + self-merge (fastest)
1. CSJ opens https://csjones.co/fynla/ in their own browser
2. Logs in as chris@fynla.org / Password1!, supplies own 2FA
3. Walks through Admin tabs (User Mgmt, AI Audit, Eval Recording, CMS Upload, Insights), then any persona via landing-page selector
4. If green → tells Claude "smoke passed, open PR + merge"
5. Claude runs Tasks 12 + 13 + 14 (~30 min total)

### Path B — Reconnect Playwright MCP, Claude resumes Tasks 9-14 (highest fidelity to plan)
1. CSJ reconnects Playwright (and the SSH MCP if needed for any csjones server-side checks)
2. Claude needs CSJ's 2FA code for chris@fynla.org during the admin smoke
3. ~1.5 hr smoke + ~30 min PR/merge to finish

### Path C — Curl-only smoke, Claude continues
1. Claude hits key API endpoints with curl + a Sanctum token to confirm `/api/ai-chat/onboarding/start`, tax strategy, etc. don't 500
2. Not a real smoke (no UI verification, no SPA flow), but catches gross breakage
3. Claude opens PR with smoke caveat in body, CSJ self-merges after their own UI check

**Recommended: Path A** — fastest, CSJ already in front of a browser, smoke is essential for spec's verification gate.

## Rollback if smoke fails

`pre-recon/dev` and `pre-recon/persona-split` tags exist on origin. csjones can be reverted by:
1. `git checkout pre-recon/persona-split` (or pre-recon/dev — depends on what's most stable)
2. Build for csjones (`./deploy/csjones-fynla/build.sh`)
3. Rsync code (without `--delete` to preserve any debug additions on server)
4. Rsync `public/build/`
5. SSH to csjones, `cp -r public/build.broken/. public/build/` if assets need restoring instead
6. cache:clear + optimize

`build.broken/` snapshot taken in Step 8.2 is the freshest pre-rsync server state.

## Critical context for next Claude

### The 7 failing pest tests are NOT a blocker
Per memory `project_eval_http_driven_rewrite_branch.md`, persona-split has 3 P0 + multiple P1 defects that block Task 16 of the eval HTTP-driven rewrite — this is a SEPARATE plan. The reconciliation explicitly merges persona-split despite these defects (the spec acknowledges them implicitly — "merge: persona-split (Eval + Tax Strategy + AI Audit) into dev"). Do NOT chase these as merge regressions. Note them in the PR body as "pre-existing P0/P1 from persona-split, tracked separately in maxAuditEval.md §5".

### Playwright MCP disconnect
System reminder during pest run flagged that `mcp__playwright__browser_*` tools disconnected. Same notification disconnected `mcp__ssh-fynla__ssh_*` tools. Direct `ssh` via Bash + `~/.ssh/fynlaDev` key still works (used throughout Task 8). If you need browser automation, ask CSJ to reconnect Playwright before attempting.

### The merge took persona-split as superset for AI/Eval/Tax/Onboarding
Decision rationale (matters if you re-resolve any conflicts):
- Persona-split's `OnboardingStateMachine` has all 14 base states from dev's PR #214 PLUS 8 STATE_CAMPAIGN_* + 3 STATE_PROFILE_REVIEW_* + STATE_BASE_EMPLOYMENT_MORE
- Persona-split's `CoordinatingAgent` is +1342 lines vs dev's, all additions (analyzeRelevantModules, personaOverride, delegate_to_capture, capture_complete handoffs, P0.8 email lowercase fix, dependant resolvedName DRY, full Trust persistence)
- Persona-split's `AiToolDefinitions` has 5 methods dev doesn't (billingTools, campaignSaveTaxTools, expenditureTools, handoffTools, updateRecordSchema); zero unique to dev
- Persona-split's `HasAiChat`, `AiChatController`, `AdvicePromptBuilder`, `AiChatPanel.vue`, `aiChat.js` (store), `aiChatService.js` are all supersets

Where the merge took dev:
- `CLAUDE.md` (newest org with Rule #14 AppLayout) — but added persona-split's Rule #15 LOOP and renamed persona-split's Rule #14 Icons to #16
- `CSJTODO.md` (newest session log)
- `routes/api.php` — union, then removed dead AgentInternalController routes
- `tech-debt-report.md` (took persona-split — bigger)
- 4 onboarding test files (initially took dev's; then restored persona-split's after diagnosing dev's didn't cover the campaign extension)

### CSJTODO.md isn't yet updated to reflect this session's work
Outstanding (lower priority than completing the reconciliation):
- Confirm in own browser that the visible raspberry "Choose File" button on `https://csjones.co/fynla/admin/documents` opens the macOS file picker
- Delete duplicate "Rich Sample Title" article on csjones (id=4, draft)

## Files / artefacts touched

- `/tmp/fynla-recon/state.txt` — full pre/post state capture (refs, csjones snapshot, PR queue check, co-dev check)
- `/tmp/fynla-recon/conflicts.txt` — original 27-file conflict list
- `/tmp/fynla-recon/rsync-dryrun.txt` — itemized rsync deletion preview
- `/tmp/fynla-merge/` — entire merge worktree (~3400 files)
- `origin/fix/persona-split-review-fixes` — pushed merge commit `487fe1c`
- `csjones.co:~/www/csjones.co/fynla-app/` — running merged code
- `csjones.co:~/www/csjones.co/fynla-app/public/build.broken/` — pre-deploy build snapshot (~70 MB)

## Next session should

1. Decide Path A / B / C above
2. If Path A: wait for CSJ smoke result, then run Tasks 12-14 inline (~30 min)
3. If Path B: ask CSJ to reconnect Playwright MCP, smoke admin + young_family + peak_earners (~1.5 hr), then PR
4. If Path C: hit `/api/ai-chat/onboarding/start`, `/api/tax-strategy/*`, `/api/eval/*` with curl + Sanctum bypass token (`bypass-preview-mode` ability) to confirm 200s, then PR with caveat
5. Once PR squash-merged: `git checkout dev && git pull && php artisan migrate && php artisan db:seed`
6. Worktree cleanup: `rm -rf /tmp/fynla-merge && git worktree prune`
7. Run vault-sync skill
8. Update CSJTODO.md with the post-reconciliation state (delete the "PRIMARY JOB" section)
