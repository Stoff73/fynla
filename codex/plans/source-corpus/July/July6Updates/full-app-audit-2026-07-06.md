# Full-App Audit & E2E — 2026-07-06

> **UPDATE — fixes applied.** Branch `audit-fixes-jul6` (off `origin/dev` `9c9e7d2`), commit `69afd7b`, 79 files. Resolves findings #1–#8, #11(reverted—see below), #12, #13, #14–#19, and the duplicate/dead-code cleanup. Full suite **5,550 passed / 30 skipped** (was 5,506; +44 new tests), the single failing test is a pre-existing load-sensitive perf benchmark that passes uncontended. Pint clean. **Not yet deployed** — the dashboard fix (#1/#2) is unit-proven but not yet live-verified on csjones (needs a build+deploy, CSJ's call). **Deferred deliberately:** #11 Estate decimal casts (reverted — breaks strict-typed float consumers across estate/IHT; needs a dedicated consumer-wide migration), #5 life-event allocation tab (no backend routes — implement-or-remove decision), #9/#10 Fyn/onboarding (design decisions), CurrencyDisplayService dead-code. Detail per finding is inline below.

---


**Scope:** whole app (web SPA + `/m` mobile-web) — code review, syntax/API integrity, security, tax compliance, tech-debt, and a full end-to-end journey.
**Target:** dev `9c9e7d2` (= what csjones runs; release window #581–#612). Prod untouched.
**Method:** isolated audit worktree `~/Desktop/fynla-audit` for static/agent analysis + full Pest run; live Playwright E2E on `https://csjones.co/fynla` (web) and `/m` (mobile) against a fresh registered user (`e2e.fullaudit.jul6@example.com`, id 189).
**Verdict:** app is broadly healthy — suite green, tax engine correct, no exploitable IDOR/SQLi/token bugs, Fyn read+write journeys work. **Two confirmed user-facing dashboard bugs, one tax-calculation correctness bug, and one PII-exposure issue are the priorities.**

---

## Headline

| Severity | Count |
|----------|-------|
| Critical / High | 8 |
| Medium | 8 |
| Low / cleanup | 12 |
| Verified clean (areas) | large |

> **Important nuance on tax:** the tax *config* (rates, allowances, bands in `TaxConfigService`/`TaxDefaults`/`taxConfig.js`) is fully 2026/27-correct. But the tax *calculation logic* has several HMRC-rule bugs — see the "Tax calculation logic" cluster below (findings #3, #3b, #14–#19). This is the most material area after the dashboard cards.

**Pest:** 5,506 pass / 30 skip — **GREEN**. (A first run showed 6 "failures"; all were `Vite manifest not found` because the audit worktree had no `public/build/` — copying the built manifest in made all 6 pass. Not code regressions.)

**E2E happy path works end-to-end:** register → MFA → 9-step onboarding (every module, joint property with correct 50/50 equity maths) → dashboard (net worth correct) → module pages → Fyn advice (grounded in real data) → Fyn inline-capture write (DB-verified, correctly non-joint ISA) → tax strategy (personalised) → estate freemium teaser (graceful gate).

---

## Critical / High

### 1. Dashboard "Protection" card shows £0 for EVERY user with cover — CONFIRMED LIVE (web + /m)
`app/Services/Dashboard/MobileDashboardAggregator.php:170` reads `$coverage['total_life_cover']` — a key that `CoverageGapAnalyzer::calculateTotalCoverage()` **never produces** (it emits `life_coverage` / `total_coverage`). That key is read but never written anywhere in `app/`, so the protection card is £0 for **every** user with cover, regardless of tier or cache. The `/protection` page shows the correct £250,000 because its Vuex getter sums raw `sum_assured`, bypassing this key.
- **Repro:** test user has a £250k L&G life policy; `/protection` page + Fyn both report it; dashboard + `/m` both show `total_coverage: 0` (`policy_count: 1`).
- **Fix:** change the key at `MobileDashboardAggregator.php:170`.

### 2. Dashboard "Retirement" card shows £0 / 0% of target — CONFIRMED LIVE (web + /m)
The card (`GamifiedDashboard.vue:332`) binds `retValue = ret.income_gap = max(0, target_retirement_income − projected)`. `target_retirement_income` is a warning-level readiness field (`RetirementDataReadinessService.php:210-226`, form-linked to `/retirement/settings`) that isn't captured at onboarding → 0 → gap collapses to 0 → "£0 / 0% of target / Plan your retirement". The aggregator (`extractRetirementSummary`, `:232-239`) never surfaces the pension pot to the card at all (though net worth correctly includes the £45k pension, and `/net-worth/retirement` shows the £45k pot and a £313,850 projection).
- **Fix:** surface a pot value in `extractRetirementSummary` and bind the headline to it (`GamifiedDashboard.vue:332,345`), or capture the retirement-income target at onboarding.
- **Also harden (runner-up, latent second-order bug):** `MobileDashboardAggregator` extracts (`:139-175`, `:220-240`) treat a `can_proceed===false` / null-coverage agent envelope as `active` with 0 rather than `not_configured`, because they only guard on `success===false`. Not the cause of #1/#2, but worth fixing in the same pass.

### 3. MPAA is never applied — tax under-warning (correctness)
`app/Services/Retirement/AnnualAllowanceChecker.php` — `checkMPAA()` (line ~265) has **zero callers** anywhere in `app/`. `checkAnnualAllowance()` ignores `has_flexibly_accessed` entirely: a user who has flexibly accessed a DC pension is still assessed against the full £60,000 allowance (shown `remaining_allowance` up to £60k, `has_excess=false`) when HMRC's Money Purchase Annual Allowance caps their DC input at £10,000. The field is captured, stored on `DCPension`, exposed in resources — then discarded at calculation. **Users are told they have headroom they legally don't.** Compounded by `PensionAACarryForwardStrategy` (`:49-130`), which never consults `hasFlexiblyAccessedDcPension()` and so can recommend a carry-forward top-up that would trigger an annual-allowance charge (FA 2004 s227ZA prohibits carry-forward against the MPAA).

### 3b. Employee pension contributions deducted TWICE in threshold-income (tax, CRITICAL)
`app/Services/Tax/IncomeDefinitionsService.php:27-47` — employee pension contributions are subtracted at line 30 (`netIncome = totalIncome − employee − giftAidGross`) **and again** at line 37 (`thresholdIncome = adjustedNetIncome − employee`). Gift Aid gross and Blind Person's Allowance are also deducted into threshold/adjusted income, though neither belongs there (FA 2004 s228ZA). Net effect: threshold income understated by `employee+giftAid+BPA`, adjusted income by `2×employee+giftAid+BPA` → **the Annual Allowance taper is under-applied for £200k+ earners, overstating their pension allowance** (feeds `AnnualAllowanceChecker`). Fix: deduct employee once (`thresholdIncome = totalIncome − employee`; `adjustedIncome = totalIncome + employer`); remove Gift Aid + BPA from the threshold/adjusted chain.

### 4. Joint-owner PII over-exposure (security, HIGH)
`PropertyResource`, `InvestmentAccountResource`, `BusinessInterestResource`, `ChattelResource` nest the **full** `UserResource` for `user`/`joint_owner`, and their controllers eager-load the full relation. Viewing a co-owned asset returns the counterparty's **entire financial profile** — email, DOB, every income stream, every expenditure category, internal flags — not just the name/share the joint pattern intends. Not gated on `hasAcceptedSpousePermission()`. Three more resources (Goal, Mortgage, GoalContribution) have the same nesting dormant (controllers don't yet eager-load the full relation).
- **Fix:** replace the nested full `UserResource` with a minimal `{id, first_name, surname}` shape (as `SavingsController` already does), or column-limit the eager load.

### 5. Live endpoint drift — "Tax Optimised Allocation" tab 404s
`resources/js/services/goalsService.js:241-260` calls three life-event allocation endpoints that don't exist in `routes/api.php`/`api_v1.php` (`GET/PUT/POST /api/life-events/{id}/allocations…`). Wired end-to-end into a mounted component: goals store actions → `LifeEventAllocationTab.vue` (shown when the 'allocation' tab is active). **Opening the "Tax Optimised Allocation" tab on any life event 404s.**
- **Fix:** implement the backend routes or remove the tab + store actions + service methods (check the Goals/Life-Events spec first).

### 6. IHT 10-year periodic charge implemented 3× with diverging results (correctness)
`app/Services/Trust/IHTPeriodicChargeCalculator.php` (full method), `app/Services/Estate/TrustService.php`, and `app/Services/Estate/PersonalizedTrustStrategyService.php:779-786` (flat approximation) each compute the relevant-property-trust 10-year periodic charge and **produce different numbers**. Consolidate on the full calculator.

---

## Medium

### 7. PreviewWriteInterceptor missing login-MFA routes — silent login dead-end (VERIFIED)
`app/Http/Middleware/PreviewWriteInterceptor.php` `EXCLUDED_ROUTES` excludes `api/auth/login` and the **password-reset** MFA routes (`password-reset/verify-mfa`, `password-reset/mfa-recovery`) but NOT the **login-flow** MFA continuation `api/auth/mfa/verify` / `api/auth/mfa/recovery` (`routes/api.php:146-147`). A preview user with a stale preview Bearer token in the same browser who logs into a real MFA-enabled account: `login()` succeeds (excluded) but the follow-up `/mfa/verify` POST is intercepted and faked (`{success:true, preview_mode:true}`) — the real login silently never completes. This is the exact failure the login exclusion was added to prevent (Rule 7).
- **Fix:** add `api/auth/mfa/verify` and `api/auth/mfa/recovery` to `EXCLUDED_ROUTES`.

### 8. Advisor activity mass-assignment
`AdvisorController::updateActivity` (`:171-180`) passes raw `$request->all()` into `ClientActivityService::update()` → `$activity->update($data)` with no validation. `ClientActivity` `$fillable` includes `advisor_id`, `client_id`, `advisor_client_id` — an authenticated advisor can PUT those in the body and re-point their own activity row at another advisor or an unassigned client. The `where('advisor_id', current)` guard only scopes which row loads, not what the payload mutates. The sibling `storeActivity()` correctly uses `StoreClientActivityRequest`.
- **Fix:** add `UpdateClientActivityRequest`, pass `$request->validated()`, exclude the linkage keys.

### 9. Web-vs-/m Fyn state divergence — same user, different Fyn (needs CSJ judgment)
For the same user (`onboarding_completed=false`, `onboarding_fyn_step=path_choice`): **web** "Chat with Fyn" answered a free-text advice question and performed an inline-capture write; **/m** Fyn showed onboarding-style journey/focus bubbles and rejected free text ("Please pick one of the options above"). The canonical contract states dispatch is server-side and **surface-agnostic** — the same user should get the same Fyn state on both surfaces. This looks like the web chat entry starting an advice conversation while /m resumes/starts the onboarding conversation. **Flagging for CSJ:** is this an intended entry-point difference, or a contract violation?

### 10. 9-step onboarding wizard doesn't flip `onboarding_completed` (needs CSJ judgment)
After completing the entire classic 9-step form wizard and landing on the dashboard, the user remained `onboarding_completed=false`, `onboarding_fyn_step=path_choice`. The dashboard still shows onboarding prompts ("How would you describe your investment knowledge?"). This may be by design (the form wizard and the Fyn bubble-onboarding are parallel paths), but it means a user who fully completes the form wizard is still treated as mid-onboarding — and (per #9) drives the Fyn divergence.

### 11. Estate money columns cast to `float` (precision drift in IHT)
`app/Models/Estate/Asset.php:35` (`current_value`), `Liability.php:39-41` (`current_balance`, `monthly_payment`), `Gift.php:31` (`gift_value`) cast `decimal(15,2)` DB columns to `float`, reintroducing binary-float drift into IHT/estate/gift-taper calculations. Contradicts the codebase-wide `decimal:2` convention (303 uses).
- **Fix:** cast these as `decimal:2` (rates as `decimal:4`).

### 12. PII in logs (no secrets/tokens)
Plaintext emails at `info`/`warning`: `LoginLockoutService.php:108-113`, `FamilyMembersController.php:122-127` (two emails, debug trace), `SpouseLinkingService.php:87-91`, `ReferralService.php:71-82` (non-consenting third party). Wholesale payload/response-body dumps: `WebhookController.php:201` (full Revolut payload), `RevolutSubscriptionService.php` (`:51,138,236,345,478` response bodies), `AIExtractionService.php:751-754` (up to 500 chars of document-derived financial content), `AssumptionsService.php:159-163,217-221` (financial values). No passwords/tokens/MFA secrets are logged. `AuthController` correctly uses `maskEmail()` — these sites are inconsistent with that pattern.

### 13. Stale frontend tax values (2026/27)
- **Stale:** Trusts dividend rate `8.75%` should be `10.75%` (`TrustsDashboard.vue:91,264`, `TrustDetailView.vue:293,341`); BADR `14%`/`10%` should be `18%` (`PrivateInvestmentFields.vue:412`, `PrivateInvestmentDetail.vue:217,275`, `BusinessInterestDetailInline.vue:271`); CGT shares `10-20%` should be `18%/24%` (`WrapperOptimizer.vue:88`, `TaxFees.vue:106`, `RebalancingCalculator.vue:96-97`).
- **Computation hardcodes:** `TaxEfficiencyPanel.vue:156` computes CGT at `0.20`; `RebalancingCalculator.vue` bound to `0.10`/`0.20`; LISA `4000` arithmetic in `ISAAllowanceTracker.vue` + `Dashboard.vue:834,858`.
- **Note:** the backend engine and `constants/taxConfig.js` ARE fully 2026/27-correct; these are display/computation strings in Vue that don't source from the API. `constants/lifeStageConfig.js` is the single biggest concentration of hardcoded display figures (interpolates `TAX_YEAR` label but hardcodes the numbers). Public marketing/education pages (`views/Public/**`) also hardcode heavily but are out of app scope.

---

## Tax calculation logic (HMRC-rule correctness) — from the full tax sweep

The config is right; these are logic/rule bugs in the calculation code. All grep-verified by the tax agent (code-read, not each live-reproduced). Several are genuinely material for a financial-planning app.

- **#14 RNRB granted without the direct-descendant condition** — `IHTCalculationService.php:1182-1205`. RNRB is given whenever a main residence exists; the "closely inherited by children/grandchildren" test (IHTA 1984 s8E) lives only in the message string, never in code. A childless homeowner is shown £175k/£350k RNRB they cannot get. Data exists (`FamilyMember.relationship`) but is unused.
- **#15 RNRB not capped at the residence value** — `IHTCalculationService.php:1198-1230`. RNRB should be the lower of the max and the net home value passing to descendants (s8E(2)). A couple with a £200k home still gets £350k RNRB → **IHT understated by up to £60k** (£150k × 40%).
- **#16 Charitable legacy never deducted from the taxable estate** — `IHTCalculationService.php:157-164, 1264-1316`. The 36% reduced rate is applied, but the charity gift itself isn't exempted (IHTA 1984 s23), so tax = `(estate − allowances) × 36%` instead of `(estate − allowances − charityGift) × 36%` → **IHT overstated by 36% of the donation**.
- **#17 Gift Aid doesn't extend the tax bands** — `UKTaxCalculator.php:634-673`. Gift Aid only reduces adjusted net income for the PA taper; the basic/higher-rate limits are never extended by the gross donation (ITA 2007 s414 — that *is* the higher-rate relief). Net-income figures are overstated for higher/additional-rate donors.
- **#18 Additional-rate boundary miscomputed as £137,710** — `RetirementStrategyService.php:1231` uses `personalAllowance + bands[1]['max']` (12,570 + 125,140) instead of the absolute £125,140. The exact "audit finding #5" pattern already fixed in `UKTaxCalculator`/`PropertyTaxService`/`TaxBandTracker` — this is the last remaining site. Incomes £125,140–£137,710 get a 40% marginal rate instead of 45%, understating pension-relief recommendations there.
- **#19 Carry-forward ignores prior-year taper** — `AnnualAllowanceChecker.php:182-238` (+ `PensionAACarryForwardStrategy:77-80`). Each prior year's unused allowance is credited against the full £60k even for a year the user had a tapered AA (down to £10k) → overstates carry-forward by up to £50k/year for exactly the tapered population the feature targets (PTM055200).
- **Backend stale dividend rates (not fallbacks):** `TaxDragCalculator.php:177-181` hardcodes `0.0875/0.3375/0.3935` (stale 2025/26) in a `match` and never reads the config it already injected → GIA dividend drag understated 2pp. (Distinct from the frontend #13 stale values.)
- **Rule-2 hardcodes in live calc:** `SaveTaxEstimateService.php:358` (`$basicLimit = 37700.0`), `SpouseOptimisationService.php:456-457` (`3600`/`2880` — `TaxDefaults` constants exist for exactly this). Values correct today, contract violated.
- **Lower-severity (edge cases / stale inline fallbacks):** dividend-allowance band-space allocation (`UKTaxCalculator:779-805`, ≤£125); Marriage-Allowance + spouse-PA double-count in the funnel (`SaveTaxEstimateService:131-166`, ~£252); ~20 services use inline `?? 0.0875`-style stale fallbacks instead of `?? TaxDefaults::X`; NMW-floor check missing in `SalarySacrificeNiStrategy`; 14-year-rule blanket NRB reduction (`IHTCalculationService:1534-1575`, conservative/labelled "Direction B"). See the tax agent's full JSON for the complete list.

---

## Low / cleanup

14. **Duplicate code:** annuity formula byte-identical in `LifeCoverCalculator` + `LifePolicyStrategyService`; `calculateTotalIncome` reimplemented 3× (`HouseholdPlanningService`, `LifeStageService`, `PrerequisiteGateService`) vs the existing `ResolvesIncome` trait.
15. **Dead code:** 3 unreferenced Retirement views (`Projections.vue`, `Recommendations.vue`, `WhatIfScenarios.vue`); `/m` `ModuleDetail.vue` (superseded scaffold, reads fields the aggregator never emits); `estateService` `analyzeEstate/recommendations/scenarios` (removed endpoints); `CurrencyDisplayService` (only unreferenced service of 420).
16. **Test coverage gap:** `RetirementIncomeService` (2,161 lines, core decumulation/income math) has no dedicated test; `PensionPortfolioAnalyzer` untested. Material money calculations.
17. **Cross-surface cache coherence (Rule 19):** Investment/Property/Mortgage/Chattel/BusinessInterest controllers invalidate the desktop cache but not the mobile aggregator cache — a mobile dashboard can lag a desktop write by up to the aggregator TTL.
18. **CSS/acronyms:** `GamificationCelebration.vue:80-94` has 8 hardcoded hex in `<style>` (Rule 11); `MPAA` on `PensionAnnualAllowancePage.vue`; `DC/DB` in `Version.vue` changelog (Rule 9).
19. **Freemium console noise:** the dashboard fires `/api/estate/trusts` + `/api/estate/calculate-iht` for free-tier users → 403 console errors (the estate *page* gates gracefully to a teaser; only the dashboard calls are noisy).
20. **Benign:** `OnboardingWizard` throws a non-blocking `Cannot read properties of undefined (reading 'scrollTo')` on some step transitions.
21. **Defense-in-depth (latent, not exploitable today):** `User` model uses a `$guarded` blocklist that omits `role_id`/`is_advisor`/`tier`/`mfa_secret`/etc. — combined with the `saving()` hook that syncs `is_admin` from `role_id`, any future `->update($request->all())` would escalate. `WillDocumentController:74` validates then forwards `$request->all()` (sink whitelists today).
22. **Deps/format:** `symfony/http-foundation` CVE-2026-48784 (medium, UrlGenerator dot-segment); `vite` GHSA-fx2h-pf6j-xcff (moderate, dev-only Windows `fs.deny` bypass); Pint reports ~85 files with formatting drift (mostly `public/pages` marketing PHP + some `app/` services).
23. **BS-NN acceptance:** 20 of 24 `tests/Browser/scenarios/BS-*.php` docblocks reference `April/April24Updates/plan|spec` paths that don't exist on dev (sprint-branch-only) — "GREEN per the plan" can't be evaluated from dev.

---

## Verified clean (healthy)

- **Pest:** 5,506 pass / 30 skip. `php -l` clean across app/database/routes/config. All routes resolve to existing methods (0 dangling). No conflict markers. Blade compiles.
- **Security:** TransientToken — all 14 `currentAccessToken()` consumers guard with `instanceof PersonalAccessToken`, fail-closed (the historical six-site bug family is covered). AdviceFyn read-only — `WRITE_TOOLS` covers every write-capable tool in the live corpus (diffed corpus `.xai.md` vs `WRITE_TOOLS`, zero escape); two-layer enforcement (catalogue-strip + dispatch GroundGate). No exploitable IDOR (every `find($id)` user-scoped or admin-gated). No SQLi. Revolut webhook HMAC-verified. SSE endpoints ownership-scoped; forensic AI columns not returned to clients. Named rate-limiters key per-path+IP (MFA self-throttle fixed). Eval routes double-gated.
- **Tax engine CONFIG (values):** `TaxConfigService` + `TaxDefaults` + `constants/taxConfig.js` fully 2026/27-correct — dividend +2pp (10.75%/35.75%), BADR 18%, IHT NRB/RNRB amounts + £2m taper + 36% charity switch, HICBC (£60k, 1%/£200), AA taper thresholds (£200k/£260k, £10k floor), PCLS capped at LSA £268,275 (no LTA remnants), ISA single-£20k allowance sharing (no joint ISA), savings ordering per ITA 2007 s12, gifting 7-year taper schedule, FSCS £120k. **The values are right — but the calculation *logic* that consumes them has the HMRC-rule bugs listed in the "Tax calculation logic" cluster (#3, #3b, #14–#19).**
- **Vue:** zero banned colour tokens (amber/orange/primary/secondary/gray) across ~680 files; no local `formatCurrency`; no `v-if`+`v-for`; no missing `:key`; no raw scores in UI (Rule 12 label-pattern followed); routed views correctly layout-wrapped; form modals emit `save`. `v-html` — all 28 bindings safe (AI content escaped + DOMPurify; will/LPA sanitised; Insights DOMPurify allow-list).
- **Models/DB:** no `sole` enum drift; `tenants_in_common` correctly property/mortgage-only; mass-assignment complete (all 106 models declare `$fillable`/`$guarded`); joint_owner indexes comprehensive; money stored `decimal` at DB. (Estate float *casts* are #11.)
- **Services:** `declare(strict_types=1)` in 100% of files; no service imports a controller; no `request()`/Auth in calculation code; no empty catches on money paths.
- **/m:** canonical Fyn contract upheld (no client-side persona split); campaign re-entry has full /m parity; every mobile API path resolves; gamification layer intact (approved). (The Fyn *state* divergence #9 is a dispatch/entry-point question, not a client-side split.)

---

## What was NOT individually re-tested (honesty)

- Web module CRUD beyond onboarding: net-worth drill-downs, Goals page CRUD, What-If scenarios, Holistic Plan, Plans module, Risk Profile questionnaire (data-entry for these modules WAS exercised via onboarding; their dedicated pages weren't each clicked through).
- Campaign funnels (`/savetax`, `/pensioncheck`) anonymously — they redirect authenticated users to the dashboard (correct); they were E2E-verified in prior sessions and #612.
- Payments/Revolut sandbox flow; admin panel; advisor portal — not in this pass.
- Findings #3, #6, #11, #13 are code-read (agent) conclusions, not each reproduced live; #1, #2, #4-scope, #5, #7 are verified against code/DB/live.

---

## Suggested priority order

1. **#1 Protection card £0** — one-line key fix (`MobileDashboardAggregator.php:170`), affects every user, both surfaces.
2. **Tax calculation-logic cluster (#3, #3b, #14–#19)** — correctness bugs that mis-state IHT and pension allowances. Highest-impact: #3b (AA taper double-deduction), #16 (charity legacy not deducted), #15/#14 (RNRB over-granting), #3 (MPAA), #17 (Gift Aid band extension). Each is a small, well-located fix.
3. **#4 Joint-owner PII leak** — security, one shared fix across 4 resources.
4. **#2 Retirement card £0** — needs a pot field + binding decision (+ extract hardening).
5. **#5 life-event allocation 404** — implement-or-remove decision.
6. **#7 PreviewWriteInterceptor MFA routes**, **#8 advisor mass-assignment**, **#11 estate float casts** — small, safe fixes.
7. **#9/#10 Fyn / onboarding-completed** — CSJ design decisions first.
8. **#13 stale frontend tax display values** — batch fix, sourced from API where possible.

*The two full agent JSON payloads (tax: ~30 findings incl. the complete lower-severity list; security: TransientToken/AdviceFyn/rate-limit clean confirmations) landed after the first draft and are folded in above. The tax sweep's own coverage note flags that an exhaustive per-component frontend read was not completed — the backend calc findings are the verified, material ones.*

*Audit target: dev `9c9e7d2`. Worktree `~/Desktop/fynla-audit` (build manifest copied in for the Pest re-run). Test user `e2e.fullaudit.jul6@example.com` (id 189) left on csjones.*
