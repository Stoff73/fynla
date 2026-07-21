---
type: handover
mode: context-clear
date: 2026-05-18
session: 1
branch: feature/csj/sp2-pr7-teaser-gate (WIP) / sp2Freemium (integration)
---

# Context Clear Handover — 2026-05-18, Session 1

## Immediate state

Executing the **SP2 freemium-tier-model 9-PR plan** via subagent-driven-development.
**PR1–PR6 are COMPLETE, two-stage-reviewed, browser-tested where relevant, and
MERGED to `sp2Freemium`** (GitHub PRs #327 #328 #329 #330 #331 #333, all merged-by-push).
`sp2Freemium` tip = `ae3ca9d8` (PR6 merge), local == `origin/sp2Freemium`.
**PR7 implementer output exists but is UNREVIEWED / UNVERIFIED / NOT MERGED** —
committed as WIP `cc254f0a` on `feature/csj/sp2-pr7-teaser-gate` (pushed) purely
to survive this context-clear. PR8 and PR9 not started.

## The thread

- User instruction: build `docs/superpowers/plans/2026-05-16-sub-project-2-freemium-tier-model-plan.md`.
  Standing directive: execute **PR5→PR9 to completion** (each: implement → spec
  review → code-quality review → fix loop → merge to `sp2Freemium`), stop only
  for a genuine new decision-blocker, **then write a session handover**.
- Method (replicate exactly): `superpowers:subagent-driven-development`. One fresh
  general-purpose subagent (model `sonnet`) per PR, given ONLY the relevant plan
  line-range + context + Fynla rules (never the whole plan). Then a spec-compliance
  reviewer subagent, then (only after spec ✅) a code-quality reviewer subagent,
  each running tests themselves and trusting nothing. Reviewer issues → SendMessage
  the SAME implementer agent to fix → re-review → repeat until both green. Then
  merge `--no-ff` into `sp2Freemium` locally + `git push origin sp2Freemium`.
- Branch model (CSJ decision, plan doc already retargeted): every PR is a feature
  branch off the current `sp2Freemium` tip, `gh pr create --base sp2Freemium`,
  then merged `--no-ff` into `sp2Freemium` and pushed (PR auto-closes MERGED).
- §22 **A9 RESOLVED** (CSJ): there are **no existing payers**; ALL legacy cohorts
  (student/standard/family/pro) convert to **Free**. No mechanical map, no legacy
  price seed. `TierResolver::isGrandfatheredLegacyPaid` stays (returns false for
  everyone in practice). PR5 price-lock is a vacuous safety net.

## Files touched / committed this session

All PR1–6 merged to `sp2Freemium` (see `git log --oneline sp2Freemium`). PR5 took
4 fix rounds (each found a real bug: confirm-500 via price-lock test, users.tier
not set on purchase = CRITICAL, expiry/cancel/purge not revoking tier,
SUBSCRIPTION_FINISHED not revoking) — full tier grant+revoke lifecycle now
correct on all 7 paths. PR7 WIP files (UNVERIFIED) on `feature/csj/sp2-pr7-teaser-gate`:
`app/Services/Tiers/TeaserGate.php`, `EstateIhtExposureDetector.php`,
`EstateController.php`, `Estate/IHTController.php`, `CheckSubscription.php`,
`resources/js/store/modules/estate.js`, `views/Estate/EstateDashboard.vue`,
`tests/Feature/Tiers/EstateTeaserGateTest.php`,
`tests/Unit/Services/Tiers/EstateIhtExposureDetectorTest.php`, +2 Estate test mods.

## What the next Claude needs to know (non-obvious)

- **The PR7 WIP commit `cc254f0a` is NOT trustworthy.** It was never spec-reviewed,
  code-quality-reviewed, or test-verified. Do NOT merge it as-is. Resume the
  two-stage review loop on it (treat the WIP as the "implementer output", dispatch
  the spec reviewer first, then code-quality, fix loop, then merge). Re-run its
  tests before believing anything.
- **Vault is ABSENT on this machine** (`/Users/Chris/Desktop/fynlaBrain` does not
  exist). vault-sync cannot run. `CLAUDE.md` + `MEMORY.md` auto-load. The campaign
  resume anchor is **`CSJTODO-freemium-series.md`** (repo root) — read it first;
  it has the full decisions log incl. A9. planning-with-files docs intentionally
  NOT created (campaign already has a robust resume channel; would clutter).
- **Local env is fully set up** (this session bootstrapped it): composer + npm
  installed, `.env` configured (`APP_DEBUG=true`, `SESSION_SECURE_COOKIE=false`,
  `MAIL_MAILER=log`, empty `ANTHROPIC_API_KEY`/`REVOLUT_API_KEY`/`AI_AUDIT_HMAC_KEY`),
  MySQL `laravel` migrated+seeded, Pest test DB `laravel_testing` exists+migrated,
  Playwright + Chromium installed. Servers may need restart: `php artisan serve
  --host=127.0.0.1 --port=8000` + `npm run dev` (Vite 5173). `storage/framework/views`
  etc. were created (don't delete). `php artisan storage:link` done.
- **Tech-debt-session NOT run** (deliberate): every PR1–6 already had per-PR
  two-stage review (spec + code-quality) that surfaced & resolved/tracked debt.
  A redundant whole-SP2 audit was skipped for a fast context-clear. PR9 runs the
  full suite as its acceptance gate anyway.
- Local Playwright login: admin `chris@fynla.org`/`Password1!`, fetch the
  verification code from DB:
  `php artisan tinker --execute="\$u=\App\Models\User::where('email','chris@fynla.org')->first(); echo \App\Models\EmailVerificationCode::where('user_id',\$u->id)->latest()->first()->code;"`
- Test users (`john@example.com`/`password`) hit the same email-verification gate;
  the repo's `tests/e2e/*.spec.js` auth helper is stale (doesn't handle the code) —
  use Playwright MCP for browser checks, not that suite.
- Two false-positive "SECURITY WARNING"s fired on bare `git push` from subagents;
  each verified scoped to its feature branch (protected branches untouched).
  Instruct subagents to push with an explicit branch name.
- **PR9 scope was AUGMENTED** (see task #9 / `CSJTODO-freemium-series.md`): besides
  the plan's lock-down (delete `DAILY_TOKEN_LIMITS`, harden boundary, remove dead
  `PermissiveTierGate` + duplicate binding test), PR9 must ALSO close pre-existing
  SP2-caused full-suite regressions surfaced in PR6 review — tests whose
  `beforeEach` seeds `PreviewUserSeeder`/`TaxConfigurationSeeder` but not
  `TierConfigurationSeeder` now fail via `SavingsStore→DbTierGate→TierConfigurationStore`
  (≥ `EvalAuthControllerTest`, `DirectWriteObserverFireTest`; sweep the full suite).
  Fix = add `TierConfigurationSeeder` to their `beforeEach` (same pattern PR3 used
  for 10 Savings tests), NEVER weaken a test. Group A (`AI_AUDIT_HMAC_KEY`) +
  Group B (`ANTHROPIC_API_KEY` ProviderSwap) `--filter=Ai` failures are
  LOCAL-ENV-ONLY (correct guards, pass with keys) — document, do NOT code-change.
- Honest deferrals carried (track in final handover): csjones deploy-stage
  browser tests for PR3/4/6/7 → SP2 deploy; Revolut sandbox sync run (no local
  keys, mocked) → SP2 deploy; PR5 double `isWeeklyBudgetExceeded` query micro-opt
  → later PR. None block PR7–9.

## Pick up from here

1. `git checkout feature/csj/sp2-pr7-teaser-gate` (HEAD `cc254f0a`, based off
   `sp2Freemium`@`ae3ca9d8`). Read `CSJTODO-freemium-series.md` + plan lines
   1697–1821 (PR7 Tasks 7.1–7.3).
2. Dispatch the **spec-compliance reviewer** subagent on the WIP (it is the
   "implementer output"): verify Tasks 7.1–7.3 vs spec §10 + Rules #3 (TaxConfigService
   for NRB/RNRB, no hardcoded), #13 (no scores — currency only), #16 (Estate is a
   detail-view = BANNED icon surface; teaser/CTA plain text + design tokens), #10
   (spell out Inheritance Tax/Nil-Rate Band), #14 (EstateDashboard keeps AppLayout),
   defence-in-depth (server authoritative; Free still LOADS the teaser page, not
   bounced; full-only sub-routes 403), `CAPABILITY_ROUTE_MAP` estate entry,
   TeaserGate via `TierConfigurationStore` (boundary moat — don't widen allowlist),
   `EstateIhtExposureDetector` reuses canonical net-worth (NOT the full Estate
   engine). It must run the tests itself (`./vendor/bin/pest tests/Feature/Tiers/EstateTeaserGateTest.php tests/Unit/Services/Tiers/EstateIhtExposureDetectorTest.php tests/ --filter=Estate -v`, `--testsuite=Architecture`, `pint --test`).
3. Fix loop via SendMessage to a PR7 implementer agent until spec ✅, then
   code-quality reviewer, then fix loop, then merge `--no-ff` to `sp2Freemium`,
   push. Local Playwright check (admin login → Estate as free→teaser, tier2→full;
   free cannot deep-link a full sub-route) before marking complete.
4. Then PR8 (plan 1824–1882) then PR9 (plan 1884–1937 + the augmented scope above),
   same loop. After PR9: verify sub-project acceptance spec §18.2 (full Pest +
   Architecture + `pint --test` green, env-key-dependent tests excepted+documented),
   update `CSJTODO-freemium-series.md` (SP2 row → execution complete), then write
   the **end-of-session handover** the user asked for.
5. Do NOT offer SP3–SP6 (separate campaign). Do NOT deploy. PR #317 stays parked.

## Context hints

- Active branch: `feature/csj/sp2-pr7-teaser-gate` (WIP HEAD `cc254f0a`);
  integration branch `sp2Freemium` @ `ae3ca9d8` (clean, pushed).
- Behind origin/main: ~160+ (long-lived feature branch — normal, ignore).
- Uncommitted: none, working tree clean.
- Last `sp2Freemium` commit: `ae3ca9d8` Merge SP2 PR6.
- TaskList state: #5 #6 completed; #7 in_progress (WIP unverified); #8 #9 pending
  (#9 description carries the augmented scope).
- Vault: unreachable (absent). No vault mirror of this handover written.
