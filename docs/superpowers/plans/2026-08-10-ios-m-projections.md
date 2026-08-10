# iOS and Mobile Web Projections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give iOS and `/m` the same server-owned retirement and Net Worth projections, with product-level reconciliation, age-banded retirement income, recorded-versus-projected separation, and editable asset-specific forecast assumptions.

**Architecture:** Add two focused server services. `RetirementProjectionContractService` composes existing pension projections into one deterministic planning contract and reconciled age bands. `NetWorthForecastService` starts from the latest canonical Net Worth totals and applies persisted per-category assumptions without writing forecast points into recorded history. Existing routes remain compatible; additive authenticated endpoints expose the shared contracts to both clients.

**Tech Stack:** Laravel 10, PHP 8.2, MySQL 8, Pest, Vue 3 mobile bundle, Vitest, SwiftUI, Swift Testing/XCTest, XCUITest, Xcode iOS Simulator.

## Global Constraints

- Both clients consume the same server-owned calculation contract; neither client recalculates financial projections.
- Defined Contribution income starts at that pension's commencement age and uses the configured sustainable withdrawal rate, currently 4.7%.
- Defined Benefit and State Pension income start at their own commencement ages.
- Aggregate age bands reconcile exactly to the active product incomes at every age.
- The primary projection uses the user's stated assumptions; uncertainty is separate and never labelled as the primary “median projection”.
- Recorded balance history contains saved snapshots only. Forecast points are returned only by the forecast endpoint.
- Net Worth assumptions are asset-specific, editable, dated, and disclose source plus nominal/real basis.
- Existing route payloads remain backwards compatible while the additive contracts roll out.
- Browser acceptance uses the user's installed Google Chrome at `https://csjones.co/fynla/m`; no Chromium substitute.
- Native acceptance uses `ios-native/Fynla.xcodeproj`, `Fynla-Staging`, and the booted iPhone 16 Pro simulator.

---

### Task 1: Shared retirement planning contract

**Files:**
- Create: `app/Services/Retirement/RetirementProjectionContractService.php`
- Create: `tests/Unit/RetirementProjectionContractServiceTest.php`
- Modify: `app/Services/Retirement/RetirementProjectionService.php`
- Modify: `app/Http/Controllers/Api/RetirementController.php`
- Modify: `tests/Feature/RetirementIntegrationTest.php`

**Interfaces:**
- Consumes: canonical `User` pension relationships, `RetirementProjectionService::projectIndividualDCPension(int $pensionId, int $userId): array`, pension assumptions from `AssumptionsService`, and `TaxConfigService::get()`.
- Produces: `RetirementProjectionContractService::build(User $user): array` with contract version `retirement_projection_v1`, `products`, `age_bands`, `planning_total_at_target_age`, `assumptions`, `uncertainty`, and `warnings`.
- API: existing `GET /api/retirement/projections` gains additive key `planning_projection`.

- [ ] **Step 1: Write the failing pure reconciliation test**

Create a table-driven test with literal products:

```php
$products = [
    ['resource_type' => 'dc_pension', 'resource_id' => 1, 'name' => 'SIPP', 'commencement_age' => 60, 'annual_income' => 9_400.00],
    ['resource_type' => 'db_pension', 'resource_id' => 2, 'name' => 'DB Scheme', 'commencement_age' => 65, 'annual_income' => 8_000.00],
    ['resource_type' => 'state_pension', 'resource_id' => 3, 'name' => 'State Pension', 'commencement_age' => 67, 'annual_income' => 11_500.00],
];

$bands = RetirementProjectionContractService::reconcileAgeBands($products, 60, 90);

expect($bands)->toBe([
    ['start_age' => 60, 'end_age' => 64, 'annual_income' => 9400.0, 'source_ids' => ['dc_pension:1']],
    ['start_age' => 65, 'end_age' => 66, 'annual_income' => 17400.0, 'source_ids' => ['dc_pension:1', 'db_pension:2']],
    ['start_age' => 67, 'end_age' => 90, 'annual_income' => 28900.0, 'source_ids' => ['dc_pension:1', 'db_pension:2', 'state_pension:3']],
]);
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
php vendor/pestphp/pest/bin/pest tests/Unit/RetirementProjectionContractServiceTest.php
```

