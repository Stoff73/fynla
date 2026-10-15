# Current list

**Created 2026-10-15 (CSJ).** This list consolidates everything still open from three places:

- the previous list, archived at `todoCurrent/archive/TODO-2026-09-30-to-2026-10-14.md` ("archive" below): its open items 18 to 49, and every open Found and DECISION line under the items crossed off there;
- the walk fix plan, `October/October12Updates/walk-2026-10-11-fix-plan.md` ("the plan"): every task in section 0b not yet released;
- the 9 October regression walk, `October/October9Updates/regression-walk-2026-10-09.md`: the sections never walked.

**How it works**

- **Work top to bottom.** The first item that is not crossed off is the current item. Every session starts there.
- **Done means crossed off, never deleted.** Tick it, strike it through, and add the date and the evidence: the PR, the commit, and where it was walked (csjones or fynla.org, web and /m).
- **Anything found while working an item goes under that item,** as an indented "Found:" line. Then carry on down the list.
- **Your decisions are marked "DECISION (CSJ)"** with the question. Work does not stop on one: take the next item and come back.
- **Plan tasks keep their T-number.** The plan stays their evidence log: the six boxes (Code, Test, Dev, Dev walk, Prod, Prod walk) and rules R1 to R5 (no sub-agents; walk as a user; a box ticked only with evidence; one task at a time; test only the change, one full run at T43) still apply. Tick the plan's box and this list together.
- **Lines carried from the archive** give the archive line they came from (`archive:NN`). Lines marked "not re-checked" were written before later work that may have fixed them: check the code first.

---

## Where things stand (2026-10-15)

