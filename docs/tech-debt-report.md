# Tech Debt Report — Session 2026-10-06 (session 2)

**Files analysed:** 51 changed in #1108 and #1112 (items 8a and 8b: smoking and health one home, no made-up premiums, protection needs config); changed lines audited.
**Issues found:** 8
**Severity breakdown:** 0 critical, 4 warnings, 4 suggestions

## Warnings

- **Hardcoded source names beside configurable figures** — `app/Services/Protection/ProtectionGapPresentationService.php:152` ("SunLife Cost of Dying Report 2025"), `:178` and `app/Services/Protection/ComprehensiveProtectionPlanService.php:387` ("Legal & General's limit"). *Inconsistency.* Admin can change the final expenses amount and the income protection tiers, but the user-facing sentences still name the original source. *Fix:* print the config's own `source` (or a short `source_label` added beside it) instead of a typed-in name.
- **Spending worked out twice per person** — `CoverageGapAnalyzer::monthlyLivingCosts` (`app/Services/Protection/CoverageGapAnalyzer.php`, the new method) calls `UserProfileService::getExpenditureBreakdown()`, which itself calls `getFinancialCommitments()` (`app/Services/UserProfile/UserProfileService.php:387`), then calls `getFinancialCommitments()` again; done for the user and the partner on every protection analysis. *Complexity/performance.* *Fix:* one read of commitments, or a breakdown that returns the property lines.
- **Unused dependency** — `app/Services/Protection/RecommendationEngine.php` and `app/Services/Protection/AdequacyScorer.php` inject `TaxConfigService` and no longer read it (0 uses after the premium and critical illness changes). *Dead code.* *Fix:* drop the constructor argument and the test mocks.
- **`calculateProtectionNeeds` is long** — `app/Services/Protection/CoverageGapAnalyzer.php` (about 190 lines; file 654 lines). *Complexity.* It assembles income, the partner, living costs, the term, four needs and state benefits. *Fix:* extract the partner block and the state benefits block.

## Suggestions

- **Repeated number trimming** — `rtrim(rtrim(number_format(...), '0'), '.')` in `ProtectionGapPresentationService.php:224`, `:226`, `CoverageGapAnalyzer.php:573`, `:608`, `ProtectionCoverPosition.php:67`. *Duplication.* *Fix:* one helper (for example on `FormatsCurrency`).
- **Unused parameter** — `LifePolicyStrategyService::calculateStrategy(?User $user = null)` (`app/Services/Estate/LifePolicyStrategyService.php:35`) no longer reads `$user`. *Fix:* drop it.
- **Labels in two languages** — `ProfileEnums::SMOKING_STATUS_LABELS` / `HEALTH_STATUS_LABELS` (PHP) and `resources/js/constants/profileOptions.js` hold the same words; only the keys are pinned (`tests/Unit/Database/ProfileEnumColumnsTest.php:77-78`). *Duplication.* *Fix:* a parity spec like the education labels have.
- **`TaxSettings.vue` is 3,157 lines** — `resources/js/components/Admin/TaxSettings.vue`. Pre-existing; this session added the protection needs section (about 90 lines). *Fix:* split per tab when next touched.

---

# Tech Debt Report — Session 2026-10-06

**Files analysed:** 36 changed in #1087–#1104 today (item 7a step 4 merges, item 8 review, found-line fixes); changed lines audited, not every whole file.
**Issues found:** 7
**Severity breakdown:** 0 critical, 4 warnings, 3 suggestions

## Warnings