Expected: FAIL because `RetirementProjectionContractService` does not exist.

- [ ] **Step 3: Implement age-band reconciliation and the product contract**

Implement a final service whose public contract is:

```php
public function build(User $user): array;

/** @param array<int, array{resource_type:string,resource_id:int,name:string,commencement_age:int,annual_income:float}> $products */
public static function reconcileAgeBands(array $products, int $startAge, int $endAge): array;
```

For DC pensions, use each pension's own projected planning value and calculate `annual_income = projected_value * sustainable_withdrawal_rate`. For DB and State products, preserve their canonical annual income. Sort products by `commencement_age`, then `resource_type`, then `resource_id`, so clients receive stable ordering. Include the withdrawal rate as decimal and percent, effective configuration source, user growth/inflation/fee assumptions, and an `as_of` date.

- [ ] **Step 4: Add endpoint regression coverage**

Extend `RetirementIntegrationTest` to assert:

```php
$response->assertOk()
    ->assertJsonPath('data.planning_projection.contract_version', 'retirement_projection_v1')
    ->assertJsonPath('data.planning_projection.assumptions.sustainable_withdrawal_rate.percent', 4.7)
    ->assertJsonCount(3, 'data.planning_projection.products');

$payload = $response->json('data.planning_projection');
foreach ($payload['age_bands'] as $band) {
    $expected = collect($payload['products'])
        ->filter(fn (array $product): bool => $product['commencement_age'] <= $band['start_age'])
        ->sum('annual_income');
    expect((float) $band['annual_income'])->toBe(round((float) $expected, 2));
}
```

Also assert the legacy `pension_pot_projection` and `income_drawdown` keys remain present.

- [ ] **Step 5: Run focused retirement tests and verify GREEN**

```bash
php vendor/pestphp/pest/bin/pest tests/Unit/RetirementProjectionContractServiceTest.php tests/Feature/RetirementIntegrationTest.php
```

Expected: PASS with no warnings.

- [ ] **Step 6: Commit the retirement contract**

```bash
git add app/Services/Retirement/RetirementProjectionContractService.php app/Services/Retirement/RetirementProjectionService.php app/Http/Controllers/Api/RetirementController.php tests/Unit/RetirementProjectionContractServiceTest.php tests/Feature/RetirementIntegrationTest.php
git commit -m "feat: add reconciled retirement projection contract"
```

### Task 2: Remove median presentation and disclose planning assumptions

**Files:**
- Modify: `resources/mobile/views/modules/Retirement.vue`
- Modify: `resources/mobile/views/modules/RetirementPensionDetail.vue`
- Modify: `tests/frontend/mobile/Retirement.test.js`
- Modify: `ios-native/Fynla/Features/Retirement/RetirementModels.swift`
- Modify: `ios-native/Fynla/Features/Retirement/RetirementView.swift`
- Modify: `ios-native/Fynla/Features/Retirement/RetirementPensionView.swift`
- Modify: `ios-native/FynlaTests/RetirementTests.swift`

**Interfaces:**
- Consumes: Task 1 `planning_projection` unchanged on both clients.
- Produces: product rows and age-band rows labelled as projected income, plus an assumptions disclosure; no rendered “Median projection” label.

- [ ] **Step 1: Write failing `/m` presentation tests**

Use a complete `planning_projection` fixture and assert:

```js
expect(wrapper.text()).toContain('Age 60–64');
expect(wrapper.text()).toContain('£9,400 a year');
expect(wrapper.text()).toContain('4.7% sustainable withdrawal rate');
expect(wrapper.text()).not.toContain('Median projection');
```

