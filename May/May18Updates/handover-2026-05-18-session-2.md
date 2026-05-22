---
type: handover
mode: end-of-session
date: 2026-05-18
session: 2
branch: sp2Freemium (integration) — all 9 SP2 PRs merged
---

# End-of-Session Handover — 2026-05-18, Session 2

## Immediate state

**SP2 freemium-tier-model campaign EXECUTION COMPLETE.** All 9 PRs are
implemented, two-stage reviewed, and MERGED to `sp2Freemium`. `sp2Freemium`
tip = `696890ed`, local == `origin/sp2Freemium` (pushed). Sub-project
acceptance §18.2 met. **NOT deployed** (csjones/prod deliberately deferred —
see Deferrals). SP3–SP6 NOT touched (separate campaign).

## What happened this session

1. **Session-start blocker (fixed, CSJ-approved).** `php artisan db:seed`
   was broken: SP2's `DbTierGate` (bound globally in PR3) had an `is_admin`
   allowlist but **no `is_preview_user` exemption**, so preview personas
   resolved to the Free tier and `PreviewUserSeeder` threw
   `TierLimitExceededException` on the 4th savings account. CSJ confirmed the
   long-standing design: **preview personas sit ENTIRELY outside tiers /
   subscriptions / gates** (same code pathways as real users, flagged
   `is_preview_user`, outside auth, Fyn chat not live). Root-caused via
   systematic-debugging, TDD fix: `DbTierGate::canCreate` short-circuits
   `true` for preview (mirrors the `is_admin` branch); `DbTierGate::hardLimit`
   returns `null` for preview so `softLimit` + any upgrade-prompt UI never
   surface a cap. Regression test added. Committed **`37e84ca7`** directly
   to `sp2Freemium` (CSJ chose "commit to sp2Freemium, then resume PR7").
   `db:seed` now exits 0, all 6 personas, 11 preview users / 26 preview
   savings accounts. PR8 later added the same `is_preview_user` exemption to
   the new `DocumentAllowanceGate`.

2. **PR7 — generic TeaserGate + Estate consumer.** Resumed the UNREVIEWED
   WIP `cc254f0a` via subagent-driven-development (handover-prescribed method).
   Spec-compliance review found **8 issues** (hardcoded IHT rate/NRB/RNRB,
   CTA not from store, ungated `calculateIHT`/`getNetWorth`/`getCashFlow`,
   NRB-only exposure threshold, missing 403 test, unauthorised
   grandfathered-icon removal, weak test) → fixed → spec ✅. Code-quality
   review: 2 Important (duplicated 403 gate → extracted
   `App\Http\Traits\GatesEstateAccess`; over-broad `\Exception` catch
   narrowed) + minors → fixed → ✅ APPROVED. **Live Playwright caught a real
   bug**: `nextTierAbove('free')` → tier1, but tier1 is still teaser-gated,
   so the CTA said "Upgrade to Tier 1" when full Estate needs tier2 → added
   `TierConfigurationStore::lowestTierWithCapability('estate','full')`,
   removed dead `nextTierAbove`. Re-verified: free→teaser ("Upgrade to Tier 2"
   CTA), tier2→full module, free→403 on net-worth/cash-flow/calculate-iht
   while index still `mode:teaser`. **Merged PR #334 (MERGED on GitHub).**

3. **PR8 — doc allowance/quota + currency/snapshot/open-API flags.**
   Implemented (`DocumentAllowanceGate`, `CurrencyDisplayService`,
   `SnapshotPolicies` static→instance, `AuthController` `tier_flags`,
   Open-Banking affordance). Spec review found **5 issues** incl. 1 HIGH
   (storage-ceiling CTA pointed a tier2 user to *downgrade to free*) → fixed
   → spec ✅. Code-quality APPROVED; M1–M5 minors (extracted
   `findUpgradeTier` helper, `readonly`, terminal-tier tests) fixed → ✅.
   Live Playwright: tier2 → disabled "Connect via Open Banking — coming soon"
   (no icons, design tokens, inert); free → affordance hidden, page normal.
   **Merged into `sp2Freemium` as `c9e43295`** (see "GitHub PR records" note).

4. **PR9 — lock-down + augmented regression sweep (Opus subagents).**
   CSJ switched subagents to Opus mid-session. Removed `DAILY_TOKEN_LIMITS`
   from `HasAiGuardrails` (metering now 100% store-driven), hardened
   `tests/Architecture/StoreBoundary/TierConfigBoundaryTest.php` (HARD moat,
   spec §17), **deleted dead `PermissiveTierGate` + its test**. Augmented
   scope: 7 SP2-regressed test files (`EvalAuthControllerTest`,
   `DirectWriteObserverFireTest`, `EvalTraceListenerTest`,
   `EvalTracePersistenceTest`, `PreviewBypassAbilityTest`,
   `PreviewResetCompletenessTest`, `CrossModuleIntegrationTest`) got
   `TierConfigurationSeeder` in `beforeEach` (PR3 per-file pattern) +
   `TokenBudgetConcurrencyTest` fixture realignment — **zero assertions
   weakened**. Spec ✅ (independently re-verified all 7 swept files green) +
   code-quality ✅ APPROVED (Opus). 3 cheap moat-hygiene minors applied
   directly (dead allowlist entry, `countTierLiteralViolations2`→
   `countTierLiteralLeaks`, §17 docblock anchor). **Merged into
   `sp2Freemium` as `696890ed`**.

## §18.2 sub-project acceptance — VERIFIED

- **Full Pest suite (clean run on `696890ed`):** `3840 passed, 25 skipped,
  15 failed (15086 assertions)`, 636s.
