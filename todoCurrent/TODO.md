# Current list

The persistent list of what we are doing, in order. Started 2026-09-30 (CSJ).

**How it works**

- **Work top to bottom.** The first item that is not crossed off is the current item. Every session starts there.
- **Done means crossed off, never deleted.** Tick it, strike it through, and add the date and the evidence: the PR, the commit, and where it was walked (csjones or fynla.org, web and /m).
- **Anything found while working an item goes under that item,** as an indented "Found:" line: bugs, issues, questions, follow-ups. That records where we were. Then carry on down the list.
- **Your decisions are marked "DECISION (CSJ)"** with the question. Work does not stop on one: take the next item and come back.
- **`CSJTODO.md` stays as the detailed record** (file paths, evidence, history). This list is the order of work. Where an item says "CSJTODO", the detail is there.

---

## Done

- [x] ~~Walk and merge #1022: the desktop Tax Strategy page says which Income Tax bands it uses~~ (2026-09-30: walked on csjones, web 1440 + /m 390; merged dev `d3e85cf2b`)
- [x] ~~#1023: a linked spouse's own income counts as their income being known~~ (2026-09-30: walked on csjones before and after, web + /m, walk accounts 442 + 444; merged dev `2725d61c8`)
- [x] ~~#1024: a linked partner who shares their data is asked only their income on "Now your spouse."~~ (2026-09-30: walked on csjones, web + /m, seeded couple 445 + 446; merged dev `eb84792db`)
- [x] ~~Release #1018 to #1024 to fynla.org, walk it, update the patch notes~~ (2026-09-30 ~18:35 BST: release #1025, main `d050b0609`; walked on fynla.org as the Carter household, web + /m; `September/September30Updates/patch-notes-2026-09-29-30.md` + PDF)
  - Found: one "Disk quota exceeded" in the production log at 01:57 on 30 September, before the release; not repeated, 346 GB free. Watch only.
  - Found: the session-3 handover recorded four tax features as approved; CSJ's reply approved two and asked what the other two are. Corrected in `CSJTODO.md` (items 3 and 4 below).

---

## To do

1. [ ] **Affordability check on every partner pension top-up** (APPROVED, CSJ 2026-09-30: "affordability check always"). A partner earning £20,000 was told to pay £15,200 in (`docs/testing/2026-09-29-savetax-scenario-matrix.md`, S9 and E4). Spec with sources first, then build, walk web + /m, release.
   - DECISION (CSJ), never put to you before: the same test found the partner's top-up (20% relief) ranked above the user's own pension move (40%) for the same money (matrix E4). Should the plan prefer the higher-rate move?
2. [ ] **Marriage Allowance tested against the law, not gov.uk's summary** (APPROVED, CSJ 2026-09-30: "widen to law"). The transferor must not pay above the basic rate after the transfer (Income Tax Act 2007 s55C(1)(c), (ca)), not "income below the Personal Allowance". The gates are in `TaxStrategyMath::marriageAllowance`; ice-cube's #982 sets out where the two differ (for example £14,000 of savings interest inside the Starting Rate for Savings).
3. [ ] **Move savings to the partner who pays less tax, for couples who both earn** (APPROVED, CSJ 2026-09-30: "Yes build it"; matrix E2, and E5 for retired couples). Explained 2026-09-30: basic-rate £1,000 Personal Savings Allowance against higher-rate £500 (ITA 2007 s12B). Example: £40,000 at 4.5% moved in part to a £20,000 earner saves about £460 a year. Spec with sources first, then build, walk web + /m, release.
4. [ ] **Pension relief at 40% below £100,000, down to £50,270** (APPROVED, CSJ 2026-09-30: "Yes build it"; matrix E3). Today the plan stops once the Personal Allowance is back; every further £1,000 paid in still saves £400, within the Annual Allowance and what is affordable. Spec with sources first, then build, walk web + /m, release.
5. [ ] **The Retirement page for someone already retired.** It shows "Years to go 1", "Retirement age 67", a projected income and a required capital, as if still saving (csjones, Pat: born 1958, retired 2020, £200,000 pot drawing £30,000). `RetirementProjectionService::projectPensionPot` clamps years to go with `max(1, …)`, and `RetirementAgeResolver` never reads `retirement_date`. Needs a view for people drawing their pension, on web and /m. Check spec, contract and vault first; ask only if nothing covers the design.
6. [ ] **Retirement module: review it in the savings and protection shape, then its how-tos** (25 definitions, none written).
   - Review first: do the cards come from the module's definitions, and carry `definition_key` and `figures` through `buildRecommendation` → adapter `extra` → aggregator → `ActionCardService`? (Protection did not, until #972.) Which keys fire on real households? Which cards are the same action? Is there a "your position" view, as protection got?
   - Then the how-to batch in the `savings.md` / `protection.md` format (`why`, branches, `outcome`, `learn:`, every claim sourced), with a `SOURCES` entry in `ActionHowToSeeder`. The draft must be on `dev` before CSJ reviews it; CSJ approves each entry.
   - Walk web + /m on csjones, release, walk fynla.org.
