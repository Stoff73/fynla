# Service Layer Conventions

This file supplements the root `CLAUDE.md` with backend service-specific patterns.

## Agent Pattern

All 9 module agents extend `BaseAgent` and implement three required methods:

```php
abstract public function analyze(int $userId): array;
abstract public function generateRecommendations(array $analysisData): array;
abstract public function buildScenarios(int $userId, array $parameters): array;
```

**Constructor injection** with `private readonly` dependencies:
```php
public function __construct(
    private readonly PortfolioAnalyzer $portfolioAnalyzer,
    private readonly TaxConfigService $taxConfig
) {}
```

**Response format:** Always use `$this->response(true, 'Message', ['data' => $result])` which returns `['success', 'message', 'data', 'timestamp']`.

**BaseAgent helpers:** `formatCurrency()`, `formatPercentage()` (via `FormatsCurrency` trait), `roundToPenny()`

**Caching:** Use `$this->remember($key, $ttl, $callback)` with auto-detection of tag support. Cache keys: `v1_{agent}_{userId}_{suffix}`. Invalidate with `invalidateUserCache($userId)`.

## Service Conventions

- **Single responsibility**: One calculation focus per service
- **Constructor injection**: All dependencies via `private readonly`
- **Pure methods**: Accept models/primitives, return arrays or scalars
- **Constants for magic numbers**: Growth rates, thresholds, multipliers as `private const`
- **Collection operations**: Heavy use of `$collection->sum()`, `->map()`, `->filter()`
- **Early returns**: For validation and edge cases

**Naming conventions:**
- Analysis: `PortfolioAnalyzer`, `CoverageGapAnalyzer`, `FeeAnalyzer`
- Calculations: `PensionProjector`, `RequiredCapitalCalculator`
- Engines: `RecommendationEngine`, `ScenarioBuilder`

## Directory Structure

Heavily-used modules have nested subdirectories; simpler modules are flat:

```
Services/             (214 services across 32 module directories)
  Investment/         (root files + 9 subdirectories)
    Analytics/, AssetLocation/, Fees/, Goals/, ModelPortfolio/,
    Performance/, Rebalancing/, Tax/, Utilities/
  Estate/             (22 services)
  Retirement/         (8 services)
  Protection/         (5 services, flat)
  Savings/            (5 services, flat)
  Coordination/       (5 services)
  Goals/              (12 services)
  TaxConfigService.php  (centralised tax lookups)
  UKTaxCalculator.php   (primary tax calculation engine)
```

## TaxConfigService

**Always use this for tax values. Never hardcode.**

```php
$taxConfig = app(TaxConfigService::class);

$personalAllowance = $taxConfig->get('income_tax.personal_allowance');
$nrb = $taxConfig->getInheritanceTax()['nil_rate_band'];
$isaAllowances = $taxConfig->getISAAllowances();
$pensionLimits = $taxConfig->getPensionAllowances();
$taxYear = $taxConfig->getTaxYear();  // '2025/26'
```

Loads active `TaxConfiguration` model (where `is_active = true`). Request-scoped singleton.

## Traits

| Trait | Purpose | Use When |
|-------|---------|----------|
| `Auditable` | Auto-audit create/update/delete via observers | On models needing change tracking |
| `HasJointOwnership` | Query scopes: `scopeForUserOrJoint()`, `scopeForUser()` | On models with `joint_owner_id` |
| `CalculatesOwnershipShare` | Calculate user's share of jointly-owned assets | In services computing net worth |
| `FormatsCurrency` | `formatCurrency()`, `formatCurrencyCompact()` | In services returning formatted output |
| `StructuredLogging` | `logInfo()`, `logError()`, `logCalculation()` with context | In services and controllers |
| `ResolvesExpenditure` | Resolve monthly expenditure from priority chain | In services needing user spending data |
| `ResolvesIncome` | Resolve gross/net annual income from priority chain | In services needing user income data |
| `TracksGoalContributions` | Auto-record goal contributions when linked account balances change | In goal-tracking observers |
| `PolicyCRUDTrait` | Common CRUD for protection policies with cache invalidation | In ProtectionController |
| `HasAiChat` | AI chat streaming, tool calling, system prompt building | In CoordinatingAgent |
| `HasAiGuardrails` | Token budgets, content filtering, rate limits for AI | In CoordinatingAgent |

## Constants

**TaxDefaults** - Fallback values when TaxConfigService unavailable (NRB: 325k, RNRB: 175k, ISA: 20k, PA: 12,570, etc.)

**ValidationLimits** - Input bounds (max currency: 999,999,999.99, max age: 125, max percentage: 100). Use `ValidationLimits::currencyRules()` and `ValidationLimits::percentageRules()` for consistent validation.

**EstateDefaults** - Conservative planning estimates (avg property: 300k, RNRB taper: 2M, default life expectancy: 85)

## Observers

Risk recalculation observers extend `RiskRecalculationObserver` and auto-trigger when relevant model fields change. They use **debouncing** (5-second cache window) to batch rapid changes before dispatching `RecalculateRiskProfileJob`.

Observers exist for: User, Property, InvestmentAccount, SavingsAccount, DCPension, FamilyMember (risk), InvestmentAccountGoal, SavingsAccountGoal (goal tracking), LifeEventMonteCarlo (Monte Carlo triggers).

## Exception Handling

Use `FinancialCalculationException` with factory methods:
```php
throw FinancialCalculationException::investmentCalculationError('Reason', ['context' => $data]);
throw FinancialCalculationException::missingData('field_name', ['user_id' => $id]);
throw FinancialCalculationException::taxConfigError('config_type', 'reason');
```

Available factories: `divisionByZero`, `missingData`, `invalidInput`, `taxConfigError`, `projectionError`, `ihtCalculationError`, `pensionCalculationError`, `investmentCalculationError`, `protectionCalculationError`, `insufficientData`, `timeout`.