- **Evaluators kept for disabled definitions** — Category 2. `app/Services/Investment/InvestmentActionDefinitionService.php` (1,741 lines) still holds the evaluators for the 12 definitions disabled today (`low_diversification`, the three fee rules, `rebalance_portfolio`, the seven savings and surplus rules). They only run if an admin re-enables a row, and three still carry `TaxDefaults` fallbacks (`evaluateIsaAllowanceRemaining`, `evaluateSurplusToPension`, `evaluateSurplusToBond`; Rule 2). Fix: delete the evaluators and their dispatch lines, keep the disabled rows' notes.
- **A second portfolio drift rule is still computed** — Category 6 (Rule 20). `InvestmentAgent.php:188` computes `allocation_deviation` with `SimpleAssetAllocationOptimizer::calculateDeviation` only so `evaluateRiskProfileMissing` (`InvestmentActionDefinitionService.php:183`) can tell whether a risk profile exists, and for the disabled `rebalance_portfolio`. `AccountDriftService` is the one rule now. Fix: test the risk profile directly and drop the portfolio deviation (keep `PensionPortfolioAnalyzer`'s own use, or move it to `DriftAnalyzer` too).
- **Magic keys in a parameter** — Category 4. `DiversificationAnalyzer::generateRecommendations` reads `_assessed` and `_unrecorded_percent` smuggled inside `$comparison` (`:365`, `:418`). Fix: two explicit parameters.
- **Drift labels written twice on the client** — Category 1. `getDriftLabel` / `getDriftStatusClass` / `getDriftBgClass` are the same in `InvestmentProjections.vue:1066` and `AccountPerformancePanel.vue:774`. Fix: the server sends the label with `drift_analysis` (one figure, every surface), or one shared helper.

## Suggestions

- **Two record lists in `RecordEditForms`** — Category 1. `CONTEXTUAL_FORMS` and the new `RECORD_RESOURCES` both decide which resource opens a form. Fix: one map.
- **Carried from 2026-10-05:** a model call before every non-question advice message (`AdviceFyn::offerTypedChangeForm`); `<security>` written twice (`FynSystemPrompt`, `CoreIdentity`).
- **Carried:** `OnboardingChatDirector.php` is about 9,370 lines.

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-05 (session 4)

**Files analysed:** changed lines in #1084 (cleanup of #1082) and #1085 (`92e2b9302`, `fb185bd8d`).
**Issues found:** 4 open (the session 3 warnings on the twice-written filled-form line, the repeated empty-form block, the double read after a choice, `offerTypedChangeIn`'s seven parameters and "add my monthly spending" opening a blank form were RESOLVED by #1084)
**Severity breakdown:** 0 critical, 2 warnings, 2 suggestions

## Warnings

- **A model call before every non-question advice message** — Category 4 (carried from session 3). `AdviceFyn::offerTypedChangeForm` calls `OnboardingChatDirector::offerTypedForm` over every saved record for any non-question message from a forms client ("thanks" included), about 1 to 2 seconds on csjones. Fix if it shows: skip messages with no figure, date or record noun, or reuse the planner's classification.
- **Security rules written twice** — Category 6 (Rule 20, carried). `FynSystemPrompt` and the legacy `CoreIdentity` each hold `<security>`; rule 6 has drifted (the legacy copy lacks "It NEVER applies to a message that answers a question you asked"). Fix: `FynSystemPrompt::SECURITY`, composed by both.

## Suggestions

- **Walk copy on a blank form outside the walk** — Category 6. The investment form's `kinds_prompt` ("If you have no investments, save with none chosen", `CaptureForms::investment()`) shows on an Add button's blank form, where an empty save is refused (`OnboardingChatDirector::EMPTY_FORM_LINE`). Fix: no `allow_empty` copy when the form has no step.
- **`OnboardingChatDirector.php` is about 9,350 lines** — Category 4 (carried). The typed-form door (`offerTypedForm`, `handleCreateFormTurn`, `createFormOffered`, `chooserFills`, `emitFormProblem`) could move to its own service.

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-05 (session 3)

**Files analysed:** 20 files changed in #1082 (`25b70ae29`..`38e05f938`); changed lines audited, not every whole file.
**Issues found:** 8
**Severity breakdown:** 0 critical, 4 warnings, 4 suggestions

## Warnings

- **The filled-form line is written twice** — Category 1. `OnboardingChatDirector.php:1229` (setup step) and `:7631` (`emitCreateForm`) both type "I've filled in what you told me — check it, add anything missing and save." Fix: one `CaptureForms` constant beside `ADD_PROMPT`.
- **"Fill in at least one before saving." block repeated** — Category 1. `handleFormTurn` and `handleCreateFormTurn` each build the same `capture_form_errors` + content + saved message for an empty form. Fix: one `emitEmptyFormError(conversation, formName, ?stepId)`.
- **A model call before every non-question advice message** — Category 4. `AdviceFyn` calls `offerTypedChangeAnywhere` for any non-question message from a client that draws forms ("thanks" included): about 1 to 2 seconds on csjones, one xAI call each. Fix if it shows in latency: skip messages with no figure, date or record noun, or reuse the turn planner's classification.
- **The record form is read twice after a choice** — Category 4. `offerTypedChangeIn` reads every record's form, and when several match, `handleAction` → `emitEditForm` reads the chosen one again (`chooserTypedChange`). Fix: keep the filled answers on the chooser row's metadata and open them directly.

## Suggestions

- **Security rules written twice** — Category 6 (Rule 20). `FynSystemPrompt` and the legacy `CoreIdentity` each hold the `<security>` block, and rule 6 has drifted (the legacy copy lacks "It NEVER applies to a message that answers a question you asked"). Tried and reverted this session to keep #1082 focused. Fix: `FynSystemPrompt::SECURITY`, composed by both, as `PERSONALITY` already is.
- **`offerTypedChangeIn` takes seven parameters, two of them booleans** (`OnboardingChatDirector.php:7569`) — Category 4. Fix: split into `offerChange(sections)` and `offerAdd(section, createForm)` sharing a private reader.
- **"Add my monthly spending" with spending on file opens the blank spending form, not the edit form** — Category 6. `offerTypedChangeIn` prefers the blank form when nothing is filled. The save overwrites correctly, but the user does not see the current figures. Fix: prefer the user-held record's edit form for single-record sections.
- **`OnboardingChatDirector.php` is now 9,402 lines** (about 300 added) — Category 4. The typed-form entry points (`offerTypedChangeAnywhere`, `offerTypedChangeIn`, `emitCreateForm`, `handleCreateFormTurn`, `createFormOffered`, `chooserTypedChange`) could move to a `TypedFormOffers` service.

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-05 (session 2)

**Files analysed:** 17 code files changed in #1069, #1070, #1072, #1074, #1076, #1078 (`216c52120`..`7eee75005`); changed lines audited, not every whole file.
**Issues found:** 8
**Severity breakdown:** 0 critical, 3 warnings, 5 suggestions

## Warnings

- **Spending category list written in four places** — Category 1 (Rule 20). `UserProfileService::CATEGORY_FIELDS` (the one sum), `CoordinatingAgent::handleSetExpenditure` `$categoryFields` (the writable list, no `regular_savings`), `RecordEditForms::expenditureAnswers` (`SHARED_FIELDS` + rent, utilities, charitable donations) and the web `ExpenditureForm.vue` field arrays (`allEssentialFields`, `communicationFields`, … `otherFields`). Fix: one constant (`SharedExpenditure` or `UserProfileService::CATEGORY_FIELDS`) that the agent and the edit form read; the web form's lists come from the server.
- **`app/Services/Onboarding/TypedFormFill.php` `extract()` repeats `ProposedFactSynthesiser::synthesise`'s xAI JSON-mode call** (endpoint constant, bearer header, `response_format`, decode of `choices[0].message.content`) — Category 1. Fix: one small client method (`XaiJson::complete($system, $user, $maxTokens)`) both use.
- **Web `ExpenditureForm.vue` still adds up section and spouse totals in the browser** (`essentialTotal` … `householdTotalMonthlyExpenditure`, ~`:1560-1600`); only the user's entered and grand totals now show the server figure in view mode (#1072) — Category 6 (one figure, every surface). Fix: the server sends section and household totals (`presentation`), the view renders them.

## Suggestions

- **`app/Services/AI/Fyn/CertaintyFilter.php` now holds two rules** (certainty, and false save claims in advice) under a name that says one — Category 3 (naming). Fix: rename to `FynOutputFilter` with its two pattern sets, callers in `HasAiChat` and the tests.
- **`app/Services/UserProfile/UserProfileService.php` `categorySpendingRows()` reads labels from `CaptureForms` (Onboarding) and types `'Regular savings'` itself** — Category 6 (coupling). Fix: the labels live with the category list (see the first warning).
- **`app/Agents/CoordinatingAgent.php` `handleSetExpenditure` resolves `app(UserProfileService::class)`** although the class is imported for other uses — Category 6. Fix: constructor injection, as the agent's other services.
- **An "Edit details" typed change builds the record's form twice per turn** (`AdviceFyn::offerTypedChangeForm` → `RecordEditForms::formForResource`, then `OnboardingChatDirector::offerTypedChangeForm` → `formFor`) — Category 4. Fix: pass the built form into the director.
- **`CertaintyFilter::WRITE_CLAIM_PATTERNS` is phrase matching** and was widened once already on csjones ("is now recorded as") — Category 4 (known limit, not a defect in itself). Fix: the forms route for unrouted typed changes (TODO 7a) removes the need; until then keep cases in `WriteClaimFilterTest`.

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-03/04 (session 2)

**Files analysed:** 32 code files changed in #1063 and #1065 (`c0ad4e42f`..`95e9ba28b`); changed lines audited, not every whole file.
**Issues found:** 8
**Severity breakdown:** 0 critical, 4 warnings, 4 suggestions

## Warnings

- **`app/Services/UKTaxCalculator.php:675` `pounds()` duplicates `app/Services/Onboarding/CaptureForms.php:698` `pounds()`** — Category 1. Both format whole pounds without pence and pence otherwise; `App\Traits\FormatsCurrency` (`formatCurrency`, `formatCurrencyWithPence`) is the existing home. Fix: one helper (the trait, or a shared static) used by both.
- **`app/Services/AI/AdvicePromptBuilder.php` (income block) calls `UserProfileService::incomeAndTaxFor` on every Fyn turn** — Category 4. That builds the whole Income tab (expenditure breakdown, Child Benefit, rental breakdown, detailed tax) to read three figures. Fix: a lean `incomeTaxNationalInsuranceAndTakeHome(User)` on `UserProfileService` that stops after the tax engine, or cache per request.
- **`app/Agents/CoordinatingAgent.php` `handleUpdateProfile` `income_occupation`** — Category 1/6. The income field list is written twice (the allowlist and the numeric-rules loop, both extended this session), and the handler writes `annual_employment_income` / `annual_self_employment_income` directly although `EmploymentIncomeService` maintains those totals from jobs (pre-existing; the other-income form never sends them). Fix: one constant for the income fields; route employment edits through the job store.
- **`app/Services/Onboarding/RecordEditForms.php` `OTHER_INCOME_SOURCES`** and **`app/Http/Requests/AI/CreateContextualConversationRequest.php:374` income source allowlist** — Category 1. Two lists of income source keys that must agree with `UserProfileService::incomeSources`. Fix: one constant the request and the edit forms both read.

## Suggestions

- **`app/Services/TaxBandTracker.php:162` `getCurrentBandPosition()`** — Category 2. Its only caller (`UKTaxCalculator::getBeneficiaryMarginalRate`) was removed with the trust fix; now unused. Fix: delete it.
- **`app/Services/UserProfile/UserProfileService.php` `totalGrossAnnualIncome()`** — Category 3 (naming). It now returns recorded income for the first-income award (estimated interest left out), not the gross total its name says. Fix: rename to `recordedIncomeForAward` with its two callers (`updateIncomeOccupation`, `GamificationBackfill:79`).
- **`app/Traits/HasAiChat.php` (four text-yield sites)** — Category 1. The round-separator block is repeated at each of the four places text is streamed (xAI text, xAI tail, Anthropic text, Anthropic tail). Fix: a small generator helper `emitText(&$fullResponse, &$iterationText, &$streamed, $text)`.
- **`app/Services/AI/ContextualConversation/ContextualConversationService.php` `create()`** — Category 6. A resource-specific branch (`income` with a recommendation keeps its own opening) sits in the generic service. Fix: let `RecordEditForms::formForResource` take the origin and decide.

Found, not code debt (on the list): `TaxConfigurationFactory` stores band, Capital Gains Tax and dividend rates as whole percentages where seeded years store fractions.

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-03

**Files analysed:** 34 code files changed in #1060 (`cd2958251`..`e10f79853`); changed lines audited, not every whole file.
**Issues found:** 7
**Severity breakdown:** 0 critical, 4 warnings, 3 suggestions

## Warnings

- **Unagreed design choices shipped to dev (Rule 16)** — Inconsistency. Not decided by CSJ: the web Income edit form no longer shows a total (`resources/js/components/UserProfile/IncomeOccupation.vue`, comment above the action buttons); the "worked out from the savings accounts" wording (`UserProfileService.php` incomeSources, `IncomeDefinitionsPanel.vue` componentLabel); the Blind Person's Allowance now lifts the higher-rate limit in the detailed calculator (`app/Services/TaxBandTracker.php` constructor); part-year Class 1 counted by paydays, else days (`UserProfileService::class1Share`). Fix: CSJ confirms or each is reverted.
- **`HouseholdCashFlowProjector::statePensionAgeFor` duplicates the resolver** (`app/Services/Estate/HouseholdCashFlowProjector.php:613-626`) — Duplicate code. Re-implements `StatePensionAgeResolver::forUser` (recorded age first) and calls `forDateOfBirth` without gender, so a woman born before 6 Dec 1953 gets the men's 65 for the int age. Fix: call `forUser($member)`.
- **Fyn income edit is phrase-gated, not a form** (`app/Services/Onboarding/OnboardingChatDirector.php:5768-5778` `verifyEditProfileFields`; `FynVerifyEditTurnInstructions.php:17`) — Inconsistency with CSJ 2026-10-01 "all Fyn capture through forms, never phrase matching". Dividends and other income on /m are only editable when the message contains the listed words; trust income and a typed interest figure have no Fyn path. Fix: a form, per the ruling.
- **iOS cannot render Fyn capture forms** (`AiChatController.php:1017` reads `X-Fynla-Forms`; `ios-native/Fynla/Features/Fyn/FynEvent.swift` has no `capture_form` case) — Inconsistency (Rule 20, all surfaces). Native gets typed questions. Deferred with iOS (TODO 35).

## Suggestions

- **Stale comments on interest caching** (`app/Services/Tax/TaxStrategyMath.php:25`, `:36`) — still say `taxableIncomeFor` fires a SavingsAccount query via `estimateAnnualInterest`; that query now runs in `IncomeDefinitionsService::interestIncome`. Fix: reword.
- **Repeated user lookup** (`app/Services/Retirement/RetirementActionDefinitionService.php:2230`, `:2239`) — `labelForUser(User::findOrFail($userId))` twice; `$spa` already holds the label. Fix: use `$spa` at `:2239`.
- **Dead/unused display fields on the web Income form** (`IncomeOccupation.vue` `form.annual_rental_income` / `annual_pension_income`, lines ~277, ~328) — read-only rows in edit mode still read the form copy rather than the server parts. Fix: read `incomeOccupation.income_parts`.

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-02

**Files analysed:** 54 code files in app/ and database/seeders (main `594781913` .. dev `1a3602a12`: #1048, #1050, #1051, #1052, #1054); changed lines audited, not every whole file.
**Issues found:** 6
**Severity breakdown:** 0 critical, 4 warnings, 2 suggestions

## Warnings

- **`app/Services/Goals/GoalAffordabilityService.php:23`, `app/Services/Goals/GoalsProjectionService.php`, `app/Services/Goals/FinancialForecastService.php`, `app/Services/Investment/Recommendation/UserContextBuilder.php:38`** (Dead code). Each still injects `UKTaxCalculator $taxCalculator`, used only by the old `ResolvesIncome::resolveNetAnnualIncome` sum, which now reads the Income tab's figure. Fix: drop the constructor dependency (and the container wiring if any).
- **`app/Services/Coordination/HouseholdPlanningService.php:613`, `:637`; `app/Services/Investment/Recommendation/UserContextBuilder.php:356`; `app/Services/Tax/TaxOptimisationService.php:474`** (Duplicate code, Rule 20). Own `determineTaxBand` / `getMarginalRate` copies remain. Their income input is now the Income page's figure, but the band rule is not `TaxStrategyMath::incomeTaxBandFor`. Fix: read the one band.
- **`app/Services/Tax/TaxActionDefinitionService.php`** (Dead code). Called by no app code (tests only); carries typed-in 20/40/45 (`:329-350`), `?? 12570` fallbacks and an invented `£200` "conservative fallback" (`:200`). Fix: CSJ's call to remove it with its tests.
- **`app/Services/Mobile/PlanningProgressService.php` `totalIncome`** (Complexity). The percentile now builds the Income page figure for every user on a cache miss (about 5 ms of 57 ms per user locally; 1 hour cache). Fine at today's user count; watch it.

## Suggestions

- **`app/Agents/InvestmentAgent.php`, `app/Traits/HasAiChat.php`** (Dead code). `use Illuminate\Support\Facades\Cache;` unused (it was before today too). Fix: remove.
- **`app/Services/UserProfile/UserProfileService.php` (1,474 lines), `app/Agents/CoordinatingAgent.php` (7,107), `app/Traits/HasAiChat.php` (2,336)** (Complexity). Long files grew slightly today; no split proposed (CaptureForms precedent: not for length alone).

---
*Generated by tech-debt-session skill*

# Tech Debt Report — Session 2026-10-01 (session 4)

**Files analysed:** 9 code files (commits `6e05b029d`..`d02203cf0`, branch `feat/retirement-decumulation-and-care-costs`)
**Issues found:** 4
**Severity breakdown:** 0 critical, 2 warnings, 2 suggestions

## Warnings

1. **`app/Services/AI/WriteIntentClassifier.php` (care-cost keywords, "I plan for" verbs, preceding-sentence offer match) and `app/Services/Onboarding/OnboardingChatDirector.php` (`inferFocusesFromEntityTypes`: `state_pension`, `retirement_goals` → retirement)** — Category 6. Patches on the wrong layer: Fyn records data through `CaptureForms`/`RecordEditForms` forms, and care costs have no form. Once the retirement goals form exists (TODO item 7, "NEXT"), check whether either patch is still needed and revert what the form makes redundant.
2. **`resources/js/store/modules/aiChat.js:725` and `:1002`** — Category 1. The consent-withdrawn sentence is now written twice in the store (and a third time in `resources/mobile/mixins/onboardingChat.js:534`). Hoist it to one constant per bundle.

## Suggestions

3. **`app/Models/StatePension.php` `getNiYearsForFullPensionAttribute`** — Category 2. The tax-config fallback never runs: `state_pensions.ni_years_required` is `NOT NULL DEFAULT 35`. Either make the column nullable (then the fallback is live) or drop the fallback. Recorded under TODO item 7a.
4. **`tests/Feature/Fyn/InlineCaptureFlowTest.php` (new "retirement capture" case)** — Category 1. Duplicates the mock set-up of the "every captureable entity type" case above it; extract a shared helper if a third case is added.

---
*Generated by tech-debt-session skill*

---

# Tech Debt Report — Session 2026-10-01 (session 2)

**Files analysed:** 28 code files (#1040 released as #1041; #1042 released as #1043)
**Issues found:** 9
**Severity breakdown:** 0 critical, 5 warnings, 4 suggestions

## Warnings

- **Two ways to price the user's own Income Tax** (`app/Services/Tax/TaxStrategyMath.php`: `incomeTaxNow` against the new `incomeTaxLiability`). Category 6.
  - **What:** `incomeTaxNow` goes through `pricingPartsFor`, which treats Gift Aid and relief at source as net-pay deductions. `incomeTaxLiability` treats them as band extensions, which is the law. The Retirement income card uses the second; the tax plan's "your tax falls from … to …" lines and every pension saving use the first. A donor sees two different tax figures.
  - **Fix:** move `pricingPartsFor` callers onto the band extension. This is the same gap as session 1's warning and TODO item 2's Found line.
- **`app/Services/Retirement/RetirementDrawdownPosition.php` `incomeLastingTo`** (Category 4: performance).
  - **What:** about 11 Monte Carlo runs (1,000 iterations × up to 30 years) by bisection on the first load of `/api/retirement/projections` for anyone drawing (~1.5s locally). Each run is cached by fingerprint.
  - **Fix:** if the first load is slow on SiteGround, search on fewer iterations or derive the figure from one simulation's path set.
- **The State Pension status labels are worked out on each client** (`RetirementDrawingView.vue` `statePensionNote` and `resources/mobile/views/modules/Retirement.vue` `statePensionNote`). Category 1, Rule 20.
  - **What:** the same `missing` / `not_paid` / `no_amount` → text map is copied on web and `/m`.
  - **Fix:** serve the line's label and action from `RetirementDrawdownPosition` and have both clients render it.
- **`app/Services/Actions/ActionHowToFacts.php` `payrollCanCarryPerMonth`** (Category 4).
  - **What:** each pension how-to render calls `PensionAffordability::moneyThisYear` (`CompositePlanService::financials`) through `app()`. The tax plan has already worked that money out (`TaxStrategyContext::pensionMoney`), so the card page computes it again.
  - **Fix:** publish the money (or the payroll per month) on the plan item and read it there.
- **`resources/js/components/NetWorth/PensionList.vue`** (Category 4).
  - **What:** now about 2,070 lines, with the drawing branch added beside the saver branch.
  - **Fix:** move the saver projection block into its own component, as `RetirementDrawingView` is.

## Suggestions

- **`RetirementDrawdownPosition::nationalInsurance`:** the State Pension date is date of birth + whole-year State Pension age (`StatePensionAgeResolver::forUser` returns an int). For the 1960–61 cohorts, whose State Pension age has a months part, the cut-over can be a few months out.
- **`RetirementDrawdownPosition::income`:** the lines read `IncomeDefinitionsService` components, but interest comes from `TaxStrategyMath::incomePartsFor`. The test pins that the total matches the parts the tax uses. A single source (`incomePartsFor` plus the component split) would remove the mix.
- **`app/Services/Onboarding/RecordEditForms.php` `find('state_pension', $id)`:** `$id` is ignored (one per user). This is correct today, but the signature suggests otherwise.
- **`resources/js/constants/lifeStageConfig.js`, `GlossaryPage.vue`, `CampaignPage.vue`:** each formats `STATE_PENSION_*` with its own `toLocaleString` / `toFixed`. A `formatStatePensionRate()` helper beside `taxConfig.js` would keep them in step.

---
*Generated by tech-debt-session skill*

# Carried forward — Session 2026-10-01 (session 1)

**Files analysed:** 31 (released: #1031 as #1032; #1033 as #1034; #1036 and #1038 as #1037 and #1039)
**Issues found:** 8
**Severity breakdown:** 0 critical, 4 warnings, 4 suggestions

## Warnings

- **`app/Services/Tax/TaxStrategyMath.php:105` and `:1089`** (Category 1: duplicate code).
  - **What:** `bandThresholdsFor()` and the new `bandExtensionFor()` both sum `gift_aid_gross` and `relief_at_source_gross`.
  - **Fix:** `bandThresholdsFor()` calls `bandExtensionFor()`.
- **`app/Services/Tax/TaxStrategyMath.php` `marriageAllowance` against `savingsMoveToPartner`** (Category 6: two ways to price one income).
  - **What:** Marriage Allowance prices through `incomeTaxWithBandExtension` (`UKTaxCalculator::calculateNetIncome`, Gift Aid and relief at source as a band extension). The savings move prices through `pricingPartsFor` + `incomeTaxOn` (`calculateDetailedNetIncome`), which treats those contributions as net-pay deductions clipped at employment income. A donor with little pay is mispriced in the savings move and in every pension saving.
  - **Fix:** one pricing helper; move `pricingPartsFor` callers onto the band extension. The same gap is recorded as a Found line under TODO item 2.
- **`app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php` `savingsMove`, `JointSavingsStrategy`, `TaxStrategyCalculator::repriceSavingsAfterPension`** (Category 4: performance).
  - **What:** one dashboard request runs `savingsMoveToPartner` up to five times: a search plus an exact price, twice because of the pension re-pricing, and once for the joint split. Each search is up to 1,000 steps × 2 engine calls (~0.03 ms each).
  - **Fix:** cache the search per (user, pensionPaid) in the request, or have the strategy return the best interest so the re-pricing only prices the exact amount.
- **`app/Services/Coordination/HouseholdFinancialContext.php` `spouseSavingsKnown`** (Category 4).
  - **What:** each `availability()` call now queries `TaxStrategyHouseholdInput`, resolves `partnerTaxPosition` (which may read the linked spouse's `incomePartsFor`), and prices `interestRemovalSaving`.
  - **Fix:** pass the household row and mode in from the composer, which already holds them.

## Suggestions

- **`app/Services/Tax/TaxStrategyMath.php` `soleNonIsaSavings` docblock** (Category 3): a broken line wrap ("null co-owner. Interest from each / account's own rate."). Reflow it.
- **`app/Services/Tax/TaxOptimisationService.php:425-426`** (Category 2): `$higherEarner` and `$lowerEarner` are assigned and never read. This predates the session, but the function was edited this session.
- **`ios-native/Fynla/Features/Dashboard/FocusAreasView.swift` `topHalf`, and the commented blocks in `resources/js/views/GamifiedDashboard.vue` and `resources/mobile/views/Dashboard.vue`** (Category 2): unused or commented code, kept on purpose (CSJ 2026-10-01: "comment out, so do not remove in case we need to reverse this"). Listed only so a future sweep does not delete it.
- **`users.marriage_allowance_eligible`** (Category 2): still written by `FunnelAnswersMapper` and two capture handlers, and read by nothing since #1031 stopped echoing it to Fyn. A candidate to drop with a migration (Found under TODO item 2).

---
*Generated by tech-debt-session skill*

# Carried forward — Session 2026-09-30 (session 4)

**Files analysed:** 18 (released: #1022-#1024 in #1025; #1026, #1027 in #1028; #1029 in #1030)
**Issues found:** 8
**Severity breakdown:** 0 critical, 5 warnings, 3 suggestions

## Warnings

- **`app/Services/Tax/TaxStrategyCalculator.php:68` and `app/Services/Tax/TaxStrategyService.php:57-61`**: Category 4 (performance). One dashboard request works out the affordability figure twice: once in the calculator and again for the tile. That means two `CompositePlanService::financials()` calls, each building the profile. And when no spending is recorded, the tile calls `financials()` a third time. Fix: have the calculator return the money it used (for example on `TaxStrategyOutputDTO`) and let the tile read it.
- **`app/Services/Coordination/HouseholdFinancialContext.php:66-67`**: Category 4 (performance). `availability()` now resolves `PensionAffordability` twice. It reads the expenditure breakdown, and for anyone who is retired or unemployed it also reads `incomePartsFor` (IncomeDefinitionsService), on every composer call. Fix: resolve once, and check `fundedFromCash` only when spending is not recorded (it already short-circuits on `||`, so only the double resolution remains).
- **`app/Services/Tax/Strategies/PensionTaxReliefStrategy.php:60` and `app/Services/Tax/Strategies/IncomeBandStrategy.php:54`**: Category 1. The same cap block (read `tax_relief.basic_rate`, `pensionFundableGross`, `min($availableAA, …)`) is written twice. Fix: a `TaxStrategyContext::capToFundable(float $availableAA, float $basicRelief)`, or keep the basic relief rate on the context so strategies call `$context->pensionFundableGross()` with no argument.
- **`resources/js/components/TaxStrategy/AllowanceCard.vue:72-84` and `resources/mobile/views/TaxStrategy.vue:232-239`**: Category 6 (Rule 20). Each client works out the allowance status label ("Fully used", "£0 of headroom" / "£0 available", "Not available"…) on its own, so today's fix had to be made twice. iOS was not checked. Fix: the server sends a `remaining_label` on each position (`TaxStrategyCalculator::position`) and every client renders it.
- **`app/Services/Tax/PensionAffordability.php:51, 68`; `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php:52`; `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php:83`; `app/Services/Onboarding/CaptureForms.php:922`; `app/Services/Onboarding/OnboardingStateMachine.php:2648`**: Category 6. New `app()` service-locator calls, some added to avoid constructor cycles (CompositePlanService sits above the tax plan). Fix: inject where no cycle exists (the strategies can take `HouseholdFinancialContext` in the constructor). Keep the lazy resolution only in `PensionAffordability`, with its comment.

## Suggestions

- **`app/Services/Onboarding/CaptureForms.php:120, 279, 1291`**: Category 2. `expenditureTax()` / `EXPENDITURE_TAX` is no longer offered (#1027). It is kept only so a conversation already sitting on that step can save it. Remove it after a few weeks, once no onboarding conversation's last form is `expenditure_tax`.
- **`app/Services/Onboarding/OnboardingChatDirector.php:3514-3519`**: Category 6. The linked-partner retry text is a state-specific branch in `emitRetry`, beside the prompt builder in `OnboardingStateMachine` (#1024). Two places hold the linked-partner wording rule. Fix: let a state's `retry_text` be a builder, as `prompt_text` is, and move the branch into the state machine.
- **`tests/Unit/Services/Tax/PartnerPensionAffordabilityTest.php:103`**: Category 3 (Rule 2, in a test). It asserts `* 720 / 2880`, typed-in figures. Fix: read `TaxStrategyMath::nonEarnerPensionContribution()` for `relief` and `net`.

---

# Carried forward — Session 2026-09-30 (session 3)

**Files analysed:** 18 (merged to `dev`: #1016, #1018-#1021; open PR #1022)
**Issues found:** 7
**Severity breakdown:** 1 critical, 4 warnings, 2 suggestions

## Critical Issues

- **`app/Services/Onboarding/OnboardingService.php:598-613`** — Rule 23 (unsourced figures). The old setup wizard (`/onboarding/full`) creates a mortgage for any property with a balance, with an invented lender ("Mortgage Provider"), a 3.5% rate, a start 5 years ago and 20 years left, and computes a monthly payment from them. The rate is also stored as `0.0350` in a percentage column (0.035%). Only reachable by typing the URL (its one link, `ProfileCompletionCards`, is unrendered). Fix: store only the balance the user gave, or retire the wizard's asset step; CSJ to decide which.

## Warnings

- **`app/Services/Documents/FieldMappers/DCPensionMapper.php:39`, `InvestmentAccountMapper.php:32`, `LifeInsuranceMapper.php:38`** — Category 6. `platform_fee_percent` and `indexation_rate` still go through `parsePercentage()`, which returns a fraction. #1018 found the same mapper storing fractions into percentage columns for savings and mortgage rates. Verify each column's convention (readers, validation) and move the percentage ones to `parseRatePercent()`.
- **`app/Services/Onboarding/SpouseHoldingTransfer.php:28-31` and `app/Services/Tax/TaxStrategyCalculator.php:482-483`** — Category 1. The working and not-working status lists are written out twice. One public constant (or a small value class) both read.
- **`app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:86-135`** — Category 4. `$stackedCapacity`, `$psaBasic`, `$reportedTransfer`, `$taxableInterestSheltered` are assigned inside the first `if` and read inside the second, which is safe only because `$estimatedAnnualTaxSaved >= 1` implies the first ran. Merge the two blocks into one `if` with an early skip.
- **`app/Services/Account/RetentionPurgeService.php:27, 98, 101`** — Category 1. `ANONYMISED_TABLES` names `audit_logs` and `ai_cost_attribution`, and Phase 6 anonymises each with its own hardcoded query. Adding a table to the constant does not anonymise it. Drive the phase from a map of table => columns to null.

## Suggestions

- **`app/Services/Onboarding/WalkFormPrefill.php:120-121`** — Category 4. `recordsFor()` loads the user's savings and pensions on every call, whatever the form. Load them lazily inside the `match` arms that need them.
- **`app/Services/Tax/TaxStrategyCalculator.php`** (646 lines) — Category 4. Over 500 lines; the allowance-grid builders (`buildUserAllowanceGrid`, the three spouse grids, `stackInterest`, `pensionPosition`, `spousePensionPosition`) are a natural `AllowanceGridBuilder` extraction.

---

# Carried forward — Session 2026-09-30 (session 2)


**Files analysed:** 17 (all merged to `dev`: #1009, #1010, #1011, #1013, #1014)
**Issues found:** 7
**Severity breakdown:** 0 critical, 4 warnings, 3 suggestions

## Critical Issues

None in the changed files. (The Retirement page for already-retired users is a product defect in unchanged files; it is on `CSJTODO.md`, not here.)

## Warnings

1. **`resources/js/utils/dateFormatter.js:18, 51, 79, 109, 126, 203`**: Inconsistency (Category 6).
   - **Problem:** `formatDate`, `formatDateForInput` for full timestamps, `parseDate`, `formatDateLong` and the helper at 203 all turn a `YYYY-MM-DD` string into a `Date` with `new Date(string)`. That is UTC midnight, so west of Greenwich the date shows a day early.
   - **Scope:** this session fixed only the date-of-birth path (`formatDateOnlyLong`, plus the date-only pass-through in `formatDateForInput`). `formatDateLong` alone has 16 callers.
   - **Fix:** read the calendar day from the string, as `formatDateOnlyLong` does, inside `parseDate`, and route the others through it.

2. **`app/Agents/CoordinatingAgent.php:6576-6600`** with the create path at `:3433`: two homes for one figure (Category 6).
   - **Problem:** `users.annual_dividend_income` is a running total maintained only by Fyn's create and edit tools. The web investment account edit (HTTP controller → `InvestmentAccountStore::update`) changes an account's dividends without moving the user's total, and deleting an account never subtracts it.
   - **Fix:** derive the taxable dividend total from the non-ISA accounts in one place, and stop keeping a running sum.

3. **`resources/js/components/UserProfile/PersonalInformation.vue:731`** against **`resources/js/components/Onboarding/ProfileReviewPanel.vue:88-89`**: duplicate label maps (Category 1).
   - **Problem:** the two employment-status maps disagree ("Full-Time" against "Full-time"). `ProfileReviewPanel` also labels `employed` as "Full-time". This was carried from session 1.
   - **Fix:** one shared map in `constants/profileOptions`.

4. **csjones `public/.htaccess`** (deploy, not code): fragility (Category 6).
   - **Problem:** the checkout's local subdirectory edit is lost to any `git reset --hard` or `checkout -f`, and the proxy cache then keeps the wrong site. `skip-worktree` cannot protect it because the checkout is sparse.
   - **Fix:** point the sparse checkout at `deploy/csjones-fynla/.htaccess` through a server-side symlink, or keep csjones's `.htaccess` outside git management. Memory `feedback_never_reset_hard_on_csjones` holds meanwhile.

## Suggestions

1. **`app/Services/Tax/TaxStrategyMath.php:558-560, 625-631`**: efficiency (Category 4).
   - **Problem:** `marriageAllowance` calls `linkedSpouseWithIncome`, which computes `incomePartsFor($linked)`, then computes it again on line 560, so income definitions are built twice per call.
   - **Fix:** return the parts from the helper, or memoise per user id.

2. **`app/Services/Onboarding/WalkFormPrefill.php:152-158`**: efficiency (Category 4).
   - **Problem:** `formSaved` loads every user message's metadata in the conversation, on each form emission.
   - **Fix:** a JSON `where` on `metadata->form->name` with `exists()`.

3. **`app/Services/Onboarding/SpouseHoldingTransfer.php`** (`splitIncome`): magic value (Category 4).
   - **Problem:** `'retired'` is compared as a bare string while its sibling lists are class constants (`WORKING_STATUSES`, `NON_WORKING_STATUSES`).
   - **Fix:** a `RETIRED_STATUS` constant, or a pension-income status list.

---
*Generated by tech-debt-session skill*