- **fynla.org:** main `ed6eb011f`, release ac (#1286, 2026-10-13 10:16 BST). It carries plan tasks T0 to T25, with T48, T58 and T17. Each was walked on fynla.org (evidence PR #1287, shots `PROD-ac-01` to `-45`), except T13: its Prod walk was never done (**I COULD NOT TEST THIS** then; the box is still empty).
- **csjones:** dev `db1f47fca` (#1340), both bundles built 2026-10-14 09:32 BST, after the last merge.
- **dev is 108 commits ahead of main.** Nothing has been released since release ac.
- **The two sessions of 2026-10-13 afternoon and 2026-10-14 morning ran out of context.** Their work is all merged and logged in the plan, except T28: its Code, Test and Dev boxes were ticked on 2026-10-15 from the PR and csjones; its Dev walk was never done (item 1).

## Waiting for release (you decide when)

Ticked to Dev walk on csjones; not on fynla.org. Evidence for each is in the plan's task log.

- T58.1: the web chat box opens after "Something else" on the resume bubbles (#1300)
- T61: the web mortgage card's annual payment reads "Not recorded", not £0 (#1288)
- T62: no trailing comma on a property with a town and no postcode (#1289)
- T63: "Purchase Price" never given reads "Not recorded", not £0, web and /m (#1290)
- T64: **figure:** the Section 24 credit for an interest-only mortgage with no payment given (#1291, #1292)
- T65: **figure:** a child's Junior ISA is the child's, not the parent's, in Inheritance Tax and cash (#1294 to #1297)
- T66: the published pension working keeps its pence (#1299)
- T67: "Marriage Allowance is lost" only for a married person or civil partner; £3,148 traced and correct (#1302)
- T68: Premium Bonds show no interest rate or interest lines, web and /m (#1306, #1307)
- T69: **figure:** the Section 24 credit limited by property profits and adjusted total income (ITTOIA 2005 s274AA) (#1310)
- T70: a Junior ISA or Premium Bonds typed to Fyn opens the savings form, linked to the child (#1312, #1313)
- T71: the Junior ISA panel names the child's allowance from configuration; the false 16-17 note removed (#1316)
- T72: **security:** a web sign-in inside /m no longer keeps the last user's /m session (#1304)
- T73: the web country search opens on typing (#1318)
- T74: the web Personal Info form offers a civil partnership (#1319)
- T76: reopening /m while signed in to /m opens the app, not the homepage (#1321)
- T77: the web property form saves a mortgage with no payment and a property with no postcode (#1323, #1324)
- T78: a Junior ISA at the ISA limit is told the limit before any form (#1314)
- T79: the web Premium Bonds limit comes from the tax configuration (#1326)
- T80: a rate never given on the web form is stored as none, not 0% (#1328)
- T81: a postcode or purchase date never given reads "Not recorded" (#1330)
- T82: the Premium Bonds maximum holds in the store for every surface and in the waterfall; a refusal gives the store's reason (#1332, #1333)
- T26: workplace pensions saved through Fyn get `scheme_type` "workplace" (#1335)
- T27: a joint record added after onboarding links to the partner (#1337)
- T28: goes with the release once its Dev walk (item 1) is ticked (#1340)

**What the release needs** (from `git diff origin/main origin/dev`, 2026-10-15):

- both bundles (web and /m);
- `resources/views/mobile-host.blade.php` (T76) and `routes/api.php` (T28);
- no migrations and no seeders;
- two data commands, each run only with your OK (see item 49): `php artisan pensions:backfill-dc-scheme-type --dry-run`, then without (T26, 9 of 25 occupational pensions on production); `php artisan couples:link-joint-records --dry-run`, then without (T27, production property 533 among them).

**After the release:** each task's Prod walk on fynla.org (web 1440, /m 390), walk accounts purged, and the plan's Prod and Prod walk boxes ticked. T13's Prod walk is still owed from release ac.

---

## Done

Everything done before 2026-10-15 is crossed off in the archive (items 1 to 17a, 39) and ticked in the plan (T0 to T25 released and walked on fynla.org).

---

## To do

1. [ ] **T28: the Liabilities page shows mortgages to Free users: Dev walk** (plan T28, F12). Code, Test and Dev are done: #1340 (`db1f47fca`), `LiabilitiesForFreeUsersTest` and `estateFetchLiabilities.spec` red then green, on csjones since 2026-10-14. The session ran out before the walk.
   - Walk web 1440 and /m 390 as a Free user with a mortgage and a debt: the web Liabilities page lists both at the user's share; /m Net Worth and its liability screen (`resources/mobile/views/modules/LiabilityDetail.vue`) agree. Then tick the plan's Dev walk box.
2. [ ] **The retirement plan fails on fynla.org for anyone whose pension funds charge above 0.5%** ("Generating retirement plan failed — Undefined variable $potentialSaving"). Carried from archive item 18 (`archive:439`, found 2026-10-08, written as "Fix next" and never done).
   - `RetirementActionDefinitionService::evaluateHighPensionFundFees` reads `$potentialSaving` at `:2746`; `8adf7e213` (1 October, release #1047) removed the variable. Re-checked 2026-10-15: still in dev and in main `ed6eb011f`.
   - Fix as written then: drop the unsourced saving sentence from the decision trace; test; own PR; release.
   - Placed ahead of the plan order because it is a live production error. Move it if you want it elsewhere.
3. [ ] **T29: Gift Aid and relief at source priced by band extension; the one-off line in the how-to** (plan T29; F7, Q4).
4. [ ] **T30: "What you can afford" and "headroom" labels** (plan T30; F8).
5. [ ] **T31: your W-0467 note on the Estate page's Inheritance Tax Summary** (plan T31; F18, P6).
6. [ ] **T32: the spending form names the payments the app adds (W-0140)** (plan T32; Q14).
7. [ ] **T33: opening /m no longer signs the web tab out** (plan T33; Q3). Seen again on the T20 to T25 walks: after /m, the web front door lands on sign-in.
8. [ ] **T34: signing in returns the user to the link they followed** (plan T34; Q25).
9. [ ] **T35: History labels; a failed turn saves its reply; failures logged** (plan T35; Q12, N6).
10. [ ] **T36: the mirror-will refusal offers the partner invite** (plan T36; Q16).
11. [ ] **T37: a 0% will beneficiary is refused** (plan T37; Q20).
12. [ ] **T38: the retirement target caption says "a target income"** (plan T38; F23).
13. [ ] **T39: form delete asks "Remove X?" first, the same on every surface** (plan T39; M7).
14. [ ] **T40: no visible self-correction in Fyn's replies** (plan T40; Q11).
15. [ ] **T44: remove the suggested prompts from Fyn's chat window** (plan T44; CSJ 2026-10-12, screenshot `remove.png`).
   - /m "Tell me more about: …" pills (`resources/mobile/views/Dashboard.vue:324-325`, `suggestions` at `:544-551`) and the web chat panel's "Suggestions" list (`resources/js/components/Shared/AiChatPanel.vue:342-365`, `suggestedPrompts` at `:644`). Native Fyn has none. The "Ask Fyn about this" button on action cards is not in the chat window and stays.
16. [ ] **T45: the write router files "Change my retirement income target …" as a `goal`** (plan T45): the goal keyword "target" wins over the `retirement_goals` wording, so the capture opens for the wrong record type (T4 walk, #22779, #22786).
17. [ ] **T46: Fyn's own arithmetic on the user's figures is wrong** (plan T46): /m gave "about £478 a month" where 5% a year compounded monthly over 228 months needs £492.89 to build £186,975. Fyn has no calculation tool (T5 walk, conversation 592). The T5 walk also found gift saving £0 that should be £484, and ISA saving £468 that should be £216.
18. [ ] **T47: Fyn printed a formula as raw LaTeX on /m** (plan T47; T5 walk, conversation 592).
19. [ ] **T49: Fyn's reply paragraphs run together** ("£5,800.Your recorded…") on /m, and on web too: a reply stored with a paragraph break renders as "savings.That's" on both (plan T49; T48 and T20 walks, shots `T20-03`, `T20-12`).
20. [ ] **T50: the what-if comparison is £0 for every figure** (plan T50; T7 walk, Alex 502). `WhatIfScenarioService::extractMetrics` reads keys the agents do not return; the `pension_contribution` override does nothing (`applyOverrides`); the user's own row is saved mid-calculation, then reverted.
   - DECISION (CSJ): T50's fix needs a design (the what-if engine is broken in three places, above). Your call before building.
21. [ ] **T51: a Free user can create a paid-plan what-if scenario through chat** (plan T51): `create_what_if_scenario` has no tier check while the API route is gated by `CheckSubscription` `what_if` (T7 walk, Alex 502).
22. [ ] **T52: on a capture failure the screen and the saved reply differ** (plan T52; T8 walk #22842, #22845).
23. [ ] **T53: Fyn's record tools read only the user's own records, never a linked spouse's who shares their data** (plan T53; T10 /m walk, Pat 506, #22859).
24. [ ] **T54: `get_module_analysis(retirement)` sends two projections** (plan T54): the page's headline (Pat £260,978.89, income £12,266.01) and `PensionProjector`'s (£217,251.58, income £8,690.06); Fyn quoted both incomes (T11 /m walk, #22861). One figure, every surface.
25. [ ] **T55: how Fyn receives the user's own Child Benefit charge** (plan T55; F6). No tax plan strategy prices it; the figure lives only in the threshold strip (`HighIncomeChildBenefitLine`), which no Fyn tool reads.
   - DECISION (CSJ), T55: the threshold position in Fyn's context, an existing tool carrying it, or a plan item?
26. [ ] **T56: the `words` search reads only the open conversation** (plan T56; T13 web walk, #22927). A figure from an earlier conversation reaches Fyn only through that conversation's summary.
   - DECISION (CSJ), T56: should the `words` search also read the user's other conversations?
27. [ ] **T57: the pages round money to whole pounds while Fyn gives pence** (plan T57; T15 walk): Pat's Income page says £26,840, Fyn says £26,839.60.
   - DECISION (CSJ), T57: should the pages show pence as Fyn does, or Fyn quote the page's whole pounds?
28. [ ] **T60: a mortgage given only a balance still gets rate type "fixed" and a 25-year term** shown as facts (plan T60; T25 /m walk: "Not recorded · Fixed", 300 months). `mortgages.rate_type` NOT NULL DEFAULT 'fixed', `MortgageNormaliser`, `mortgage.default_term_months`.
29. [ ] **T59: a single person with no dependants and no debts is told "Your life cover is £730,573 short"** (plan T59; T23 web walk, Nia 527, £68,000). Check the protection need's basis against the cover rules before changing anything.
30. [ ] **T75: /m says "Single-person household" for a user recorded as married with no partner on file** (plan T75; checked 2026-10-14, no change made: `resources/mobile/views/PersonalInformation.vue:207-216`).
   - DECISION (CSJ), T75: what should that line say? Proposal: "Married, partner not added yet" (or "In a civil partnership, partner not added yet"), keeping "Single-person household" for single, divorced and widowed.
31. [ ] **T41: live checks first, then fix what they show** (plan T41): /m message typed after a page change (M1); silent Saves (F10, Q24); will builder name and warning (Q19, Q20); retirement projection change (Q21); /m dashboard blank area.
32. [ ] **T42: Brett's account 812: the "declared none" flags and the unasked goal written by these bugs** (plan T42; F19, P2).
   - DECISION (CSJ), T42: a database change on production; done only with your OK.
33. [ ] **Item 18's own remainder: tax plan items carry their working, and Fyn presents it as the plan's** (archive item 18, `archive:432-438`).
   - Released so far: `PensionTaxReliefStrategy` publishes `working` (release z, #1142; z2, #1143/#1144); T12 published working for savings-to-spouse and the estate; T66 keeps its pence.
   - Still open (re-checked 2026-10-15): `FynContextAssembler::actionGrounding` still frames everything as "the card below" (`app/Services/AI/Fyn/FynContextAssembler.php:733`, `:737`), so web Fyn said the £3,700 "comes directly from the working shown on the card" on fynla.org (z2 walk). Next step as written then: label the working as the plan's own (`how_the_plan_worked_it_out`), test, csjones walk web and /m with several asks, release, fynla.org walk.
   - Found (`archive:433`): the other tax strategies carry no working yet (for example `IncomeBandStrategy`'s pension items above £100,000).
   - Found (`archive:436`): Fyn said moving the pension to salary sacrifice saves £120 "with no change to your take-home pay"; the National Insurance saving raises take-home pay. `SalarySacrificeStrategy` carries no working yet.
   - Note: F5, P3, F6, M8 and Q8 in the plan overlap this item.
34. [ ] **T43: the full test run, once everything above is done** (plan T43, R5). The only full run in the plan.
   - Known red before it (not from the plan's changes): `tests/Feature/Fyn/FynRankedRecommendationsAreTheActionsListTest.php` fails on dev (`900.0` vs `900`).
35. [ ] **Finish the 9 October regression walk** (`October/October9Updates/regression-walk-2026-10-09.md`; CSJ 2026-10-09: "before we do item 18, do full walks checking everything is good and there are no regressions"). Sections A to D are done; R19 to R39 shipped in release ac (plan T0).
   - Still to walk: E (edits, E1 to E5), F (actions, F1 to F4), G (how-tos, G1 to G3), H (model, log and purge, H1 to H3). Then retest every fixed flow on fynla.org.
   - R13 on /m on fynla.org was never retested.
   - DECISION (CSJ), R9: which pension pot figure the Retirement page shows (two projections on one page), with R20's remaining card questions and the Portfolio Projection's assumed £142.86 a month.
   - DECISION (CSJ), R11: /m top action titles get about 87px of a 271px row and wrap one word per line.
   - Walk accounts from it: fynla.org Ellis 807, Morgan 808, Sam 809, Casey 810 (not recorded as purged); csjones Rory 504, Quinn 505, Pat 506, Alex 507, Drew 508.
36. [ ] **"Ask Fyn about this" from a card is swallowed while onboarding is paused** (the director greets "Welcome back… continue?" and never answers; csjones user 419). (was archive item 19)
37. [ ] **/m cannot mark an emergency fund account,** and Fyn cannot set `is_emergency_fund`. (was item 20)
38. [ ] **Savings cards that duplicate tax actions** can sit on one list (`psa_breached` / `cash_isa_recommended` vs `isa_topup_vs_psa`, `spouse_psa_shift` vs `savings_to_spouse`, `child_no_jisa` vs `junior_isa`, `excess_cash_pension` vs `pension_tax_relief`). (was item 21)
39. [ ] **The regular saver claim ("usually pays more") has no stored rate behind it.** (was item 22)
40. [ ] **Rule 2: typed-in tax fallbacks in `PSACalculator::determineTaxBand`** (`?? 12570`, `?? 37700`, `?? 125140`). (was item 23)
41. [ ] **Typed-in tax figures on the routed `/savetax/plan/v2` and `/v3` mock-ups,** served on fynla.org (critical, 29 September tech debt). (was item 24)
42. [ ] **The old setup wizard invents a mortgage** for any property balance (`OnboardingService.php:598-613`: "Mortgage Provider", 3.5%, 5 years in, 20 left); reachable only by typing `/onboarding/full` (critical, 30 September tech debt). (was item 25)
43. [ ] **Protection still has two engines:** `RecommendationEngine` still feeds `ProtectionAgent::analyze()['recommendations']` (the plan page's own section and the rollback path). (was item 27)
44. [ ] **The tool-result depth cap hides nested rows from Fyn** (`HasAiChat::trimForModel`, depth 3). (was item 28)
45. [ ] **Fyn behaviour found under item 17a (2026-10-08), not fixed:** (was items 42 to 48)
   - "It's the same one" after a form-save duplicate question: Fyn tries to add the account again (csjones message 22568); needs one handler for the answer to the guard's question, in the walk and after it ("the same one" = nothing changes, "Already on file — …", C5). (item 42)
   - Fyn repeats its own earlier wrong replies from history: Jamie's csjones conversation 533 keeps "the plan is missing your pension contribution history"; production has 2 such replies (15-18 September, grok-4.3, conversations 877, 898). (item 43)
   - Fyn's strategy guides reach users they cannot apply to when the data reads "missing" rather than "does not apply": Jamie (£26,000) still gets the Tapered Annual Allowance and Additional-rate avoidance guides (`HouseViewApplicability` drops only `null` keys). (item 44)
   - Questions answered with a navigation or a form instead of an answer: "What information is the plan still missing before it can look at my pension?" → only "Navigating to Pensions & Retirement."; "How much do I have left over each month after my spending and pension?" → the spending form. Plan tasks T2, T3, T5 and T6 (released in ac) target this class: re-check before building. (item 45)
   - **Figure:** Fyn invents a missing pension detail: told Alex "the key missing detail is your total pension contributions for this tax year" with salary sacrifice and employer payments on file. (item 46)
   - A joint owner sees "Unlock savings info": Jamie holds the joint £24,000 account but `HouseholdFinancialContext::hasSavingsBalance` does not count the joint owner. (item 47)
   - After onboarding, a typed "Add my … £24,000 at 4%, joint" opens the savings form blank; during onboarding the same message fills it (`TypedFormFill`). (item 48)
46. [ ] **Open Found lines from the crossed-off items, never given an item of their own** (not re-checked; check each is still live first).
   - Figures and tax (Rule 23: never minor):
     - `higherRateSlice` takes the Personal Savings Allowance off the top of the slice, so with taxed interest the card stops up to £500 short of a stretch that would save 60% (tax review F4; `archive:81`).
     - Marriage Allowance received is not taken off the Income Tax shown (`incomeTaxOn` returns tax before the ITA 2007 s55B reducer); nothing records that a transfer has been made (`archive:99`, restated `archive:163`, CSJ 2026-10-04 "does not make sense").
     - `salary_sacrifice_floor_warning` triggers on the £10,000 auto-enrolment proxy, not the National Minimum Wage for the user's age and hours; needs hours captured (tax review F8; `archive:117`).
     - The /m Personal Savings Allowance insight uses a joint account's whole interest (£960) where Alex's half is £480, inside the £500 allowance (ITA 2007 s12B); Fyn's own working says it is covered (`archive:396`).
     - The bond card says the 5% withdrawal is "tax-free" (it is tax-deferred, ITTOIA 2005 s507); no `figures` on investment cards; `TaxDefaults::ISA_ALLOWANCE` fallbacks (Rule 2); Title Case titles (`archive:236`).
     - Partner top-up descriptions type "25%" for the tax-free lump sum (`NonEarnerSpousePensionStrategy.php:108`, `:199`) where config has `pension.pcls_rate`; `SpouseOptimisationService` is a second engine for the Tax Strategy card without its affordability cap or age rule (Rule 20); `SpouseHoldingTransfer` looks up the inviter's holding twice; gift writes clear caches twice (`archive:346`).
     - "No, they don't currently work" opens a form that asks no income, so a retired partner on a pension reaches the plan with none; the typed iPhone question states "around £40,000 of unused tax allowances" with no source (`fyn-onboarding.v1.md` `campaign_spouse_non_working_assets`) (`archive:332`).
     - The two retirement insight articles type in £241.30 / £12,548; `PensionProjector.php:164` falls back to £11,973 when config is missing (`archive:98`).
     - `TaxConfigurationFactory` stores rates as whole percentages (20, 18, 8.75) where seeded years store fractions; tests on the safety-net row get figures 100 times too large (`archive:166`).
   - Fyn behaviour:
     - Advice Fyn told Pat "Your State Pension record has been updated" with no write; an unbacked write claim is not caught on the output path (`archive:92`).
     - Fyn's read-back of a spending save adds up the form's answers itself (`CaptureForms::expenditureSentence`), so a homeowner who types rent hears a total that includes it (`archive:174`).
     - The /m unlock card "Date of birth is required …" sends "Help me add my pension details", and an "add" request is answered with typed questions, not a form (against CSJ 2026-10-01 "ALL capture via Fyn is done with forms") (`archive:183`).
     - Wording: the working says the threshold "was raised … by your Gift Aid and personal pension payments" when only Gift Aid raised it (`archive:397`); the duplicate question reads as a refusal ("I couldn't save …"; CSJ: ask, never refuse) (`archive:398`); "I've noted monthly spending of £1,900" with no word that it is Jamie's half of £3,800 (`archive:403`); suggested questions read 'How do I "You have no will recorded"?' (`archive:405`; T44 may remove them).
     - `PensionContributionOptimizer.php:229` says "This is free money!" (`archive:340`).
   - Retirement:
     - The other Retirement tabs and the dashboard's `years_to_retirement` keep the saver framing for someone drawing (`archive:93`; item 7 reviewed the module since).
     - A retiree with no earnings is told the Investment, Protection and Savings gates need "gross annual income" (`archive:124`).
     - The drawing view for someone retired with nothing drawn yet shows £0 income and a chart that only grows (live since #1043) (`archive:226`).
     - DECISION (CSJ): the wording for the drawing view when someone is retired with nothing drawn yet (`archive:226`).
   - Savings and Investment:
     - The Investment module keeps its own emergency fund cards beside Savings' (Rule 20; `archive:208`; item 8 reviewed the module since).
     - Someone who holds an onshore or offshore bond gets no card about its deferred tax position (`archive:251`).
     - `InvestmentActionDefinitionService::evaluateSwitchSavingsRate` filters on `comparison.rating`, which `RateComparator` never sends: the "switch savings rate" card can never fire (`archive:366`).
     - /m Expenditure lists this account's half of each shared category while the edit form shows the household's (`archive:175`).
   - Estate (item 9 reviewed the module since):
     - The web IHT screen's second-death branch reads `second_death_analysis`, which no endpoint sends (`archive:227`).
     - `IHTFormattingService::formatLiabilitiesBreakdown` projects mortgages to an assumed age 85 and reads `Liability::where('user_id')` (W-0470's remaining half) (`archive:228`).
   - Plans, billing, demo and admin:
     - The Holistic Plan page shows the orchestration's own ranked list, not the actions list (`archive:223`; see item 51).
     - Billing: a user with an active Premium entitlement and a lapsed old payment record is refused every write (`CheckSubscription::handle` reads `users.subscription` only) (`archive:173`).
     - Two old preview accounts on fynla.org are not seeder personas and have no protection profile: `admin@fps.com` (268), `preview_widow@fynla.local` (392) (`archive:216`).
     - "Mark as done" on a demo card is accepted, not saved, and looks dead (`archive:218`).
     - DECISION (CSJ): remove `TaxActionDefinitionService`? No app code calls it (tests only); its typed-in bands and "£200 conservative fallback" never reach a user (`archive:220`).
     - DECISION (CSJ): the admin AI provider choice lives only in the cache (`AiProvider.php:69-93`), so a deploy's `cache:clear` reverts to `.env` `AI_PROVIDER`. Production `.env` was set to `openai` at release aa, so production is safe today. Keep it, or store the choice where a deploy does not clear it (`archive:394`)?
     - `PipelineAiClient` (marketing pipeline) throws for `openai`; admin pages run under the Fyn side rail at 1440 (`archive:377-378`; was item 49's second half).
47. [ ] **Tech debt carried** (was items 26, 29, 30, 31, plus the 13 October handover's list):
   - 30 September (`docs/tech-debt-report.md`, session 3): `platform_fee_percent` and `indexation_rate` mappers may store fractions (`DCPensionMapper.php:39`, `InvestmentAccountMapper.php:32`, `LifeInsuranceMapper.php:38`); status lists written twice (`SpouseHoldingTransfer.php:28-31`, `TaxStrategyCalculator.php:482-483`); split `if` blocks in `AssetShiftingBundleStrategy.php:86-135`; `ANONYMISED_TABLES` does not drive the purge's Phase 6. (item 26)
   - Help audit leftovers (section 7): "A user account will be created for your spouse" (`FamilyMemberFormModal.vue:38-39`); `GET /api/user/letter-to-spouse/spouse` has no client; no input for `nrb_transferred_from_spouse`; critical illness forced to standalone (`PolicyFormModal.vue:1000`); dead components (CSJTODO). (item 29)
   - #944 review minors: web and /m card error states; clients derive `action_category` and `tax_` ids; `UNLOCK_CONSEQUENCES` lives in `ActionCardService`; about ten places compute the 6 April tax-year start separately. (item 30)
   - 29 September (`docs/tech-debt-report.md`): Stop no longer stops the server turn; `adoptLinkedSpouseWorkStatus` writes inside a skip predicate; retry reload waits a fixed 3s; the idempotency body hash includes `current_route`; the income page writes the employment total directly; four copies of the protection needs assembly. (item 31)
   - 13 October handover: `SaveAccountModal.vue:262-266` writes "£20,000" and "£9,000" into copy (Rule 2; check after T71 and T79, which touched this file); "Not recorded" lives in three places (web `utils/interestRate.js`, `PropertyDetailInline.vue` inline, /m `MortgageDetail.vue` inline) and needs one helper per surface; `OnboardingStateMachine::isSaveTaxArrival` is read by the mobile `NextActionsService` and belongs in a shared support class; the Free-limit sentence is still written once per entity in `CoordinatingAgent` (`:3136` to `:4112`); `OnboardingChatDirector.php` is about 9,800 lines.
48. [ ] **Production and CI housekeeping:** (was items 32, 33)
   - The Apple bridge is not installed on fynla.org (`route:list` fails); production `vendor/` carries dev packages. (item 32)
   - CI Unit and Feature take 20 to 25 minutes each. Split them across runners so CI is useful again (never wait on them meanwhile). (item 33)
49. [ ] **Decisions and data changes waiting on you, not tied to one task.** Work never stops on these; each is answered here.
   - Wording already live on fynla.org since release ac, never approved:
     - DECISION (CSJ): T6 "Opening <page>." and "<page> is only on the website for now."; T48 placeholder "Fyn usage limit reached — resets in 2d 4h"; T8 "You don't have a <thing> saved, so there's nothing to delete." and "Which goal did you mean: A or B?"; T9 "You're in preview mode, so I haven't sent an invitation. Create your own account to invite your spouse."
     - DECISION (CSJ): T58 chat box placeholder "Pick one of the options above"; T18's limit sentence reused outside onboarding, "You've reached your plan's limit of N … To add more, upgrade your plan."
     - DECISION (CSJ): T20 "There's a form for each of the N you told me about. I've filled in the first — check it, add anything missing and save, and the next one opens." and "Here's the next one, filled in — check it, add anything missing and save." Recorded as approved from your "Good go to T21" (2026-10-13); confirm.
     - DECISION (CSJ): T21 "Annual profit", "What the business takes in, less allowable expenses, before tax", and the two line forms.
     - DECISION (CSJ): T23 "Here are your top actions, built from what you told me — in the order I'd tackle them:", "Your actions are ready, {first_name}. Open any action on the next screen to see the steps, or ask me about it here.", "Take me to my actions", the partner invite with "actions", and the bullet suffix taken from the dashboard row ("you could save £7,080").
     - DECISION (CSJ): T24 form labels "Town or city", "Mortgage lender", "Years left on the mortgage", "Letting agent's fee each month", "Service charge each month", "Is it written in trust?", "The child's first name / date of birth", "Final salary or career average pension", the pension prompt's added sentence, and "… counts with your ISAs.".
   - Open from session 5 (2026-10-12):
     - DECISION (CSJ): keep or revert #1257 (the estate working's assumptions).
     - DECISION (CSJ): `App\Support\Money` vs `FormatsCurrency`: two formatters (Rule 20).
     - DECISION (CSJ): #1242 Child Benefit charge rounded to whole pounds (ITEPA 2003 s681C(3)) against the exact-pence rule for Fyn.
     - DECISION (CSJ): #1248 the savings-to-spouse rewrite and the "source required" loader.
     - DECISION (CSJ): T17's dropped half: "park typed facts and pre-fill later forms" was not built, because T58 locks the box at bubble steps. Agree?
   - Data model:
     - DECISION (CSJ), F11: where the spouse's date of birth is kept. On the Save Tax path the spouse's figures live on `TaxStrategyHouseholdInput`; there is no spouse family-member row until the invite.
     - DECISION (CSJ), T81: a property saved through Fyn holds its type as the first address line, so the web page reads "Address: Buy to let, Woking". T25 kept it on purpose (Fyn and the edit chooser name a property by it). Keep it, or store no address and name the property another way?
   - Production database changes (each needs your OK):
     - DECISION (CSJ): at the next release, run `pensions:backfill-dc-scheme-type` (T26; 9 of 25 occupational pensions, Priya's 529 among them)?
     - DECISION (CSJ): at the next release, run `couples:link-joint-records` (T27; property 533 among them)?
     - DECISION (CSJ): link Junior ISAs saved through Fyn before #1313 to their child by name (csjones 922, 925; production may hold some from release ac)?
     - DECISION (CSJ): set accounts saved through the web form before #1328 with 0% where no rate was given to "not recorded" (csjones 927, 928; production may hold others)?
     - DECISION (CSJ): clear the placeholders kept on production mortgages 386 and 387 and property 531 (T25)?
50. [ ] **Fyn typed memory and dense recall** (`docs/superpowers/plans/2026-09-24-fyn-typed-memory-and-dense-recall.md`, 9 tasks, run inline). Confirm D5 (OpenAI `text-embedding-3-small`, 512 dimensions) before Task 7. After Tasks 1 to 6 ship, you switch `FYN_LEARNING_ENABLED=true` on production (off since 2026-09-24). (was item 34)
51. [ ] **Demo and plan pages:** (was items 38, 40, 41)
   - Demo dashboards open blurred on the web: every demo persona but the Bennetts is seeded with setup unfinished (`PreviewUserSeeder.php:211-218`), so `onboarding_fyn_needs_start` is true; `Dashboard.vue:4-7`, `:1366-1370` blurs the page and calls `ai-chat/onboarding/start`, which a demo gets 403 for. (item 38)
   - Switching demo household signs out every other visitor to the one left: `PreviewController::switch` deletes every token of the persona, which since #1087 also removes those visitors' "Mark as done" rows. (item 40)
   - The web Holistic Plan's "Plans Included" is a second list: `loadAllPlans` per-module plans show items the actions list does not have (Mitchell: "Review fees on David's Stocks & Shares ISA", "Coordinate ISA contributions between partners"). (item 41)
52. [ ] **Whether xAI and Anthropic keep Fyn conversations by default** (CSJ: never retain data at an AI provider). (was item 49, first half)
53. [ ] **iOS (deferred; CI verifies iOS, never the laptop simulator):** Fyn forms on iOS (typed changes, adds and unlock cards still get typed questions; the advice model can answer a stated change with the prompt-injection refusal, seen locally 2026-10-05); render how-to `learn_more` links; the #967 list `meta` join ships with the next build; two native changes on `main` never seen on screen; the phone is tested by you (CSJ 2026-09-30); the remaining `deferred-ios` items; the UI-test typing helper (CSJTODO). (was item 35)
   - Also carried: iOS has no drawing view (`drawdown_position` not decoded) (`archive:186`); `ExpenditureModels.swift` decodes its own seven `categories`, not `presentation.category_rows` (`archive:176`); T23's `DeepLinkParser` `/actions` mapping runs in CI only; T6 gives native the /m navigation path (confirm with the next iOS build).
54. [ ] **Parked by you, listed so they are not lost:** in-app purchase (until App Store review); Scottish Income Tax as its own programme; the Neo4j decision; the estate residence departure date. (was item 36)
55. [ ] **Housekeeping: purge walk accounts** (keep any a remaining walk still needs until it is done). (was item 37, extended)
   - csjones, from the archive's item 37: 442, 444, 445, 446, 447, 448, 449, 450, 451, 452, 453, 454, 455, 456, 457, 458; Pat 439.
   - csjones, 9 October regression walk: Rory 504, Quinn 505, Pat 506, Alex 507, Drew 508 (three pending invitations), plus Alex 502.
   - csjones, the plan's walks (all `slaterjoneschris+…@gmail.com`, `Password1!`): Robin 509 (holds test family members, property 228 and two life policies), Priya 510, Sanjay 511; Maya, Leo (T58); Asha, Ravi (T17); Nadia, Omar, Lena, Theo (T18); Iris (T19); Priya 521, Tom 522, Ellie 523 (T20); Tunde 524 (T21, T66, T67); Amara 525, Kofi 526 (T22); Nia 527, Zain 528, Sasha 529 (T23); Ravi 530, Meera 531 (T24, T25).
   - fynla.org: Ellis 807, Morgan 808, Sam 809, Casey 810 (9 October regression walk; not recorded as purged). Release ac's Ada 824, Bea 825, Cal 826 and c.jones 811 were purged.
   - You flush the SiteGround dynamic cache for csjones.co; check the vault sync for 29 September session 3 and 30 September ran.
