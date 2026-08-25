# Testing Infrastructure - Complete System Map

## 1. System Overview

Fynla uses a five-layer testing strategy covering the entire application stack from architectural constraints down to end-to-end user flows. The test suite comprises **6 categories** across **3 runtimes**:

| Category | Runtime | Framework | File Count | Purpose |
|----------|---------|-----------|------------|---------|
| Architecture | PHP | Pest (arch()) | 6 files | Enforce structural rules, naming conventions, dependency boundaries |
| Unit | PHP | Pest + PHPUnit | 53 files | Test individual services, agents, and models in isolation |
| Feature | PHP | Pest + PHPUnit | 37 files | Test API endpoints, authentication, controller behaviour with database |
| Integration | PHP | Pest | 2 files | Test cross-module workflows and data aggregation |
| E2E | JavaScript | Playwright | 6 spec files + 2 helpers | Test full user flows through the browser |
| Frontend | JavaScript | Vitest + Vue Test Utils | 36 test files + 1 setup | Test Vue component rendering, props, events, and computed properties |

**Total test files: ~140**

---

## 2. Test Configuration

### 2.1 Pest.php (`tests/Pest.php`)

The central Pest configuration file binds test case classes and traits to test directories:

- **Feature tests** (`tests/Feature/`): Uses `Tests\TestCase` + `RefreshDatabase` trait
- **Unit/Services tests** (`tests/Unit/Services/`): Uses `Tests\TestCase` + `RefreshDatabase` trait
- **Specific Agent tests** (`ProtectionAgentTest.php`, `SavingsAgentTest.php`, `GoalsAgentTest.php`): Uses `Tests\TestCase` + `RefreshDatabase` trait
- **BaseAgentTest**: Uses `Tests\TestCase` only (pure unit tests, no database)
- **Integration tests** (`tests/Integration/`): Uses `Tests\TestCase` + `RefreshDatabase` trait

**Global `beforeEach` hook**: Automatically ensures an active `TaxConfiguration` record exists before every test that touches the database. This runs for Feature, Unit/Services, specific Agent tests, and Integration tests. This prevents failures in services that depend on `TaxConfigService`.

**Custom expectation**: `toBeOne()` - asserts value is exactly `1`.

### 2.2 TestCase.php (`tests/TestCase.php`)

Minimal base class that extends `Illuminate\Foundation\Testing\TestCase` and uses the `CreatesApplication` trait. All PHP tests inherit from this class.

### 2.3 CreatesApplication Trait (`tests/CreatesApplication.php`)

Standard Laravel trait that bootstraps the application by requiring `bootstrap/app.php` and calling `Kernel::bootstrap()`.

### 2.4 PHPUnit Configuration (`phpunit.xml`)

Defines three test suites:
- **Unit**: `tests/Unit` directory
- **Feature**: `tests/Feature` directory
- **Architecture**: `tests/Architecture` directory

Environment overrides for testing:
- `APP_ENV=testing`
- `BCRYPT_ROUNDS=4` (faster hashing)
- `CACHE_DRIVER=array` (in-memory cache)
- `MAIL_MAILER=array` (captured, not sent)
- `QUEUE_CONNECTION=sync` (synchronous execution)
- `SESSION_DRIVER=array` (in-memory sessions)
- SQLite in-memory database is commented out; tests use the configured MySQL database with `RefreshDatabase`

---

## 3. Architecture Tests

Architecture tests use Pest's `arch()` function to enforce structural constraints at the code level. These tests never touch the database and run very quickly.

### 3.1 `tests/Architecture/ApplicationArchitectureTest.php`
The most comprehensive architecture test file, enforcing application-wide rules:

- **Controller hierarchy**: All API controllers in `App\Http\Controllers\Api` must extend `App\Http\Controllers\Controller`
- **Agent hierarchy**: All agent classes in `App\Agents` must extend `App\Agents\BaseAgent` (ignoring BaseAgent itself)
- **Model hierarchy**: All models in `App\Models` must extend Eloquent Model (ignoring User which extends Authenticatable)
- **HasFactory trait**: All models must use `HasFactory`
- **Form request convention**: All requests in `App\Http\Requests` must extend `FormRequest` and have suffix "Request"
- **No direct DB in controllers**: Controllers must not use `DB` facade directly (exceptions: IHTController, TaxSettingsController, DCPensionHoldingsController, PreviewController)
- **Strict types everywhere**: All agents and services must use `declare(strict_types=1)`
- **No deprecated functions**: Application must not use `mysql_query`, `mysql_connect`, `ereg`, `split`, `create_function`
- **No dangerous functions**: Application must not use `eval`, `exec`, `shell_exec`, `system`, `passthru` (exception: AdminController)
- **Clean models**: Models must not use Cache, Queue, or Mail facades
- **Module organization**: Services must be organized into module namespaces (Protection, Savings, Investment, Retirement, Estate, Coordination)
- **Agent methods**: All agents must have `analyze` method
- **Test naming**: All test classes must end with "Test" suffix

### 3.2 `tests/Architecture/BaseAgentTest.php`
Validates the BaseAgent contract:

- `BaseAgent` must be abstract
- Must have `analyze`, `generateRecommendations`, and `buildScenarios` methods
- All classes in `App\Agents` must be classes

### 3.3 `tests/Architecture/ProtectionArchitectureTest.php`
Protection module structural validation:

- `ProtectionAgent` must extend `BaseAgent`
- Protection services must be concrete classes in `App\Services\Protection`
- All 6 protection models (`ProtectionProfile`, `LifeInsurancePolicy`, `CriticalIllnessPolicy`, `IncomeProtectionPolicy`, `DisabilityPolicy`, `SicknessIllnessPolicy`) must have `user()` relationship method
- Protection form requests must extend `FormRequest`
- `ProtectionController` must exist as a class
- Strict types enforced on Protection services and ProtectionAgent

### 3.4 `tests/Architecture/Phase1ModelsArchitectureTest.php`
Validates Phase 1 model architecture (8 models: Household, FamilyMember, Property, Mortgage, BusinessInterest, Chattel, CashAccount, PersonalAccount):

- All must extend Eloquent Model
- All must use HasFactory trait
- All must use strict types
- Household must use HasMany relationships
- Property must use BelongsTo and HasMany relationships
- Mortgage must use BelongsTo relationships
- All must be in `App\Models` namespace
- None may use Cache, Queue, Mail, or Log facades

### 3.5 `tests/Architecture/Phase02ArchitectureTest.php`
Phase 2 architecture validation for UserProfile, FamilyMembers, and PersonalAccounts:

