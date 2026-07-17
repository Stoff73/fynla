# iOS Package 6: Financial Feature Waves Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development` for every feature, `systematic-debugging` for every failure, `verification-before-completion` before each wave and final gate, and `verify-m` whenever shared backend behaviour is touched. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port every financial screen currently shipped in `/m` to native SwiftUI in five bounded waves, preserving server-owned calculations, tier gates and Fyn edit workflows.

**Architecture:** Each feature has its own response DTO, API client, observable model and SwiftUI view. It reads the same endpoint as its `/m` counterpart and uses the shared native Fyn surface for add/edit intents where `/m` does. Detail routes carry only record identifiers and refetch canonical server data. Each wave closes independently before the next is called complete.

**Tech Stack:** SwiftUI, Observation, Foundation Decimal formatting, Swift Charts where projection series already exist, Swift Testing, XCTest UI; existing Laravel module APIs.

## Global Constraints

- The current `resources/mobile/router.js` and its 21 view files are the version 1 scope inventory.
- Do not port desktop-only forms or invent native CRUD that `/m` does not have. Existing `/m` add/edit actions route through Fyn; native does the same.
- Do not calculate tax, net worth, ownership shares, gaps, projection results, allowances, goal progress, estate liability or recommendations in Swift.
- Use `Decimal` for decoded money and UK locale formatters for display. Do not replace missing values with zero.
- Do not show numerical financial-quality scores.
- Do not add icons, emoji or decorative glyphs to module/detail cards.
- Every screen must implement loading, populated, empty/not configured, unavailable, offline, authentication-expired, upgrade-required, validation and server-error states where applicable.
- A server `upgradeRequired` response presents the canonical Free/Premium explanation and native subscription route; frontend hiding is not the entitlement boundary.
- Every successful Fyn edit/capture triggers refetch of the current screen and affected dashboard. No optimistic financial write.
- Preserve joint-ownership values exactly as returned by the server; do not recalculate spouse shares client-side.
- Each wave requires iPhone 11 simulator and physical-device evidence plus desktop/`/m` regression for any shared change.

## File map

| Path | Responsibility |
|---|---|
| `ios-native/Fynla/Core/Formatting/MoneyFormatter.swift` | GBP/percentage/date presentation only |
| `ios-native/Fynla/Core/Components/FinancialValueView.swift` | Explicit value/unavailable rendering |
| `ios-native/Fynla/Core/Components/ScreenStateView.swift` | Shared loading/offline/error presentation |
| `ios-native/Fynla/Core/FynEditing/FynEditIntent.swift` | Deterministic edit prompt from loaded names |
| `ios-native/Fynla/Features/Income/` through `HolisticPlan/` | Feature-isolated models/views |
| `ios-native/FynlaTests/Fixtures/Financial/` | Sanitised endpoint fixtures by wave |
| `docs/architecture/client-parity-ledger.md` | Wave evidence |

### Task 1: Build shared financial presentation primitives

**Files:** Create the four shared files above and tests.

- [ ] Write tests for GBP with negatives, pence, nil/unavailable, large values, en-GB dates and percentage display supplied by the server.
- [ ] `MoneyFormatter` accepts `Decimal` and never parses formatted server strings back into numbers.
- [ ] `FinancialValueView` distinguishes £0 from unavailable; decoding errors never become £0.
- [ ] Implement deterministic Fyn edit intent matching `/m`:

```swift
struct FynEditIntent: Sendable, Equatable {
    static func message(updateScope: String, addPhrase: String, names: [String?]) -> String {
        let clean = names.compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
            .filter { !$0.isEmpty }
        return clean.isEmpty
            ? addPhrase
            : "I'd like to update my \(updateScope). I currently have: \(clean.joined(separator: ", "))."
    }
}
```

- [ ] Only call this helper with fixed app-owned scope/phrase and server names; do not treat its output as trusted navigation.
- [ ] Shared screen state supports retry without destroying unsent Fyn draft or claiming saved data.
- [ ] Run primitive tests; expect PASS.

**Intended review boundary:** `feat: add native financial presentation primitives`

## Wave A: Income, Expenditure and Net Worth

### Task 2: Port Income

**Reference:** `resources/mobile/views/Income.vue` and `GET /api/user/profile`.

**Files:** Create `Features/Income/IncomeModels.swift`, `IncomeClient.swift`, `IncomeModel.swift`, `IncomeView.swift`; fixtures/tests.

