# CSJTODO — Fynla

*Last updated: 2026-09-28: four releases live (#949 action cards + lanes, #952 spouse earnings cap + carry forward gate, #955 help page, #958 how-to help links + Estate cards). Next: Fyn "Ask Fyn about this" narration.*

## The board position

Computed from the board files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 338 |
| **resolved** | **332** — W-0541–W-0550 closed 2026-09-10 (W-0548 already resolved, W-0549 folded) |
| **outstanding** | **6** — all `deferred-ios` |
| critical | **0** |
| high | **0** |
| medium / low in scope | **0** |

Every non-iOS item is closed. The rule is unchanged — **a citation is not a verification**
— and **verify the instrument before trusting the measurement**: CSJ's 2026-09-10 order to
re-check the nine items before fixing found one already resolved and one overstated.

## Capture forms in the Fyn chat (CSJ, 2026-09-15/16/17) — done, on production

- [x] Every capture step is a structured form on web and `/m`, on all three entry points. Save Tax: property, ISA, bank and savings, investment, pension, date of birth, both spouse variants, expenditure. Journey: personal details, spouse details (keeping its skip link), dependants with its own loop, work and income, protection cover, and the "Anything else" focuses opening on the same forms. Pension check: the same walk plus a personal-pension-only form for the not-employed, and spouses now use the Save Tax spouse forms. Multi-record steps ask "another?"; at the plan cap the question becomes the limit statement with one Continue bubble. Released to main `94ad79c33` (PRs #886–#895) and verified live on fynla.org by a fresh Save Tax registration. Recipe: handover 2026-09-16 session 2, "How to add a capture form".
- [x] Expenditure (CSJ 2026-09-16): one box for everyone; the web page's five category groups on Premium, asking "whole household or just you?" first when a spouse is on file.
- [x] Spouse holding transfer (CSJ 2026-09-16): when the spouse accepts the link, the facts given during onboarding are copied onto their account once — date of birth, employment status, income, savings, a Stocks and Shares ISA with its provider, investments, and the pension pot with its contribution. Migration `2026_09_16_220000` ran on production.
- [x] The unlinked-spouse action (CSJ 2026-09-16) and the `/m` all-actions list (CSJ 2026-09-17). Released to main `07f772d8b` and verified live on fynla.org.
- [ ] Adjacent, CSJ to decide (seen again 2026-09-19 on both servers): `/savings` verify page lists accounts only in preview mode (real users see the Open Banking promo); on Free two ISAs use up the investment cap (a General Investment Account is then refused); native (no forms header) still gets the typed questions; after a partial refusal the saved kind stays editable; a form at a non-form state returns a friendly message not the spec's 422; the web SPA hung after opening Chat with Fyn in a phone-width desktop window (csjones, 2026-09-16 session 1). New today: a retired user is still asked "when would you like to retire"; Fyn says "I've saved your State Pension" when the user answered that they do not know it.

## Application mapping programme (CSJ, 2026-09-14) — in progress

Evidence-only maps in `docs/app-map/` via the `app-map` skill; index at `docs/app-map/INDEX.md`;
bugs raised (never fixed inside a run) in `September/September14Updates/mappingBugs2026-09-14.md`.

- [x] Overview baseline, 17 emails and push, 01 auth and registration, 02a onboarding (Fyn flow,
      legacy wizard, journeys, life stage), 02b Save Tax and Pension Check campaigns (funnels,
      hand-off, both walks, re-entry, Tax Strategy page; driven live on web and `/m`).
- [ ] **BLOCKED ON CSJ — mapping-bug decisions** (register at the top of the bugs file). Live
      user-facing first: MB-23 (paused user's next message gets no reply), MB-44 (Save Tax has no
      way back after "No thanks"), MB-37 (web savings verify page shows no accounts — which page),
      MB-45 (Tax Strategy: which list is canonical), MB-25 (paused walk can never resume), MB-38
      (Pension Check hardcoded bands, no cross-check), MB-54 (Save Tax drops the pension pot),
      MB-53 (re-entry re-asks pensions), MB-49 (natural correction produced no write). Dead code
      and copies: MB-01 + MB-09 + MB-29/30/31, MB-39, MB-41, MB-42, MB-43, MB-08, MB-10, MB-11,
      MB-06. Data and copy: MB-32, MB-34, MB-40, MB-14/15, MB-21.
- [ ] **NEXT — merge today's eleven PRs to dev in order, rebasing each onto dev after the one
      before (every branch edits the bugs register on adjacent lines; keep both sides), csjones gate
      per the admin-merge memory (four PRs change `resources/mobile/`: #831, #834, #835, #836 need
      `build:mobile`), then `/release` dev → main (CSJ types it). Order: #829 → #832 (stacked on
      #829) → #830 → #831 → #834 → #835 → #836 → #837 → #838 → #839 → #833 (the `bugsFixed.md`
      ledger — after merging, flip its rows from "Fixed, PR open" to "Fixed" with SHAs and make sure
      it is on dev, main and in the local root). Handover session 4 has the detail.
- [ ] **BLOCKED ON CSJ — these 10 fixes have never landed.** Fixed 2026-09-14, PRs still open:
      MB-27+47 (#829), MB-48 (#830), MB-24 (#831), MB-56/57/58 (#832), MB-18/19/20 (#834),
      MB-50 (#835), MB-26 (#836), MB-28 (#837), MB-33 (#838), MB-35 (#839).
      They are stacked on the `docs-bugs-fixed-log` branch: 12 commits, 66 files, +1,754 lines,
      with tests. **Re-checked 2026-09-18 — tree clean, pushed, still merges into current `dev`
      with 17 files overlapping and ZERO conflict hunks.** The 10 `mb-*` local branches are its
      constituent parts; its worktree was removed (clean), the branch deliberately kept.
      Land it, rebase it, or abandon it. MB-35 (wizard copy British + civil partnership) is a live
      user-facing wording bug; MB-28 touches the web profile-review pause, which the 2026-09-18
      release rerouted for the journey path — read that before merging.
      MB-54's cause corrected (deterministic backstop after a model refusal; decision still open).
- [ ] **Remaining no-decision MBs** after the release, one per branch, test red first, live on web
      and `/m`: MB-17, MB-22, MB-16, MB-46, MB-51, MB-52, MB-55 (subject to MB-44). Unskip
      `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php` with MB-23.
- [ ] Mapping paused at section 03 (dashboard) until CSJ restarts it; index rows are ready.

## NEXT — how-to batches and the savings fallout (updated 2026-09-29)

Released 2026-09-29 and walked live on fynla.org: #966 (#960 Ask Fyn grounding + no-certainty filter, #962 iOS keyboard, #964 savings how-tos, #965) and hotfix #967 (see `handover/September/29/handover-2026-09-29-session-1.md`).

- [x] **RELEASED 2026-09-29 evening:** main `c9d789135` == dev tree; migration, ActionHowToSeeder, fyn-memory, config, both bundles; spouse repair `--force` run by CSJ (spouse 760: copied row 43 removed, £64,000 → £32,000). Walked on fynla.org, web 1440 + /m; walk account 763 purged.
- [x] **RELEASED 2026-09-30 (session 2):** main `11caa4b38` — #1009 (date of birth no longer slips; dates read the same in every time zone; /m shows the date in words; Full-Time; "reclaims all but £20"), #1010 (servers hold runtime files only), #1011 (a question at a bubble step is answered, never ticked). Walked on fynla.org web 1440 + /m (walk account 764, purged).
- [x] **Servers cleaned to runtime files only (CSJ 2026-09-30):** `deploy/server-runtime-paths.txt`; csjones is a sparse checkout (3.3 GB → 960 MB); fynla.org 690 → 633 MB, removed files moved to `~/release-backups/2026-09-30-server-cleanup/`.
- [ ] **CSJ flushing — SiteGround dynamic cache for csjones.co** (Site Tools → Speed → Caching → Flush). `/fynla/login` and `/fynla/m` serve a cached copy of another site from a ~2-minute window when my `git reset --hard` replaced csjones's subdirectory `.htaccess` (restored; memory `feedback_never_reset_hard_on_csjones`). PURGE is refused (403). Cache-busted URLs work.
- [x] **User 668's date of birth: CSJ 2026-09-30 — leave it.**
- [x] **fynla.org `~/release-backups` pruned (CSJ 2026-09-30):** 1.3 GB → 58 MB, the three 2026-09-30 folders kept.
- [x] **Spouse transfer (ruling 50): #1013, dev `f62bc3d3d`, walked on csjones web + /m; RELEASED 2026-09-30 in #1015 (main `266c35c1b`), walked on fynla.org.** Each walk form (savings, ISA, investment, pension, personal pension) opens the transferred record as an edit; fixed on the way: workplace pensions and stored GIAs opened as the wrong kind in Edit; edits dropped dividends (and did not move the dividend total) and pension drawdown / lump sum.
- [x] **A retired partner's pension arrives as pension income (#1014, RELEASED 2026-09-30 in #1015, main `266c35c1b`):** stored as what they draw on the one transferred pension; the personal pension step is no longer skipped for a partner not employed, so they confirm it; walked web + /m (Income: "Pension income £30,000").
- [x] **Marriage Allowance uses the income given when a linked spouse's records hold none (#1014):** `TaxStrategyMath::linkedSpouseWithIncome`; csjones user 435 web + /m no longer offered £252.
- [x] **A retired partner's income counted once (#1016, RELEASED 2026-09-30 in #1017, main `a58497914`):** found walking #1015 on fynla.org — a partner given £30,000 with no status who then answered "Retired" kept the estimate as pay beside the drawdown (£60,000, higher rate, NI, "pay £9,700 into your pension"). `SpouseEstimateStatusObserver` restates the estimate when status becomes retired (drawdown on the one pension) or not working (other income). Also: #1013's `WalkFormPrefill`/`RecordEditForms` read models directly and turned the pension/savings StoreBoundary tests red; now through the stores. Walked csjones + fynla.org, web + /m (Income: "Pension income £30,000", no NI); walk accounts 766-769 purged.
- [x] **Patch notes 29 + 30 September consolidated (CSJ 2026-09-30):** `September/September30Updates/patch-notes-2026-09-29-30.md` + `.pdf`, all ice-cube PRs included; the two single-day files removed. Corrected two claims: the 29 Sept evening release (#1008) was walked on local copies, not csjones; unknown partner status carries the income as an estimate of pay (644d4cd86), not nothing.
- [ ] **ice-cube issues checked 2026-09-30 (CSJ: fix 1-7 in order).** Merged to dev, walked csjones web + /m, NOT released:
  - [x] 1. #1018 interest rate of 1% or less read as 100x (`App\Support\SavingsInterestRate`; wizard + upload writers store percentages).
  - [x] 2. #1019 retention purge deletes all 38 missed user tables incl. Fyn conversations; guard test; 4 prod release-walk accounts (751-755) force-deleted.
  - [x] 3. #1020 no £0 gift / spouse ISA card; interest stacks PA -> Starting Rate -> PSA once (`stackInterest`).
  - [x] 4. #1021 partner pension card shows the relief limit (£3,600 without earnings), not £60,000; `?? 3600` fallback removed.
  - [x] 5. #1022 desktop Tax Strategy shows the rates note from one server sentence (`TaxStrategyOutputDTO::TAX_BASIS_NOTE`). Walked csjones web 1440 (note above "Allowances for 2026/27") + /m 390 (new bundle `main-B4mBouQu.js`, note served); merged dev `d3e85cf2b`.
  - [x] 6. #1023 `spouse_income` and `spouse_income_amount` share `spouseIncomeAmountKnown` (linked spouse's own income, else the figure given). Walked csjones web + /m before/after on walk accounts 442 + 444 (linked as spouses, CSJ allowed): "Unlock spouse's income info" gone from /actions on both; Tax Strategy unchanged (£4,000 -> £800 pension card). Merged dev `2725d61c8`.
  - [ ] 7. The partner questions ask a linked partner's ISAs / pension already on their own account (#978 follow-up 3; seen on the fynla.org walk).
  - Release #1018-#1023 together: app code; #1022 also needs both bundles.
- [ ] **CSJ decisions 2026-09-30, to build after 1-7:** savings-shift strategy for dual earners (yes); 40% relief below £100,000 down to £50,270 (yes); affordability check on every spouse pension top-up (yes); Marriage Allowance widened to the law's test, ITA 2007 s55C(1)(c) (yes); "Does your spouse work?" stays for a retired partner (no change). Account 645's savings row: CSJ said 4%; set to 4.0000 on fynla.org through SavingsStore (projected interest unchanged at £124).
- [ ] **Old wizard invents a mortgage** (`OnboardingService.php:598-613`: "Mortgage Provider", 3.5%, 5 years in, 20 left) for any property balance; only reachable by typing `/onboarding/full`.
- [ ] **Web setup never asks whether the partner is retired:** the inviter's "Now your spouse" form has no status field, so the link cannot split the income; #1016 restates it once the partner answers. Status is only known up front from the Save Tax funnel's 60%-band question.
- [ ] **Retirement page is wrong for someone already retired** (csjones walk 2026-09-30, Pat: born 1958, retired 2020, £200,000 pot drawing £30,000): `RetirementProjectionService::projectPensionPot` never reads `retirement_date` and clamps years to go at `max(1, …)`, so it shows "Years to go 1", "Retirement age 67", "Projected Gross Income £9,235" and a required capital, as if still saving. Needs a decumulation view for retired users; same module as the retirement how-to batch.
- [ ] **Open, not fixed:** the Apple bridge (`services/apple_store_bridge` + `.venv`) is not installed on fynla.org (`route:list` fails `invalid_configuration`); prod `vendor/` carries dev packages.
- [ ] **Retirement how-to batch (26) — NEXT**, then investment (17), estate (12). One module at a time: draft, CSJ approves, walk, release. Follow `database/seeders/data/action-how-to/protection.md` + `savings.md` headers (user's own money, never "Fynla", sourced, household branches). First check the module's cards really come from its definitions and carry `definition_key` + `figures` (protection did not — #972); add to `ActionHowToSeeder::SOURCES`.
- [ ] **Protection still has two engines:** `RecommendationEngine` still feeds `ProtectionAgent::analyze()['recommendations']` (the plan page's own recommendations section and the composed-flag-off rollback path); the cards no longer use it.
- [ ] **Two sums for "ISA allowance used this year" (Rule 20 / Rule 23, user-facing).** `ISATracker::buildOwnerStatus` (savings cards, Savings page) counts a Stocks and Shares ISA only when `investment_accounts.tax_year` is the current year; that column is stale (preview users 74/78 hold `2025/26`), so £20,000 paid in shows as £20,000 left. `TaxStrategyMath::estimateIsaSubscriptionsThisYear` counts `isa_subscription_current_year` whatever its year (wrong after 6 April). One rule, with the year kept right on each payment. How-to steps use TaxStrategyMath meanwhile. Also: `evaluateCashISARecommended` should not fire when the allowance is used.
- [ ] **Savings market rates fall back to an invented 4.00%** (`RateComparator::getMarketBenchmarks`, `getBenchmarkForAccount`) when no stored rate exists; the card can fire from it (Rule 23).
- [ ] **Tax plan items carry no working, so Fyn invents it (Rule 23).** "Talk me through my tax plan" → "£60,000 − £50,270 = £9,730 taxed at 40%"; the plan's £3,700 is adjusted net income £54,000 − £50,270. The composed item (`pension_tax_relief`) has no figures behind `suggested_contribution`.
- [ ] **Ask Fyn from a card while mid-onboarding is swallowed:** a user with `onboarding_completed = 0` and a step set goes to the onboarding director, which greets "Welcome back… continue?" and never answers the card's question (csjones user 419, 2026-09-29).
- [ ] **/m cannot mark an emergency fund account** (no control; Fyn cannot set `is_emergency_fund`: `UpdateRecordAllowlist`, `CaptureForms`, `RecordEditForms::savingsAnswers`). The how-to names the web Savings page.
- [ ] **Savings cards that duplicate tax actions** can sit on one list (`psa_breached`/`cash_isa_recommended` vs `isa_topup_vs_psa`, `spouse_psa_shift` vs `savings_to_spouse`, `child_no_jisa` vs `junior_isa`, `excess_cash_pension` vs `pension_tax_relief`).
- [ ] **Savings how-tos with no verified source yet:** `offset_mortgage_better`, `excess_cash_bond`, `excess_cash_gia` (MoneyHelper blocks automated fetch). CSJ to supply a source.
- [ ] **Regular saver claim** ("usually pays more") has no stored rate behind it.
- [ ] **Rule 2: hardcoded tax fallbacks in `PSACalculator::determineTaxBand`** (`?? 12570`, `?? 37700`, `?? 125140`).
- [ ] **Tool-result depth cap hides nested rows from Fyn** (`HasAiChat::trimForModel`, depth 3).
- [ ] **CI Unit/Feature take 20-25 minutes each** — never wait on them (CSJ); splitting them across runners would make CI useful again.
- [ ] **iOS: render how-to `learn_more` links**; iOS list `meta` join fix from #967 ships with the next iOS build.
- [ ] **Help audit leftovers (section 7):** `FamilyMemberFormModal.vue:38-39` "A user account will be created for your spouse"; `GET /api/user/letter-to-spouse/spouse` has no client; no web or /m input for `nrb_transferred_from_spouse`; critical illness cover type forced to standalone (`PolicyFormModal.vue:1000`); dead components (`RiskAnalysisSection`, `BenchmarkComparison`, `PerformanceAttribution`, `PortfolioOptimization`, `AnnualAllowanceTracker`, `StrategiesTab`).
- [ ] **#944 review minors:** web and /m card error states; clients derive `action_category` and `tax_` ids; `UNLOCK_CONSEQUENCES` lives in `ActionCardService`; about ten places compute the 6 April tax-year start separately.
- [ ] **Tech debt from 2026-09-29** in `docs/tech-debt-report.md` — criticals: spouse-transfer double counting (above); typed-in tax figures on the routed `/savetax/plan/v2`, `/v3` mock-ups. Warnings: Stop no longer stops the server turn (#976); `adoptLinkedSpouseWorkStatus` writes inside a skip predicate (#978); retry reload waits a fixed 3s; the idempotency body hash includes `current_route`; income page still writes the employment total directly; four copies of the protection needs/coverage assembly (Rule 20).
- [ ] **In-app purchase (parked until App Store review, CSJ 2026-09-27):** scripts in the 2026-09-27 session scratchpad — `asc-iap-setup.sh` and `prod-apple-bridge-install.sh`.
- [ ] **Scottish income tax: a separate programme** (CSJ 2026-09-25). Marriage Allowance carries a caveat line meanwhile.
- [ ] **Estate residence follow-up:** no UK departure date is captured, so the leaver "tail" (in config) is not applied.

## QUEUED — Fyn memory and dense-recall plan (starts only on CSJ's go)

CSJ 2026-09-25: queued, not started automatically. A session presents it as the next item and waits for CSJ to say go.

- [ ] **Run it inline** (CSJ 2026-09-24: "done inline, not sub-agent driven"; `superpowers:executing-plans`). Confirm D5 (OpenAI `text-embedding-3-small` at 512 dimensions) with CSJ before Task 7.
- [ ] Plan `docs/superpowers/plans/2026-09-24-fyn-typed-memory-and-dense-recall.md`, 9 tasks:
  1. `MemoryFactGuard`
  2. `user_memory_facts` + repository
  3. per-user learning writes memory, active immediately
  4. recall and erase read typed memory; retire the Markdown fact store
  5. conversation summaries are the one episodic recall path
  6. settings memory screen on web and `/m`, plus "forget that" in chat
  7. OpenAI embeddings in MySQL
  8. hybrid recall
  9. relevance eval and switch-on record
- [ ] After Tasks 1–6 are released: CSJ switches `FYN_LEARNING_ENABLED=true` on production (it is **false** since 2026-09-24 16:41). `OPENAI_API_KEY` goes on the servers (CSJ) before Task 9.
- [ ] Parked by CSJ until Option 5 proves itself: the Neo4j/Aura decision, including which tier. Research: `September/September24Updates/neo4j-graph-vector-architecture-research.md`.

## Optional decisions after the 2026-09-22 releases (CSJ)

Three small decisions, all optional:

1. `resources/js/components/Retirement/RequiredCapitalDetail.vue` is imported nowhere (0 references). Delete it with the other MB-09 dead pages, or wire it in.
2. `campaign_verify_announce` / `verifyPromptAnnounce()` are orphaned since #932 (the Okay tap is gone — "let's try this"). Keep the trial going, then delete both or restore the gate.
3. Item 7 (Brett): the retirement projection is hidden on the onboarding verify visit only. CSJ 2026-09-22: "hidden during onboarding is fine". No change unless Brett asks again.

## Next session starts here — iOS (CSJ, 2026-09-09; still open 2026-09-12)

- [ ] **Two native changes are on `main` but have never been seen on screen.** Both compile-verified
      only (`BUILD SUCCEEDED`) and both ship via TestFlight rather than the web deploy, so nothing
      reached users unverified — but the cards and the climb are still on any installed build until
      a build is cut. (a) The level-climb in the wheel (2026-09-17): `LevelProgressView.swift`,
      `LevelCelebrationSequence.swift`. (b) The milestone/insight card removal (2026-09-18):
      `DashboardView.swift`, `NextMilestoneView.swift`. The JS twins are the reference behaviour.
- [ ] **BLOCKED ON CSJ — how the phone gets tested.** Build 10 (Production configuration,
      "Fynla" record, VALID 2026-09-10 08:49 BST) carries #773 and all of build 9. On-screen
      checks owed: headline rows; an information-request row opens Fyn with "I can help you
      enter the information for …"; Yes / No thanks; "No thanks" closes the cover and
      replaces the row; Settings → Plan and billing reads "Upgrade on the web" (Free).
      `FynlaTests` cannot run on the device — fixture tests load by absolute Mac path
      through `try!` (`FynlaTests/AuthClientTests.swift:271`), the runner crashes and
      restarts. Either bundle the fixtures as test resources (harness change) or write a UI
      test for the two screens; simulators wedge the Mac. CSJ decides.
- [ ] **The remaining `deferred-ios` items** — W-0090, W-0243, W-0311, W-0416 need Swift;
      W-0044 and W-0496 have the Swift landed and need the on-screen check. Fresh branch
      off dev. Parity ledger: `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`.
- [ ] **iOS harness debt**: the shared UI-test typing helper cannot clear the email keyboard
      off the password field on an iPhone 11; the 6 red `Local StoreKit configuration`
      tests are a real signal (no IAP products in App Store Connect).

## Parked, non-iOS — need CSJ, do not start unasked

- W-0540 dead-component clusters and the Rule 15 lint scope (carried since 5 September;
  now measured as MB-01 (153 files) + MB-09 (56 guard-blocked Vue public pages) in the
  mapping bugs file — one decision covers all three).
- The unstyled homepage pension-check block parked in PR #770's description.
- The 34 remaining sweep findings — decide, do not chase.
- Tax-compliance review — W-0367, W-0514, W-0508, W-0338, W-0470, W-0518, W-0498;
  design-lead / quality-lead on W-0497; chief-of-staff on W-0506.
- The savings web form captures no account name (institution + product only); the
  display-name fallback covers the recommendation copy, the form gap is a product call.
- **One page per module for dashboard routes** — `GamifiedDashboard.webRouteFor` (`/net-worth/cash`)
  vs `semanticDestinations.overviewPaths` / `GateRoutes::MAP` (`/savings`). Product call (PR #818).

## Settled by CSJ — do not re-raise

- **Fyn memory (2026-09-24):**
  - Memory and learning are **per user**, and learned facts **apply automatically**, with no approval queue. The only block is the pointer rule: no live values (money, percentages, dates of birth, stored records) in memory. Review is only for global procedural and regulatory content.
  - No household or user-data copy in any vector or graph store; always look up live.
  - Option 5 first (dense recall inside MySQL), not Neo4j.
  - Production is SiteGround **shared** hosting (no port 7687, no daemons).

- **W-0144** — revocation of former wills is the law; the 28-day survivorship period is
  standard drafting. Defaults unchanged, no prompt needed.
- **W-0155** — consent is a single accept button. There is no withdrawal journey.
  `declineCookies()` is the banner's Decline path, not dead code.
- **W-0546 (2026-09-10)** — the decline copy is "Declining switches off Google Analytics and
  our affiliate tracking, and nothing else. Registration, signing in and every feature
  work as normal." Approved by CSJ; one home `resources/js/constants/cookieCopy.js`.
- **W-0544 (2026-09-10)** — a user is told a plan cap is reached BEFORE filling a form.
- **Recommendation ids (2026-09-10)** — a per-record rule gets one id per record
  (`_a{account}` etc.); single-instance ids unchanged. Never re-add a shared id.
- **W-0524** — agricultural relief is a property-type design decision, deferred.
- **One PR, not split.** **No parallel agents, of any kind, for any purpose.**
- **The board loop is web and `/m` ONLY.** Every iOS item defers, marked `deferred-ios`.
- **The board-loop skill is gospel, not guidance.**
- **(2026-09-09)** The seeded priority wins the ranking; the corpus workflow file is the one
  home for the onboarding table; the SaveTax consent gate is re-wired, not deleted
  ("No thanks"); the young family Junior ISAs stay; the ten phantom triggers alias real
  rules; months of runway replace the emergency-fund grade; the protection
  `adequacy_score` contract stays; the `/m` Fyn panel over the focus cards is not an issue.
- **(2026-09-11/12)** The savings_account cap is "bank accounts" (the page is "Bank Accounts"
  everywhere; the Cash page cards are account types; "Cash Management" is the nav section).
  Spouse figures from the SaveTax step show provider names like every record. The post-plan
  spouse invitation copy is approved verbatim (PR #826); pensioncheck gets the same state.
  The ownership loop was NOT a regression — the July fixes relied on the model re-calling.
- **(2026-09-09) Closed for handover purposes:** the production `.env` keys
  `COMPANIES_HOUSE_API_KEY` / `GETADDRESS_API_KEY`, and the unconfigured Apple
  verification bridge on production.

## Known issues

- **`users.life_stage` is overloaded by design** — it also holds the journey or focus area
  last started (`JourneyStateService`, `OnboardingService`). The client keeps only real
  stages (`lifeStage.js` `setCurrentStage`); do not "fix" the backend column.
- **The auto-mode permission classifier** refuses some routine deploy/SSH commands
  (compound prod deploys, `git checkout` on csjones, reading codes over SSH). `rsync` of
  `public/build/`, `git switch` on csjones and the `ssh-fynla` MCP pass. Any command
  handed to CSJ must use absolute paths — one run from `~` put prod into maintenance mode.
- **The formatter hook strips a just-added `use` import before its usage lands** — add
  import and first use in ONE edit, then check it survived.
- **`OnboardingView` mounts no `AppLayout`** — nothing the layout loads exists in the
  wizard unless the step asks (`auth/fetchSubscriptionData` is the pattern).
- **`public/build/` and `public/m-build/` locally hold whichever build ran last** (csjones
  or prod base path). `./dev.sh` on 8000/5173 serves the web SPA through Vite; `/m` serves
  the built `public/m-build` — run `npm run build:mobile` before a `/m` check.
- **Persona passwords are `Password1!`**, not `password`. A 401 is probably not a bug.
- **A preview persona's `/m` token is rotated by the app's refresh flow** — mint a fresh
  one (`POST /api/preview/login/{persona}`) per browser session.
- The iOS `test-and-build` CI job is **not a release gate and must never be re-run
  unasked** (CSJ 2026-09-07). dev's Quality Gate is fully green since #923 (2026-09-22);
  the reds were tests writing enum values migrations had removed, a pinned seeder count,
  a Vite manifest the CI test job never has, and one real bug (legacy paid plan slugs not
  canonicalised at settlement). Keep it green: a red is a bug, not weather.
- **Never run two Pest processes at once** — they share `laravel_testing` and both fail
  with QueryExceptions that look like real failures (2026-09-22, twice).
- **fynla.org's page cache serves a stale response for a repeated URL** — a diagnostic
  probe re-read under the same filename returned the old body for 20 minutes. Use a
  fresh filename (or query string) per probe before concluding a change "did not take".
- **Before any release, check what prod actually runs** (bundle hash, `migrate:status`,
  vendor mtime). Prod deploys rsync `app config database routes resources/views public/pages
  public/build` (+ `fyn-memory/` and `resources/js/data/` when they change) and `rm` any
  deleted classes by hand. **Never rsync `bootstrap/`.**
- **Universal links on SiteGround are served from the site-root `.well-known/`**, not
  `public/.well-known/` — `deploy/DEPLOY.md` step 8.
- **fynla.org's 8 paying customers are on `premium`** since the 2026-09-08 collapse.
- **Never run a targeted suite while the full suite is running** — same MySQL database.
- **Pest refuses a file and its parent directory in one invocation**; pass the directory.
- **`./vendor/bin/pint app/` times out** at 2 minutes — format only changed files.
  **`pest --filter=""` matches nothing and exits 0**, which looks like a pass.
- **Test accounts:** fynla.org `slaterjoneschris+fynla0910@gmail.com` (real Free account, one
  property added and removed 2026-09-11); csjones john (two 0% current accounts, £1,800 joint
  expenditure, life stage "university") and `savetax-0911a…f` / `savetax-0912a/b@example.com`
  (0912b holds a pending spouse invitation); local David and `journey-0910@example.com`.
- **Playwright's main tab can stop delivering input** while scripts still run — drive a second
  browser context from `browser_run_code_unsafe` (re-find it each call); never `browser_close`.
- **Prod deploys with a migration** rsync `database/migrations/` first; corpus changes rsync
  `fyn-memory/procedural/` and run `fyn:procedural:validate` on prod.
- **Adding a corpus workflow state:** constants + `inCodeStates()` + corpus in the same ORDER;
  guard `nextFrom…('')` (skip rules pass an empty answer; `matchBubble('')` hits the first bubble).

## Deploy state

- **2026-09-27: prod (fynla.org) = main `569957ef8` (#947).** Morning #945 (`a675058bd`: migration `2026_09_27_000001`, `TaxConfigurationSeeder`, `ActionHowToSeeder`, both bundles); evening #947 (PHP only). Backups `~/release-backups/2026-09-27/` and `2026-09-27b/`. Walked live on web and `/m`; accounts 754 and the evening walk account purged. csjones back on `dev` at `a21778c18`; test account 428 `psa-walk-2026-09-27@example.com` left there. Prod `route:list` crashes on the Apple bridge (`invalid_configuration`) — harmless until IAP; never use it as a deploy check.

- **2026-09-24:**
  - Production `.env` now carries `FYN_LEARNING_ENABLED=false` (it was on 08:35–16:41, and nothing was staged). Backup: `.env.bak-2026-09-24-learning`.
  - 432 test-output episode files deleted from production `fyn-memory/episodic/episodes/`.
  - csjones pulled `dev` at `67ca3793f`. It still has `FYN_LEARNING_ENABLED=true` and 206 pending figure-laden rows in `proposed_semantic_facts` (test accounts); plan Task 3 retires that path.

- **csjones is on `dev` at d6650eb93 (2026-09-25 13:07)**, both bundles uploaded (built from the #939 branch, same frontend as dev). Test accounts left there: 422 `savetax-only-2026-09-25@example.com`, users 419–421 `brett-a/c/d-2026-09-22@example.com` and 397 `formwalk-0915@example.com` (all `Password1!`), disposable.
- **2026-09-25: prod (fynla.org) = main `9bc414107` (#940).** Save Tax-only onboarding (#939) plus #935–#937. No migrations or seeders; corpus rsynced with episodes excluded; learning stays off. Walked live on web and `/m` (account 751, purged). Backup `~/release-backups/2026-09-25/`. Auto mode blocks prod deploys: the deploy ran as a script CSJ executed with `!`.
- **2026-09-22: prod (fynla.org) = main `df14df1e2`.** Three releases, all walked live on `/m` with fresh registrations, since purged; nothing unreleased. Backups `~/release-backups/2026-09-22{a,b,c}/`.
  - `904364c31` (#924) — threshold position, RSU value and labels, free-tier childcare and Gift Aid, Gift Aid one-write, green gate (#919–#923).
  - `f2f880fc7` (#926) — disabled-child flag, taper copy, `serialize_precision` at boot (#925); migration `2026_09_22_150000` ran.
  - `df14df1e2` (#934) — Brett's phone-walk batch #929–#933 (recap glyph, actions list, job title optional, verify-visit cards hidden, property equity on `/m`, dividends on the investment and non-working spouse forms, non-earner £2,880 yes/no, no Okay tap, Save Tax expenditure asks the tax fields only, fuller acks) and #928 (expenditure one-home, wizard prefill). No migration; three corpus files rsynced.


- **2026-09-19: prod (fynla.org) = main `8fdaac326`.** Two releases, both verified live on web and `/m`; nothing unreleased. Backups `~/release-backups/2026-09-19a/` (full, before #915) and `2026-09-19b/` (four files, before #918).
  - `c5fc88981` (#915) — Azlan/Laura/Brett batches #907–#914; migration `2026_09_19_120000_create_spouse_invitations_table` ran.
  - `8fdaac326` (#918) — #916 `SpouseJointRecords::carry()` (joint records keep the invitee's id after onboarding completes) and #917 (savings card agrees with net worth before the readiness gate; "I don't know" spouse income reads back as unknown). PHP only.
- **2026-09-17: prod (fynla.org) = main `0988f6d31`.** Three releases today, all verified live on web and `/m`; nothing unreleased. Backups `~/release-backups/2026-09-17{a,c,d}/`. `c.jones` purged after the last one.
  - `94ad79c33` (#895) — capture forms on every entry point + the spouse holding transfer; migration `2026_09_16_220000`.
  - `07f772d8b` (#898) — the unlinked-spouse action and the `/m` all-actions list. No migration.
  - `e2368a982` (#900) — the module label comes from the server; both client maps deleted. No migration.
  - `0988f6d31` (#902) — the level-up redesign. **Migration `2026_09_17_120000` ran**: it backfills `celebrated_level = level` for every row, verified on all 43 production users (zero owed a climb) and on 95 csjones users.
- **Native iOS is on `main` but has never been run on a device.** It ships via TestFlight, not the web deploy, so nothing reached users unverified. CSJ is taking the check.
- 2026-09-16 session 2: main `b64ec17cc` (releases #872, #874, #876, #878, #881, #883, #885). Backups `~/release-backups/2026-09-16b..f/`.

- Eleven mapping PRs (#829–#839) still open and unmerged (rebased stack, parked on CSJ).
- **TestFlight "Fynla" 1.0 (10)** on the `org.fynla.app.dev` record, Production
  configuration reading fynla.org, VALID 2026-09-10 08:49 BST; native tree unchanged since.
  The `org.fynla.app` record is "Fynla (legacy)" — never upload there unasked.
- **Dashboard recommendation routing**: one home `app/Services/Mobile/RecommendationRouting.php`
  (keys on `rule_key` since 2026-09-10); ids composed in
  `RecommendationsAggregatorService::composeId` / `disambiguate`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- **(2026-09-24)** `tests/Pest.php:111`: per-test `fyn-test-memory-*` temp directories are never deleted. They go away when plan Tasks 4 and 5 remove the two memory path keys.
- **(2026-09-22)** `RequiredCapitalDetail.vue` dead (0 imports); `campaign_verify_announce` + `verifyPromptAnnounce()` orphaned since #932; `CoordinatingAgent` 6,979 lines with `handleSetExpenditure` 203; the spouse acks build the dividends/contribution clauses twice (`OnboardingChatDirector:6423`, `:6479`); the childcare hint is written three times in `CaptureForms` (`:1267`, `:1291`, `:1345`); `expenditureColumns()` / `expenditureColumnsOf()` walk the same list; `nonEarnerNetContribution()` resolves `TaxConfigService` via `app()` in a static class; `.user.ini` left on both servers.

- **(2026-09-19)** the onboarding-scratch reset exists three times in `OnboardingChatDirector.php` (`:862`, `:6317`, `:7667`; today added a line to each — one `clearOnboardingScratch()`); two extra queries on the gated return `SavingsAgent.php:79-80`; the unknown-income sentence twice in `CaptureForms.php:605/630`; `SpouseDashboardSavingsTileTest.php:27` hedges over the response envelope.
- **(2026-09-11/12)** The spouse "What you told Fyn" row mapping is written once per surface
  (`resources/mobile/views/Income.vue:79`, `IncomeOccupation.vue:538`); five homes for the
  `capture_spouse_household_data` field list (handler allowlist + rules, model fillable, both
  schema mds); two canned-refusal recognisers (`HasAiChat.php:1757`, director `:4491`); the
  invite outcome copy lives in PHP (`OnboardingChatDirector.php:2141`); two stop-word lists
  (`SpouseHouseholdPhrasings:61`, `OnboardingFactExtractor:38`); the same email regex twice;
  `OnboardingChatDirector` 7,668 and `CoordinatingAgent` 6,827 lines; `IncomeOccupation.vue` 854.
- **(2026-09-10, still open)** the vanilla cookie banner is a hand copy pinned only for the
  decline sentence; `ExpenditureForm.vue` ≈2,550 and `SavingsActionDefinitionService.php`
  ≈3,780 lines.
- **(2026-09-09 pm, still open)** the web path map exists twice (`semanticDestinations.js:10`,
  `GamifiedDashboard.vue:365`) — product call; two /m contextual-open methods
  (`Dashboard.vue:781`, `MobileChrome.vue:344`) — share `createContextualConversation` already;
  `ContextualConversationService::recommendationFor` runs a full aggregation per tap;
  `HolisticPlanningController::markRecommendationDone` is a second completion path;
  `ActionsOverviewCard.vue` imported nowhere.
- **(2026-09-09)** `RecommendationsAggregatorService::aggregateRecommendations` is 175 lines
  (five raw-path rollback blocks behind `coordination.composed_module_plans`; retire the flag
  and they go). `PriorityRanker::MODULE_WEIGHTS` carries both `tax_optimisation` and `tax`.
  `StrategyPriority` has no `Critical` case (adapters carry `extra['seeded_priority']`).
  `0.00005` and the flat `0.0400` benchmark block are unnamed.
- **(2026-09-07)** `SubscriptionManagementView.swift` writes the web-handoff button + error
  block twice; `TierCollapsePreflight.php` re-derives "plans that confer premium" instead
  of one `TierConfigurationStore::plansConferringPremium()`; `WillFactory.php` repeats the
  unnamed column default `100.00`.
- **Two mechanisms answer "what does this user owe"** — `NetWorthService:155` and
  `CrossModuleAssetAggregator:404`. Parity held by a test, not by construction.
- **The debt protection panel exists twice** — the canonical service `/m` consumes, and
  the web `/protection` page's own component.
- **`InvestmentController`'s write paths disagree** — create guards the auto-Cash row with
  `&& ! $hasCashHolding` (`:439`), update does not (`:587`). Same asymmetry as W-0321.
- **No UI field for `lpa_attorneys.is_bankrupt` (W-0105) or the professional
  certificate-provider details (W-0106).** Column, validation and check exist; nothing asks.
- **`TaxConfigService::hasSurvivorshipRights()` and `allowsWillOverride()` have zero callers
  BY DESIGN** (`:828-846`, W-0498). Listed so a dead-code sweep does not delete them.
- **`RetirementProjectionService` takes nine constructor arguments**; two test files
  construct it by hand.
- **`GiftAnnualExemption` does not model s20, s21 or s22** — s21 is W-0525's remaining half.
- 52 unused private injections outside the TaxConfigService cluster.
- `database/schema/mysql-schema.sql` is stale. Wrong, not harmful.
- The gifting UI still offers edit/delete on a trust-owned gift (422); needs `trust_id` on
  `GiftResource`.
- Spouse WRITES require reciprocity but not consent — deliberate, open to challenge.
- **W-0351 acceptance 3 NOT done** — the sweep for other `v-if`s gating on fields their
  Resource never returns.
- **Three compliance-lead copy reviews outstanding** (W-0108, W-0152, W-0153).
- `CanonicalPortfolio.vue:23` prints "OCF" unexpanded on `/m` (Rule 9). Pre-existing.
