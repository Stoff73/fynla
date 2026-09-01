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

## Legal-sounding copy carries its source (W-0153)

Any user-facing sentence that states what the law is must name the provision that says
so. `WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE` read "A will cannot appoint its
own testator as executor" — a rule in Fynla's own voice the reader could not check —
while the powers-of-attorney side stated the same class of thing attributed and
paragraph-referenced. Nothing required attribution, so the two diverged in silence.

The test is **act, not object**: describe what the office or instrument *is*, cite the
provision, and let the consequence follow.

```php
// No.  An unattributable prohibition in Fynla's voice.
'A will cannot appoint its own testator as executor.'

// Yes. What the office is, cited, and the contradiction follows from it.
'An executor is the person who collects in the estate and administers it after the
 testator has died (Administration of Estates Act 1925, section 25), so a will naming
 its own testator is a contradiction Fynla cannot resolve for you.'
```

**Never invent a citation to satisfy this.** Where no express provision exists, the
statement is a warning that describes, not a refusal that prohibits — that is why the
three `LpaComplianceService` party-role conflicts stay warnings while the two statutory
limbs refuse (W-0145). An unattributable claim is a claim that should not be made.

Homes for this vocabulary: `LpaCheckPolicy`, `WillTypePolicy`, `WillDocumentService`.
A statement of a legal position belongs in one of them, once — never inline at a call
site, and never in a Vue file.

**A lint rule is deliberately not built.** A regex for legal-sounding copy fires on the
correct cases too, and a detector that cries wolf on attributed statements trains
reviewers to dismiss it. The failure this rule addresses was judgement, not detection.