- [ ] Write fixture decode tests for employed, self-employed, mixed income, no income and spouse-section responses.
- [ ] Render the same annual income categories and totals as `/m`; totals must be server-provided or explicit already-returned fields, not recomputed financial results.
- [ ] Honour onboarding `section` route context for user versus spouse wording.
- [ ] Edit opens Fyn with the same income scope and refreshes after capture/navigation completion.
- [ ] Add loading, empty, offline, 401 and decoding-error UI tests.

### Task 3: Port Expenditure

**Reference:** `resources/mobile/views/Expenditure.vue` and `GET /api/user/profile`.

**Files:** Create `Features/Expenditure/ExpenditureModels.swift`, client/model/view/tests.

- [ ] Decode and display the same monthly/annual summary and category fields as `/m`.
- [ ] Preserve server category labels and values; do not calculate affordability or infer spending guidance.
- [ ] Honour onboarding section context and same-screen Fyn refetch.
- [ ] Gate detailed expenditure according to canonical capability response; Free behaviour must match `/m` after Package 1.
- [ ] Test zero, partial, detailed Premium, Free-gated and failure states.

### Task 4: Port Net Worth and category detail

**Reference:** `resources/mobile/views/modules/NetWorth.vue`, `NetWorthCategory.vue`; `GET /api/net-worth/overview`, `GET /api/net-worth/assets-summary-detailed`.

**Files:** Create `Features/NetWorth/NetWorthModels.swift`, `NetWorthClient.swift`, `NetWorthModel.swift`, `NetWorthView.swift`, `NetWorthCategoryView.swift`; tests.

- [ ] Decode overview and detailed asset/liability categories with stable server identifiers.
- [ ] Render total assets, liabilities and net worth exactly as returned; no client sum is used as authority.
- [ ] Category routes support every key currently mapped in `/m`, including properties, savings, investments, pensions, business interests, valuables, cash, mortgages, loans and credit cards where returned.
- [ ] Each detail row displays ownership and balance fields returned by server. Never infer `100 - ownership_percentage` in Swift.
- [ ] Invalid/unknown category routes show unavailable and do not crash or disclose another record.
- [ ] Fyn edit intents use loaded record names and refetch overview/category after confirmed capture.

### Task 5: Close Wave A

- [ ] Run Wave A unit/UI tests and full native foundation/auth/dashboard tests.
- [ ] Compare native and `/m` against the same seeded personas for every populated/empty state.
- [ ] Verify Fyn edits persist and refresh all three screens.
- [ ] Verify Free/Premium expenditure differences on desktop, `/m` and native.
- [ ] Run on iPhone 11 hardware for long lists, Dynamic Type and memory pressure.
- [ ] Mark only Wave A ledger rows green.

**Intended review boundary:** `feat: port income expenditure and net worth`

## Wave B: Savings and Investments

### Task 6: Port Savings and account detail

**Reference:** `resources/mobile/views/modules/Savings.vue`, `SavingsAccount.vue`; `GET /api/savings`, `GET /api/savings/accounts/{id}`.

**Files:** Create `Features/Savings/SavingsModels.swift`, client/model/views/tests.

- [ ] Decode summary, emergency-fund runway/status, account collection, interest fields and ISA allowance fields used by `/m`.
- [ ] Render account list and detail with provider/name/type, balance, rates, dates, contributions and ownership only when returned.
- [ ] Account detail fetches by route ID and treats 404/403 distinctly; never select another account as fallback.
- [ ] Use Fyn prompts equivalent to “add savings” or named loaded accounts.
- [ ] Test no accounts, one/two Free accounts, Premium many accounts, cap rejection after Fyn create, detail missing and partial server data.

### Task 7: Port Investment and account detail

**Reference:** `resources/mobile/views/modules/Investment.vue`, `InvestmentAccountDetail.vue`, `investmentFormat.js`; `GET /api/investment`.

**Files:** Create `Features/Investment/InvestmentModels.swift`, `InvestmentFormatter.swift`, client/model/views/tests.

- [ ] Decode portfolio summary, accounts, holdings, allocation, allowances and recommendations actually used by `/m`.
- [ ] Port presentation-only mappings from `investmentFormat.js` into tested Swift formatters; do not port calculation logic.
- [ ] Account detail loads the canonical investment response and selects exact authenticated account ID; missing ID is unavailable, not first-account fallback.
- [ ] Do not show diversification or portfolio-health scores even if present in a legacy payload.
- [ ] Gate advanced investment tools through server capability; no local tier comparison.
- [ ] Fyn add/edit uses loaded account names and confirmed-capture refetch.
- [ ] Test empty, Free cap, populated, Premium advanced, missing detail, unknown allocation and failure states.

### Task 8: Close Wave B

