# Canonical Details and Navigation Reuse Implementation Plan

> **For Codex:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Apply superpowers:test-driven-development for every production change and superpowers:verification-before-completion before publishing.

**Goal:** Complete PR3 of the approved iOS and `/m` parity design by routing Goals, Net Worth assets/liabilities, Income, and Expenditure through canonical detail contracts, removing overview editing, and bounding Holistic Plan loading.

**Architecture:** Laravel remains the single authority for ownership, financial values, tax position, expenditure mode, and detail payloads. Existing Goal, Property, Mortgage, profile, income-definition, and Net Worth endpoints are reused or extended; the liability read endpoint is added because none exists. Vue and Swift map the same semantic destination names to platform-native routes and render the same server fields without recalculating them.

**Tech Stack:** Laravel/Pest, Vue 3/Vitest, SwiftUI/Swift Testing, installed Google Chrome, Xcode iPhone 16 Pro simulator.

---

## Task 1: Freeze the shared detail destination contract

**Files:**
- Modify: `tests/Feature/AI/ContextualConversationContractTest.php`
- Modify: `resources/mobile/navigation/__tests__/semanticDestinations.spec.js`
- Modify: `ios-native/FynlaTests/NavigationMenuTests.swift`
- Modify: `app/Http/Requests/AI/CreateContextualConversationRequest.php`
- Modify: `resources/mobile/navigation/semanticDestinations.js`
- Modify: `ios-native/Fynla/Core/Navigation/SemanticDestination.swift`
- Modify: `ios-native/Fynla/App/AppRouter.swift`
- Modify: `ios-native/Fynla/Features/Navigation/NavigationDestinationFactory.swift`

1. Add failing tests for `goal_detail`, `property_detail`, `mortgage_detail`, `liability_detail`, and `income_detail`, including positive integer IDs, canonical fallbacks, unknown-screen fallback, and rejection of unexpected financial-value parameters.
2. Run the focused Pest, Vitest, and Swift navigation tests and retain the expected failures as the red baseline.
3. Add the entity resource types, identifier keys, screens, fallback rules, Vue paths, Swift routes, and destination factory cases.
4. Re-run the focused tests until green; commit `feat: add canonical financial detail destinations`.

## Task 2: Move Goals to a canonical detail screen

**Files:**
- Modify: `resources/mobile/views/__tests__/GoalsOverviewActions.spec.js`
- Create: `resources/mobile/views/__tests__/GoalDetail.spec.js`
- Modify: `resources/mobile/views/modules/Goals.vue`
- Create: `resources/mobile/views/modules/GoalDetail.vue`
- Modify: `resources/mobile/router.js`
- Modify: `ios-native/FynlaTests/GoalsTests.swift`
- Modify: `ios-native/Fynla/Features/Goals/GoalModels.swift`
- Modify: `ios-native/Fynla/Features/Goals/GoalsClient.swift`
- Modify: `ios-native/Fynla/Features/Goals/GoalsModel.swift`
- Modify: `ios-native/Fynla/Features/Goals/GoalsView.swift`
- Create: `ios-native/Fynla/Features/Goals/GoalDetailView.swift`

1. Add failing tests proving goal cards open details, overview cards have no Edit action, the detail exposes purpose/rationale, target/current values, created/target dates, milestones, contribution status, and one contextual Edit action.
2. Implement `/goals/:id` and the equivalent Swift route using `GET /api/goals/{id}`; use the server payload without client financial calculations.
3. Send only action/resource/destination identifiers when opening contextual Fyn.
4. Run the focused Vue, Swift, and contextual-contract tests until green; commit `feat: add canonical goal details`.