7. [ ] **Investment module: the same review, then its how-tos** (24 definitions, none written). As item 6.
8. [ ] **Estate module: the same review, then its how-tos** (12 definitions, none written). As item 6.
9. [ ] **A retired partner's State Pension or final salary pension is recorded as drawdown from a personal pension** (found in the 2026-09-30 ice-cube check; #1014 and #1016 treat all of a retired partner's other income as drawdown).
10. [ ] **Web setup never asks whether the partner is retired.** The inviter's "Now your spouse" form has no status field, so the link cannot split the income until the partner answers (CSJTODO).
11. [ ] **Check the "0 holdings" caption on an ISA added through Fyn** (ice-cube; not checked 2026-09-30, the caption's source was not found).
12. [ ] **Walks not yet done on fynla.org:** the "HMRC adds £720" wording for a partner who does not earn; #1024's "Now your spouse." step for a linked partner (walked on csjones only).
13. [ ] **"(enter 0 if none)" sits inside a label that reaches Fyn's prompts** (`HouseholdFinancialContext.php:152`, `spouse_income_amount`; 28 September tech debt).
14. [ ] **DECISION (CSJ): sources for three savings how-tos:** `offset_mortgage_better`, `excess_cash_bond`, `excess_cash_gia`. MoneyHelper blocks automated fetching; supply or approve a source.
15. [ ] **One rule for "ISA allowance used this year"** (Rule 20 / 23, user-facing). `ISATracker::buildOwnerStatus` and `TaxStrategyMath::estimateIsaSubscriptionsThisYear` disagree; a stale `investment_accounts.tax_year` shows £20,000 paid in as £20,000 left (CSJTODO).
16. [ ] **Savings market rates fall back to an invented 4.00%** (`RateComparator::getMarketBenchmarks`, `getBenchmarkForAccount`), and a card can fire from it (Rule 23).
17. [ ] **Tax plan items carry no working, so Fyn invents the arithmetic** ("£60,000 − £50,270 = £9,730 taxed at 40%"; the plan's £3,700 is adjusted net income £54,000 − £50,270).
18. [ ] **"Ask Fyn about this" from a card is swallowed while onboarding is paused** (the director greets "Welcome back… continue?" and never answers; csjones user 419).
19. [ ] **/m cannot mark an emergency fund account,** and Fyn cannot set `is_emergency_fund`.
20. [ ] **Savings cards that duplicate tax actions** can sit on one list (`psa_breached` / `cash_isa_recommended` vs `isa_topup_vs_psa`, `spouse_psa_shift` vs `savings_to_spouse`, `child_no_jisa` vs `junior_isa`, `excess_cash_pension` vs `pension_tax_relief`).
21. [ ] **The regular saver claim ("usually pays more") has no stored rate behind it.**
22. [ ] **Rule 2: typed-in tax fallbacks in `PSACalculator::determineTaxBand`** (`?? 12570`, `?? 37700`, `?? 125140`).
23. [ ] **Typed-in tax figures on the routed `/savetax/plan/v2` and `/v3` mock-ups,** served on fynla.org today (critical, 29 September tech debt).
24. [ ] **The old setup wizard invents a mortgage** for any property balance (`OnboardingService.php:598-613`: "Mortgage Provider", 3.5%, 5 years in, 20 left); reachable only by typing `/onboarding/full` (critical, 30 September tech debt).
25. [ ] **30 September tech debt** (`docs/tech-debt-report.md`, session 3): `platform_fee_percent` and `indexation_rate` mappers may store fractions (`DCPensionMapper.php:39`, `InvestmentAccountMapper.php:32`, `LifeInsuranceMapper.php:38`); the status lists written twice (`SpouseHoldingTransfer.php:28-31`, `TaxStrategyCalculator.php:482-483`); the split `if` blocks in `AssetShiftingBundleStrategy.php:86-135`; `ANONYMISED_TABLES` does not drive the purge's Phase 6.
26. [ ] **Protection still has two engines:** `RecommendationEngine` still feeds `ProtectionAgent::analyze()['recommendations']` (the plan page's own section and the rollback path).
27. [ ] **The tool-result depth cap hides nested rows from Fyn** (`HasAiChat::trimForModel`, depth 3).
28. [ ] **Help audit leftovers (section 7):** "A user account will be created for your spouse" (`FamilyMemberFormModal.vue:38-39`); `GET /api/user/letter-to-spouse/spouse` has no client; no input for `nrb_transferred_from_spouse`; critical illness forced to standalone (`PolicyFormModal.vue:1000`); dead components (CSJTODO).
29. [ ] **#944 review minors:** web and /m card error states; clients derive `action_category` and `tax_` ids; `UNLOCK_CONSEQUENCES` lives in `ActionCardService`; about ten places compute the 6 April tax-year start separately.
30. [ ] **29 September tech debt** (`docs/tech-debt-report.md`): Stop no longer stops the server turn; `adoptLinkedSpouseWorkStatus` writes inside a skip predicate; retry reload waits a fixed 3s; the idempotency body hash includes `current_route`; the income page writes the employment total directly; four copies of the protection needs assembly.
31. [ ] **Production housekeeping:** the Apple bridge is not installed on fynla.org (`route:list` fails); production `vendor/` carries dev packages.
32. [ ] **CI Unit and Feature take 20 to 25 minutes each.** Split them across runners so CI is useful again (never wait on them meanwhile).
33. [ ] **Fyn typed memory and dense recall** (`docs/superpowers/plans/2026-09-24-fyn-typed-memory-and-dense-recall.md`, 9 tasks, run inline). Confirm D5 (OpenAI `text-embedding-3-small`, 512 dimensions) before Task 7. After Tasks 1 to 6 ship, CSJ switches `FYN_LEARNING_ENABLED=true` on production (off since 2026-09-24).
34. [ ] **iOS (deferred; CI verifies iOS, never the laptop simulator):** render how-to `learn_more` links; the #967 list `meta` join ships with the next build; two native changes on `main` never seen on screen; DECISION (CSJ): how the phone gets tested; the remaining `deferred-ios` items; the UI-test typing helper (CSJTODO).
35. [ ] **Parked by CSJ, listed so they are not lost:** in-app purchase (until App Store review); Scottish Income Tax as its own programme; the Neo4j decision; the estate residence departure date.
36. [ ] **Housekeeping:** purge csjones walk accounts 442, 444 (linked as spouses for the #1023 walk), 445 and 446; CSJ to flush the SiteGround dynamic cache for csjones.co; check the vault sync for 29 September session 3 and 30 September ran.