- [ ] Run Savings/Investment tests and all prior native tests.
- [ ] Compare the same seeded accounts/holdings on `/m` and native, including joint ownership.
- [ ] Verify Free create caps are server-enforced through a Fyn write and existing records stay visible.
- [ ] Verify no banned scores appear through an accessibility-tree text assertion.
- [ ] Run Savings/Investment navigation and large holding lists on iPhone 11 hardware.
- [ ] Mark only Wave B rows green.

**Intended review boundary:** `feat: port savings and investments`

## Wave C: Retirement and Protection

### Task 9: Port Retirement and pension detail

**Reference:** `resources/mobile/views/modules/Retirement.vue`, `RetirementPensionDetail.vue`; `GET /api/retirement`, `POST /api/retirement/analyze`, `GET /api/retirement/projections`, `GET /api/retirement/dc-pensions/{id}/projections`.

**Files:** Create `Features/Retirement/RetirementModels.swift`, client/model/views, `RetirementProjectionChart.swift`, tests.

- [ ] Decode profile/readiness, DC/DB pensions, state pension, target/projected income, gap, years and server projection points.
- [ ] Sequence GET plus analyze exactly as `/m`; POST analyze is never automatically replayed after uncertain failure.
- [ ] Use Swift Charts only to plot returned series. Axis formatting is presentation-only; no local projection/interpolation.
- [ ] Pension detail selects exact type and ID and fetches DC projections only for a returned DC record.
- [ ] Do not label DB/DC acronyms alone in user copy; spell out Defined Benefit and Defined Contribution, while decoding canonical API keys internally.
- [ ] Fyn prompts handle no pension, named pensions and missing target income.
- [ ] Test not configured, no pensions, mixed pensions, no target, projections unavailable, Premium planning gate and detail errors.

### Task 10: Port Protection and policy detail

**Reference:** `resources/mobile/views/modules/Protection.vue`, `ProtectionPolicy.vue`; `GET /api/protection`.

**Files:** Create `Features/Protection/ProtectionModels.swift`, client/model/views/tests.

- [ ] Decode profile readiness, coverage, gaps, policy groups and recommendations actually rendered by `/m`.
- [ ] List all returned policy types and route with type plus ID. Detail selects exact policy within its canonical group.
- [ ] Display cover/premium/term/benefit fields without estimating policy adequacy or cost in Swift.
- [ ] Do not expose a numerical adequacy score.
- [ ] Fyn prompts handle no cover and named policies, then refetch after confirmed write.
- [ ] Test no profile, no policies, each policy type, missing detail, partial analysis and failure states.

### Task 11: Close Wave C

- [ ] Run Retirement/Protection tests and all prior native tests.
- [ ] Compare projection points and policy values against `/m` for seeded personas; native plotted values must exactly match response fixtures.
- [ ] Verify Fyn edit/create, same-screen refetch and server audit evidence.
- [ ] Verify Premium retirement-decumulation gating across all three clients.
- [ ] Test charts, pension/policy lists and Fyn interruption on iPhone 11 hardware.
- [ ] Mark only Wave C rows green.

**Intended review boundary:** `feat: port retirement and protection`

## Wave D: Estate Planning and Goals

### Task 12: Port Estate Planning

**Reference:** `resources/mobile/views/modules/Estate.vue`; `GET /api/estate`, `GET /api/estate/net-worth`.

**Files:** Create `Features/Estate/EstateModels.swift`, client/model/view/tests.

- [ ] Decode estate summary, assets, liabilities, will/trust/lasting-power fields and recommendations actually displayed by `/m`.
- [ ] Use estate net-worth endpoint for the complete property/pension-inclusive figure where `/m` does; do not substitute dashboard net worth.
- [ ] Spell out Inheritance Tax in user text except where a server-provided legal label is canonical; internal DTO keys may retain `iht`.
- [ ] Free renders only the approved teaser/capability outcome; Premium renders full returned engine output.
- [ ] Do not compute nil-rate bands, residence nil-rate band, effective rates or liability in Swift.
- [ ] Fyn entry uses an approved estate update/add prompt only where `/m` offers the action; do not add an unplanned form.
- [ ] Test Free teaser, Premium populated, no profile, partial estate, endpoint mismatch and server error.

### Task 13: Port Goals and life events

**Reference:** `resources/mobile/views/modules/Goals.vue`; `GET /api/goals`, `GET /api/goals/dashboard-overview`.

**Files:** Create `Features/Goals/GoalModels.swift`, client/model/view/tests.