- Controllers (UserProfileController, FamilyMembersController, PersonalAccountsController) must extend base Controller
- Services and controllers must use strict types
- Form requests must exist (UpdatePersonalInfoRequest, UpdateIncomeOccupationRequest, StoreFamilyMemberRequest, UpdateFamilyMemberRequest, StorePersonalAccountLineItemRequest, UpdatePersonalAccountLineItemRequest)
- Controllers must not use `DB::table` directly (service layer pattern)
- Services must have proper return type declarations
- Naming conventions enforced (Controller suffix, Service suffix, Request suffix)
- Dependency injection verified

### 3.6 `tests/Architecture/Phase03ArchitectureTest.php`
Phase 3 architecture validation for NetWorth module:

- NetWorthController must extend base Controller
- NetWorthService must use strict types
- Controller must not use DB facade directly
- Service methods must have return type `array` (calculateNetWorth, getAssetBreakdown, getNetWorthTrend, getAssetsSummary, getJointAssets)
- Naming conventions enforced
- Dependency injection: NetWorthService must have CrossModuleAssetAggregator dependency
- Joint asset methods validated
- Cache implementation verified (Cache:: usage, net_worth key prefix)
- HTTP method correctness: GET for read operations, POST for cache refresh
- Required asset type calculation methods verified (calculateBusinessValue, calculateChattelValue, calculatePensionValue)
- Model imports verified (Property, InvestmentAccount, BusinessInterest, Chattel, CrossModuleAssetAggregator)

---

## 4. Unit Tests

### 4.1 Agents

| File | Description |
|------|-------------|
| `tests/Unit/Agents/BaseAgentTest.php` | Tests all protected BaseAgent utility methods via a TestableAgent subclass: `getUserCacheKey` (key generation format), `clearUserCache` (default and custom suffixes), `invalidateUserCache` (clears all known cache patterns), `invalidateCacheForUsers` (batch invalidation, null-safe), `formatCurrency` (pound sign, commas, decimals, negatives), `formatPercentage` (percent sign, decimal places), `calculatePercentageChange` (positive, negative, zero-division), `calculateCompoundGrowth` (1-year, multi-year, edge cases), `calculatePresentValue` (inverse of compound growth), `response` (success/failure structure with ISO 8601 timestamp), `getCurrentTaxYear` (UK tax year April 6 boundary), `validateRequired` (missing field exceptions), `calculateAge` (from string and DateTime, before/on birthday), `roundToPenny` (two decimal rounding) |
| `tests/Unit/Agents/ProtectionAgentTest.php` | Tests the ProtectionAgent with database (RefreshDatabase). Requires TaxConfiguration. |
| `tests/Unit/Agents/SavingsAgentTest.php` | Tests the SavingsAgent with database (RefreshDatabase). Requires TaxConfiguration. |
| `tests/Unit/Agents/GoalsAgentTest.php` | Tests the GoalsAgent with database (RefreshDatabase). Requires TaxConfiguration. |

### 4.2 Services/Auth

| File | Description |
|------|-------------|
| `tests/Unit/Services/Auth/LoginLockoutServiceTest.php` | Tests login lockout/rate limiting logic |
| `tests/Unit/Services/Auth/MFAServiceTest.php` | Tests multi-factor authentication service (TOTP generation, verification) |
| `tests/Unit/Services/Auth/PermissionServiceTest.php` | Tests permission/role checking service |
| `tests/Unit/Services/Auth/SessionServiceTest.php` | Tests session management and token handling |

### 4.3 Services/Estate

| File | Description |
|------|-------------|
| `tests/Unit/Services/Estate/IHTCalculatorTest.php` | Comprehensive IHT calculation tests with mocked TaxConfigService: `calculateIHTLiability` (NRB only, NRB+RNRB, spouse NRB transfer, RNRB taper for estates over 2m, zero IHT below NRB), `checkRNRBEligibility` (own home, no home, zero home value), `calculateCharitableReduction` (36% rate at 10%+ giving, 40% below), `applyTaperRelief` (full tax within 3 years, graduated relief 3-7 years, exempt after 7), `calculatePETLiability` (multiple gifts, NRB threshold, 7-year filter, non-PET filter) |
| `tests/Unit/Services/Estate/NetWorthAnalyzerTest.php` | Tests net worth calculation across all asset types |
| `tests/Unit/Services/Estate/AssetLiquidityAnalyzerTest.php` | Tests asset liquidity scoring and categorisation |
| `tests/Unit/Services/Estate/CashFlowProjectorTest.php` | Tests estate cash flow projection calculations |
| `tests/Unit/Services/Estate/FutureValueCalculatorTest.php` | Tests future value calculations for estate assets |
| `tests/Unit/Services/Estate/GiftingStrategyTest.php` | Tests gifting strategy analysis and recommendations |
| `tests/Unit/Services/Estate/IntestacyCalculatorTest.php` | Tests UK intestacy rules distribution calculations |
| `tests/Unit/Services/Estate/PersonalizedTrustStrategyServiceTest.php` | Tests personalised trust strategy recommendations |

### 4.4 Services/Investment

| File | Description |
|------|-------------|
| `tests/Unit/Services/Investment/MonteCarloSimulatorTest.php` | Tests Monte Carlo simulation engine: `simulate` (correct structure with summary/year_by_year/iterations, percentile calculation at 10th/25th/50th/75th/90th, increasing medians over time, monthly contribution impact, zero starting value), `generateNormalDistribution` (mean convergence, standard deviation proportionality), `calculateGoalProbability` (percentage calc, 100%, 0%, empty array), performance (10,000 iterations in under 10 seconds), edge cases (high volatility wide range, negative returns) |
| `tests/Unit/Services/Investment/AssetAllocationOptimizerTest.php` | Tests optimal asset allocation algorithms |
| `tests/Unit/Services/Investment/PortfolioAnalyzerTest.php` | Tests portfolio analysis calculations |
| `tests/Unit/Services/Investment/FeeAnalyzerTest.php` | Tests investment fee impact analysis |
| `tests/Unit/Services/Investment/DiversificationAnalyzerTest.php` | Tests portfolio diversification scoring |
| `tests/Unit/Services/Investment/ContributionOptimizerTest.php` | Tests contribution optimisation strategies |
| `tests/Unit/Services/Investment/TaxEfficiencyCalculatorTest.php` | Tests tax-efficient investment wrapper ordering |

### 4.5 Services/Protection

