# CSJTODO — Fynla

*Last updated: 1 May 2026 (session 2 context-clear wrap) — review-fix sweep on `fix/persona-split-review-fixes`, PR #239 open back to `feature/fyn-persona-split`.*
*Previous session: 125 / session 1 (1 May morning — full-branch review compiled, FAIL).*

---

## Session 2 (1 May 2026, evening) — Review-fix sweep

**Branch:** `fix/persona-split-review-fixes` @ `5320f64` (HEAD), pushed. **PR #239** open to `feature/fyn-persona-split`.

### Completed this session

14 commits resolving 9 P0s + 18 P1s from `branch-review-fyn-persona-split.md`:

- [x] **All 5 RED `TaxStrategyCalculatorTest` assertions** (P0.5) — aligned with 23f68ec strategy contract (`353e863`).
- [x] **AdviceFyn write-tool leak** (P0.2) — 6 `capture_*` tools added to `WRITE_TOOLS`; `AdviceFynToolListTest` now auto-enumerates from `getTools(false)` regex (`b1965b9`).
- [x] **`is_eval_user` dead column dropped + `eval_user_id` renamed → `preview_user_id` + `EvalPurgeCommand` repointed** to operate on `eval_recording_sessions` (P0.1, `80c4189`).
- [x] **`resetPersonaIfMutating` shape-aware** for both flat and canonical `{created,updated,deleted}` shapes (P0.4, `1874e98`).
- [x] **`AdviceFyn::wrapStream` drops every non-DELEGATE handoff event** (P0.9, `48d0cc0`).
- [x] **Spouse email lowercased before lookup** in `SpouseLinkingService` + `CoordinatingAgent` (P0.8, `00a52ab`).
- [x] **Vue-3 `v-on="$listeners"` removed** from `FynOnboardingChat.vue` (P0.7, `65f22f4`).
- [x] **`estimateIsaSubscriptionsThisYear` scoped to current tax year** via `created_at >= getEffectiveFrom()` (P0.6, `65f22f4`).
- [x] **`EvalDeltaBuilder result_path` back-fills tool results** from `ai_messages.metadata.tool_calls.result_summary` (P0.3, `9a89f44`).
- [x] **`AssistantContentSanitiser` → `XaiFunctionCallLeakStripper`** rename (P0.10, `7ff64b3`).
- [x] **User-copy acronyms expanded**: NIC's → National Insurance contributions, SIPP → Self-Invested Personal Pension, "tapered AA" → "tapered Annual Allowance" (M1, M5, M6).
- [x] **`SaveTaxCampaignPage` mixes in currencyMixin** (M2); aiChat consent error reworded to point at support not non-existent Settings toggle (M3); admin eval glyphs (`✓✗×→←`) text-replaced (M4).
- [x] **`IncomeBandStrategy` + `AssetShiftingBundleStrategy` band rates from `TaxConfigService`** (M7, M8).
- [x] **Junior pension `£2,880`/`£720` lifted to `TaxDefaults::NON_EARNER_PENSION_*`** (M9, partial — promote to `TaxConfigService` schema still deferred under S-3).
- [x] **`JointSavingsStrategy` honours `civil_partnership`** (M10).
- [x] **`Protection::hasIncome()` includes `annual_interest_income`** (M14).
- [x] **HMAC key fail-loud** — `AuditChainService::hmacKey()` throws if config empty (M19).
- [x] **`EvalRecordCommand::locateScenario` validates id before `glob()`** (M22).
- [x] **`completeness_percent` aligned across 5 readiness services** + Investment `loadMissing` guard (M12, M13).
- [x] **`AiAuditRetentionJob` chunks DELETE in 5k batches + new `(operation, created_at)` covering index** (M15).
- [x] **`QuerySchemas::HOLISTIC_PRIORITY` const → static method** sourcing PA-taper bands from `TaxConfigService`/`TaxDefaults` (M16).
- [x] **`PensionInputHistory` uses `Auditable` trait** (M17).
- [x] **`AiChatController::sendMessage` → `SendAiChatMessageRequest` FormRequest** (M18).
- [x] **Bypass-preview-mode token mint + use logged** via configurable `eval_audit_channel` (M20).
- [x] **`EvalTraceListener` routes through `EvalBypassGate::isActive`** — F-12 closure (M21).
- [x] **Every SVG icon stripped from Fyn chat surfaces** (`AiChatPanel`, `AiMessageContent`, `StaticFynChat`) per Rule #14 (M24).
- [x] **`unused_carry_forward` rename downstream impact audit complete** — only the strategy class itself referenced the old key (M23).