- [ ] **Step 2: Run the focused Vitest and verify RED**

```bash
PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:$PATH npm run test:run -- tests/frontend/mobile/Retirement.test.js
```

Expected: FAIL because the current UI renders `median_at_retirement` and has no age-band presentation.

- [ ] **Step 3: Render the server contract on `/m`**

Add computed properties that only select and format `planning_projection.products`, `age_bands`, and `assumptions`. Do not calculate income or bands in Vue. Replace the two median rows with “Planning projection” and link/expand the assumptions copy in both aggregate and pension detail views.

- [ ] **Step 4: Write failing Swift decoding and view-model tests**

Decode a literal `planning_projection` JSON fixture and assert three age bands, a 4.7% disclosure, and `planningTotalAtTargetAge`. Assert the view's accessibility tree contains `retirement.age-band.60-64` and does not expose “Median projection”.

- [ ] **Step 5: Run the focused Swift tests and verify RED**

```bash
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,id=94F2B841-2099-4291-88AB-EDAA797ADF75' -only-testing:FynlaTests/RetirementTests CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
```

- [ ] **Step 6: Add Swift models and render the shared contract**

Add `RetirementPlanningProjection`, `RetirementProjectionProduct`, `RetirementIncomeBand`, and `RetirementProjectionAssumptions` as `Decodable`, `Sendable`, `Equatable` values. Views format server fields without recomputing totals.

- [ ] **Step 7: Run focused web and iOS tests and verify GREEN**

Run the commands from Steps 2 and 5. Expected: PASS.

- [ ] **Step 8: Commit cross-client retirement presentation**

```bash
git add resources/mobile/views/modules/Retirement.vue resources/mobile/views/modules/RetirementPensionDetail.vue tests/frontend/mobile/Retirement.test.js ios-native/Fynla/Features/Retirement ios-native/FynlaTests/RetirementTests.swift
git commit -m "feat: reconcile retirement projections across clients"
```

### Task 3: Persist asset-specific Net Worth forecast assumptions

**Files:**
- Create: `database/migrations/2026_08_10_200000_create_net_worth_forecast_assumptions_table.php`
- Create: `app/Models/NetWorthForecastAssumption.php`
- Create: `app/Services/NetWorth/NetWorthForecastAssumptionService.php`
- Create: `tests/Feature/Services/NetWorth/NetWorthForecastAssumptionServiceTest.php`

**Interfaces:**
- Produces: one user-owned assumption record with decimal percent fields `property`, `investments`, `pensions`, `cash`, `business`, `valuables`, `mortgages`, and `other_liabilities`; `basis` is `nominal` or `real`; `effective_from` is a date.
- API service methods: `forUser(User $user): array`, `update(User $user, array $validated): array`, `reset(User $user): array`.

- [ ] **Step 1: Write failing ownership, validation, and reset feature tests**

Assert defaults include all eight categories, source `system_default`, basis `nominal`, and an effective date. Assert updates affect only the authenticated user's row, reject rates outside `-20..30`, reject invalid basis, and reset deletes only that user's override.

- [ ] **Step 2: Run the test and verify RED**

```bash
php vendor/pestphp/pest/bin/pest tests/Feature/Services/NetWorth/NetWorthForecastAssumptionServiceTest.php
```

- [ ] **Step 3: Add the focused table, model, and service**

Use a unique `user_id` foreign key with cascade delete, eight nullable `decimal(6,3)` rate columns, `basis`, `effective_from`, and timestamps. Keep defaults in the service and return every category as:

```php
[
    'rate_percent' => 3.0,
    'source' => 'system_default',
    'effective_from' => '2026-08-10',
    'basis' => 'nominal',
]
```

- [ ] **Step 4: Run the feature test and verify GREEN**

Run Step 2 again. Expected: PASS.

- [ ] **Step 5: Commit assumption persistence**