| File | Description |
|------|-------------|
| `tests/Unit/Services/Protection/RecommendationEngineTest.php` | Tests protection recommendation generation logic |
| `tests/Unit/Services/Protection/ScenarioBuilderTest.php` | Tests what-if scenario construction (death, illness, disability) |
| `tests/Unit/Services/Protection/CoverageGapAnalyzerTest.php` | Tests gap analysis between needs and existing coverage |
| `tests/Unit/Services/Protection/AdequacyScorerTest.php` | Tests protection adequacy scoring algorithm |

### 4.6 Services/Retirement

| File | Description |
|------|-------------|
| `tests/Unit/Services/Retirement/DecumulationPlannerTest.php` | Tests retirement decumulation planning |
| `tests/Unit/Services/Retirement/AnnualAllowanceCheckerTest.php` | Tests pension annual allowance calculations (tapering, MPAA) |
| `tests/Unit/Services/Retirement/PensionProjectorTest.php` | Tests pension fund value projections |
| `tests/Unit/Services/Retirement/RetirementProjectionServiceTest.php` | Tests overall retirement projection calculations |

### 4.7 Services/Savings

| File | Description |
|------|-------------|
| `tests/Unit/Services/Savings/EmergencyFundCalculatorTest.php` | Tests emergency fund target and runway calculations |
| `tests/Unit/Services/Savings/ISATrackerTest.php` | Tests ISA allowance tracking across accounts |
| `tests/Unit/Services/Savings/LiquidityAnalyzerTest.php` | Tests savings liquidity analysis |
| `tests/Unit/Services/Savings/GoalProgressCalculatorTest.php` | Tests savings goal progress calculations |

### 4.8 Services/Coordination

| File | Description |
|------|-------------|
| `tests/Unit/Services/Coordination/ConflictResolverTest.php` | Tests cross-module recommendation conflict resolution |
| `tests/Unit/Services/Coordination/RecommendationsAggregatorServiceTest.php` | Tests aggregation of recommendations across modules |

### 4.9 Services/Trust

| File | Description |
|------|-------------|
| `tests/Unit/Services/Trust/TrustAssetAggregatorServiceTest.php` | Tests trust asset aggregation |
| `tests/Unit/Services/Trust/IHTPeriodicChargeCalculatorTest.php` | Tests IHT periodic charge calculations for relevant property trusts |

### 4.10 Services/GDPR

| File | Description |
|------|-------------|
| `tests/Unit/Services/GDPR/ConsentServiceTest.php` | Tests GDPR consent management |
| `tests/Unit/Services/GDPR/DataExportServiceTest.php` | Tests GDPR data export (SAR) functionality |
| `tests/Unit/Services/GDPR/DataErasureServiceTest.php` | Tests GDPR right to erasure implementation |

### 4.11 Services/Audit

| File | Description |
|------|-------------|
| `tests/Unit/Services/Audit/AuditServiceTest.php` | Tests audit trail logging service |

### 4.12 Services/Risk

| File | Description |
|------|-------------|
| `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php` | Tests automatic risk profile calculation |

### 4.13 Other Service Tests

| File | Description |
|------|-------------|
| `tests/Unit/Services/MortgageServiceTest.php` | Tests mortgage calculation service |
| `tests/Unit/Services/TaxConfigServiceTest.php` | Tests TaxConfigService retrieval of UK tax parameters |
| `tests/Unit/Services/PropertyServiceTest.php` | Tests property service calculations |
| `tests/Unit/Services/PropertyTaxServiceTest.php` | Tests property tax calculations (SDLT, CGT, rental income tax) |
| `tests/Unit/Services/NetWorthServiceTest.php` | Tests net worth service calculations |
| `tests/Unit/Services/PersonalAccountsServiceTest.php` | Tests personal accounts (P&L, cashflow, balance sheet) |
| `tests/Unit/Services/ProfileCompletenessCheckerTest.php` | Tests profile completeness scoring |
| `tests/Unit/Services/UserProfileServiceTest.php` | Tests user profile service |

### 4.14 Model Tests

| File | Description |
|------|-------------|
| `tests/Unit/Models/UserDomicileTest.php` | Tests User model domicile-related functionality |

### 4.15 Other

| File | Description |
|------|-------------|
| `tests/Unit/Services/UserProfile/FinancialCommitmentsTest.php` | Tests financial commitments calculations |
| `tests/Unit/ExampleTest.php` | Laravel default example test (assertTrue) |

---

## 5. Feature Tests

### 5.1 API Tests

| File | Description |
|------|-------------|
| `tests/Feature/Api/PropertyControllerTest.php` | Full CRUD tests for properties: list (user isolation), create (main_residence, buy_to_let), show, update, delete, cross-user access denial (404), validation (invalid property_type, ownership_percentage > 100), SDLT calculation, CGT calculation, rental income tax calculation, authentication requirement. Uses PHPUnit class-based style with `setUp()`, factory creation, and Sanctum token auth. |
| `tests/Feature/Api/PersonalAccountsControllerTest.php` | Tests personal accounts CRUD and P&L/cashflow/balance sheet endpoints |
| `tests/Feature/Api/FamilyMembersControllerTest.php` | Tests family member CRUD operations |
| `tests/Feature/Api/DomicileInfoTest.php` | Tests domicile information endpoints |
| `tests/Feature/Api/CountryTrackingTest.php` | Tests country residence tracking |
| `tests/Feature/Api/ProfileCompletenessTest.php` | Tests profile completeness API endpoint |
| `tests/Feature/Api/RecommendationsControllerTest.php` | Tests cross-module recommendations API |
| `tests/Feature/Api/TrustsTest.php` | Tests trust CRUD and analysis endpoints |
| `tests/Feature/Api/MortgageControllerTest.php` | Tests mortgage CRUD operations |
| `tests/Feature/Api/UserProfileControllerTest.php` | Tests user profile update endpoints |
| `tests/Feature/Api/RetirementControllerTest.php` | Tests retirement module API endpoints |
| `tests/Feature/Api/InvestmentControllerTest.php` | Tests investment module API endpoints |
| `tests/Feature/Api/NetWorthControllerTest.php` | Tests net worth calculation endpoints |

### 5.2 Auth Tests

