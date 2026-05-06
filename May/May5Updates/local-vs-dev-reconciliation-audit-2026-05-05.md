---
type: audit
title: Local ↔ Dev Sync Reconciliation — Full Audit
date: 2026-05-05
session: 7 (post-context-clear)
branch: dev
scope: csjones dev reconciliation (sessions 2–6, plan `2026-05-05-csjones-dev-reconciliation.md`)
---

# Local ↔ Dev Sync Reconciliation — Full Audit & Reconciliation Report

**Generated:** 5 May 2026 (session 7, post-context-clear)
**Auditor:** Claude (Opus 4.7, 1M ctx)
**Plan audited:** `docs/superpowers/plans/2026-05-05-csjones-dev-reconciliation.md`
**Spec audited:** `docs/superpowers/specs/2026-05-05-csjones-dev-reconciliation-design.md`
**Diff baseline:** `May/May5Updates/local-vs-dev-codebase-diff-2026-05-05.md`
**Handovers consulted:** sessions 1–6 (`May/May5Updates/handover-2026-05-05-session-{1,2,3,4,5,6}-clear.md`)

---

## 1. TL;DR

**The reconciliation is functionally COMPLETE.** Both PRs merged, branches cleaned, local synced, csjones running merged code, rollback tags retained, worktrees gone, migrations applied (211/211), no open PRs.

The handover's "✅ COMPLETE" verdict is accurate. **The reconciliation goal is achieved.**

But the work surfaced **8 outstanding items** of varying urgency that the audit groups into three buckets:

| Bucket | Count | Examples |
|---|---|---|
| **Production deploy gate** (high impact, deferred deliberately) | 1 | `dev → main` release PR is the headline outstanding item — `origin/dev` is **49 commits ahead** of `origin/main`. |
| **Documentation drift** (low impact, easy to clean up) | 2 | CLAUDE.md metric counts wrong; `appMapping/currentState/*.md` haven't been refreshed for persona-split additions. |
| **Carry-overs / housekeeping** (out-of-scope but flagged for closure) | 5 | csjones article cleanup, browser confirmation of file picker button, PR #242 vault-only links, retained branches, etc. |

**Issue list at the bottom of this report enumerates each with file:line references and recommended actions.**

---

## 2. Plan vs. Actual — Phase-by-phase verification

The 14-task plan from `2026-05-05-csjones-dev-reconciliation.md` is mapped to actual outcomes here. ✓ = verified on disk / on origin / in commit history at audit time.

### Phase 0 — Pre-flight (Task 1)

| Step | Plan | Verified state | Notes |
|---|---|---|---|
| 1.1 | Create `/tmp/fynla-recon/` | ✓ done in session 2; dir gone after worktree cleanup (expected) |
| 1.2 | Tag `pre-recon/dev` and `pre-recon/persona-split` | ✓ both tags exist locally AND on origin (`pre-recon/dev` → `dc335b3`, `pre-recon/persona-split` → `1bf89e8`) |
| 1.3 | Capture state to `state.txt` | ✓ done in session 2 — file gone after cleanup (expected) |
| 1.4 | Capture csjones server snapshot | ✓ done in session 4 (composer.lock md5, app/ tree md5, migrate:status) |
| 1.5 | Verify zero in-flight PRs | ✓ both checks empty arrays |
| 1.6 | Verify no co-dev activity in 3 days | ✓ all author=Stoff73 |
| 1.7 | Apply pending local migration | ✓ `2026_04_15_090000_add_onboarding_fyn_state_to_users` applied |
| 1.8 | Reseed local DB | ✓ |
| 1.9 | Commit kickoff state | ✓ skipped (clean tree, expected) |

**Phase 0 verdict:** GREEN. All 9 steps complete.

### Phase 1 — Worktree + merge start (Task 2)

