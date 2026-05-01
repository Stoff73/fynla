# CSJTODO — Fynla

*Last updated: 1 May 2026 (post-midnight, session 124 wrap) — dev deploy of `feature/fyn-persona-split` complete + 4 critical fixes shipped during smoke.*
*Previous session: 123 (30 April 2026 evening — `/tax-strategy` redesign + fynPersona deploy guide).*

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