| File | Description |
|------|-------------|
| `tests/Feature/Auth/LoginTest.php` | Tests login flow: valid credentials (200 with token), token creation, invalid email (401 with user_not_found), invalid password (401), validation (422 for missing fields, invalid email format), multiple logins create multiple tokens. Uses `is_preview_user = true` to skip email verification in tests. |
| `tests/Feature/Auth/LogoutTest.php` | Tests logout and token revocation |
| `tests/Feature/Auth/RegistrationTest.php` | Tests user registration flow |
| `tests/Feature/Auth/AuthenticatedUserTest.php` | Tests authenticated user endpoint access |
| `tests/Feature/Auth/MFATest.php` | Tests MFA setup, verification, and recovery |
| `tests/Feature/Auth/SessionApiTest.php` | Tests session management endpoints |
| `tests/Feature/Auth/GDPRApiTest.php` | Tests GDPR consent, export, and erasure endpoints |

### 5.3 Dashboard Tests

| File | Description |
|------|-------------|
| `tests/Feature/Dashboard/DashboardApiTest.php` | Tests dashboard overview, health score, and alerts API endpoints |

### 5.4 Protection Tests

| File | Description |
|------|-------------|
| `tests/Feature/Protection/ProtectionApiTest.php` | Tests protection module full API (profile, policies, analysis) |
| `tests/Feature/Protection/ProtectionCacheInvalidationTest.php` | Tests that protection cache is properly invalidated when data changes |

### 5.5 Savings Tests

| File | Description |
|------|-------------|
| `tests/Feature/Savings/SavingsApiTest.php` | Tests savings module API endpoints |
| `tests/Feature/Savings/SavingsIntegrationTest.php` | Tests savings module integration (accounts + goals + ISA tracking) |

### 5.6 Estate Tests

| File | Description |
|------|-------------|
| `tests/Feature/Estate/EstateApiTest.php` | Tests estate module API endpoints |
| `tests/Feature/Estate/EstateIntegrationTest.php` | Tests estate module integration flows |

### 5.7 Investment Tests

| File | Description |
|------|-------------|
| `tests/Feature/InvestmentModuleTest.php` | Tests investment module feature set |
| `tests/Feature/PortfolioOptimizationTest.php` | Tests portfolio optimisation endpoint |

### 5.8 Retirement Tests

| File | Description |
|------|-------------|
| `tests/Feature/RetirementModuleTest.php` | Tests retirement module feature set |
| `tests/Feature/RetirementIntegrationTest.php` | Tests retirement module integration (pensions + projections + allowances) |

### 5.9 Risk Tests

| File | Description |
|------|-------------|
| `tests/Feature/Risk/RiskApiTest.php` | Tests risk profile API endpoints |

### 5.10 Security Tests

| File | Description |
|------|-------------|
| `tests/Feature/Security/UserMassAssignmentTest.php` | Verifies `is_admin` cannot be set via mass assignment. Two tests: (1) creating a User with `is_admin: true` in the data array does not set the flag, (2) updating a user with `is_admin: true` does not change the value. Critical security validation. |

### 5.11 Cross-Module Tests

| File | Description |
|------|-------------|
| `tests/Feature/CrossModuleIntegrationTest.php` | Tests cross-module data flows and coordination |
| `tests/Feature/AdminRBACTest.php` | Tests admin role-based access control |
| `tests/Feature/TaxConfigurationTest.php` | Tests tax configuration management |

### 5.12 Other

| File | Description |
|------|-------------|
| `tests/Feature/ExampleTest.php` | Laravel default example (GET / returns 200) |

---

## 6. Integration Tests

Integration tests validate cross-module workflows with real database interactions.

### 6.1 `tests/Integration/DashboardIntegrationTest.php`
Tests the `DashboardAggregator` service end-to-end (24 tests):

- **Data aggregation**: Verifies `aggregateOverviewData` returns data from all 5 modules (protection, savings, investment, retirement, estate)
- **Module summaries**: Each module summary includes expected keys (adequacy_score, emergency_fund_runway, portfolio_value, income_gap, net_worth)
- **Financial health score**: Validates composite score (0-100 range), breakdown includes all 5 modules, each module has score/weight/contribution, weights sum to 1.0, contributions sum to composite score
- **Weight validation**: Protection=20%, Emergency Fund=15%, Retirement=25%, Investment=20%, Estate=20%
- **Score labels**: Maps to Excellent/Good/Fair/Needs Improvement based on ranges
- **Alerts**: Validates aggregation from all modules, severity sorting (critical > important > info), required fields (id, module, severity, title, message, action_link, action_text, created_at)
- **Cache workflow**: First call caches, second call returns same data from cache
- **Cache invalidation**: POST to invalidate-cache clears all dashboard caches
- **Graceful degradation**: Handles partial module failures without crashing

### 6.2 `tests/Integration/ProtectionWorkflowTest.php`
Full protection planning journey integration test (5 tests):

- **Complete journey**: Creates user, creates protection profile, adds life/critical illness/income protection policies, retrieves all data (verifies structure), runs analysis (verifies adequacy_score, recommendations, scenarios for death/critical_illness/disability), updates policy, adds disability policy, deletes critical illness policy, verifies final database state
- **User isolation**: Two users with independent data; User 1 cannot see or delete User 2's policies (404)
- **Pre-analysis validation**: User without profile receives `success: false` from analysis endpoint
- **Comprehensive portfolio**: Creates all 5 policy types (life, critical illness, income protection, disability, sickness/illness), runs analysis, verifies gap categories (human_capital, debt_protection, education_funding, income_protection)
- **Profile updates and re-analysis**: Creates profile, runs analysis, updates profile (life changes), re-runs analysis, verifies score changed

---

## 7. E2E Tests (Playwright)

End-to-end tests use Playwright to drive a real browser against the running application. Each spec file registers a new user in `beforeEach`.

### 7.1 `tests/E2E/01-protection.spec.js` (8 tests)
- Load Protection dashboard (verify h1 and Coverage Score)
- Add Life Insurance policy (fill form, submit, verify policy card visible)
- Add Critical Illness policy (fill form, submit, verify no 422 error)
- Add Income Protection policy (fill form, submit, verify no errors)
- Navigate through all Protection tabs (Current Situation, Gap Analysis, Recommendations, What-If Scenarios, Policy Details)
- Display coverage adequacy gauge (radialbar chart visible)
- Display policy details after adding policy (navigate to Policy Details tab, verify provider name)
- Handle form validation errors (submit empty form, check for validation messages)

### 7.2 `tests/E2E/02-savings.spec.js` (6 tests)
- Load Savings dashboard (verify h1 and Emergency Fund)
- Add savings account (cash_isa type, balance, interest rate)
- Add savings goal (name, target, current amount)
- Display Emergency Fund gauge
- Display ISA allowance tracker
- Navigate through Savings tabs (Emergency Fund, Accounts, Goals, Recommendations)

