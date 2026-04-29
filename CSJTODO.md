# CSJTODO — Fynla

*Last updated: 29 April 2026 — session 114 (BS-27 + BS-28 root-cause fixes shipped, both live GREEN).*
*Previous session: 113-evening (29 April — BS-26 GREEN + spouse_work blocker logged as TOP PRIORITY).*

---

## Session 114 (29 April 2026 — late evening) — BS-27 + BS-28 unblocked end-to-end

**Branch:** `feature/fyn-persona-split` (50 commits ahead of `origin/main`, all pushed). **1 commit this session:** `34b9915`.

### Completed this session

#### TOP PRIORITY blocker (carried from session 113-evening) — RESOLVED

- [x] **`STATE_CAMPAIGN_SPOUSE_WORK` bubble→tool wiring** — added a generic `bubble_capture` config to `OnboardingStateMachine`. New `OnboardingChatDirector::dispatchBubbleCapture()` runs synchronously between `persistCapture` and `getNextStateId` so the routing callable sees `users.household_calculation_mode` set by `capture_spouse_work_status`. Goes through `CoordinatingAgent::executeTool()` to keep preview gating + audit + xAI parity intact.

#### Three further blockers uncovered while driving BS-27/BS-28 — also RESOLVED in same commit

- [x] **Campaign tools registered for grouped_extract** — `AiToolDefinitions::onboardingExtractionTools()` now `array_merge($tools, $this->campaignSaveTaxTools())`. Director was rejecting `capture_spouse_household_data` / `capture_spouse_non_working_assets` calls as "not found" because the registry only carried the 4 base capture tools.
- [x] **Capture handlers return canonical receipt** — `handleCaptureSpouseHouseholdData` and `handleCaptureSpouseNonWorkingAssets` now return `{onboarding_capture: true, field_group, summary, details}` matching the pattern used by `handleCapturePersonalDetails`. `HasAiChat` now yields the `onboarding_field_captured` SSE event the director needs to advance state.
- [x] **Terminal-with-navigate from grouped_extract** — `handleGroupedExtractTurn` now branches to `emitTerminalNavigationTurn` when next state is `turn_type=terminal` with `navigate_to`, mirroring the free-text path. Without this, BS-27/BS-28 stalled at `campaign_terminal` and never reached `/tax-strategy`.

#### TDD + verification

- [x] **+3 new Pest cases** in `CampaignBubbleCaptureTest` (TDD: RED → GREEN proves the wiring works at director level).
- [x] **4 existing direct-write tests updated** (`CaptureSpouseHouseholdDataTest`, `CaptureSpouseNonWorkingAssetsTest`) for the new receipt shape.
- [x] **608/608** across onboarding + Fyn + architecture suite. Zero regressions.
- [x] **BS-27 (Path B / dual_earner)** verified live in browser with `bs27v2-2026-04-29@example.com` — `mode='dual_earner'`, `ma_eligible=false`, twin AllowanceGrid + "Coordinate as a household" panel + GIA-to-spouse suggestion; AssetShiftingPanel correctly hidden.
- [x] **BS-28 (Path C / single_earner_couple)** verified live in browser with `bs28-2026-04-29@example.com` (£50k basic-rate) — `mode='single_earner_couple'`, `ma_eligible=true`, browser auto-navigated to `/tax-strategy`; AssetShiftingPanel showed Marriage Allowance £252/yr + Gift £5k savings to spouse £3,714/yr + ISA top-up £15k; cross-spouse panel correctly hidden.

#### Tech-debt sweep

- [x] Clean bill — 0 critical, 0 warnings, 1 deferable suggestion (4-conditional guard clause in `dispatchBubbleCapture` — only worth touching if a second bubble→tool wiring is added). Report at `tech-debt-report.md`.

### NOT Done — Outstanding for next session

#### Deploy combined sessions 112 + 113 + 114 to dev (csjones.co/fynla)

Session 114 only modified files already in the existing deploy set; **no new files to upload**. The deploy lists in [[April/April29Updates/savetax-section4-6-deploy-notes|savetax-section4-6-deploy-notes]] (sections 4-6) and [[April/April29Updates/deploy-notes|deploy-notes]] (sections 1-3) are still accurate — the **content** of the files has changed (new `34b9915` versions), but the upload set hasn't.

- [ ] Open PR `feature/fyn-persona-split → dev`, merge after Stoff73 approval
- [ ] Build with `./deploy/csjones-fynla/build.sh`
- [ ] Upload `public/build/` + the cumulative file set: 16 PHP backend + 11 frontend + 4 migrations (1 from session 112 + 3 from session 113)
- [ ] SSH: `php artisan migrate --force && php artisan db:seed --class=TaxConfigurationSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`
- [ ] Re-drive BS-26/27/28 against `csjones.co/fynla` per Rule #15 LOOP UNTIL CORRECT (live browser walkthroughs of all 3 paths)

#### After dev green — production deploy

- [ ] Open PR `dev → main`, merge, repeat with `./deploy/fynla-org/build.sh`
- [ ] Monitor `storage/logs/laravel.log` for 10-15 minutes post-deploy
- [ ] Push `34b9915` (already on origin/feature/fyn-persona-split)

#### After SaveTax — return to Sprint 1 work