```bash
git add database/migrations/2026_08_10_200000_create_net_worth_forecast_assumptions_table.php app/Models/NetWorthForecastAssumption.php app/Services/NetWorth/NetWorthForecastAssumptionService.php tests/Feature/Services/NetWorth/NetWorthForecastAssumptionServiceTest.php
git commit -m "feat: persist net worth forecast assumptions"
```

### Task 4: Build the canonical Net Worth forecast contract

**Files:**
- Create: `app/Services/NetWorth/NetWorthForecastService.php`
- Create: `tests/Unit/Services/NetWorth/NetWorthForecastServiceTest.php`
- Modify: `app/Http/Controllers/Api/NetWorthController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/NetWorthForecastTest.php`

**Interfaces:**
- Consumes: `NetWorthService::calculateNetWorth(User $user)`, Task 3 assumptions, and known canonical contribution/repayment fields.
- Produces: `NetWorthForecastService::forecast(User $user, int $years = 30): array` with `contract_version = net_worth_forecast_v1`, `recorded_as_of`, `current`, `points`, `assumptions`, `warnings`, and `methodology`.
- API: `GET /api/net-worth/forecast`, `PUT /api/net-worth/forecast/assumptions`, `DELETE /api/net-worth/forecast/assumptions`.

- [ ] **Step 1: Write the failing pure forecast test**

Use literal starting categories and rates. Assert year zero equals the canonical recorded values and a one-year point equals the hand-calculated result:

```php
expect($result['points'][0]['net_worth'])->toBe(350000.0)
    ->and($result['points'][1]['categories']['property'])->toBe(515000.0)
    ->and($result['points'][1]['categories']['cash'])->toBe(20200.0)
    ->and($result['points'][1]['liabilities']['mortgages'])->toBe(194000.0);
```

Also assert `BalanceHistorySnapshot` rows are unchanged before and after forecasting.

- [ ] **Step 2: Run the focused tests and verify RED**

```bash
php vendor/pestphp/pest/bin/pest tests/Unit/Services/NetWorth/NetWorthForecastServiceTest.php tests/Feature/Api/NetWorthForecastTest.php
```

- [ ] **Step 3: Implement the forecast service and authenticated endpoints**

Apply each category rate independently. Add known annual contributions to cash, investments, and pensions after growth; subtract known principal repayments from mortgages and other liabilities without allowing negative balances. Return missing-input warnings instead of inferring unknown contributions or repayments. Never call the balance-history write service.

- [ ] **Step 4: Run forecast and history regression tests and verify GREEN**

```bash
php vendor/pestphp/pest/bin/pest tests/Unit/Services/NetWorth/NetWorthForecastServiceTest.php tests/Feature/Api/NetWorthForecastTest.php tests/Feature/History
```

- [ ] **Step 5: Commit the Net Worth forecast contract**

```bash
git add app/Services/NetWorth/NetWorthForecastService.php app/Http/Controllers/Api/NetWorthController.php routes/api.php tests/Unit/Services/NetWorth/NetWorthForecastServiceTest.php tests/Feature/Api/NetWorthForecastTest.php
git commit -m "feat: add canonical net worth forecast"
```

### Task 5: Render recorded and projected Net Worth separately on `/m`

**Files:**
- Create: `resources/mobile/components/NetWorthForecast.vue`
- Modify: `resources/mobile/views/modules/NetWorth.vue`
- Create: `tests/frontend/mobile/NetWorthForecast.test.js`
- Create: `tests/frontend/mobile/NetWorth.test.js`

**Interfaces:**
- Consumes: Task 4 endpoints only.
- Produces: a forward forecast section distinct from the existing recorded Balance History action, plus an assumptions editor/reset flow.

- [ ] **Step 1: Write failing component tests**