### 7.3 `tests/E2E/03-investment.spec.js` (8 tests)
- Load Investment dashboard (verify h1 and Portfolio)
- Add investment account (stocks_shares_isa type)
- Add investment holding (after creating account first)
- Add investment goal (target amount and years)
- Display asset allocation chart
- Display performance chart
- Navigate through Investment tabs (Portfolio, Accounts, Holdings, Goals, Analysis)
- Run Monte Carlo simulation (add pension account, run simulation, check for results)

### 7.4 `tests/E2E/04-retirement.spec.js` (9 tests)
- Load Retirement dashboard (verify h1 and Readiness)
- Add DC pension (scheme name, fund value, contribution rates, salary)
- Add DB pension (scheme name, annual amount, retirement age)
- Add State Pension (amount and state pension age)
- Display retirement readiness gauge
- Display annual allowance tracker
- Navigate through Retirement tabs (Readiness, Pension Inventory, Contributions, Projections, Recommendations)
- Display income projection chart (after adding pension)
- Calculate contribution optimisation (after adding DC pension)

### 7.5 `tests/E2E/05-estate.spec.js` (9 tests)
- Load Estate dashboard (verify h1 and Net Worth)
- Add asset (property type with name and value)
- Add liability (mortgage type with balance, monthly payment, interest rate)
- Add gift (PET type with recipient and value)
- Display net worth calculation (after adding asset)
- Display IHT liability calculation
- Navigate to Cash Flow tab without hanging (verify loading completes)
- Navigate through all Estate tabs (Net Worth, IHT Planning, Gifting Strategy, Cash Flow, Assets & Liabilities, Recommendations)
- Display IHT waterfall chart (after adding high-value property)

### 7.6 `tests/E2E/06-profile-completeness.spec.js` (10 tests)
- Show completeness alert on Protection Dashboard for incomplete profile
- Show completeness alert on Estate Dashboard for incomplete profile
- Show missing fields in completeness alert (income, asset, protection, domicile)
- Have actionable link to complete profile
- Show completeness warning on Comprehensive Protection Plan (Generic Plan badge)
- Show completeness warning on Comprehensive Estate Plan
- Allow dismissing completeness alert
- Show higher completeness percentage after adding income
- Highlight married users missing spouse link
- Show recommendations in completeness alert

### 7.7 Helpers

**`tests/E2E/helpers/auth.js`**: Three functions:
- `login(page, email, password)` - Navigate to /login, fill credentials, wait for /dashboard
- `register(page, userData)` - Navigate to /register, fill form with timestamp-based unique email, wait for /dashboard, return user data
- `logout(page)` - Click logout button, wait for /login

**`tests/E2E/helpers/common.js`**: Utility functions:
- `waitForApiResponse(page, urlPattern)` - Wait for specific API response
- `fillField(page, selector, value)` - Clear and fill form field
- `selectOption(page, selector, value)` - Select dropdown option
- `clickAndWait(page, selector, timeout)` - Click and wait (default 3s)
- `takeScreenshot(page, name)` - Full-page screenshot with timestamp
- `isVisible(page, selector)` - Safe visibility check (returns false on error)
- `waitForLoading(page)` - Wait for `.animate-spin` to disappear
- `navigateToModule(page, module)` - Navigate to `/{module}` and wait for loading
- `formatCurrencyInput(amount)` - Convert number to string
- `generateEmail()` - Timestamp-based unique email
- `randomNumber(min, max)` - Random integer in range

---

## 8. Frontend Tests (Vitest)

Frontend tests use Vitest with jsdom environment and Vue Test Utils. They test Vue component rendering, computed properties, event emission, and user interactions.

### 8.1 Setup (`tests/frontend/setup.js`)
- Mocks `$route` (params, query) and `$router` (push, replace) globally
- Mocks `ApexCharts` class globally (render, updateOptions, updateSeries, destroy)
- Stubs `apexchart` Vue component with a simple div

### 8.2 Dashboard Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Dashboard/FinancialHealthScore.test.js` | Tests composite score calculation with weighted modules (Protection 20%, Emergency Fund 15%, Retirement 25%, Investment 20%, Estate 20%), score labels (Excellent/Good/Fair/Needs Improvement), SVG gauge dashOffset, toggle breakdown details, emergency fund score capping at 100, zero scores handling. Uses mock Vuex stores with namespaced modules. |
| `tests/frontend/components/Dashboard/NetWorthSummary.test.js` | Tests net worth summary display component |
| `tests/frontend/components/Dashboard/QuickActions.test.js` | Tests quick action buttons component |
| `tests/frontend/components/Dashboard/AlertsPanel.test.js` | Tests alerts panel display and interactions |
| `tests/frontend/views/Dashboard.test.js` | Tests Dashboard view component |

### 8.3 Protection Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Protection/PolicyCard.test.js` | Tests policy card: renders summary (provider, sum assured, premium), expand/collapse toggle, Edit and Delete buttons, delete confirmation modal, edit event emission with policy data, policy type formatting (snake_case to Title Case), smoker status display. |
| `tests/frontend/components/Protection/CoverageAdequacyGauge.test.js` | Tests coverage adequacy gauge rendering |
| `tests/frontend/components/Protection/ProtectionOverviewCard.test.js` | Tests protection overview card |
| `tests/frontend/components/Protection/RecommendationCard.test.js` | Tests recommendation card rendering |

### 8.4 Investment Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Investment/AccountCard.test.js` | Tests investment account card display |
| `tests/frontend/components/Investment/AccountForm.test.js` | Tests investment account form |
| `tests/frontend/components/Investment/AssetAllocationChart.test.js` | Tests asset allocation donut chart |
| `tests/frontend/components/Investment/GoalCard.test.js` | Tests investment goal card |
| `tests/frontend/components/Investment/HoldingsTable.test.js` | Tests holdings table component |
| `tests/frontend/components/Investment/InvestmentOverviewCard.test.js` | Tests investment overview card |

### 8.5 Retirement Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Retirement/AccumulationChart.test.js` | Tests pension accumulation chart |
| `tests/frontend/components/Retirement/DrawdownSimulator.test.js` | Tests retirement drawdown simulator |
| `tests/frontend/components/Retirement/IncomeProjectionChart.test.js` | Tests income projection chart |
| `tests/frontend/components/Retirement/PensionCard.test.js` | Tests pension card display |
| `tests/frontend/components/Retirement/RetirementOverviewCard.test.js` | Tests retirement overview card |
| `tests/frontend/components/Retirement/AnnualAllowanceTracker.test.js` | Tests annual allowance tracker component |