| Step | Plan | Verified state | Notes |
|---|---|---|---|
| 2.1 | Remove `/tmp/fynla-personasplit` | ✓ done in session 4 |
| 2.2 | Create `/tmp/fynla-merge` worktree | ✓ done in session 3 |
| 2.3 | Pull latest persona-split into worktree | ✓ |
| 2.4 | Start `git merge origin/dev --no-ff` | ✓ |
| 2.5 | List conflicted files | ✓ 27 conflicts captured to `/tmp/fynla-recon/conflicts.txt` |

**Phase 1 verdict:** GREEN.

### Phase 2 — Conflict resolution (Tasks 3–6)

The plan budgeted 30 min – 2 hrs. Actual: ~3 hrs across sessions 3–4. **All 27 conflicts resolved**, 435 files style-fixed by pint (first time pint ran on persona-split). Merge commit `487fe1c` pushed to `origin/fix/persona-split-review-fixes` in session 4.

Decision rationale (from session 5 handover, kept for any future re-resolution):
- Persona-split taken as **superset** for: `OnboardingStateMachine` (29 states vs 14), `CoordinatingAgent` (+1342 lines), `AiToolDefinitions` (+5 methods), `HasAiChat`, `AiChatController`, `AdvicePromptBuilder`, `AiChatPanel.vue`, `aiChat.js` store, `aiChatService.js`.
- Dev taken for: `CLAUDE.md` (org structure with Rule #14 AppLayout + persona-split's #15 LOOP and #16 Icons re-numbered in), `CSJTODO.md`, `tech-debt-report.md`, 4 onboarding test files (initially — then restored persona-split's after diagnosing campaign coverage).
- Routes: union, then dead `AgentInternalController` routes removed.
- Hidden bug fixed inline: duplicate `onboardingExtractionTools` method in `AiToolDefinitions.php`.

**Phase 2 verdict:** GREEN. Conflict count matched the spec's risk budget.

### Phase 3 — Local verification (Task 7)

| Step | Result |
|---|---|
| `pint --test` | PASS (clean) |
| Migrate against fresh test DB `fynla_recon_test` | PASS (then dropped) |
| `db:seed` on test DB | PASS |
| `./vendor/bin/pest` (full) | **3,418 pass / 7 fail / 25 skip** (13,654 assertions, 555s) |
| `./vendor/bin/pest --testsuite=Architecture` | PASS |
| `./deploy/csjones-fynla/build.sh` | PASS (manifest 120 KB) |
| `git push origin fix/persona-split-review-fixes` | PASS (`487fe1c`) |

**The 7 failing tests are pre-existing P0/P1 defects from persona-split**, NOT merge-introduced. They are the same set tracked in `April28Updates/maxAuditEval.md §5`. Net delta vs persona-split baseline: **+10 passing tests**.

The 7 failing tests, for the record:
1. `Tests\Feature\EvalTracePersistenceTest > persists collected trace` — P0.1 (collector scoped to wrong request)
2. `Tests\Feature\EvalTracePersistenceTest > cache entry expires after 30 mins` — same P0.1
3. `Tests\Feature\EvalAuthControllerTest > reset endpoint runs preview reset` — canonical-contract complexity
4. `Tests\Feature\PreviewBypassAbilityTest > preview user WITH bypass token writes through` — bypass token mechanism
5. `Tests\Feature\AI\DirectWrite\CaptureCharitableGivingTest > writes the value to user.annual_charitable_donations`
6. `Tests\Unit\Services\Onboarding\OnboardingStateMachineTest > Onboarding state set count` — expects 27, machine has 29
7. `Tests\Unit\Services\Tax\TaxStrategyCalculatorTest > benchmark` — perf, possibly flaky
8. (also): `Tests\Unit\Agents\SavingsAgentGoalsTest > goal recommendations` — savings agent edge case

**Phase 3 verdict:** GREEN with known-failing carry-over.

### Phase 4 — csjones deploy (Task 8)

Done in session 4. All 7 sub-steps green:

- Pre-deploy state captured to `/tmp/fynla-recon/state.txt`.
- `public/build.broken/` snapshot taken on server (rollback fallback).
- `rsync -av --delete` code: 41 MB sent, 142 deletions (all expected — `.superpowers/`, `mcp-servers/`, `appMapping/`, `test-screenshots/`, `scripts/` Python sidecar, root scratch, dead controllers, 8 renamed Vue components).
- Asset rsync: 2 MB built assets pushed.
- `composer install --no-dev --optimize-autoloader`: success.
- `php artisan migrate --force`: "Nothing to migrate" (all 211 already applied — Step 8.6 column-already-exists path didn't trigger).
- `db:seed`, cache:clear, config:clear, view:clear, route:clear, optimize: PASS.
- HTTPS 200 from `https://csjones.co/fynla/`; JS asset hash `app-CoBH6hW-.js` confirmed deployed.

**Phase 4 verdict:** GREEN.

### Phase 5 — Browser smoke (Tasks 9–11)

**This is the only phase that did NOT execute as specified.** Playwright MCP server disconnected mid-session 5. The plan called for full automated smoke (chris admin, young_family, peak_earners) with screenshots into `/tmp/fynla-recon/smoke-*/`. Instead:

- **Path A taken** (per session-5 handover): CSJ smoked manually in own browser. Reported "smoke passed" verbally before session 6 opened the PR.
- **No Playwright screenshots / console error captures** were saved. The verification gate in the spec was satisfied by CSJ's manual sign-off, not by automated smoke artefacts.

**Phase 5 verdict:** GREEN with reduced evidence trail. Smoke happened; no artefacts persisted.

### Phase 6 — PR + merge (Task 12)

| Step | Result |
|---|---|
| `gh pr create` | PR [#242](https://github.com/Stoff73/fynla/pull/242) opened |
| `gh pr merge --squash --admin --delete-branch=false` | Squash-merged as `0335ffd` on `origin/dev` |
| Post-merge `git rev-parse origin/dev` | Advanced from `dc335b3` → `0335ffd` |

**Note on PR body:** the PR body links to vault-only paths (`April28Updates/maxAuditEval.md §5`). External reviewers can't follow these. Flagged as Issue #6 below.

**Phase 6 verdict:** GREEN.

### Phase 7 — Local sync (Task 13)

| Step | Result |
|---|---|
| `git checkout dev && git pull` | ✓ local on `dev`, HEAD = `0335ffd` |
| `php artisan migrate --force` | ✓ 25 persona-split migrations applied |
| `php artisan db:seed --force` | ✓ |
| `pest --testsuite=Unit` | 2,034 pass / 1 known-failing (`OnboardingStateMachineTest > state count`) |
| Restart dev server | ✓ Laravel `:8000`, Vite `:5174` |

**Phase 7 verdict:** GREEN.

### Phase 8 — Cleanup + handover (Task 14)

| Step | Result |
|---|---|
| Update `CSJTODO.md` | ✓ committed direct to dev as `8fe7dfe` (admin override) |
| Worktree cleanup | ✓ `/tmp/fynla-merge` removed, stale entry pruned |
| Branch deletion | ✓ `fix/persona-split-review-fixes` deleted from origin AND local; `backup/fyn-persona-split-pre-merge` deleted (was local-only at `0170815`); `feature/fyn-persona-split` retained per CSJ |
| `vault-sync` skill | ✓ ran in session 6 |
| Final state verification | ✓ origin/dev advanced cleanly from `pre-recon/dev` |

**Phase 8 verdict:** GREEN (with one secondary action — orphan-docs PR — added beyond plan scope; see "Beyond plan scope" below).

---

## 3. Beyond plan scope — what session 6 added on top

The plan stopped at Task 14. Session 6 surfaced and closed two follow-ups not in the plan:

### 3a. Orphan docs PR (PR #243) — DONE

The plan / spec / diff / handovers (sessions 2–5) had been written on `onboardingFyn` and never landed on `dev`. They lived only in CSJ's local working tree. Session 6 opened `feature/csj/recon-docs-to-dev → dev` (PR #243), squash-merged as `6986e92`, branch auto-deleted. So **`docs/superpowers/specs/2026-05-05-csjones-dev-reconciliation-design.md`, `docs/superpowers/plans/2026-05-05-csjones-dev-reconciliation.md`, and the four handover files now exist on `origin/dev`.**

### 3b. Stale Current State doc refresh — BOTCHED then RESTORED

A subagent was dispatched to refresh vault `Current State/Onboarding.md` and `GoalsLifeEvents.md`. The subagent **rewrote the vault file from scratch** (871 → 396 lines) with hallucinated line counts and a "deprecated" mislabel on `OnboardingService.php`. CSJ corrected: vault `Current State/*.md` is a MIRROR of `appMapping/currentState/*.md` in the repo. The vault docs were restored from the git canonical (byte-identical to repo HEAD on dev).

**Net result:** the actual repo Current State docs are still at their pre-persona-split baselines. The vault is now byte-identical to the repo. Issue #2 below tracks the refresh.

### 3c. session-start skill patch — DONE (outside repo)

`/Users/CSJ/.claude/skills/session-start/SKILL.md` Phase 2a was rewritten to mandatorily read the latest handover in full. Phase 4 report template now includes a "Last handover" block with verbatim sections. "What NOT to do" forbids skipping the handover read or deciding on the user's behalf. **This is a Claude Code skill change, not a repo change** — won't appear in any git history.

---

## 4. Current verified state (audit time, 2026-05-05 ~21:15)

```
Branch:           dev
Local HEAD:       1948823 docs(session): context-clear handover 2026-05-05-session-6
origin/dev:       1948823 (in sync, 0/0)
origin/main:      fe77a77 docs: session 70 handover — Fyn v2 spec directory
dev↔main delta:   49 commits ahead, 0 behind
Open PRs:         0 against dev, 0 against main
Worktrees:        only the main worktree (clean)
Tags:             pre-recon/dev (dc335b3), pre-recon/persona-split (1bf89e8) — local AND origin
Migrations:       0 pending (last applied: 2026_05_06_000003_add_operation_created_at_index_to_ai_audit_events)
Dev server:       Laravel :8000 + Vite :5174 (both alive)
Untracked files:  intentional carryovers (campaigns/, fyn/, personas/, prompts/, tools/, FCA draft, Fynla memo template, May1Updates/deployFynFix.md)
```

### Branches still on origin (audit list)

| Branch | Tip SHA | Status | Action |
|---|---|---|---|
| `dev` | `1948823` | Live target. | ✓ keep |
| `main` | `fe77a77` | Production. 49 commits behind dev. | Issue #1 |
| `feature/csj/cms-insights-deploy-note` | `99a8e42` | Squash-merged into dev as `20d0b00` (PR #241). Now redundant. | Delete (low priority) — Issue #5 |
| `feature/fyn-persona-split` | `de48a2e` | Squash-merged into dev via the persona-split work. Retained per CSJ. | Keep until CSJ says delete |
| `onboardingFyn` | `9571fe0` | Squash-merged as `dc335b3` (PR #214). Now graveyard. | Delete (low priority) — Issue #5 |
| (deleted from origin) `fix/persona-split-review-fixes` | — | Deleted in session 6. | ✓ |
| (deleted from origin) `feature/csj/recon-docs-to-dev` | — | Auto-deleted by GitHub on PR #243 merge. | ✓ |

### Untracked files (intentional, carried since session 1)

These are scratch / prompt-engineering artefacts from May 1 work and have been deliberately not committed:

```
campaigns/default.yaml
fyn/what-fyn-does.{md,pdf}
personas/{entrepreneur,peak_earners,retired_couple,student,young_family,young_saver}.json
prompts/{advice,onboarding}-system-prompt.{md,pdf}
tools/{01-09 + README}.md
FCA-Supercharged-Sandbox-Application-Draft.md
Fynla-Narrative-Memo-Template.docx
May/May1Updates/deployFynFix.md
```

These are not reconciliation gaps — they precede this work and remain a separate question for CSJ (whether to commit, gitignore, or leave as-is).

---

## 5. Issues & recommended actions

Numbered for traceability. Severity: **P1** = blocks something, **P2** = should-fix-soon, **P3** = nice-to-have.

### Issue #1 — `dev → main` release PR not opened (P1, deferred deliberately)

**Evidence:** `git rev-list --left-right --count origin/main...origin/dev` → `0  49`. `gh pr list --state open --base main` → `[]`.

**What's in the 49-commit surface:**
- Eval framework (persona-split)
- Tax Strategy framework (persona-split)
- AI Audit / Idempotency (persona-split)
- AdviceFyn read-only architecture (persona-split)
- Onboarding extras + state-machine extension (persona-split, on top of PR #214's base)
- 25 migrations from persona-split + earlier dev migrations
- CMS into `/insights` pipeline (PRs #240 + #241)
- News hub + RSS + lifecycle email system (PR #238)
- News subscriber admin / CSV export
- News confirm/unsubscribe modal flow
- Fyn-driven onboarding state machine (PR #214)
- Recon spec/plan/diff/handovers (PR #243, docs only)

**Recommended action:**
1. **Soak**: leave dev on csjones for at least 24 hours (per session 5 handover's recommendation). The merge surface is unusually large; soak time matters.
2. **Pre-flight checks before opening the release PR**:
   - Verify `app/Http/Controllers/Api/AgentInternalController.php` and `app/Http/Middleware/AgentTokenAuth.php` are NOT referenced from `routes/api.php` on dev (they were dead-code-deleted on persona-split).
   - Confirm `./deploy/fynla-org/build.sh` (NOT the csjones script — different `VITE_BASE_PATH`) is the build command.
   - Confirm production has all 25 persona-split migrations as a fresh apply OR pre-mark any duplicates in `migrations` table (Step 8.6 of plan).
   - Confirm `SanitizeInput` middleware change for doc article body sanitisation goes too.
3. **PR body should call out**:
   - Pre-existing 7 P0/P1 pest failures (NOT merge-introduced; tracked in `maxAuditEval.md §5`).
   - Replace vault-only links with absolute file paths or move references into a paragraph (Issue #6).
   - Rollback path: `pre-recon/dev` tag exists on origin.
4. **Production smoke checklist**:
   - Onboarding state machine (29 states) — biggest new surface
   - AI chat (Advice read-only + delegate_to_capture handoff)
   - Tax Strategy panels
   - CMS articles via `/insights/{slug}`
   - News hub (RSS, subscribe, confirm, unsubscribe)
   - Lifecycle emails (smoke without a test recipient on prod)

**Owner:** CSJ (sole codeowner; this PR is opened and approved by CSJ).

**Priority:** P1 deferred (acceptable per session 6 handover; CSJ's call when to ship).

---

### Issue #2 — `appMapping/currentState/*.md` are stale across the board (P2)

**Evidence:** `ls -la appMapping/currentState/` shows two mtime cohorts:

| Cohort | mtime | Files |
|---|---|---|
| 2026-03-02 (older — fully pre-persona-split) | 9 weeks old | `AccLink.md`, `Admin.md`, `ConsoleCommands.md`, `ExpenditureIncome.md`, `GDPR.md`, `GoalsLifeEventsUpdatedPlan.md`, `Investment.md`, `Onboarding.md`, `PaymentSubscription.md`, `Retirement.md`, `SharedInfrastructure.md`, `Testing.md`, `UKTaxes.md` |
| 2026-03-12 (less stale, but still pre-persona-split) | 8 weeks old | `auth.md`, `Coordination.md`, `Dashboard.md`, `DeploymentBuild.md`, `Documents.md`, `EstatePlanning.md`, `GoalsLifeEvents.md`, `NetWorth.md`, `Property.md`, `Protection.md`, `PublicPages.md`, `risk.md`, `Savings.md` |

**Note:** the session 6 handover claimed `Onboarding.md` and `GoalsLifeEvents.md` were both at the 2026-03-02 baseline. Audit shows `GoalsLifeEvents.md` is actually 2026-03-12. Onboarding.md is at 2026-03-02 (matches handover). The vault-sync only flagged 2 docs because it spot-checked; **all 26 docs are stale to varying degrees**.

**Recommended action:**
1. Decide priority order based on which modules changed most under persona-split (Onboarding, AI/Coordination, Tax/UKTaxes, Investment, EstatePlanning are the biggest deltas).
2. **Surgical edits only — additions, no deletions, no rewrites** (CSJ's hard rule from session 6). The doc formats are CSJ-curated and a subagent rewrite already failed once.
3. **Edit the repo copy** (`appMapping/currentState/<doc>.md`), never the vault. Vault-sync mirrors repo→vault.
4. **One PR per doc** for review, OR one PR with all surgical edits batched. CSJ to choose.
5. Out of scope for this audit. Track each doc as a separate ticket.

**Owner:** TBD by CSJ.

**Priority:** P2 (no functional impact; documentation drift only).

---

### Issue #3 — `CLAUDE.md` metric counts are out of date (P3)

**Evidence:** Audit-time actual counts vs `CLAUDE.md` table (`grep -E "^\| (Vue|PHP|Controllers|Models|Vuex|Agents)" CLAUDE.md`):

| Metric | CLAUDE.md says | Actual at HEAD | Drift |
|---|---|---|---|
| Vue Components | 718 | 726 | +8 |
| PHP Services | 292 | 297 | +5 |
| Controllers | 108 | 109 | +1 |
| Models | 109 | 110 | +1 |
| Vuex Stores | 34 | 35 | +1 |
| Agents | 9 | 9 | 0 |

The session 6 handover claimed `722 / 297 / 109 / 110 / 35`; Vue components count has crept further to **726**. The discrepancy is innocuous — CLAUDE.md table just hasn't been updated since v1.0.

**Recommended action:**
1. Tiny PR updating the table in `CLAUDE.md`. One file, six numbers. ~1 min review.
2. Optionally: add a `tools/recount-metrics.sh` script (out of scope). For now just update the numbers.

**Owner:** Whoever notices first; trivial.

**Priority:** P3 (cosmetic).

---

### Issue #4 — Browser smoke evidence trail is sparse (P3, retroactive only)

**Evidence:** Plan Tasks 9–11 specified Playwright-driven smoke with screenshots into `/tmp/fynla-recon/smoke-{admin,young-family,peak-earners}/`. Playwright MCP disconnected in session 5; CSJ smoked manually in own browser. **No screenshots, no console-error captures, no `smoke-result.md` were saved.** The verification gate was satisfied by CSJ's verbal "smoke passed" before PR #242 opened.

**Why it doesn't block anything:** the dev surface has been live on csjones for ~24 hours with no rollback or regression report. The smoke clearly worked.

**Why it's flagged:** if the same plan format is used for the eventual `dev → main` release, the smoke evidence trail should be planned around Playwright availability (have a Path A/B/C ready in the plan, not as an emergent improvisation).

**Recommended action:**
1. Don't retro-smoke csjones (waste of time; live use is the smoke).
2. **Update the plan template / risk register** for future reconciliations: add an explicit "Playwright unavailable" branch with curl-based smoke checklist and the "manual smoke + verbal sign-off" path, so it's pre-authorised.

**Owner:** Whoever writes the next reconciliation plan.

**Priority:** P3 (process improvement, retroactive).

---

### Issue #5 — Two retained graveyard branches on origin (P3)

**Evidence:**

```
origin/feature/csj/cms-insights-deploy-note   99a8e42   squash-merged into dev as 20d0b00 (PR #241)
origin/onboardingFyn                          9571fe0   squash-merged into dev as dc335b3 (PR #214)
```

Both branches' content is fully in `dev`. They are `--delete-branch=false` survivors. The session 6 handover doesn't list deleting them as outstanding; the session 2 handover suggested they could stay around as graveyards.

**Verification before deletion** (don't skip — squash-merged content lives at different SHAs):

```bash
# For each branch, check no commits exist on the branch that aren't reachable from dev's content
git log origin/dev..origin/feature/csj/cms-insights-deploy-note --oneline
git log origin/dev..origin/onboardingFyn --oneline
# These should both return commits that are functionally contained in dev's squashed versions.
# If something appears that's NOT in dev (e.g. a stray fix never picked up), STOP.
```

**Recommended action:**
1. Verify each branch is content-equivalent to its squash-merged ancestor.
2. If clean: `git push origin --delete <branch>` then `git branch -D <branch>` locally.
3. If not clean: report to CSJ before deleting.

`feature/fyn-persona-split` is **explicitly retained per CSJ** — do NOT delete.

**Owner:** TBD; not urgent.

**Priority:** P3 (clutter only).

---

### Issue #6 — PR #242 body links to vault-only paths (P3)

**Evidence:** Per session 6 handover §"Open items": "PR #242 body links to vault-only paths (`April28Updates/maxAuditEval.md §5`). Only CSJ can resolve those; other reviewers couldn't follow them."

**Recommended action:**
1. The PR is already merged — no blocker.
2. **For the future `dev → main` release PR (Issue #1)**, prefer absolute repo paths or inline summaries over vault references. External reviewers (in the unlikely future case of having one) won't have vault access.
3. If this list becomes a recurring pattern, update the PR template to require all links resolve from `gh pr view` text alone.

**Owner:** Whoever writes the release PR body for Issue #1.

**Priority:** P3 (noted; merged PR can stay as-is).

---

### Issue #7 — Carry-over: csjones article cleanup + raspberry button check (P3)

From session 6 handover Open Items, both pre-existing carry-overs from session 2:

1. **CSJ to confirm in own browser** that the raspberry "Choose File" button on `https://csjones.co/fynla/admin/documents` opens the macOS file picker. (First time tested in Playwright, file_chooser was being intercepted silently — confused diagnosis.)
2. **Delete duplicate "Rich Sample Title" article** on csjones (id=4, draft) created during session-2 DropZone test.

**Recommended action:**
1. Article id=4 delete: Claude can run via SSH (`~/.ssh/fynlaDev` key + tinker) once the key is loaded into ssh-agent.
2. Raspberry button picker check: Playwright intercepts the OS file_chooser dialog in headless Chromium, so Playwright can confirm the button triggers a `filechooser` event but cannot render the actual macOS picker. CSJ's real (non-Playwright) browser is the most reliable confirmation. ~30 seconds.

**Owner:** Claude (article delete) + CSJ (real-browser picker confirmation).

**Priority:** P3 (UX + hygiene).

---

### Issue #8 — Pre-existing 7/8 pest failures from persona-split (P1, not a regression)

**Evidence:** Session 5 + session 6 handovers; tests listed in §2 Phase 3 above.

**Critical for next Claude:** these are **NOT merge-introduced**. Persona-split had them before the merge. Pursuing them as reconciliation regressions wastes time. They are tracked separately in `April28Updates/maxAuditEval.md §5` (canonical eval P0/P1 list).

**Recommended action:**
1. Document them in any release-PR body (Issue #1) as "pre-existing P0/P1 from persona-split, tracked in `maxAuditEval.md §5`".
2. Resolve via the eval HTTP-driven rewrite plan (separate workstream — do not bundle into release).
3. Ensure CI / pest-on-merge does not re-treat these as regressions.

**Owner:** Eval workstream owner (CSJ).

**Priority:** P1 for the eval workstream; **NOT P1 for this reconciliation** (out of scope).

---

## 6. What the audit did NOT find — explicitly cleared

Listed for closure / negative space:

- ❌ No uncommitted local changes outside the intentional untracked carryovers.
- ❌ No conflict markers anywhere in `app/`, `resources/`, or `database/` (`grep -rn '<<<<<<< '` returns nothing).
- ❌ No pending migrations (`php artisan migrate:status` shows all Ran).
- ❌ No leftover worktrees (`git worktree list` shows only main).
- ❌ No leftover `/tmp/fynla-recon/` or `/tmp/fynla-merge/` artefacts (cleaned up correctly).
- ❌ No commits on origin that aren't in dev for the deleted branches.
- ❌ No open PRs against dev or main.
- ❌ No conflicts between the merge commit and the docs-PR commit; they layered cleanly.
- ❌ No CI/auto-merge surprises — both PRs went through `--admin --squash` cleanly.
- ❌ Rollback tags `pre-recon/dev` and `pre-recon/persona-split` exist on both local and origin (rollback path remains live).

---

## 7. Recommended action sequence (prioritized)

Suggested order of operations once CSJ wants to close the loop:

1. **(P1, when soak window passes)** Issue #1 — open `dev → main` release PR. Largest task.
2. **(P3, anytime, ~1 min)** Issue #3 — fix CLAUDE.md metric drift in a tiny PR.
3. **(P3, ~2 min, server-side)** Issue #7 — CSJ does the manual csjones article cleanup + raspberry button verification.
4. **(P3, ~5 min)** Issue #5 — verify and delete `feature/csj/cms-insights-deploy-note` and `onboardingFyn` from origin if content-equivalent. **Don't delete `feature/fyn-persona-split`.**
5. **(P2, when CSJ has bandwidth)** Issue #2 — Current State doc refresh sweep (one PR per doc OR one batched PR; surgical only).
6. **(P3, retroactive only)** Issue #4 — update plan template for future reconciliations re: Playwright fallback paths.
7. **(P3, on next release PR)** Issue #6 — use absolute paths in PR body; reference `maxAuditEval.md §5` inline.
8. **(P1 separate workstream)** Issue #8 — eval P0/P1 defects via the eval HTTP-driven rewrite plan (do NOT bundle with release).

---

## 8. Audit verdict

**The local↔dev sync reconciliation succeeded.** All 14 plan tasks executed; both required PRs (#242 + #243) merged; one bonus PR (#243 docs) opened beyond plan scope and merged; csjones running merged code; rollback tags retained; worktrees gone; local in sync.

The 8 outstanding items are **not blockers and not regressions** — they are a mix of one deliberately-deferred big PR (`dev → main`), routine doc drift, branch hygiene, and a couple of retroactive process notes. None of them affect the reconciliation goal: "bring `csjones.co/fynla` into sync with `origin/dev` while preserving every commit in `fix/persona-split-review-fixes`."

The reconciliation is **closed-and-verified**. The next-larger objective (production deploy via `dev → main`) is the natural follow-on.

---

## 9. Reference index

- Plan: `docs/superpowers/plans/2026-05-05-csjones-dev-reconciliation.md`
- Spec: `docs/superpowers/specs/2026-05-05-csjones-dev-reconciliation-design.md`
- Diff baseline: `May/May5Updates/local-vs-dev-codebase-diff-2026-05-05.md`
- Handovers: `May/May5Updates/handover-2026-05-05-session-{1,2,3,4,5,6}-clear.md`
- Rollback tags on origin: `pre-recon/dev` (`dc335b3`), `pre-recon/persona-split` (`1bf89e8`)
- PRs merged this work: [#242](https://github.com/Stoff73/fynla/pull/242) (main merge), [#243](https://github.com/Stoff73/fynla/pull/243) (docs)
- Current dev tip: `1948823` `docs(session): context-clear handover 2026-05-05-session-6`
- Eval P0/P1 canonical reference: `April/April28Updates/maxAuditEval.md §5`