Assert the component labels “Recorded balance history” and “Projected net worth” separately, plots only forecast endpoint points in the forecast section, shows source/effective-date/basis for all assumptions, saves valid edits, renders server validation errors, and resets to server defaults.

- [ ] **Step 2: Run Vitest and verify RED**

```bash
PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:$PATH npm run test:run -- tests/frontend/mobile/NetWorth.test.js tests/frontend/mobile/NetWorthForecast.test.js
```

- [ ] **Step 3: Implement the `/m` forecast and editor**

Use `apiGet`, `apiPut`, and `apiDelete`; keep the existing `/net-worth/history` navigation unchanged. The chart/table uses returned points directly and the editor submits percent values exactly as displayed.

- [ ] **Step 4: Run tests and the mobile production build**

```bash
PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:$PATH npm run test:run -- tests/frontend/mobile/NetWorth.test.js tests/frontend/mobile/NetWorthForecast.test.js
PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:$PATH npm run build:mobile
```

- [ ] **Step 5: Commit `/m` Net Worth projections**

```bash
git add resources/mobile/components/NetWorthForecast.vue resources/mobile/views/modules/NetWorth.vue tests/frontend/mobile/NetWorth.test.js tests/frontend/mobile/NetWorthForecast.test.js
git commit -m "feat: separate recorded and projected net worth on mobile web"
```

### Task 6: Render recorded and projected Net Worth separately on iOS

**Files:**
- Create: `ios-native/Fynla/Features/NetWorth/NetWorthForecastModels.swift`
- Create: `ios-native/Fynla/Features/NetWorth/NetWorthForecastClient.swift`
- Create: `ios-native/Fynla/Features/NetWorth/NetWorthForecastModel.swift`
- Create: `ios-native/Fynla/Features/NetWorth/NetWorthForecastView.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthView.swift`
- Modify: `ios-native/Fynla/App/FynlaApp.swift`
- Modify: `ios-native/Fynla/App/AppRootView.swift`
- Create: `ios-native/FynlaTests/NetWorthForecastTests.swift`
- Modify: `ios-native/Fynla.xcodeproj/project.pbxproj`

**Interfaces:**
- Consumes: Task 4 contract and endpoints.
- Produces: native forecast state `.idle`, `.loading`, `.loaded`, `.saving`, `.offline(previous:)`, and `.failed`; recorded history remains owned by `BalanceHistoryModel`.

- [ ] **Step 1: Write failing decoding, endpoint, and stale-state tests**

Assert literal JSON decoding, exact `/fynla/api/net-worth/forecast` requests, assumption PUT/DELETE paths, preservation of the previous forecast when refresh goes offline, and category-specific edit values.

- [ ] **Step 2: Run Swift tests and verify RED**

```bash
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,id=94F2B841-2099-4291-88AB-EDAA797ADF75' -only-testing:FynlaTests/NetWorthForecastTests CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
```

- [ ] **Step 3: Implement models, client, observable model, and SwiftUI view**

Render the server's yearly points and assumption metadata. Provide native numeric fields with percent suffixes, Save, Reset to defaults, retry, stale/offline messaging, Dynamic Type-safe rows, and accessibility identifiers `net-worth.forecast`, `net-worth.forecast.assumptions`, and `net-worth.forecast.save`.

- [ ] **Step 4: Run focused Swift tests and Production build**

```bash
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,id=94F2B841-2099-4291-88AB-EDAA797ADF75' -only-testing:FynlaTests/NetWorthForecastTests CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
xcodebuild build -project ios-native/Fynla.xcodeproj -scheme Fynla-Production -destination 'generic/platform=iOS Simulator' CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO SWIFT_TREAT_WARNINGS_AS_ERRORS=YES
```

- [ ] **Step 5: Commit iOS Net Worth projections**

```bash
git add ios-native/Fynla/Features/NetWorth ios-native/Fynla/App ios-native/FynlaTests/NetWorthForecastTests.swift ios-native/Fynla.xcodeproj/project.pbxproj
git commit -m "feat: add native net worth forecasts"
```