## Task 3: Reuse canonical Property, Mortgage, and Liability details from Net Worth

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/EstateController.php`
- Create: `tests/Feature/Api/EstateLiabilityControllerTest.php`
- Modify: `tests/Feature/Api/PropertyControllerTest.php`
- Modify: `tests/Feature/Api/MortgageControllerTest.php`
- Create: `resources/mobile/views/__tests__/NetWorthDetailNavigation.spec.js`
- Modify: `resources/mobile/views/modules/NetWorthCategory.vue`
- Create: `resources/mobile/views/modules/PropertyDetail.vue`
- Create: `resources/mobile/views/modules/MortgageDetail.vue`
- Create: `resources/mobile/views/modules/LiabilityDetail.vue`
- Modify: `resources/mobile/router.js`
- Modify: `ios-native/FynlaTests/NetWorthTests.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthModels.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthClient.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthModel.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthCategoryView.swift`
- Create: `ios-native/Fynla/Features/NetWorth/PropertyDetailView.swift`
- Create: `ios-native/Fynla/Features/NetWorth/MortgageDetailView.swift`
- Create: `ios-native/Fynla/Features/NetWorth/LiabilityDetailView.swift`

1. Add failing ownership tests for `GET /api/estate/liabilities/{id}` and detail-payload assertions for existing Property and Mortgage endpoints.
2. Add failing client tests proving property/liability cards open canonical details, property cards display outstanding mortgage in smaller red text, linked mortgages reuse mortgage detail, and the generic Net Worth/category Edit action is absent.
3. Implement the ownership-filtered liability read action and render all server-provided ownership, balance, rate, repayment, term, security, maturity, and property-link fields.
4. Run focused backend, Vue, and Swift tests until green; commit `feat: reuse canonical net worth details`.

## Task 4: Add canonical Income detail and reconcile Expenditure modes

**Files:**
- Modify: `tests/Feature/Api/UserProfileIncomeSummaryTest.php`
- Modify: `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`
- Create: `tests/Feature/Api/UserProfileExpenditurePresentationTest.php`
- Modify: `app/Services/Tax/IncomeDefinitionsService.php`
- Modify: `app/Http/Controllers/Api/IncomeDefinitionsController.php`
- Modify: `app/Http/Controllers/Api/UserProfileController.php`
- Modify: `resources/mobile/views/Income.vue`
- Create: `resources/mobile/views/IncomeDetail.vue`
- Modify: `resources/mobile/views/Expenditure.vue`
- Modify: `resources/mobile/router.js`
- Modify: `ios-native/FynlaTests/IncomeTests.swift`
- Modify: `ios-native/FynlaTests/ExpenditureTests.swift`
- Modify: `ios-native/Fynla/Features/Income/IncomeModels.swift`
- Modify: `ios-native/Fynla/Features/Income/IncomeView.swift`
- Create: `ios-native/Fynla/Features/Income/IncomeDetailView.swift`
- Modify: `ios-native/Fynla/Features/Expenditure/ExpenditureModels.swift`
- Modify: `ios-native/Fynla/Features/Expenditure/ExpenditureView.swift`

1. Add failing server tests for source, amount, frequency, ownership, server-owned tax-position labels, expenditure entry mode, reconciled active total, detail availability, and summary-only reason.
2. Add failing Vue and Swift tests for income-card navigation and explicit expenditure mode; summary-only screens must explain the limitation and expose a contextual Fyn capture action containing no financial values.
3. Extend the existing server responses with presentation metadata and render it on both clients without duplicate tax or total calculations.
4. Run focused backend, Vue, Swift, and contextual Fyn tests until green; commit `feat: align income and expenditure details`.

## Task 5: Bound Holistic Plan loading and finish accessibility parity

**Files:**
- Modify: `resources/mobile/views/HolisticPlan.vue`
- Create: `resources/mobile/views/__tests__/HolisticPlan.spec.js`
- Modify: `ios-native/FynlaTests/HolisticPlanTests.swift`
- Modify: `ios-native/Fynla/Features/HolisticPlan/HolisticPlanClient.swift`
- Modify: `ios-native/Fynla/Features/HolisticPlan/HolisticPlanModel.swift`
- Modify: `ios-native/Fynla/Features/HolisticPlan/HolisticPlanView.swift`
- Modify: detail views created in Tasks 2-4

1. Add failing tests for real-plan rendering, typed subscription gate, request timeout, bounded retryable failure, and accessible visible detail headings.
2. Add request timeouts/cancellation and deterministic retry state while preserving the server gate as authoritative.
3. Give every detail screen a visible semantic heading and matching accessibility identifier/trait.
4. Run focused tests until green; commit `fix: bound holistic plan loading states`.

## Task 6: Execute the user-style verification loop and publish PR3

**Files:**
- Modify: `docs/testing/2026-08-10-contextual-fyn-conversation-history-evidence.md`
- Create: `docs/testing/2026-08-10-canonical-details-ios-m-evidence.md`

1. Run `./vendor/bin/pest` for all changed feature/unit suites, `npm`/Vitest suites for mobile, and the full native unit-test scheme.
2. Build and launch Fynla in Xcode's iPhone 16 Pro simulator. Exercise every changed journey as a user, document each issue, fix it under a failing regression test, and repeat until green.
3. Use the existing signed-in installed Google Chrome session for `/m`; exercise the same journeys and the contextual conversation/history loop, documenting and fixing each issue until green. Do not substitute Chromium or the in-app browser.
4. Update both evidence documents with commands, results, simulator/device, Chrome journeys, failures found, and final status.
5. Run `git diff --check`, all affected test suites, and a production build. Commit the evidence, push the branch, open PR3 ready for review, monitor every check, repair failures, and manually merge only after all checks are green.