### 8.6 Savings Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Savings/ISAAllowanceTracker.test.js` | Tests ISA allowance tracker display |
| `tests/frontend/components/Savings/SavingsGoals.test.js` | Tests savings goals list component |
| `tests/frontend/components/Savings/EmergencyFundGauge.test.js` | Tests emergency fund gauge |
| `tests/frontend/components/Savings/SavingsOverviewCard.test.js` | Tests savings overview card |

### 8.7 Estate Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Estate/CashFlowProjectionChart.test.js` | Tests cash flow projection chart |
| `tests/frontend/components/Estate/GiftCard.test.js` | Tests gift card display |
| `tests/frontend/components/Estate/GiftingTimelineChart.test.js` | Tests gifting timeline chart |
| `tests/frontend/components/Estate/NRBRNRBTracker.test.js` | Tests NRB/RNRB tracker display |
| `tests/frontend/components/Estate/NetWorthWaterfallChart.test.js` | Tests net worth waterfall chart |
| `tests/frontend/components/Estate/EstateOverviewCard.test.js` | Tests estate overview card |
| `tests/frontend/components/Estate/IHTLiabilityGauge.test.js` | Tests IHT liability gauge |

### 8.8 Shared Components

| File | Description |
|------|-------------|
| `tests/frontend/components/Shared/ISAAllowanceSummary.test.js` | Tests shared ISA allowance summary component |

### 8.9 API Tests (Frontend)

| File | Description |
|------|-------------|
| `tests/frontend/api/protectionApi.test.js` | Tests Protection API via axios against running server: authentication, profile creation, policy CRUD (life, critical illness, income protection), coverage analysis, recommendations retrieval, what-if scenarios. Requires running server. |
| `tests/frontend/api/test-protection-api.sh` | Shell script to run protection API tests |

---

## 9. Test Factories

28 factory files across 3 directories, creating test data for all major models:

### 9.1 Root Factories (`database/factories/`)

| Factory | Model | Key Fields | States |
|---------|-------|------------|--------|
| `UserFactory` | User | first_name, middle_name, surname, email, password, marital_status | `unverified()` |
| `HouseholdFactory` | Household | household_name, notes | - |
| `FamilyMemberFactory` | FamilyMember | relationship, name, first_name, last_name, date_of_birth, gender, is_dependent | `child()`, `parent()`, `spouse()` |
| `PropertyFactory` | Property | property_type (main_residence/secondary_residence/buy_to_let), ownership_type, ownership_percentage, address fields, purchase_price, current_value, rental fields for BTL | - |
| `MortgageFactory` | Mortgage | property_id, lender_name, mortgage_type (repayment/interest_only/mixed), original_loan_amount, outstanding_balance, interest_rate, rate_type, monthly_payment, maturity_date | - |
| `BusinessInterestFactory` | BusinessInterest | business_name, company_number, business_type, ownership_type, current_valuation, annual_revenue/profit/dividend | - |
| `ChattelFactory` | Chattel | chattel_type (vehicle/art/antique/jewelry/collectible/other), name, ownership_type, current_value, vehicle-specific fields | - |
| `CashAccountFactory` | CashAccount | account_name, institution_name, account_type, ownership_type, current_balance, interest_rate, ISA fields | - |
| `PersonalAccountFactory` | PersonalAccount | account_type (profit_and_loss/cashflow/balance_sheet), line_item, category, amount | - |
| `SavingsAccountFactory` | SavingsAccount | account_type (easy_access/notice/fixed_rate), institution, current_balance, interest_rate, ISA fields | - |
| `SavingsGoalFactory` | SavingsGoal | goal_name, target_amount, current_saved, target_date, priority | - |
| `TaxConfigurationFactory` | TaxConfiguration | tax_year, effective_from/to, is_active, comprehensive config_data with all UK tax bands (income tax, NI, CGT, dividend, ISA, pension, IHT, gifting, stamp duty, trusts, assumptions, domicile) | `active()`, `forTaxYear()` |
| `ProtectionProfileFactory` | ProtectionProfile | annual_income, monthly_expenditure, mortgage_balance, other_debts, number_of_dependents, dependents_ages, retirement_age, occupation, smoker_status, health_status | - |
| `LifeInsurancePolicyFactory` | LifeInsurancePolicy | policy_type (term/whole_of_life/decreasing_term/family_income_benefit/level_term), provider, sum_assured, premium_amount, premium_frequency, in_trust | - |
| `CriticalIllnessPolicyFactory` | CriticalIllnessPolicy | policy_type (standalone/accelerated), provider, sum_assured, premium_amount, conditions_covered (array of 5-12 conditions) | - |
| `IncomeProtectionPolicyFactory` | IncomeProtectionPolicy | provider, benefit_amount, benefit_frequency, deferred_period_weeks, benefit_period_months, premium_amount, occupation_class | - |
| `DisabilityPolicyFactory` | DisabilityPolicy | provider, benefit_amount, deferred_period_weeks, premium_amount, coverage_type (accident_only/accident_and_sickness) | - |
| `SicknessIllnessPolicyFactory` | SicknessIllnessPolicy | provider, benefit_amount, benefit_frequency, conditions_covered (array), exclusions (string) | - |
| `RetirementProfileFactory` | RetirementProfile | current_age, target_retirement_age, current_annual_salary, target_retirement_income, essential/lifestyle expenditure, life_expectancy, risk_tolerance | - |
| `DCPensionFactory` | DCPension | scheme_name, scheme_type, provider, current_fund_value, employee/employer_contribution_percent, investment_strategy, platform_fee_percent, retirement_age | - |
| `DBPensionFactory` | DBPension | scheme_name, scheme_type, accrued_annual_pension, pensionable_service_years, normal_retirement_age, revaluation_method, spouse_pension_percent, inflation_protection | - |
| `StatePensionFactory` | StatePension | ni_years_completed, ni_years_required, state_pension_forecast_annual, state_pension_age, ni_gaps | - |

### 9.2 Investment Factories (`database/factories/Investment/`)

| Factory | Model | Key Fields | States |
|---------|-------|------------|--------|
| `InvestmentAccountFactory` | InvestmentAccount | account_type (isa/gia/onshore_bond/offshore_bond/vct/eis), provider, current_value, contributions_ytd, platform_fee_percent, ownership_type | `isa()`, `gia()` |
| `InvestmentGoalFactory` | InvestmentGoal | goal_name, goal_type (retirement/education/wealth/home), target_amount, target_date, priority, is_essential | `retirement()`, `education()` |
| `RiskProfileFactory` | RiskProfile | risk_tolerance, capacity_for_loss_percent, time_horizon_years, knowledge_level, attitude_to_volatility, esg_preference | `cautious()`, `balanced()`, `adventurous()` |
| `HoldingFactory` | Holding | holdable_id/type (polymorphic), asset_type (equity/bond/fund/etf/alternative), security_name, ticker, isin, quantity, purchase/current_price, cost_basis, dividend_yield, ocf_percent | `forAccount()`, `equity()`, `bond()` |

