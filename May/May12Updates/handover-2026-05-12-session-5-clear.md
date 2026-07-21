---
type: handover
mode: context-clear
date: 2026-05-12
session: 5
branch: audit-criticals (local checkout) — Wave 1 work was on audit-quickwins
trigger: context tripwire at ~370k tokens (>97.5% of 200k budget)
previous_session: 2026-05-12 session 4 (audit-criticals Tier 1-3 complete)
---

# Context Clear Handover — 2026-05-12, Session 5

## Immediate state

PR #281 (`audit-criticals → dev`) AND PR #282 (`audit-quickwins → dev`) are both OPEN with green tests. CSJ has approved continuing Wave 1 (W1-M, W1-N, W1-L) and Wave 2 batch on additional parallel branches off `dev`. Tripwire fired before W1-M kick-off. Local checkout is on `audit-criticals` (clean tracked tree, standing untracked carry-over only).

## The thread

1. Auto-resumed from session 4 handover: reworded WIP `a5e4770` as `f3be30e` "fix(audit): cast Holding fields to (float) at numeric boundaries (S-02 follow-up)"; force-pushed `audit-criticals` (now at `7cdef41`).
2. Re-ran full Pest suite: **3,542 passed / 25 skipped / 1 flaky** (`InvestmentControllerTest > PUT /api/investment/accounts/{id} > updates an investment account`). Passes at file scope (10/10) and in isolation; only fails at full-suite scale. Inter-test isolation flake unrelated to audit-criticals work (touches `InvestmentAccount`, not the Holding cast surface). Same pattern as the prior `SavingsAgentGoalsTest` flake. Documented in #281 body, not blocking.
3. Opened **PR #281** (`audit-criticals → dev`) — title: "fix(audit): Tier 1-3 critical findings — tax thresholds, TransientToken family, data-type corrections, audit-log index, migration safety". URL: https://github.com/Stoff73/fynla/pull/281
4. CSJ overruled the audit on Tier 1 #4 (salary sacrifice): **the £2,000 cap IS the upcoming UK statutory law** (next tax year per CSJ, treat as 2027/28). Fix path is codify in `TaxConfigService` with `effective_from`, NOT remove. Audit doc's "post-2029, wrong rule" framing was incorrect. Saved to memory as `project_salary_sacrifice_2k_upcoming_law.md`.
5. CSJ amended Rule #16 to be **forward-only**: existing emoji/Unicode/icon violations are grandfathered (`goalIcons.js` 🔥🎯📈⭐🏆, `AdminDashboard.vue:199` ▲▼ etc.). New code must comply strictly. Edits made to `CLAUDE.md` Rule #16 "Enforcement" block (now reads "Enforcement (forward-only — existing violations grandfathered)"). Saved to memory as `feedback_rule_16_grandfather_existing.md`. Both linked from `MEMORY.md` "Top laws" + "Memory files".
6. Created **audit-quickwins** branch off `origin/dev` (clean checkout, NOT off audit-criticals — Wave 1 is independent of #281). 10 commits landed:
   - `8e56c1c` docs(audit): Rule #16 forward-only + tax-year 2026/27 (W1-A — CLAUDE.md Rule #16 amend + bump "active" tax year line)
   - `cab512d` fix(audit): wrap DebugEnv view in AppLayout (W1-B — REVIEW §4 Critical #13 / Rule #14)
   - `90b5020` fix(audit): X-Frame-Options conflict + remove X-XSS-Protection (W1-C — Top-10 #7 / §4 High #17; 3 .htaccess + 11 new regression cases)
   - `8d5282d` fix(audit): remove admin auto-promotion at login + registration (W1-D — Top-10 #6 / §4 High #15; 2 new regression cases)
   - `6e6c551` fix(audit): constant-time comparison for password-reset code + GDPR deletion tokens (W1-E — §4 High #19; 4 sites via `hash_equals`)
   - `6741efc` fix(audit): fail-closed env guard for eval routes (W1-F — §4 High #20; ALLOWED_ENVIRONMENTS whitelist; 3 new regression cases)
   - `c6c96e5` fix(audit): read income-tax thresholds from seeded bands array (W1-G — §4 Critical #5; 4 sites in DecumulationPlanner + RetirementActionDefinitionService)
   - `c6772bc` fix(audit): correct (float) $x ?? 0.0 operator-precedence (W1-I — §4 High #25; 3 sites in PensionContributionOptimizer)
   - `2e2803f` fix(audit): per-module failure isolation in DashboardAggregator (W1-J — §4 High #26)
   - `5c81faa` fix(audit): use to.matched.some() for nested-route meta gating (W1-K — §4 High #27; 4 sites in router/index.js)
7. **W1-H deferred.** `RetirementController.php:217` double-`analyze()` call already mitigated by `RetirementAgent::analyze()`'s `$this->remember()` cache (key `retirement_analysis_{userId}`, TTL 3600s). Real fix needs controller-pattern refactor, not quickwins. Documented in PR #282 body.
8. Opened **PR #282** (`audit-quickwins → dev`) — title: "fix(audit): Wave 1 quickwins — security headers, admin promo, constant-time, eval guard, router gating, DashboardAggregator isolation, docs". 18 files, +405/-110. URL: https://github.com/Stoff73/fynla/pull/282. 98 tests green across all touched surfaces.
9. Switched local checkout back to `audit-criticals` so any review feedback on #281 can be addressed in-place. (Triggered the "linter-modified files" system-reminders — those are just branch-switch diffs, audit-quickwins state is safely in origin.)
10. CSJ approved continuing in parallel with W1-M / W1-N / W1-L / Wave 2 batch. Tripwire fired before kick-off.

## Open decisions surfaced (already resolved in this session)

1. ~~Tier 1 #4 salary sacrifice~~ — CSJ resolved: codify in TaxConfigService with effective_from year (deferred to its own follow-up PR per memory `project_salary_sacrifice_2k_upcoming_law.md`).
2. ~~Rule #16 emoji/Unicode existing violations~~ — CSJ resolved: forward-only grandfathering (CLAUDE.md Rule #16 amended + memory written).
3. ~~PR opening~~ — CSJ resolved: #281 + #282 opened.
4. ~~WIP squash style~~ — Resolved: standalone follow-up commit `f3be30e`.

## Files touched (uncommitted)

None. Local tree is clean (tracked). Untracked files (`FCA/`, `personas/`, `prompts/`, etc.) are standing carry-over from prior sessions, not session-5 work.

## What the next Claude needs to know

- **PR #281 and PR #282 are both OPEN against `dev` with zero file overlap.** Either can merge first. Verified via `git diff --stat origin/dev..HEAD` on each.
- **Local checkout is `audit-criticals` at `7cdef41` (pushed).** If kicking off new Wave 1 / Wave 2 branches, branch off `origin/dev`, not off audit-criticals. The Phase 2c command pattern that worked this session:
  ```
  git fetch origin dev
  git checkout -b <new-branch> origin/dev
  ```
  Verify the working tree is clean BEFORE branching — uncommitted edits from audit-criticals (e.g. the Rule #16 amendment) carried over the first time. Confirm with `git diff --stat HEAD` after the checkout.
- **TaxConfigurationSeeder.php and UKTaxCalculator.php are touched by #281.** Any new branch that needs to modify these will conflict with #281 until it merges. Stick to the Wave 3 list for those.
- **`Unit/Http` and `Unit/Database` are NOT yet bound in `tests/Pest.php` on the `dev` tip** — that's an audit-criticals addition pending merge. New test files in those dirs must `uses(TestCase::class)` (with `RefreshDatabase::class` if DB needed) at the top of the file. Pattern used in `tests/Unit/Http/Middleware/SecurityHeadersTest.php` this session.
- **CLAUDE.md Rule #16 is now forward-only.** Don't strip existing emoji/Unicode/icon violations in new audit work. Existing offenders: `resources/js/constants/goalIcons.js` (🔥🎯📈⭐🏆), `resources/js/views/Admin/AdminDashboard.vue:199` (▲▼). Reference: memory `feedback_rule_16_grandfather_existing.md`.
- **Salary sacrifice £2,000 cap IS upcoming UK law.** Don't remove it. When implementing Tier 1 #4 follow-up, codify via TaxConfigService with `effective_from` (likely 2027/28 — confirm with CSJ). Reference: memory `project_salary_sacrifice_2k_upcoming_law.md`.
- **The 1 flaky test** (`InvestmentControllerTest > PUT updates`) is a pre-existing suite-scale isolation issue. NOT caused by any session work. Documented in #281 body. Not blocking.

## Pick up from here (auto-continue contract)

Per CSJ's `/session-end` args: after `/clear`, run `session-start` which will read this handover. Then execute the following four work items as parallel branches off `origin/dev`, in this order:

### Step A — W1-M: BugReportController hardening

REVIEW.md Top-10 #8 / §4 High.

- **File:** `app/Http/Controllers/Api/BugReportController.php` (currently unauthenticated, IP-rate-limited to 5/hour, posts user content to chris@fynla.org via `Mail::send`).
- **Issue:** Attacker-controlled `description` / `user_agent` / `page_url` / `console_logs` (up to 10KB) can craft credible phishing content that arrives from `noreply@fynla.org`. Inline HTML in `Mail::send` can be interpreted by clients.
- **Fix:**
  1. Strip HTML from all input fields server-side via the existing `UserContentSanitiser` (or `strip_tags` if simpler — check what the project uses for similar paths).
  2. Cap `console_logs` to 2KB (down from 10KB).
  3. Route through a queue (default queue connection — check `config/queue.php`) so abuse can be detected before delivery.
  4. Replace `Mail::send` with a templated mail (new Mailable class) that escapes all user content via Blade `{{ }}`.
  5. Consider requiring authentication (move route inside `auth:sanctum` middleware group) OR adding a captcha. Lean toward auth — Bug Report from authenticated users only is reasonable.
- **Branch name:** `audit-bugreport-hardening`
- **Test plan:** new Pest feature test in `tests/Feature/Api/BugReportControllerTest.php` covering HTML stripping, console_logs cap, and (if auth-only) 401 on unauthenticated POST.

### Step B — W1-N: npm audit fix

REVIEW.md Top-10 #9 / §4 High #18.

- **Source:** `npm audit` reports 11 advisories. Critical: `@capgo/capacitor-native-biometric` (auth-bypass GHSA-vx5f-vmr6-32wf — directly impacts Face ID login). Plus axios <1.15 (13 advisories), `@babel/plugin-transform-modules-systemjs`, `tar`, `serialize-javascript`, `vite`, `fast-uri`, `postcss`.
- **Fix sequence:**
  1. Run `npm audit fix` first (non-breaking).
  2. Run `npm audit` again to see what's left.
  3. For `@capgo/capacitor-native-biometric`: upgrade past the patched version OR swap to a maintained alternative. Mobile biometric is the most-touched mobile flow — needs iOS smoke after the upgrade.
  4. Pin `axios` to >=1.15.
  5. For anything still flagged after the auto-fix, evaluate `npm audit fix --force` (may bump majors — check each breaking change).
- **Branch name:** `audit-npm-deps`
- **iOS smoke required:** rebuild via `./deploy/mobile/build-ios.sh`, open Xcode, run on simulator or device, verify Face ID setup + Face ID login flow. Reference memory `mobile_capacitor_patterns.md` for the WKWebView gotchas.
- **Test plan:** `npm audit` returns 0 advisories OR a documented residual list with mitigation rationale. iOS Face ID login + setup verified manually.

### Step C — W1-L: Consent DB query cache at SSE start

REVIEW.md §4 High #23.

- **File:** `app/Http/Controllers/Api/AiChatController.php:189` (consent check fires on EVERY SSE event during chat streaming).
- **Fix:** Cache the consent check result once at SSE start; reuse for the duration of the stream. Use request-scoped property OR a short-lived cache keyed by `user_id`. Probably simplest: store the consent boolean on the controller instance at the top of `sendMessage` and reference it from the streaming loop.
- **Branch name:** `audit-consent-sse-cache`
- **Test plan:** Pest feature test asserts the consent check fires once per SSE stream, not once per event. Browser smoke: send a long Fyn chat message; verify DB shows one consent query per request via Laravel Telescope or query log.

### Step D — Wave 2 batch (single PR, multiple fixes)

REVIEW.md Cross-cutting + §4 High.

- **Branch name:** `audit-wave2`
- **Commits (suggested order, each its own commit):**
  1. **purple-* / indigo-* → violet-*** bulk find-replace (REVIEW Cross-cutting 3.4). ~15-20 Vue files. Use `grep -rln 'purple-\|indigo-' resources/js/ --include="*.vue"` to enumerate. Worst offender: `AssetsStep.vue` (onboarding). Run Pest after each batch to catch any test that asserts on class names.
  2. **`RISK_TAILWIND_CLASSES` palette refactor** (REVIEW §4 #29 / Cross 3.4). `resources/js/constants/designSystem.js:200-231` uses `yellow-* / pink-* / green-* / teal-* / blue-*` — replace with palette-only tokens. Then audit callers to ensure no visual regression.
  3. **Architecture tests for Rules #5/#9/#13/#14** (REVIEW Phase B #10). Add Pest arch tests in `tests/Architecture/`:
     - #5: no `'sole'` ownership_type in enums or code
     - #9: no `amber-* / orange-*` in Vue files (already 0 hits per audit — lock with arch test)
     - #13: no score badges / score metric cards (find the pattern, ban regex)
     - #14: every routed view wraps in AppLayout or PublicLayout (parse `views/*.vue` for layout import)
  4. **IHTCalculationService save-on-read** (REVIEW §4 High #22). `IHTCalculationService.php:227` — `saveCalculation()` is called during a read flow. Move write to an explicit action OR queue it. Add regression test.
  5. **BusinessInterestService BADR fallback `0.10`** (REVIEW §4 High #34). `BusinessInterestService.php:171,189` falls back to old 10% rate with user-facing text "10% rate". Fix to read current rate from TaxConfigService; if config missing, fail loud rather than display wrong advice.
  6. **TaxDragCalculator stale 2024/25 rates + missing `forUserOrJoint`** (REVIEW §4 High #21). `app/Services/Investment/AssetLocation/TaxDragCalculator.php:303, 317` — stale hardcoded dividend rates AND raw `orWhere` join (missing the `forUserOrJoint` scope per CLAUDE.md Rule #7). Two fixes in one file.
- **Test plan:** Each commit includes regression coverage. Full Pest suite green at branch tip. Visual regression check via dev server for the colour swap items.

### Sequencing notes

- All four branches are parallel — each off `origin/dev`, none depending on the others.
- **Watch for file overlap** with #281 and #282 before starting each branch:
  - `UKTaxCalculator.php` is in #281 — Wave 2 stays away from it.
  - `TaxConfigurationSeeder.php` is in #281 — only the salary-sacrifice follow-up needs to touch it (separate PR after #281 merges).
  - `app/Http/Controllers/Api/AuthController.php` is in #282 — don't touch on parallel branches until #282 merges.
- **PR open timing:** Open each PR as soon as the work is green. Each PR is independent so review/merge can interleave.
- **Tier 1 #4 salary sacrifice follow-up** is NOT in this list — it needs the TaxConfigurationSeeder schema decision + CSJ's confirmation of the effective tax year (2027/28? 2028/29?) before implementation. Tracked in memory file. Carry forward.

### What the next session should NOT do

- Do NOT touch existing emoji/Unicode/icon violations on banned surfaces (Rule #16 forward-only — grandfathered).
- Do NOT remove the £2,000 salary sacrifice limit (it's upcoming law).
- Do NOT branch off `audit-criticals` or `audit-quickwins` — both are feature branches awaiting merge to `dev`. Branch new work off `origin/dev`.
- Do NOT skip the iOS smoke for W1-N — biometric is the most-touched mobile flow.

## Branch / deploy state

- **`audit-criticals`** at `7cdef41` (pushed; PR #281 OPEN). Local checkout.
- **`audit-quickwins`** at `5c81faa` (pushed; PR #282 OPEN). Last touched this session, now on origin only.
- **`dev`** at `0f54592` (last pulled this session).
- **`main`** unchanged.
- **csjones.co/fynla** still tracks `main` (last deploy was f15e068 prod). Neither audit-criticals nor audit-quickwins is deployed anywhere.
- **fynla.org** unchanged.

## Commits landed this session

```
On audit-quickwins (10 commits, branched from origin/dev):
5c81faa fix(audit): use to.matched.some() for nested-route meta gating (REVIEW §4 High #27)
2e2803f fix(audit): per-module failure isolation in DashboardAggregator (REVIEW §4 High #26)
c6772bc fix(audit): correct (float) $x ?? 0.0 operator-precedence (REVIEW §4 High #25)
c6c96e5 fix(audit): read income-tax thresholds from seeded bands array (REVIEW §4 Critical #5)
6741efc fix(audit): fail-closed env guard for eval routes (REVIEW §4 High #20)
6e6c551 fix(audit): constant-time comparison for password-reset code + GDPR deletion tokens
8d5282d fix(audit): remove admin auto-promotion at login + registration (REVIEW Top-10 #6)
90b5020 fix(audit): resolve X-Frame-Options conflict + remove deprecated X-XSS-Protection
cab512d fix(audit): wrap DebugEnv view in AppLayout (Rule #14)
8e56c1c docs(audit): Rule #16 forward-only + tax-year refresh to 2026/27

On audit-criticals (1 reword + 1 handover, force-pushed):
7cdef41 docs(session): context-handover 2026-05-12-session-4
f3be30e fix(audit): cast Holding fields to (float) at numeric boundaries (S-02 follow-up)
(was previously a5e4770 "wip: context-handover snapshot" + b52ff07 handover-doc)
```

Memory files written/updated:
- `feedback_rule_16_grandfather_existing.md` (new)
- `project_salary_sacrifice_2k_upcoming_law.md` (new)
- `MEMORY.md` (Top laws + Memory files index updated)
- `CLAUDE.md` Rule #16 "Enforcement" block (committed as `8e56c1c` on audit-quickwins)

## PRs opened this session

- **#281** — `audit-criticals → dev` — https://github.com/Stoff73/fynla/pull/281
- **#282** — `audit-quickwins → dev` — https://github.com/Stoff73/fynla/pull/282

Vault-sync deferred this session (tripwire fired). Carry forward to next session's eod wrap alongside sessions 6-12 of May 11 (already in the backlog).