### NOT Done — Outstanding for next session

- [ ] **M11 — income-basis inconsistency** in `AssetShifting`, `CrossSpouse`, `JointSavings` strategies (raw `annual_employment_income` vs composed `taxableIncomeFor`). Deliberately deferred — needs HMRC-rule analysis per strategy. Marriage Allowance in particular keys off TAXABLE income at HMRC, so `AssetShifting:42` may need updating.
- [ ] **Browser smoke** — drive £75k persona on `/tax-strategy` via Playwright, verify £ amounts (per `feedback_smoke_must_verify_amounts.md`). Reviewers can't substitute.
- [ ] **Full `./vendor/bin/pest` sweep** — only touched-area tests run (143/143 GREEN). 3 migrations + multiple service rewrites warrant full suite before PR merges.
- [ ] **Re-record any eval recordings** whose `result_path` previously graded falsely-`success` — P0.3 fix changes the recorded shape; old fixtures may now mismatch.
- [ ] **Pre-existing red noted, not regressed by this branch** — `EvalAuthControllerTest > "reset endpoint runs preview:reset for the persona"` was already RED on parent `614867bc`. Separate issue.
- [ ] **PR #239 review + merge** into `feature/fyn-persona-split`. CSJ owns the merge timing.

### Branch / deploy status

| Environment | Branch | Status |
|---|---|---|
| Production (`fynla.org`) | `main` | NOT touched |
| Dev / staging (`csjones.co/fynla`) | `feature/fyn-persona-split` @ `23f68ec` | LIVE — does NOT include the review-fix work yet |
| Feature branch | `feature/fyn-persona-split` @ `97b21a3` | Parent of fix branch; 14 review-fixes pending in PR #239 |
| Fix branch | `fix/persona-split-review-fixes` @ `5320f64` | Pushed, PR #239 open |

**Nothing to deploy this session.** Per `feedback_no_deploy_recommendations.md`, CSJ owns merge + deploy timing. The fix branch is structurally correct but not yet browser-smoked.

---

## Session 125 / session 1 (1 May 2026, morning) — Skill migration + full-branch review

**Branch:** `feature/fyn-persona-split` @ `41eed00` (HEAD), pushed to origin. 259 ahead of `origin/dev`, 0 behind.

### Completed this session

- [x] **Moved session-start / session-end / vault-sync skills to `~/.claude/skills/`** (`97b21a3`). Project-level copies deleted on this branch. session-end upgraded to onboardingFyn version (context-clear vs end-of-day mode, dated handover, planning-with-files mirror). vault-sync wrapped to dispatch via Haiku 4.5 subagent at high effort.
- [x] **Full-branch eval review** (`41eed00`) via 6 parallel `eval-reviewer` agents covering 212 of 213 non-doc/non-test files in `feature/fyn-persona-split` vs `origin/dev` (259 commits, +157,628 / -3,887 lines). Aggregated FAIL verdict: **8 critical, 32 major, 45 minor, 37 nit**. Full report at `May/May1Updates/branch-review-fyn-persona-split.md` (also synced to vault).

### NOT Done — Outstanding for next session

#### TOP PRIORITY — branch is FAIL, need P0 fix pass before merge to dev

Full prioritised list in `May/May1Updates/branch-review-fyn-persona-split.md`. Headline P0s:

