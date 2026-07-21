---
type: handover
mode: context-clear
date: 2026-05-12
session: 6
branch: dev (clean) — work landed on 4 feature branches (audit-bugreport-hardening, audit-npm-deps, audit-consent-sse-cache, audit-wave2)
trigger: context tripwire at ~530k tokens (>97.5% of 200k Fynla budget)
previous_session: 2026-05-12 session 5 (audit-criticals + audit-quickwins PRs open)
---

# Context Clear Handover — 2026-05-12, Session 6

## Immediate state

All four Wave 1 / Wave 2 work items from session 5's "Pick up from here" are **shipped as open PRs against `dev`**. Six audit PRs total now open: #281, #282 (carry-over from session 5), plus #283, #284, #285, #286 (this session). Local checkout is `dev` at `0f54592` (clean tracked tree; standing untracked carry-over only). All four new feature branches pushed to origin.

CSJ confirmed mid-session-end that the **salary sacrifice £2,000 cap goes live in tax year 2027/28** — memory `project_salary_sacrifice_2k_upcoming_law.md` updated with that exact year, removing the prior "treat as 2027/28 unless CSJ specifies otherwise" hedge.

## The thread

1. Auto-resumed from session 5 handover. Executed all four work items as parallel feature branches off `origin/dev`, in the order the handover specified (W1-M → W1-N → W1-L → Wave 2). No file overlap between any of the new branches and PRs #281 / #282.
2. **PR #283 (`audit-bugreport-hardening → dev`)** — W1-M plus a pre-existing app-wide handler bug surfaced while writing the W1-M throttle test. Two commits.
3. **PR #284 (`audit-npm-deps → dev`)** — `npm audit fix` non-breaking + axios SemVer pin + `serialize-javascript ^6.0.2` override for Node-18 PWA SW build compat + `docs/security/npm-audit-deferrals.md` documenting the 5 root residuals.
4. **PR #285 (`audit-consent-sse-cache → dev`)** — Bounded-TTL consent recheck (default 2.0s) instead of the handover's "cache once" recommendation, because "cache once" breaks the S0.9 strict-termination contract enforced by `tests/Feature/AI/ConsentRuntimeCheckTest.php:166`. Existing test reframed via `config('ai_chat.consent_recheck_interval_seconds', 0)` for strict mode.
5. **PR #286 (`audit-wave2 → dev`)** — Six commits in order: 2.1 purple/indigo→violet bulk (70 files, 312 occurrences); 2.2 RISK_TAILWIND_CLASSES palette refactor; 2.3 architecture tests for Rules #5 + #9; 2.4 IHTCalculationService persistence opt-in; 2.5 BusinessInterestService BADR fail-loud + dynamic rate text; 2.6 TaxDragCalculator yields from config + `forUserOrJoint` scope.
6. CSJ confirmed salary sacrifice effective year is **2027/28** (audit's "post-2029" framing was wrong). Memory file updated. The Tier 1 #4 follow-up is now ready to implement when CSJ schedules it — just needs the TaxConfigService schema decision + the regression test.

## Discoveries the handover did NOT anticipate (now in PR bodies)

1. **Pre-existing global `HttpResponseException` → 500 bug** in `app/Exceptions/Handler.php`. Affects every named rate-limiter that uses `RateLimiter::for(...)->response(callback)` — namely `bug-reports`, `mobile-dashboard`, `ai-chat`, and `sensitive-actions`. Custom renderer at `handleApiException` had `method_exists($e, 'getStatusCode') ? ... : 500`, which silently swallowed `HttpResponseException`'s embedded JsonResponse and replaced it with a generic 500. Folded into PR #283 as a second commit with regression test in `tests/Feature/Exceptions/HandlerTest.php`.
2. **Biometric advisory GHSA-vx5f-vmr6-32wf is Android-specific.** Vulnerable code is `AuthActivity.java`; Fynla is iOS-only (no `android/` dir, only `deploy/mobile/build-ios.sh`). The deployed code path never executes the buggy native layer. Upgrade to ≥8.3.6 requires `@capacitor/core` ≥8.0.0 — a Capacitor 6→8 stack bump across 16 packages with Xcode/`pod install`/native review. Documented as deferred in `docs/security/npm-audit-deferrals.md` §1.
3. **`serialize-javascript@7.x` requires Node 19+** (uses bareword `crypto.getRandomValues` at module load). We're on Node 18, so the audit-fix bump silently breaks PWA SW emission. Workbox-build is the only consumer and feeds its own build config in (no user input) — pinned to `^6.0.2` via npm `overrides`. Documented in deferrals §4.
4. **W1-L "cache once at SSE start" would break the S0.9 mid-stream consent withdrawal contract.** Existing `ConsentRuntimeCheckTest:166` asserts byte-perfect strict termination. Reframed: bounded TTL (default 2s) keeps the contract within "without undue delay" per ICO Art. 7(3), while reducing in-stream DB load ~100×. New perf test asserts 1 query for a 26-event fast stream.

## What the next Claude needs to know

- **Six PRs are now open against `dev` — none have merged yet.** Order doesn't matter; they're independent. The natural shipping order is:
  - #281 + #282 first (carry-over from session 5; reviewed across two sessions; nothing depends on them)
  - #283 + #284 next (independent, each adds an explicit defensive layer)
  - #285 + #286 last (largest visual/test surface — review more carefully)
- **PR #283's second commit is the handler-bug fix** affecting all named-limiter routes app-wide. Mention this in the merge announcement — if anyone is currently triaging 500s on rate-limited endpoints in prod logs, that's likely the cause.
- **PR #284 adds an npm override** — anyone running `npm audit fix --force` after this merges will re-break PWA SW emission unless they understand why the override exists. The `docs/security/npm-audit-deferrals.md` explains it.
- **PR #286 commit 2.1 (purple/indigo → violet) is 70 files.** Visual smoke is mandatory after deploy — any prior surface using `purple-500` for a non-warning CTA is now `violet-500` (Fynla's "caution / focus" colour). May read differently to the user; if any specific surface looks wrong, it's not a bug, it's the design-system migration finally landing. Adjust copy or change to `raspberry-*` (CTA) or `horizon-*` (cool/neutral) if appropriate.
- **`InvestmentControllerTest > PUT updates` flake** is unchanged and still pre-existing — passes in isolation (10/10), fails only at full-suite scale. Documented in #281's PR body. Don't chase.
- **`audit-criticals` branch is the only one carrying May12Updates handovers 1–5** — they were committed there during sessions 3/4/5. Today (session 6) handover lives on `dev`. If session-start tomorrow runs on `dev`, it'll find this file and only this file (the prior session-N's are on other branches). That's fine — this handover is the cumulative state.

