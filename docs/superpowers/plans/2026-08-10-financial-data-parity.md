# Financial Data Parity Implementation Plan

> **For Codex:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Apply superpowers:test-driven-development for every production change and superpowers:verification-before-completion before publishing.

**Goal:** Complete PR4 of the approved iOS and `/m` parity design with canonical protection-gap explanations, ISA contribution history and ownership, one honest portfolio look-through/drift method across DC pensions, investment accounts, and S&S ISAs, canonical holdings/performance views, and server-enforced freemium creation limits.

**Architecture:** Laravel remains the single authority for financial facts and calculations. A shared portfolio presentation service will classify direct holdings, use only recorded look-through mixes for composite funds, expose unclassified value and coverage, and calculate percentage-point drift against a frozen entered baseline and/or server recommendation only when coverage meets the configured safe threshold. Additive schema stores look-through provenance and entered baselines; existing dated value snapshots power performance history, with explicit unavailable states when history is absent. Vue and Swift decode and render the same API contract without recalculating financial facts.

**Tech Stack:** Laravel/Pest, Vue 3/Vitest, SwiftUI/Swift Testing, installed Google Chrome, Xcode iPhone 16 Pro simulator.

---

## Task 1: Freeze the shared portfolio analysis contract

**Files:**
- Create: `tests/Unit/Services/Investment/PortfolioExposureServiceTest.php`
- Modify: `tests/Unit/Services/Investment/PortfolioAnalyzerTest.php`
- Create: `app/Services/Investment/PortfolioExposureService.php`
- Modify: `app/Constants/InvestmentDefaults.php`
- Create: `database/migrations/2026_08_10_000001_add_portfolio_context_fields.php`
- Modify: `app/Models/Investment/Holding.php`
- Modify: `app/Models/Investment/InvestmentAccount.php`
- Modify: `app/Models/DCPension.php`

1. Add failing tests for direct classification, recorded mixed-fund look-through, explicit unclassified value, absolute/whole/classified exposure percentages, coverage, source/effective-date provenance, entered and recommended comparison vectors, percentage-point drift, and suppression below the safe coverage threshold.
2. Add failing regression coverage proving unknown assets no longer silently default to equities and mixed funds are not assigned a fabricated 60/30/10 split.
3. Add nullable JSON/provenance/effective-date fields for holding look-through and wrapper entered baselines, with model casts and no destructive backfill.
4. Implement the shared service and route legacy analyzers through its classification rules where compatible.
5. Run focused Pest tests until green; commit `feat: add shared portfolio exposure engine`.

## Task 2: Publish canonical holdings, performance, and drift APIs

**Files:**
- Create: `app/Services/Investment/PortfolioPresentationService.php`
- Modify: `app/Http/Controllers/Api/InvestmentController.php`
- Modify: `app/Http/Controllers/Api/RetirementController.php`
- Modify: `app/Http/Resources/Investment/InvestmentAccountResource.php`
- Modify: `app/Http/Resources/Retirement/DCPensionResource.php`
- Modify: `tests/Feature/Api/InvestmentControllerTest.php`
- Create: `tests/Feature/Api/RetirementPortfolioParityTest.php`

1. Add failing ownership and contract tests for the same portfolio payload on investment, S&S ISA, and DC pension wrappers.
2. Assert each holding includes recorded value, wrapper percentage, whole relevant portfolio percentage, classified exposure, charges, cost-basis performance where available, and explicit unavailable metadata otherwise.
3. Assert wrapper history uses canonical dated value snapshots and never fabricates market history.
4. Integrate the shared engine into the existing investment index and retirement portfolio-analysis endpoints while preserving existing response keys.
5. Run focused backend tests until green; commit `feat: expose canonical cross-wrapper portfolios`.

## Task 3: Add canonical ISA ownership and contribution history

**Files:**
- Create: `database/migrations/2026_08_10_000002_create_isa_contributions_table.php`
- Create: `app/Models/ISAContribution.php`
- Modify: `app/Services/Savings/ISATracker.php`
- Modify: `app/Http/Controllers/Api/SavingsController.php`
- Modify: `tests/Unit/Services/Savings/ISATrackerTest.php`
- Create: `tests/Feature/Api/ISAContributionHistoryTest.php`

1. Add failing tests for current and prior tax years, cash and S&S ISA records, primary/joint/partner owner labels, ledger provenance, and an account breakdown whose amounts exactly total the overview.
2. Add an additive contribution ledger that records canonical subscriptions per account and tax year; retain existing summary fields as clearly labelled legacy-summary evidence when no ledger exists.
3. Extend the existing ISA allowance endpoint and savings payload with tax-year navigation, owner-aware breakdown, contribution records, total/remaining values, and explicit data provenance.
4. Run focused Pest tests until green; commit `feat: add canonical ISA contribution history`.