### Task 7: Cross-client acceptance journey and regression closure

**Files:**
- Create: `ios-native/Fynla/Testing/ProjectionParityUITestSupport.swift`
- Modify: `ios-native/FynlaUITests/FynlaUITests.swift`
- Create: `tests/E2E/10-projection-parity.spec.js`
- Create: `docs/testing/2026-08-10-projection-parity-evidence.md`
- Modify: `ios-native/Fynla.xcodeproj/project.pbxproj`

**Interfaces:**
- Consumes: Tasks 1–6.
- Produces: deterministic same-household evidence for product reconciliation, age bands, 4.7% disclosure, recorded/forecast separation, and saved assumption parity.

- [ ] **Step 1: Add a failing deterministic XCUITest journey**

The journey must navigate Retirement, assert at least three commencement bands and the 4.7% disclosure, open Net Worth, distinguish recorded history from forecast, edit one property rate, save, reload, and assert the persisted server-shaped fixture value.

- [ ] **Step 2: Add the equivalent Google Chrome acceptance specification**

Use the same literal household and assertions. Configure any command-line support with installed Google Chrome (`channel: 'chrome'`); final acceptance remains interactive through the Chrome connector at exact `/fynla/m`.

- [ ] **Step 3: Run the visible iPhone 16 Pro journey**

```bash
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -configuration UITesting -destination 'platform=iOS Simulator,id=94F2B841-2099-4291-88AB-EDAA797ADF75' -only-testing:FynlaUITests/FynlaUITests/testPR5ProjectionParityJourney -parallel-testing-enabled NO CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
```

- [ ] **Step 4: Run full affected suites**

```bash
php vendor/pestphp/pest/bin/pest tests/Unit/Services/Retirement tests/Unit/Services/NetWorth tests/Feature/RetirementIntegrationTest.php tests/Feature/Api/NetWorthForecastTest.php tests/Feature/Services/NetWorth/NetWorthForecastAssumptionServiceTest.php tests/Feature/History
PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:$PATH npm run test:run
PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:$PATH npm run build:mobile
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,id=94F2B841-2099-4291-88AB-EDAA797ADF75' -skip-testing:FynlaTests/StoreKitTestTests CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
```

- [ ] **Step 5: Run installed-Chrome `/m` acceptance and record evidence**

Sign in to `https://csjones.co/fynla/m`, run the same Retirement and Net Worth workflow, capture route/state/screenshots and console errors, fix any defect test-first, and repeat until green.

- [ ] **Step 6: Commit acceptance evidence**

```bash
git add ios-native/Fynla/Testing/ProjectionParityUITestSupport.swift ios-native/FynlaUITests/FynlaUITests.swift tests/E2E/10-projection-parity.spec.js docs/testing/2026-08-10-projection-parity-evidence.md ios-native/Fynla.xcodeproj/project.pbxproj
git commit -m "test: add projection parity acceptance journey"
```

### Task 8: Publish PR5 and merge only when green

**Files:**
- Modify only files required by failures found in verification.

**Interfaces:**
- Produces: a clean, reviewable PR against `dev` with all checks and both user-style journeys green.

- [ ] **Step 1: Run final hygiene checks**

```bash
git diff --check
git status --short --branch
```

- [ ] **Step 2: Push and open the PR**

```bash
git push -u origin codex/ios-m-projections
gh pr create --base dev --head codex/ios-m-projections --title "feat: align projections across iOS and mobile web" --body-file /tmp/pr5-body.md --draft
```

- [ ] **Step 3: Monitor every CI gate and fix failures test-first**

```bash
gh pr checks --watch --interval 10
```

- [ ] **Step 4: Mark ready and merge after green acceptance**

```bash
gh pr ready
gh pr merge --merge
```

Expected: PR state `MERGED`, branch clean, and `origin/dev` contains the merge commit.