- [ ] Decode goal list plus dashboard overview, progress, dates, targets and status.
- [ ] Render server progress/amounts; do not recompute goal probability or forecast.
- [ ] Add Goal opens Fyn with exactly “I'd like to add a new goal.” Edit uses `I'd like to update my "{server name}" goal.` with safe plain-text interpolation.
- [ ] Free Goal/Life Event count caps remain server-enforced and existing over-cap records remain visible.
- [ ] Test no goals, active/completed goals, Free caps, Premium many goals, missing/unknown fields and errors.

### Task 14: Close Wave D

- [ ] Run Estate/Goals and all previous tests.
- [ ] Compare seeded estate/goal screens to `/m`, including Free teaser and Premium full engine.
- [ ] Verify add/edit through Fyn, cap errors and post-write refresh with database evidence.
- [ ] Assert no tax constants or goal calculations exist under native feature sources.
- [ ] Run long goal lists and Estate view on iPhone 11 hardware.
- [ ] Mark only Wave D rows green.

**Intended review boundary:** `feat: port estate planning and goals`

## Wave E: Tax Strategy and Holistic Plan

### Task 15: Port Tax Strategy

**Reference:** `resources/mobile/views/TaxStrategy.vue`; `GET /api/tax-strategy`, `POST /api/recommendations/{id}/mark-done`.

**Files:** Create `Features/TaxStrategy/TaxStrategyModels.swift`, client/model/view/tests.

- [ ] Decode current tax position, recommendations, quantified savings, allowances and onboarding verification state exactly as returned.
- [ ] Render server values/copy; no UK tax constants or local tax arithmetic.
- [ ] Mark recommendation complete only after server acknowledgement, then refetch dashboard/tax strategy.
- [ ] Honour Fyn navigation into Tax Strategy and Gate 2 Continue/Edit state without inventing client persona logic.
- [ ] Spell out user-facing acronyms other than ISA in app-owned copy.
- [ ] Test Free/Premium canonical access, no recommendations, mixed recommendations, complete action, Fyn verification and failure states.

### Task 16: Port Holistic Plan

**Reference:** `resources/mobile/views/HolisticPlan.vue`; `GET /api/holistic/composite-plan`.

**Files:** Create `Features/HolisticPlan/HolisticPlanModels.swift`, client/model/view/tests.

- [ ] Decode plan sections, priorities, dependencies, recommendations, projected outcomes and server warnings used by `/m`.
- [ ] Preserve server ordering and wording; do not re-rank or combine strategies in Swift.
- [ ] Premium-only access uses canonical upgrade response. Free never receives hidden plan data through the client.
- [ ] Render unavailable sections honestly; do not turn a module failure into a valid zero plan.
- [ ] Link any action back to an allowlisted route or Fyn prompt, never an arbitrary URL.
- [ ] Test Premium populated/partial, Free gate, server composition error, offline and decoding errors.

### Task 17: Close Wave E and the complete financial port

- [ ] Run Tax/Holistic and all prior native tests.
- [ ] Compare values and recommendation completion to `/m` and desktop with the same user.
- [ ] Verify Free/Premium gates and that no protected payload appears for Free.
- [ ] Run full route inventory test asserting every route in the Package 6 inventory has a native destination and UI test.
- [ ] Perform a screen-by-screen iPhone 11 physical-device pass, including Dynamic Type, VoiceOver, low-memory relaunch, offline transition and Face ID relock.
- [ ] Update all Package 6 parity rows with named fixtures, test commands, browser/device evidence and CSJ approval.

Route inventory assertion must cover:

```text
/income
/expenditure
/net-worth
/net-worth/:category
/protection
/protection/policy/:policyType/:id
/savings
/savings/account/:id
/investment
/investment/account/:id
/retirement
/retirement/pension/:type/:id
/estate
/goals
/tax-strategy
/holistic-plan
```

Package command:

```bash
./vendor/bin/pest tests/Feature/Mobile tests/Feature/Contracts tests/Feature/Api tests/Feature/Payment tests/Feature/AI
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=iPhone 11' test CODE_SIGNING_ALLOWED=NO
```

Expected: PASS. Use the narrow failing suite during the loop, then the full package command before completion.

### Package 6 exit criteria

- [ ] Every current `/m` financial route has a native SwiftUI equivalent.
- [ ] Each screen's populated, empty, unavailable, offline, auth, upgrade and error states pass.
- [ ] Detail routes refetch by exact record ID and enforce account isolation server-side.
- [ ] All add/edit flows match `/m` and use one Fyn surface.
- [ ] No tax/financial/entitlement logic is duplicated in Swift.
- [ ] No banned financial score, decorative icon or unexplained acronym was added.
- [ ] All five wave gates have independent desktop/`/m`/native evidence.
- [ ] Full iPhone 11 physical-device pass is approved by CSJ before platform/release work closes.