## Task 4: Publish canonical protection-gap explanations

**Files:**
- Create: `app/Services/Protection/ProtectionGapPresentationService.php`
- Modify: `app/Http/Controllers/Api/ProtectionController.php`
- Modify: `tests/Unit/Agents/ProtectionAgentTest.php`
- Create: `tests/Feature/Api/ProtectionGapPresentationTest.php`

1. Add failing tests for server-calculated gap cards that include need, cover, shortfall, calculation inputs, assumptions, severity, explanation, and relevant policy references.
2. Build presentation fields from `CoverageGapAnalyzer` output and canonical policies/profile only; expose them from `GET /api/protection` without client-side financial calculation.
3. Preserve the existing analysis contract and prove overview totals match the presented breakdown.
4. Run focused Pest tests until green; commit `feat: explain canonical protection gaps`.

## Task 5: Enforce freemium caps at every authoritative write boundary

**Files:**
- Modify: `app/Traits/PolicyCRUDTrait.php`
- Modify: affected store/capture/import services identified by the boundary audit
- Modify: `tests/Feature/Mobile/FreemiumCapsTest.php`
- Modify: relevant `tests/Architecture/StoreBoundary/*Test.php`

1. Audit forms, direct API controllers, upload/import flows, onboarding, and Fyn capture for savings, investments, pensions, and protection policies.
2. Add failing tests proving every creation path is rejected at the server boundary when capped, while reads and updates of existing over-limit records remain allowed.
3. Route missing paths through the canonical stores/tier gate and return the typed limit response with a subscription-comparison destination.
4. Run focused feature and architecture tests until green; commit `fix: enforce financial account creation caps`.

## Task 6: Render the financial-data contract on `/m`

**Files:**
- Modify: `resources/mobile/views/modules/Protection.vue`
- Modify: `resources/mobile/views/modules/Savings.vue`
- Modify: `resources/mobile/views/modules/SavingsAccount.vue`
- Modify: `resources/mobile/views/modules/Investment.vue`
- Modify: `resources/mobile/views/modules/InvestmentAccountDetail.vue`
- Modify: `resources/mobile/views/modules/Retirement.vue`
- Modify: `resources/mobile/views/modules/RetirementPensionDetail.vue`
- Create or modify: focused tests under `resources/mobile/views/__tests__/`

1. Add failing Vitest coverage for tappable protection explanations, ISA tax-year/owner/ledger breakdown, holdings, performance history or unavailable states, entered/recommended drift with coverage, unknown exposure, and typed freemium upgrade actions.
2. Render the canonical API fields without local gap, ISA, exposure, performance, or drift calculations.
3. Keep contextual Fyn actions identifier-only and preserve canonical detail navigation.
4. Run focused Vitest and production-build checks until green; commit `feat: render financial data parity on mobile web`.

## Task 7: Render the same financial-data contract in native iOS

**Files:**
- Modify: models, clients, models, and views under `ios-native/Fynla/Features/Protection/`
- Modify: models, clients, models, and views under `ios-native/Fynla/Features/Savings/`
- Modify: models, clients, models, and views under `ios-native/Fynla/Features/Investment/`
- Modify: models, clients, models, and views under `ios-native/Fynla/Features/Retirement/`
- Modify: focused suites under `ios-native/FynlaTests/`

1. Add failing Swift tests for the same decoded fixtures and presentation semantics covered on `/m`.
2. Extend Codable models and SwiftUI views to show protection explanations, ISA history/ownership, holdings, canonical performance, both drift comparators, coverage, and unavailable states.
3. Keep the native client free of financial calculations and send only identifiers in contextual Fyn requests.
4. Run focused Swift tests and the full native test scheme until green; commit `feat: render financial data parity on iOS`.

## Task 8: Execute the user-style loop and publish PR4

**Files:**
- Create: `docs/testing/2026-08-10-financial-data-parity-evidence.md`

1. Run all changed Pest, architecture, Vitest, Swift, migration, and production-build checks.
2. Build and launch Fynla in Xcode's iPhone 16 Pro simulator. Exercise protection explanations, ISA current/prior history, investment/S&S ISA/DC pension holdings and drift, performance unavailable states, and freemium limit actions as a user. Record every issue, add a failing regression test, fix, and repeat until green.
3. Exercise the same journeys in the existing signed-in installed Google Chrome session on `/m`, using no browser substitute. Record and fix each discrepancy until green.
4. Record commands, fixtures, simulator/device, Chrome journeys, defects found, repairs, and final status in the evidence file.
5. Run `git diff --check`, complete affected suites, migration status, and production builds. Commit evidence, push, open PR4 ready for review, monitor all checks, repair failures, and manually merge only after every check is green.