## Salary sacrifice — newly-confirmed by CSJ

- **Effective tax year: 2027/28** (CSJ confirmed at session-end, 2026-05-12).
- Memory `project_salary_sacrifice_2k_upcoming_law.md` updated to read "becomes UK law on 2027/28" and "Use `effective_from: '2027-04-06'`". The prior hedge ("treat as 2027/28 unless CSJ specifies otherwise") is gone.
- **Ready to implement** in a separate follow-up PR. The work: codify the £2,000 limit in `TaxConfigService` with `effective_from: '2027-04-06'`, leave the existing `RetirementStrategyService:1186` cap in place (it's the upcoming law), and add a Pest test pinning behaviour both before and after the effective date using `Carbon::setTestNow`.

## Branch / deploy state

- `audit-criticals` at `7cdef41` (PR #281 OPEN, awaiting merge) — pushed
- `audit-quickwins` at `5c81faa` (PR #282 OPEN, awaiting merge) — pushed
- **`audit-bugreport-hardening` at `458109f` (PR #283 OPEN)** — pushed
- **`audit-npm-deps` at `2a9fd61` (PR #284 OPEN)** — pushed
- **`audit-consent-sse-cache` at `ac858cb` (PR #285 OPEN)** — pushed
- **`audit-wave2` at `c7de743` (PR #286 OPEN)** — pushed
- `dev` at `0f54592` (unchanged today; six PRs targeting it)
- `main` unchanged
- csjones.co/fynla still tracks `main` at `f15e068` — none of the six PRs deployed anywhere yet
- fynla.org unchanged

## PRs opened this session

- **#283** — W1-M BugReportController hardening + HttpResponseException handler fix — https://github.com/Stoff73/fynla/pull/283
- **#284** — W1-N npm audit fix + deferral docs — https://github.com/Stoff73/fynla/pull/284
- **#285** — W1-L bounded-TTL consent recheck on SSE stream — https://github.com/Stoff73/fynla/pull/285
- **#286** — Wave 2 batch (6 commits) — https://github.com/Stoff73/fynla/pull/286

## Tests added this session

16 new Pest cases, all green:

- `tests/Feature/Api/BugReportControllerTest.php` — 6 cases (401, 200 + queued mail, 422 console_logs cap, strip_tags applied, 422 description required, 429 rate limit)
- `tests/Feature/Exceptions/HandlerTest.php` — 1 case (HttpResponseException 429 round-trip via throttle middleware)
- `tests/Feature/AI/ConsentRuntimeCheckTest.php` — +1 case (W1-L perf: 1 hasConsent call for 26-event stream); existing strict-termination test reframed via `interval = 0`
- `tests/Architecture/DesignSystemInvariantsTest.php` — 2 cases (Rule #5 no `'sole'`, Rule #9 no amber/orange)
- `tests/Unit/Services/Estate/IHTCalculationPersistTest.php` — 2 cases (default no write, persist:true writes one row)
- `tests/Unit/Services/Business/BusinessInterestBADRTest.php` — 2 cases (no "10% rate" string, fail-loud when config missing)
- `tests/Unit/Services/Investment/AssetLocation/TaxDragCalculatorTest.php` — 3 cases (cash yield from config, bonds yield from config, `forUserOrJoint` scope)

Touched-module sweeps (Feature/Auth + Feature/Api + Feature/AI + Feature/Fyn + Unit/Services/Estate + Unit/Services/Business + Unit/Services/Investment + Architecture suite) all green. Total: 388 + 346 + 95 + 7 = ~836 tests run across the four PRs' regression sweeps. Zero net regressions. Only failure is the pre-existing `InvestmentControllerTest > PUT updates` full-suite flake which is documented in #281's body.

## Pick up from here

The expected next action depends on CSJ's intent:

**If merging PRs:** ship them in the order suggested above (#281 → #282 → #283 → #284 → #285 → #286). Each will need a fresh `gh pr checks <N>` to confirm CI green before merge. Use the established `gh pr merge <N> --merge --admin` pattern (see memory `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`). After #284 merges to dev, anyone running `npm audit fix --force` going forward will re-break PWA SW build — the override is the safety net.

**If continuing audit work:** the next natural follow-up is the **salary sacrifice 2027/28 implementation**, now unblocked by CSJ's confirmation. Branch `audit-salary-sacrifice-2027-28` off `origin/dev` after #281 merges (since #281 touches `TaxConfigurationSeeder.php` and the new work also needs to touch it). The work pattern is in `project_salary_sacrifice_2k_upcoming_law.md`.

**If deploying to dev (csjones.co/fynla):** none of the six PRs has been deployed. After the first merge, deploy dev per CLAUDE.md "Deploying to dev (csjones.co/fynla)". The npm changes in #284 (after it merges) will require a `npm ci` step on the csjones server during the deploy — note that override resolutions require a fresh install, not just `npm install`.

**If continuing other audit follow-ups (lower priority):** sibling fallbacks in `BusinessInterestService` (`higher_rate ?? 0.20`, `basic_rate ?? 0.10`), pink-* off-palette usage in `badge-vct` / `badge-eis`, arch tests for Rules #13 and #14 (need AST walker + router-parsing), W1-H controller-pattern refactor (deferred from session 5), net-worth Fyn `get_net_worth` tool (deferred from 8 May session 11).

## What the next session should NOT do

- Do NOT `npm audit fix --force` blindly. Read `docs/security/npm-audit-deferrals.md` first.
- Do NOT touch the existing strict-termination test (`ConsentRuntimeCheckTest:166`) without setting `interval = 0` first — that test is the byte-perfect S0.9 guard.
- Do NOT remove the £2,000 salary sacrifice limit (it IS the upcoming law — CSJ confirmed 2027/28 effective).
- Do NOT skip the iOS smoke if anyone actually undertakes the Capacitor 6 → 8 upgrade (Face ID is the most-touched mobile flow; Capacitor major bumps frequently break iOS bridge).
- Do NOT touch existing emoji/Unicode/icon violations on banned surfaces (Rule #16 forward-only — grandfathered per memory `feedback_rule_16_grandfather_existing.md`).

## Vault sync

**Deferred** this session due to tripwire pressure. Carry forward to next session-end alongside sessions 6-12 of May 11 (already in the backlog from prior sessions). Batch via Haiku 4.5 subagent.

## Memory files touched this session

- **Updated:** `project_salary_sacrifice_2k_upcoming_law.md` — effective year confirmed as 2027/28, prior hedge removed.

No new memory files written this session. All discoveries are documented in the open PR bodies (so the next reviewer / Claude can pick up the context from GitHub) and in this handover.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
