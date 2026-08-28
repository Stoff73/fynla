# Service Layer Conventions

Supplements the root `CLAUDE.md`. For projections, money bases and values that don't survive the journey to the screen, load the `data-integrity-traps` skill.

## Agent Pattern

Module agents extend `BaseAgent` and implement:

```php
abstract public function analyze(int $userId): array;
abstract public function generateRecommendations(array $analysisData): array;
abstract public function buildScenarios(int $userId, array $parameters): array;
```

- **Response format:** always `$this->response(true, 'Message', ['data' => $result])` → `['success', 'message', 'data', 'timestamp']`.
- **Caching:** `$this->remember($key, $ttl, $callback)` (auto-detects tag support). Keys `v1_{agent}_{userId}_{suffix}`. Invalidate with `invalidateUserCache($userId)`.
- Helpers from `FormatsCurrency`: `formatCurrency()`, `formatPercentage()`, `roundToPenny()`.

## Service Conventions

- Constructor injection, all dependencies `private readonly`.
- One calculation focus per service; pure methods taking models/primitives and returning arrays or scalars.
- Magic numbers (growth rates, thresholds, multipliers) as `private const`.
- Early returns for validation and edge cases.
- **Naming:** analysis → `*Analyzer` (`PortfolioAnalyzer`, `FeeAnalyzer`); calculations → `*Calculator` / `*Projector`; orchestration → `*Engine`, `*Builder`.

**Module-agnostic root files:** `TaxConfigService` (centralised tax lookups), `UKTaxCalculator` (primary tax engine), `TaxBandTracker` (band allocation across income sources), `PrerequisiteGateService` (onboarding readiness gates).

## TaxConfigService

**Always use this for tax values. Never hardcode** (Rule 2).

```php
$taxConfig = app(TaxConfigService::class);
$taxConfig->get('income_tax.personal_allowance');
$taxConfig->getInheritanceTax()['nil_rate_band'];
$taxConfig->getISAAllowances();
$taxConfig->getPensionAllowances();
$taxConfig->getTaxYear();   // '2026/27' — never hardcode the year either
```

Loads the active `TaxConfiguration` (`is_active = true`) as a request-scoped singleton.

## Traits

| Trait | Use when |
|---|---|
| `Auditable` | The model needs change tracking (create/update/delete via observers) |
| `HasJointOwnership` | The model has `joint_owner_id` — gives `scopeForUserOrJoint()`, `scopeForUser()` |
| `CalculatesOwnershipShare` | Computing a user's share of a jointly-owned asset |
| `CalculatesOCF` | Ongoing charges figure calculations for funds and platforms |
| `FormatsCurrency` | Returning formatted output |
| `StructuredLogging` | `logInfo()`, `logError()`, `logCalculation()` with context |
| `ResolvesExpenditure` / `ResolvesIncome` | Resolving spending or income from the priority chain |
| `TracksGoalContributions` | Recording goal contributions when linked balances change |
| `PolicyCRUDTrait` | Protection policy CRUD with cache invalidation |
| `HasAiChat` / `HasAiGuardrails` | AI streaming and tool calling / token budgets and rate limits (CoordinatingAgent) |

## Constants

- **`TaxDefaults`** — fallbacks when `TaxConfigService` is unavailable. Not a substitute for it.
- **`ValidationLimits`** — input bounds. Use `currencyRules()` and `percentageRules()` for consistency.
- **`EstateDefaults`** — conservative planning estimates (RNRB taper 2M, default life expectancy 85).

## Observers

Risk observers extend `RiskRecalculationObserver` and fire when relevant fields change. They **debounce over a 5-second cache window** before dispatching `RecalculateRiskProfileJob`. Coverage: risk (User, Property, InvestmentAccount, SavingsAccount, DCPension, FamilyMember), goal tracking, Monte Carlo triggers.

## Exceptions

```php
throw FinancialCalculationException::missingData('field_name', ['user_id' => $id]);
throw FinancialCalculationException::investmentCalculationError('Reason', ['context' => $data]);
```

Factories: `divisionByZero`, `missingData`, `invalidInput`, `taxConfigError`, `projectionError`, `ihtCalculationError`, `pensionCalculationError`, `investmentCalculationError`, `protectionCalculationError`, `insufficientData`, `timeout`.