- **The 15 failures are 100% the documented LOCAL-ENV-ONLY set** — proven:
  running ONLY `HashChainTest` + `RetentionPseudonymisationTest` +
  `ChainTamperDetectionTest` (Group A, `AI_AUDIT_HMAC_KEY`/`APP_KEY` absent
  → `AuditChainService:53` RuntimeException) + `ProviderSwapLockTest`
  (Group B, `ANTHROPIC_API_KEY` absent → designed 422) yields **exactly 15
  failed**, equalling the full-suite count → every other test (3840) passes.
  These files are byte-identical to base `c9e43295` (pre-PR9) — NOT
  regressions; they pass WITH the keys. Do NOT code-change them.
- **Architecture suite:** `129 passed` (1 deprecated pre-existing Sanctum, 1
  skipped eval-minima) — boundary moat HARD and executed.
- **Pint:** `app/ tests/ --dirty --test` → passed.
- The `TaxStrategyCalculatorTest > benchmark` 100ms wall-clock test (host-
  speed dependent, untouched by SP2) passed this run; it may flake on a
  loaded host — not a defect, out of SP2 scope.

## What is NOT done / deferred (honest)

- **NOT deployed.** csjones (dev) + fynla.org (prod) deploys deferred. The
  plan's per-PR "browser-test on csjones" (§18.1) was satisfied LOCALLY via
  Playwright for PR7/PR8; csjones deploy-stage browser tests for PR3/4/6/7/8
  remain a documented deferral → do at SP2 deploy time.
- **Revolut sandbox sync run** — no local Revolut keys (mocked in tests);
  deferred to SP2 deploy.
- **PR5 double `isWeeklyBudgetExceeded` query micro-opt** — deferred to a
  later PR (noted in session-1 handover; still open, non-blocking).
- **PR #317 (dev→main release)** — still parked. SP2 now sits on
  `sp2Freemium`, not yet on `dev`. The path to prod is:
  `sp2Freemium` → PR into `dev` → deploy/test csjones → PR `dev`→`main`.
  No one has opened the `sp2Freemium`→`dev` PR yet — that is a CSJ decision.
- **Vault absent** on this machine (`/Users/Chris/Desktop/fynlaBrain` does
  not exist) — vault-sync impossible; CSJTODO-freemium-series.md + this
  handover are the resume channel.
- **SP3–SP6** — separate campaign, not started, do not offer.

## GitHub PR records (cosmetic discrepancy — read this)

The campaign's merge mechanism is local `git merge --no-ff` into
`sp2Freemium` + `git push` (PRs auto-close MERGED only if the feature branch
was on origin BEFORE the merge). PR7's branch existed on origin from
session-1, so **PR #334 shows MERGED correctly**. PR8 and PR9 feature
branches were pushed AFTER their local `--no-ff` merge, so GitHub returns
"No commits between …" and **no PR record could be created** — the merge
commit messages reference `#335` (PR8) and `#336` (PR9) which **do not
exist as GitHub PRs**. This is purely cosmetic: the code is correctly and
fully in `sp2Freemium` (`c9e43295`, `696890ed`) and pushed. Branches
`feature/csj/sp2-pr8-sp1-flags` and `feature/csj/sp2-pr9-lockdown` are
pushed to origin for traceability. If a clean PR audit trail matters before
the dev release, recreate #335/#336 as closed/notes or accept the
merge-commit references as the record. Nothing functional is affected.

## Pick up from here (next session)

1. **Decision (CSJ):** open the `sp2Freemium` → `dev` PR? SP2 is execution-
   complete and green locally. Nothing should merge to `dev`/`main` without
   CSJ — do NOT auto-open it. Surface this and wait.
2. If deploying to dev: follow CLAUDE.md "Deploying to dev (csjones.co/fynla)"
   — build `./deploy/csjones-fynla/build.sh`, upload `public/build/`, SSH
   pull + migrate + cache-clear, then the deferred csjones Playwright
   browser tests for the tier gates (PR3/4/6/7/8) end-to-end on staging.
   Set the real `AI_AUDIT_HMAC_KEY` / `ANTHROPIC_API_KEY` on the server so
   Group A/B tests pass there (they are env-only locally).
3. Do NOT re-review or re-merge SP2 PRs — all 9 are done and green.
4. Do NOT start SP3–SP6 unless CSJ explicitly asks (separate campaign in
   CSJTODO-freemium-series.md).

## Context hints

- Branch: `sp2Freemium` @ `696890ed` (clean, pushed, == origin). Working
  tree has uncommitted `CSJTODO-freemium-series.md` + this handover (commit
  them — see below).
- Behind `origin/main` ~170+ (long-lived feature branch — normal, ignore).
- Feature branches pushed: `feature/csj/sp2-pr7-teaser-gate` (PR #334),
  `feature/csj/sp2-pr8-sp1-flags`, `feature/csj/sp2-pr9-lockdown`.
- `db:seed` is healthy again; local env fully set up; dev server was up on
  :8000 / Vite :5173 this session.
- Subagent models: PR7/PR8 on `sonnet`, PR9 on `opus` (CSJ switched). Method
  = `superpowers:subagent-driven-development`, two-stage review per PR.
- Key new files this campaign session: `app/Services/Tiers/TeaserGate.php`,
  `EstateIhtExposureDetector.php`, `app/Http/Traits/GatesEstateAccess.php`,
  `app/Services/Documents/DocumentAllowanceGate.php`,
  `app/Services/Stores/CurrencyDisplayService.php`,
  `TierConfigurationStore::lowestTierWithCapability()`,
  `tests/Architecture/StoreBoundary/TierConfigBoundaryTest.php` (HARD moat).
- Process note: I ran ~1h+ autonomously before CSJ interrupted asking for
  visibility. Going forward in long campaigns: check in at PR boundaries.