- [ ] **5 RED tests** in `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` from fix commit `23f68ec` — assertions need updating to match new gates (Marriage Allowance hidden for single users, carry-forward gated by liquid wealth, field rename `unused_carry_forward` → `unused_carry_forward_total`).
- [ ] **AdviceFyn leaks 6 capture_* tools** to LLM — Two-Fyn contract violation. Add to `WRITE_TOOLS`: `capture_salary_sacrifice`, `capture_spouse_work_status`, `capture_spouse_household_data`, `capture_spouse_non_working_assets`, `capture_pension_history`, `capture_charitable_giving`. AND replace `AdviceFynToolListTest` fixture with auto-enumeration (current fixture omits same 6 tools so test gives false assurance).
- [ ] **`is_eval_user` dead column + `EvalPurgeCommand`** violate canonical 0.2 (triple-confirmed by 3 reviewers). Drop column + index in new migration; remove command or repoint to `eval_recording_sessions`/`eval_provider_runs` rows.
- [ ] **`EvalDeltaBuilder result_path` always returns `'success'`** — SSE doesn't carry tool result strings. Any scenario asserting `result_path: success_false` cannot grade GREEN. Same shape as P0.2 regression.
- [ ] **`EvalRecordCommand::resetPersonaIfMutating`** uses `empty($writes)` against `{created:[],updated:[],deleted:[]}` shape — fires reset on every scenario (canonical 0.1). Replace with `! ($writes['created'] || $writes['updated'] || $writes['deleted'])`.
- [ ] **`estimateIsaSubscriptionsThisYear` returns LIFETIME ISA balance** — root cause of yesterday's £75k user defects only suppressed at one site. Restrict to current-tax-year subscriptions.
- [ ] **`v-on="$listeners"` in `FynOnboardingChat.vue:55`** is Vue 3 incorrect (`$listeners` removed in v3). Drop it; `v-bind="$attrs"` already covers events.
- [ ] **Spouse email lookup is case-sensitive** — `SpouseLinkingService.php:95` + `CoordinatingAgent.php:1186`. Add `strtolower(trim(...))` to prevent duplicate accounts.
- [ ] **`capture_complete` handoff event can leak to frontend** (INV-2.4.1). Drop ALL `type === 'handoff'` events in `AdviceFyn::wrapStream`, not just `DELEGATE_TO_CAPTURE`.
- [ ] **`AssistantContentSanitiser` is misnamed** — only strips xAI `<function_call>` tags, not a prompt-injection guard. Rename to `XaiFunctionCallLeakStripper` or expand scope.

#### P1 (should fix before merge to dev — full list in branch review)

Headlines: NIC's / SIPP / "tapered AA" acronyms (Rule #10), local `formatAmount` in SaveTaxCampaignPage (Rule #6), Unicode glyphs in admin eval components (Rule #14), hardcoded tax rates 0.40/0.45/0.20/0.60 in IncomeBandStrategy + AssetShiftingBundleStrategy (Rule #3), JointSavingsStrategy missing civil_partnership, inconsistent income basis across 4 strategies, 5 DataReadiness services have drifted return shapes, Investment DataReadinessService missing `loadMissing` guards, Protection `hasIncome()` missing `annual_interest_income`, AiAuditRetentionJob will lock production at scale, HMAC key triple-fallback to literal string, "Re-grant in Settings" message conflicts with memory law, etc.

#### Skill migration follow-up

- [ ] **Cherry-pick `97b21a3` (project-level skill deletion) to `dev`** — until this lands on dev, checking out dev or main will restore the old project-level files which override user-level. The migration is functionally complete on this branch only.
- [ ] **Plugin fallback asymmetry accepted knowingly** — `plugins/fynla-dev-skills/skills/` has session-start + session-end fallbacks but NOT vault-sync. If `~/.claude/skills/vault-sync/` is ever lost, `session-end` Phase 7 has no fallback. Decision: live with it (CSJ chose option B over adding the stub).

#### Carried forward from session 124 (still open)