### 9.3 Estate Factories (`database/factories/Estate/`)

| Factory | Model | Key Fields | States |
|---------|-------|------------|--------|
| `TrustFactory` | Trust | trust_name, trust_type (9 types), trust_creation_date, initial/current_value, trust-type-specific fields (discount, loan, life insurance), is_relevant_property_trust, beneficiaries, trustees | `relevantPropertyTrust()`, `bareTrust()`, `lifeInsuranceTrust()`, `loanTrust()`, `active()`, `inactive()` |
| `LiabilityFactory` | Liability | liability_type (9 types), ownership_type, current_balance, monthly_payment, interest_rate, maturity_date | `mortgage()`, `personalLoan()`, `creditCard()`, `studentLoan()`, `joint()`, `inTrust()` |

---

## 10. Test Commands & Scripts

### 10.1 PHP Tests (Pest/PHPUnit)

```bash
# Run ALL PHP tests (Unit + Feature + Architecture)
./vendor/bin/pest

# Run only unit tests
./vendor/bin/pest tests/Unit

# Run only feature tests
./vendor/bin/pest tests/Feature

# Run only architecture tests
./vendor/bin/pest tests/Architecture

# Run only integration tests
./vendor/bin/pest tests/Integration

# Run a single test file
./vendor/bin/pest tests/Unit/Services/Estate/IHTCalculatorTest.php

# Run tests matching a filter
./vendor/bin/pest --filter="IHT"

# Run tests with verbose output
./vendor/bin/pest --verbose

# Run tests in parallel (if supported)
./vendor/bin/pest --parallel
```

### 10.2 Frontend Tests (Vitest)

```bash
# Run all frontend tests in watch mode
npm test
# or
npx vitest

# Run all frontend tests once (CI mode)
npm run test:run
# or
npx vitest run

# Run a specific test file
npx vitest tests/frontend/components/Dashboard/FinancialHealthScore.test.js

# Run with coverage
npx vitest run --coverage
```

### 10.3 E2E Tests (Playwright)

```bash
# Install browsers (first time)
npx playwright install

# Run all E2E tests
npx playwright test

# Run a specific spec file
npx playwright test tests/E2E/01-protection.spec.js

# Run with headed browser (visible)
npx playwright test --headed

# Run with UI mode (interactive)
npx playwright test --ui

# Show HTML report
npx playwright show-report

# Debug mode
npx playwright test --debug
```

Note: E2E tests require the Laravel dev server running at `http://127.0.0.1:8000`. The Playwright config includes `webServer` configuration that auto-starts `php artisan serve` (reuses existing server in non-CI mode).

---

## 11. Configuration Files

### 11.1 `vitest.config.js`

```javascript
{
  plugins: [vue()],
  test: {
    globals: true,              // No need to import describe/it/expect
    environment: 'jsdom',       // Browser-like DOM environment
    setupFiles: ['./tests/frontend/setup.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      include: ['resources/js/**/*.{js,vue}'],
      exclude: ['resources/js/app.js', 'resources/js/bootstrap.js']
    }
  },
  resolve: {
    alias: {
      '@': './resources/js'      // Matches Vite production alias
    }
  }
}
```

### 11.2 `playwright.config.js`

```javascript
{
  testDir: './tests/e2e',
  timeout: 60000,                      // 60 seconds per test
  fullyParallel: false,                // Sequential execution
  forbidOnly: process.env.CI,          // Fail on .only in CI
  retries: process.env.CI ? 2 : 0,    // Retry on CI only
  workers: process.env.CI ? 1 : undefined,
  reporter: [['html'], ['list'], ['json', { outputFile: 'test-results/results.json' }]],
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },
  projects: [
    { name: 'chromium', use: devices['Desktop Chrome'] }
  ],
  webServer: {
    command: 'php artisan serve',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120000
  }
}
```

Key settings:
- Only tests on Chromium (no Firefox/Safari)
- Sequential execution (not parallel) - important for stateful tests
- Auto-starts Laravel server if not running
- Captures traces, screenshots, and video only on failures
- JSON results exported for CI consumption

### 11.3 `phpunit.xml`

Defines three test suites (Unit, Feature, Architecture), with environment overrides for testing (array cache, array mail, sync queue, array sessions, reduced bcrypt rounds).

---

## 12. Test Patterns & Conventions

### 12.1 Authentication in API Tests

Two patterns are used:

**Pattern 1: Sanctum Token (PHPUnit class-based tests)**
```php
$this->user = User::factory()->create();
$this->token = $this->user->createToken('test-token')->plainTextToken;
$response = $this->withToken($this->token)->getJson('/api/properties');
```

**Pattern 2: actingAs (Pest functional tests)**
```php
$user = User::factory()->create();
$response = $this->actingAs($user)->getJson('/api/dashboard');
```

### 12.2 Factory Usage Patterns

- Factories are used inline with `create()` for persistence or `make()` for instances
- `count(n)` for batch creation
- User ID is set explicitly when creating associated records: `Property::factory()->create(['user_id' => $this->user->id])`
- TaxConfiguration is auto-seeded via the global `beforeEach` in `Pest.php` or explicitly via `$this->seed(TaxConfigurationSeeder::class)`

### 12.3 Assertion Patterns

**PHP (Pest style)**
```php
expect($value)->toBe(500000.0)
    ->and($result['nrb'])->toBe(325000.0)
    ->and($result['rnrb'])->toBe(175000.0);
```

**PHP (PHPUnit style)**
```php
$response->assertStatus(200)
    ->assertJsonStructure(['success', 'data' => ['property' => ['id', 'current_value']]])
    ->assertJson(['success' => true]);
$this->assertDatabaseHas('properties', ['user_id' => $user->id]);
```

**JavaScript (Vitest)**
```javascript
expect(wrapper.vm.compositeScore).toBeGreaterThan(0);
expect(wrapper.vm.compositeScore).toBeLessThanOrEqual(100);
expect(wrapper.text()).toContain('Test Insurance Co');
```

### 12.4 Mock Patterns