- [ ] **Re-record the 9 mitchell scenarios** with the new EngineCalled emits captured in `engine_trace`. ~$1.80 total.
- [ ] **S1.7.a** — Extend `tests/Feature/Fyn/Eval/AssertionHelpers.php` with new keys per `April/April27Updates/eval-expectations-rewrite.md` §3. **Blocks every other S1.7 sub-task.**
- [ ] **S1.7.b–i** — Architecture meta-test #6, 4 canonical-behaviour YAMLs, EvalProviderRun column additions + dashboard, 14 state-machine YAMLs, 14 handoff YAMLs, 16 resume YAMLs, hard-gate verification doc.
- [ ] **S1.7.c addendum** — Eval YAML scenarios for the **10** new SaveTax campaign states (was 9 — `STATE_CAMPAIGN_INTRO` added in session 113-evening). 10 state-machine + 3 handoff + ~3 resume YAMLs.
- [ ] **S1.9** — Browser matrix: BS-03, BS-08, BS-09, BS-24 + BS-01–BS-23 regression.
- [ ] **S1.10** — Sprint 1 verification rollup (Rubric-A ≥17/40 🟠).

### Tech-debt deferred (don't auto-fix)

- [ ] **`OnboardingChatDirector::dispatchBubbleCapture` 4-conditional guard** (`OnboardingChatDirector.php:786`) — extract to `validateBubbleCaptureConfig()` helper if a second bubble→tool wiring is ever added. Not blocking.
- [ ] **`TaxStrategyCalculator.php:314` — hardcoded £500 dividend allowance** in the GIA-to-spouse suggestion's `description` string + `available_dividend_allowance` field (carried from session 113). Should source from `TaxConfigService::getDividendTax()['allowance']`. Quick fix.
- [ ] **`TaxStrategyCalculator::buildUserAllowanceGrid` (line 79–138) is 60+ lines** (carried from session 113). Extract per-allowance helpers when next adding a new allowance.

### Carried tech-debt from earlier sessions

- [ ] **§5.4 spec-parity — codify simplification (option b) decision.** §5.4(a) shipped via session 110; option (b) (trim `*_recommendation` strings) now moot. Mark §5.4 closed.
- [ ] **`Auditable::shouldAudit` must gate on `bypass-preview-mode`** when first mutating scenario lands. Currently in `PreviewBlockSitesCheckBypassTest` ignore-list with TODO.
- [ ] **`SaveTaxCampaignPage.vue:191-194`** inline `formatAmount` duplicates `currencyMixin.formatCurrency` (session 112 carry).
- [ ] **`SaveTaxCampaignPage.vue:14-26`** hardcoded fallback allowances (session 112 carry — intentional graceful degradation).
- [ ] **Vue 3 `$listeners` warning** on `<FynOnboardingChat docked=true onCollapse=fn>` — Vue 2 idiom in a Vue 3 component (carried from session 113-evening). Quick fix: replace `$listeners` references with explicit prop emits.
- [ ] **`StructuredResponseValidator` flagged "SIPP" as banned acronym** in an LLM ack (carried from session 113-evening). Either add a per-state allowlist for terms used in the prompt, or add SIPP to the canonical exceptions alongside ISA in CLAUDE.md Rule #10.
- [ ] **TaxStrategyCalculator under-counts pension AA usage on initial load** (carried from session 113-evening). Worth confirming the calculator includes salary-sacrifice contributions in the AA usage on first render.

### Deploy status

**All 3 SaveTax campaign paths verified live end-to-end on local dev:** Path A (BS-26, session 113-evening), Path B (BS-27, session 114), Path C (BS-28, session 114). Pest 608/608 across onboarding + Fyn + architecture. The session 114 commit `34b9915` is pushed to `origin/feature/fyn-persona-split` (50 commits ahead of `origin/main`). **Nothing pushed to dev or prod yet.**

### Context for next session

The campaign branch is feature-complete and live-verified across all 3 household paths. The remaining work is operational: open the PR to dev, merge, build, upload, smoke test in browser against `csjones.co/fynla`, then promote to production. After that, return to Sprint 1 (mitchell re-record → S1.7.a AssertionHelpers extension → S1.7.c eval YAMLs covering the 10 SaveTax campaign states including STATE_CAMPAIGN_INTRO).

---

## Session 113 (29 April 2026 — afternoon/evening) — SaveTax sections 4-6 (post-expenses + terminal dashboard)

(Previous session log condensed — full detail at [[Git History/Apr2026/Apr29|Apr29.md]] and [[April/April29Updates/savetax-section4-6-spec|savetax-section4-6-spec]].)

**6 commits:** `6e75afc`, `eb7761c`, `612952c`, `9c5cdf8`, `560313e`, `916a0f4`. Plus 2 evening commits (`fde5b53`, `aad29db`) shipping STATE_CAMPAIGN_INTRO consent gate + 3 inline campaign-flow fixes after BS-26 live walkthrough.

Outcome: SaveTax sections 4-6 shipped end-to-end. 9 new state-machine states + 4 capture tools + TaxStrategyCalculator + /tax-strategy dashboard + 3 first-class household paths. All 6 implementation phases shipped. 791/791 → 417/417 in onboarding+Fyn after evening fixes.

---

## Session 112 (29 April 2026 — morning) — SaveTax campaign onboarding + channel attribution

(Previous session log condensed — full detail at [[Git History/Apr2026/Apr29|Apr29.md]] and [[April/April29Updates/savetax-feature-patch-notes|savetax-feature-patch-notes]].)

**7 commits:** `d45f6bf`, `d910833`, `38bac32`, `2a34ee6`, `fd9fc26`, `c0f0a99`, `823d0f0`. Outcome: SaveTax sections 1-3 shipped (landing page, `?from=` wire-through, campaign-welcome, live tax allowances, channel attribution, typo fixes). +26 new Pest tests.