- [ ] **Cleanup `public/build.old/` on csjones.co** after 24h rollback window expires (1 May evening).
- [ ] **Tail `storage/logs/laravel.log`** for any post-deploy errors over the next 12-24h.
- [ ] **`interest_rate` column convention drift** — patched inline in tax-strategy code; deeper fix is to pick decimal vs percent and migrate data + factory + seeders + UI.
- [ ] **Pension % → £ conversion** — onboarding never derives `monthly_contribution_amount`; audit other consumers.
- [ ] **PSA "Fully used" wording** — review for equal/over-PSA cases.
- [ ] **Sanity-check OTHER strategies** for implausible recommendations on persona-realistic data: `LifecycleStrategy`, `GiftAidHigherRateReliefStrategy`, `BedAndIsaStrategy`, `DividendAllowanceHarvestStrategy`, `SalarySacrificeNiStrategy`, `NonEarnerSpousePensionStrategy`, `CrossSpouseBundleStrategy`, `TaperedAnnualAllowanceStrategy`. Top priority before dev → main release PR.
- [ ] **Liquid wealth threshold for carry-forward** — currently hardcoded `MIN_LIQUID_WEALTH_TO_RECOMMEND = 10000.0`; tune with persona evidence.
- [ ] **W-1 (session 123)** — orphaned slider backend pipework (`taxStrategy/recalculate` Vuex, `TaxStrategyController::calculate`, etc.) all unreachable from UI. Rip out.
- [ ] **S-3 (sessions 118-120)** — hardcoded £2,880 / £720 Junior Pension in `LifecycleStrategy::generate` — expose via `TaxConfigService`.
- [ ] **W-1 (session 122 / Rule #14)** — `StaticFynChat.vue` lines 26/30/34/101 violate icon ban in Fyn chat window.
- [ ] **W-2 (session 122)** — `SaveTaxCampaignPage.vue` bottom CTA green vs the other 6 raspberry — visually inconsistent.
- [ ] **Rate-normalisation duplication** — `if ($r > 1) $r /= 100` in 3 places; extract once data convention is settled.

### Context for Next Session

- **Branch is FAIL** — do NOT merge to dev. Fix path is at minimum 6–10 commits before mergeable. See branch review for prioritised order.
- **Recommended next steps:** (1) Get tests GREEN (5 RED Tax tests), (2) batch the P0 contract fixes into one commit (no behaviour change for happy path, just close holes), (3) real-money fixes (`estimateIsaSubscriptionsThisYear`, `EvalDeltaBuilder result_path`, etc.) into separate commit with browser smoke verification, (4) user-copy fixes (acronyms), (5) UI cleanups.
- **Per `feedback_smoke_must_verify_amounts.md` (issued today)** — after real-money fixes, drive Playwright against £75k user persona and verify £ amounts on `/tax-strategy` against actual profile.
- **Per `feedback_no_deploy_recommendations.md`** — branch is nowhere near deploy-ready. Don't suggest deploy; CSJ decides.
- **Skill canonical source** is now `~/.claude/skills/`. Don't add project-level copies. dev/main still have stale project-level files that need cleanup.

### Deploy Status

| Environment | Branch | Status |
|---|---|---|
| Production (`fynla.org`) | `main` | NOT touched — gated on full P0 fix pass + dev verification |
| Dev / staging (`csjones.co/fynla`) | `feature/fyn-persona-split` @ `23f68ec` | LIVE from session 124 — has the same defects this branch review surfaced |
| Feature branch local | `feature/fyn-persona-split` @ `41eed00` | Pushed, branch review committed, NOT mergeable yet |

### Memory laws relevant to next session

- `feedback_loop_until_correct.md` — Don't stop until GREEN per plan.
- `critical_browser_testing_law.md` — "Browser tested" = clicked / filled / submitted in Playwright.
- `feedback_smoke_must_verify_amounts.md` — Verify £ amounts against user's actual profile.
- `feedback_no_deploy_recommendations.md` — Don't suggest deploy as next step.
- `feedback_main_via_dev_only.md` — Production gated on dev first.
- `feedback_advice_fyn_is_read_only.md` — Two-Fyn contract; AdviceFyn has zero write tools.
- `feedback_eval_canonical_contract.md` — Canonical 0.1 + 0.2 (no mirror user / no `is_eval_user`).
- `feedback_evals_surface_engineering_issues.md` — Failing eval = real bug, fix code not test.

---

## Session 124 (30 April → 1 May 2026, late evening) — Dev deploy + 4 critical fixes

**Branch:** `feature/fyn-persona-split` @ `23f68ec` (HEAD), pushed to origin. 257 ahead of `dev`, 0 behind.

### Completed this session

- [x] **Pre-flight Step 4** flagged 33-commit drift behind `origin/dev` (PR #238 news/RSS/lifecycle/newsletter merged 28 Apr). Without merging, deploy would have regressed those features on csjones.co.
- [x] **Merge `origin/dev` into feature branch** (`5dfe1a3`). 4 conflict files all additive — `CLAUDE.md`, `CSJTODO.md`, `routes/api.php`, `tech-debt-report.md`. Backup at `backup/fyn-persona-split-pre-merge`.
- [x] **Pest test infrastructure fix** (`6ce6510`). `Eval` testsuite was a subdirectory of `Feature` testsuite, double-binding `Tests\TestCase` and crashing every default-suite sweep. Fixed with 1-line `<exclude>` in `phpunit.xml`. 44 + 6 + 22 sample tests now pass.
- [x] **Fix the deploy guide** — `April/April30Updates/fynPersona.md` updated for post-merge state (commit count 252 → 255, file lists corrected, sibling-dir paths fixed: artisan from `~/www/csjones.co/fynla-app/`, build to `~/www/csjones.co/public_html/fynla/public/build/`).
- [x] **Phase 1 build** — `./deploy/csjones-fynla/build.sh` produced `public/build/` with 335 assets, 8.3M, manifest scope `/fynla/build/`. Both feature-branch (`SaveTaxCampaignPage`, `TaxStrategyDashboard`) and dev-merged (`newsService`) artefacts present.
- [x] **Phase 2a** — `mv public/build → public/build.old` on server (preserve-old-chunks; 2293 stale chunks retained for in-flight sessions).
- [x] **Phase 2b** — rsynced new `public/build/` (335 → 2551 after merge of cached chunks, 70M total).
- [x] **Phase 2c** — rsynced 171 source files (133 app/, 4 config/, 1 route, 31 db/, composer.json/lock).
- [x] **Phase 3** — deleted `AgentInternalController.php`, `AgentTokenAuth.php`, `SystemPromptBuilder.php` (CRITICAL — autoloader collision avoided), `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`.
- [x] **Phase 4** — `php artisan down` → `composer install --no-dev --optimize-autoloader` (installed `symfony/yaml v7.4.8`) → `migrate --force` (22 migrations DONE in 2.5s) → `db:seed --force` clean → cache clears → `optimize` → `up`.
- [x] **`AI_AUDIT_HMAC_KEY` added to dev `.env`** via `openssl rand -base64 32`. Verified `config('app.ai_audit_hmac_key')` returns the new value distinct from `APP_KEY`.
- [x] **HTTP smoke** on `https://csjones.co/fynla` — 6/6 endpoints 200 (`/savetax`, `/news`, `/api/news`, `/api/public/tax-allowances`, `/feed/news.xml`, `/fynla` 301 redirect).
- [x] **End-to-end browser smoke** of `/savetax → register → onboarding → /tax-strategy` with fresh user `savetax-smoke-30apr@example.com` (single, £75k income, £45k pension, £25k ISA, £10k cash @ 4%).
- [x] **Defect 1 fix (`f93f605`)** — onboarding stuck in 6-message loop on `campaign_charitable_giving`. `handleCaptureCharitableGiving` returned wrong shape — missing `onboarding_capture: true` flag — so SSE pipeline never emitted `onboarding_field_captured`. User data WAS being persisted on every attempt; only state-machine advance was broken. Verified: same user clicked Continue, replied, hit "All set, SaveTax — let me show you your tax position", `onboarding_completed=true`.
- [x] **Defects 2-5 fix (`23f68ec`)** — dashboard recommended absurd things to a £75k earner (PA Taper Rescue / £162k Carry-Forward / £15,800 ISA-wrap saving / Starting Rate "fully used" £5,000 / Marriage Allowance to a single user). Six fixes to four files: (a) `estimateAnnualInterest` normalises rate when stored as percent; (b) `estimatePensionContributionThisYear` falls back to `(employee% + employer%) × annual_salary` when `monthly_contribution_amount` is NULL; (c) `buildUserAllowanceGrid` filters Starting Rate when tapered to zero AND Marriage Allowance for non-partnered users; (d) `PensionAACarryForwardStrategy` capped by HMRC tax-relief rule + liquid-wealth gate (£10k minimum); (e) `IsaTopUpStrategy` uses centralised `estimateAnnualInterest`; (f) `JointSavingsStrategy` + `AssetShiftingBundleStrategy` inline-normalise `Collection::avg('interest_rate')`. Live-verified: hero **£90,650 → £3,075**, 5 recs → 3, 8 allowances → 6 (eligibility-gated), pension AA `£60k headroom → £52,500 headroom`, savings `£500 fully used → £100 headroom / £400 used`.
- [x] **Codebase metrics in CLAUDE.md updated** — Vue 713 → 718 / Controllers 103 → 108 / Models 107 → 109 (post-merge with PR-238 additions).

### NOT Done — Outstanding for next session

#### Post-deploy follow-ups (low urgency)

- [ ] **Cleanup `public/build.old/` on csjones.co** — currently retained for ~24h rollback window. Safe to delete after 1 May evening (24h after deploy) once dev is unbroken. SSH: `cd ~/www/csjones.co/fynla-app && rm -rf public/build.old`.
- [ ] **Tail `storage/logs/laravel.log`** for any post-deploy errors over the next 12-24h.

#### Real defects found during smoke that need follow-up — bugs in OTHER strategies

These five fixes addressed the symptoms CSJ called out, but the **underlying data-convention drift** is wider:

- [ ] **`interest_rate` column convention** — factory writes decimals (0.04), seeders + onboarding write percents (4.0). Patched in tax-strategy code with inline normalisation, but the deeper fix is to pick ONE convention and migrate the data + factory + seeders + UI render code. Affected files outside the tax-strategy fix: `app/Services/Savings/SavingsActionDefinitionService.php` (lines 1194/1197/1278/1281/1365/1369/1438 — multiplies `× 100` to display, expects decimal — would render `19.9` as `1990%`), `app/Models/SavingsAccount.php` (cast is `decimal:4`), `database/factories/SavingsAccountFactory.php` (`fake()->randomFloat(4, 0.01, 0.05)`), `database/seeders/ChrisUserSeeder.php` (writes `4.5`, `4.1`, `19.9`). Pick one. Probably decimal — and migrate the existing rows + onboarding tool to write decimals.
- [ ] **Pension % → £ contribution conversion** — onboarding captures `employee_contribution_percent` and `employer_contribution_percent` but never derives a `monthly_contribution_amount`. Other parts of the app (retirement projections, action definitions) may still read `monthly_contribution_amount` directly and silently treat as zero. Audit usages of `monthly_contribution_amount` for the same gap.
- [ ] **PSA fully-used logic** — `min($personalSavingsAllowanceAmount, $estimatedAnnualInterest)` is correct but presented as "Fully used" when interest >= PSA. The user's £400 < £500 PSA case showed correctly post-fix, but the framing for the equal/over case may still confuse. Review the wording.
- [ ] **Sanity-check OTHER strategies** for similar implausible recommendations on persona-realistic data. The fixes covered IsaTopUp / JointSavings / AssetShifting / Carry-Forward; **not yet sanity-checked**: `LifecycleStrategy` (Junior ISA, Junior Pension, Lifetime ISA), `GiftAidHigherRateReliefStrategy`, `BedAndIsaStrategy`, `DividendAllowanceHarvestStrategy`, `SalarySacrificeNiStrategy`, `NonEarnerSpousePensionStrategy`, `CrossSpouseBundleStrategy`, `TaperedAnnualAllowanceStrategy`. Walk through each with the £75k john persona + a high-earner persona + a dual-earner couple persona and verify the £ amounts pass the smell test before claiming the dashboard is correct for arbitrary users.
- [ ] **Liquid wealth threshold for carry-forward** — currently hardcoded `MIN_LIQUID_WEALTH_TO_RECOMMEND = 10000.0`. Heuristic, may need tuning with real persona evidence.

#### Tech-debt items carried forward

- [ ] **W-1 (carried from session 123)** — Orphaned slider backend pipework. `StrategySliderPanel.vue` deleted in session 123 but `taxStrategy/recalculate` Vuex action, `TaxStrategyController::calculate`, `TaxStrategyCalculateRequest`, `TaxStrategyOverridesDTO`, `TaxStrategyService::recalculate`, `POST /api/tax-strategy/calculate` route, plus 9 Pest cases all unreachable from UI. CSJ was emphatic about removing sliders — option (b) rip-out is likely correct. Confirm before touching.
- [ ] **S-3 (carried from sessions 118-120)** — Hardcoded Junior Pension £2,880 / £720 in `LifecycleStrategy::generate`. Comment cites HMRC source; exposing via `TaxConfigService` would let CSJ tweak figures without a code change.
- [ ] **W-1 (carried from session 122 — Rule #14)** — `StaticFynChat.vue` lines 26, 30, 34 (3 checkmark SVGs in welcome list) and 101–103 (paper-plane SVG on Send button) violate Rule #14's ban on icons inside the Fyn chat window. Replace with text bullets / "Send" label when cleanup is in scope.
- [ ] **W-2 (carried from session 122)** — `SaveTaxCampaignPage.vue` lines 102-106 — bottom CTA renders green (`bg-spring-500`) while the other 6 CTAs render raspberry. Now that all 7 share the same label/destination, the colour split is visually inconsistent.
- [ ] **Rate-normalisation duplication (new — low)** — The percent/decimal normalisation logic (`if ($r > 1) $r /= 100`) is now in 3 places (`TaxStrategyMath::estimateAnnualInterest`, `JointSavingsStrategy::generate`, `AssetShiftingBundleStrategy::generate`). Extract to `TaxStrategyMath::normaliseSavingsRate(SavingsAccount): float` once data convention is settled.

### Context for Next Session

- **Dev is live and functional** at `https://csjones.co/fynla`. Smoke verified end-to-end (register → onboarding → /tax-strategy with sane numbers).
- **Top priority next session: sanity-check the remaining strategies** (the un-tested 8 listed above) against persona-realistic data BEFORE the dev → main release PR. Don't repeat the session 124 mistake of declaring smoke green based on HTTP 200 + payload shape alone.
- **Production deploy is GATED** on (a) the strategy sanity sweep, (b) CSJ explicit approval. Per `feedback_main_via_dev_only.md` — nothing reaches main without dev verification first.
- **Branch state**: `feature/fyn-persona-split` @ `23f68ec`, 257 ahead of `origin/dev`, 0 behind. Working tree clean. All today's commits on remote.

### Deploy Status

| Environment | Branch | Status |
|---|---|---|
| Production (`fynla.org`) | `main` | NOT touched — production deploy is GATED on strategy sanity sweep + CSJ approval |
| Dev / staging (`csjones.co/fynla`) | `feature/fyn-persona-split` @ `23f68ec` | **LIVE** — full deploy + 4 in-flight fixes shipped this session |
| Feature branch | `feature/fyn-persona-split` @ `23f68ec` | Pushed, ready for further iteration |

### Memory laws relevant to next session

- `feedback_loop_until_correct.md` — Don't stop until GREEN per the plan. Apologies aren't fixes.
- `critical_browser_testing_law.md` — "Browser tested" = clicked / filled / submitted in Playwright. Verify the £ amounts on the rendered output, not just status codes.
- `feedback_never_minimize_bugs.md` — Don't downplay visible bugs as "minor". A £15,800 saving claim on a wrap that saves £0 is BROKEN.
- `feedback_main_via_dev_only.md` — Production deploy is gated.
- `feedback_dev_server_is_separate.md` — confirm the dev server's branch state before assuming what's deployed.

---

## Codebase metrics (post-session 124, post-merge)

- Vue Components: **718** (was 713 pre-merge — +5 from PR-238 news/RSS components)
- PHP Services: 292
- Controllers: **108** (was 103 — +5 from PR-238 controllers)
- Models: **109** (was 107 — +2 from `News\NewsSubscriber`, `News\NewsArticle`)
- Vuex Stores: 34
- Agents: 9
- Migrations: **206** (was 204 — +2 from `news_articles`, `news_subscribers`)
- Factories: **68** (was 66)
- Service dirs: 37
- API Services: **49** (was 47)