- **Mockery**: Used for service mocks in unit tests (`Mockery::mock(TaxConfigService::class)`)
- **Cache facade mocking**: `Cache::shouldReceive('forget')->once()->with('key')`
- **Vuex store mocking**: Create mock stores with `createStore()` and namespaced module getters
- **ApexCharts mocking**: Global stub in `setup.js` prevents chart rendering errors

### 12.5 User Isolation Testing

A common pattern across Feature and Integration tests: create two users, verify User A cannot access User B's data (expects 404), and verify data integrity after cross-user access attempts.

### 12.6 Preview User Flag in Tests

Login tests set `is_preview_user = true` on factory-created users to bypass email verification requirements that would otherwise block test login flows.

### 12.7 E2E Test Pattern

Every E2E spec follows the same structure:
1. `test.beforeEach`: Register a fresh user via the `register()` helper
2. Navigate to module via `navigateToModule(page, 'module-name')`
3. Interact with UI elements using selectors
4. Assert visibility or absence of elements

---

## 13. Coverage by Module

| Module | Unit | Feature | Integration | E2E | Frontend |
|--------|------|---------|-------------|-----|----------|
| **Dashboard** | - | DashboardApiTest | DashboardIntegrationTest (24 tests) | - | FinancialHealthScore, NetWorthSummary, QuickActions, AlertsPanel, Dashboard view |
| **Protection** | RecommendationEngine, ScenarioBuilder, CoverageGapAnalyzer, AdequacyScorer, ProtectionAgent | ProtectionApiTest, CacheInvalidation | ProtectionWorkflowTest (5 tests) | 01-protection (8 tests), 06-profile-completeness (10 tests) | PolicyCard, CoverageAdequacyGauge, ProtectionOverviewCard, RecommendationCard, protectionApi |
| **Savings** | EmergencyFundCalculator, ISATracker, LiquidityAnalyzer, GoalProgressCalculator, SavingsAgent | SavingsApiTest, SavingsIntegration | - | 02-savings (6 tests) | ISAAllowanceTracker, SavingsGoals, EmergencyFundGauge, SavingsOverviewCard |
| **Investment** | MonteCarloSimulator, AssetAllocationOptimizer, PortfolioAnalyzer, FeeAnalyzer, DiversificationAnalyzer, ContributionOptimizer, TaxEfficiencyCalculator | InvestmentModule, PortfolioOptimization, InvestmentController | - | 03-investment (8 tests) | AccountCard, AccountForm, AssetAllocationChart, GoalCard, HoldingsTable, InvestmentOverviewCard |
| **Retirement** | DecumulationPlanner, AnnualAllowanceChecker, PensionProjector, RetirementProjectionService | RetirementModule, RetirementIntegration, RetirementController | - | 04-retirement (9 tests) | AccumulationChart, DrawdownSimulator, IncomeProjectionChart, PensionCard, RetirementOverviewCard, AnnualAllowanceTracker |
| **Estate** | IHTCalculator, NetWorthAnalyzer, AssetLiquidityAnalyzer, CashFlowProjector, FutureValueCalculator, GiftingStrategy, IntestacyCalculator, PersonalizedTrustStrategy | EstateApiTest, EstateIntegration | - | 05-estate (9 tests) | CashFlowProjectionChart, GiftCard, GiftingTimelineChart, NRBRNRBTracker, NetWorthWaterfallChart, EstateOverviewCard, IHTLiabilityGauge |
| **Coordination** | ConflictResolver, RecommendationsAggregator | CrossModuleIntegration, Recommendations | - | - | - |
| **Auth/Security** | LoginLockout, MFA, Permission, Session | Login, Logout, Registration, AuthenticatedUser, MFA, Session, GDPR, MassAssignment, AdminRBAC | - | - | - |
| **User Profile** | UserProfileService, ProfileCompleteness, FinancialCommitments | UserProfileController, ProfileCompleteness, FamilyMembers, PersonalAccounts, DomicileInfo, CountryTracking | - | 06-profile-completeness (10 tests) | - |
| **Trust** | TrustAssetAggregator, IHTPeriodicChargeCalculator | Trusts | - | - | - |
| **Net Worth** | NetWorthService | NetWorthController | - | - | - |
| **Property** | PropertyService, PropertyTaxService, MortgageService | PropertyController, MortgageController | - | - | - |
| **Risk** | AutoRiskCalculator | RiskApi | - | - | - |
| **Tax Config** | TaxConfigService | TaxConfiguration | - | - | - |
| **GDPR** | ConsentService, DataExportService, DataErasureService | GDPRApi | - | - | - |
| **Audit** | AuditService | - | - | - | - |
| **Shared** | - | - | - | - | ISAAllowanceSummary |

---

## 14. Known Gaps & Issues

### 14.1 Missing Test Coverage

1. **Goals & Life Events module**: The GoalsAgent has a unit test, but there are no Feature, Integration, E2E, or Frontend tests for the Goals & Life Events module UI or API endpoints.

2. **Coordinating Agent**: No direct unit test for the `CoordinatingAgent` class. Cross-module coordination is tested through the `CrossModuleIntegrationTest` and `RecommendationsAggregatorServiceTest`, but the agent itself lacks isolated tests.

3. **Frontend API layer tests**: Only `protectionApi.test.js` exists. No equivalent API integration tests for Savings, Investment, Retirement, or Estate modules.

4. **E2E Dashboard tests**: No E2E spec file tests the main dashboard user experience (health score display, alerts, module navigation from dashboard).

5. **E2E User Profile tests**: No dedicated E2E spec for the user profile editing flow (beyond what `06-profile-completeness.spec.js` covers indirectly).

6. **Coordination Frontend**: No Vue component tests for the Coordination module UI.

7. **Browser coverage**: E2E tests only run on Chromium. No Firefox or Safari testing is configured.

### 14.2 Test Configuration Notes

- SQLite in-memory database is commented out in `phpunit.xml`; tests use MySQL, which is slower but more production-accurate.
- The global `beforeEach` in `Pest.php` checks for TaxConfiguration existence before every test that uses the database, which adds a small overhead to every test.
- E2E tests use `page.waitForTimeout()` with hard-coded delays (1-5 seconds) rather than deterministic waits, which can cause flakiness on slow systems.

### 14.3 Architectural Notes

- `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php` are Laravel boilerplate examples that could be removed.
- The `something()` function placeholder in `Pest.php` is unused.
- `tests/frontend/api/protectionApi.test.js` requires a running server, making it unsuitable for standard CI without server orchestration.
- Some E2E tests use defensive `if` checks (`if (await button.isVisible())`) which silently pass when expected elements are missing, potentially hiding real failures.
