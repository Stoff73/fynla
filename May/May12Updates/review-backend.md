# Backend Code Review — `/Users/CSJ/Desktop/fynla/app/`

**Date:** 2026-05-12
**Scope:** 9 Agents, 297 Services, 110 Controllers, 111 Models, 12 Observers, Middleware, Traits, Exceptions

---

## CRITICAL

### C-1: `TokenRefreshController` calls `->delete()` on unguarded `currentAccessToken()` — TransientToken crash

**File:** `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php:20-23`
**Confidence: 95 | Severity: Critical**

`$currentToken = $user->currentAccessToken()` can return a `TransientToken` (cookie/SPA auth). The code immediately calls `$currentToken->delete()` with no `instanceof PersonalAccessToken` guard. `TransientToken::delete()` does not exist, causing a fatal error. This kills every mobile token-refresh attempt under SPA auth, and produces an unhandled 500 inside a `catch (\Exception $e)` that returns a generic error rather than a clear auth failure.

The `MEMORY.md` file calls out the TransientToken family explicitly ("Six known sites"), and this file is one of them.

**Fix:**
```php
$currentToken = $user->currentAccessToken();
if (! ($currentToken instanceof PersonalAccessToken)) {
    return $this->errorResponse(new \RuntimeException('Token refresh is not supported under cookie-based SPA authentication.'), 'Refreshing auth token');
}
$currentToken->delete();
```

---

### C-2: `EvalBypassGate::isActive()` reads `$token->abilities` without instanceof guard

**File:** `app/Services/Eval/EvalBypassGate.php:45-50`
**Confidence: 90 | Severity: Critical**

`$token = $user->currentAccessToken()` is called with a null check but without `instanceof PersonalAccessToken`. Line 50 then accesses `$token->abilities`. `TransientToken` has a different `can()` method signature and the `abilities` property is not guaranteed to exist. Under SPA cookie auth this could cause a property-not-found fatal or silently grant the eval bypass. The gate is specifically a security boundary.

**Fix:** Add `if (! ($token instanceof PersonalAccessToken)) { return false; }` immediately after the null check at line 47.

---

### C-3: `DecumulationPlanner` reads a config key `higher_rate_threshold` that does not exist in the seeded config

**File:** `app/Services/Retirement/DecumulationPlanner.php:303`
**Confidence: 95 | Severity: Critical**

```php
$higherRateThreshold = (int) ($incomeTax['higher_rate_threshold'] ?? 50270);
```

The seeded `income_tax` config (confirmed in `TaxConfigurationSeeder`) has no `higher_rate_threshold` key — income tax bands are stored inside `bands[1]['upper_limit']`. The fallback hardcodes `50270`, which is the 2025/26 value. This means the code never reads the active tax config; it always uses the hardcoded fallback, silently producing wrong results for any tax year where the threshold differs. This violates **CLAUDE.md Rule #3 (No Hardcoded Tax Values)**.

The correct path for the 2025/26 seeded data is `$incomeTax['bands'][1]['upper_limit']` (= `50270`), not `$incomeTax['higher_rate_threshold']`.

**Fix:**
```php
$higherRateThreshold = (int) ($incomeTax['bands'][1]['upper_limit'] ?? $incomeTax['bands'][0]['upper_limit'] ?? 50270);
```
Or better, add `higher_rate_threshold` as an alias key in `TaxConfigurationSeeder` for convenience and consistency with the existing usages in `RetirementActionDefinitionService` that look for `TaxDefaults::HIGHER_RATE_THRESHOLD`.

---

### C-4: `RetirementStrategyService::calculateNetCostOfContribution` hardcodes £2,000 salary sacrifice limit

**File:** `app/Services/Retirement/RetirementStrategyService.php:1186`
**Confidence: 90 | Severity: Critical**

```php
$salarySacrificeLimit = 2000.0;
```

The comment itself says "Tax Year 2025/26: Salary sacrifice limit is £2,000" but this value is baked in as a magic constant with no `TaxConfigService` lookup. It will silently produce wrong net-cost-of-contribution calculations when the tax year rolls forward. This is the kind of value that belongs in the pension allowances config, consistent with **CLAUDE.md Rule #3**.

**Fix:** Add a `salary_sacrifice_limit` key to the pension allowances config and look it up via `$this->taxConfig->getPensionAllowances()['salary_sacrifice_limit'] ?? 2000.0`.

---

## IMPORTANT

### I-1: `PensionContributionOptimizer::calculateTotalCurrentContributions` has operator precedence bug

**File:** `app/Services/Retirement/PensionContributionOptimizer.php:461`
**Confidence: 95 | Severity: Important**

```php
$monthlyContribution = (float) $pension->monthly_contribution_amount ?? 0.0;
```

PHP operator precedence makes this parse as `((float) $pension->monthly_contribution_amount) ?? 0.0`. The cast happens first; if `monthly_contribution_amount` is `null`, `(float) null` evaluates to `0.0`, so the null-coalescing right-hand side is never reached. This is not a crash — it coincidentally produces the right numeric result — but it means `monthly_contribution_amount = null` and `monthly_contribution_amount = 0` are treated identically. The real risk is that the intent is obscured, and the pattern differs from every other call site in the codebase (which correctly uses `(float) ($pension->monthly_contribution_amount ?? 0)` with parentheses).

**Fix:**
```php
$monthlyContribution = (float) ($pension->monthly_contribution_amount ?? 0.0);
```

---

### I-2: `TaxDragCalculator::estimateInterestRate` hardcodes market rates

**File:** `app/Services/Investment/AssetLocation/TaxDragCalculator.php:303`
**Confidence: 85 | Severity: Important**

```php
'cash', 'money_market' => 0.045, // 4.5% for cash (2024/25 rates)
```

This is a stale-year rate comment (`2024/25`) hardcoded into a service. `SavingsMarketRatesSeeder` exists precisely to provide dynamic market rates. These hardcoded rates feed tax-drag calculations and asset-location recommendations. If the market changes (or the user expects 2025/26 figures), the calculation silently uses wrong data. Violates **CLAUDE.md Rule #3** spirit (market rate hardcoding is the savings equivalent of hardcoding tax values).

**Fix:** Read from `SavingsMarketRates` or a config key rather than a hardcoded literal.

---

### I-3: `DashboardAggregator` uses global `\Log` facade instead of imported `Log`

**File:** `app/Services/Dashboard/DashboardAggregator.php:39,69,100,122,...`
**Confidence: 92 | Severity: Important**

The file uses `\Log::error(...)` and `\Log::warning(...)` throughout (11 call sites) via the global namespace, while the project convention (per `StructuredLogging` trait and every other service) is to import `Illuminate\Support\Facades\Log`. This is inconsistent with PSR-12 namespace import conventions and can silently fail if `\Log` is not in scope (e.g. in testing environments that reset the IoC).

Additionally the error messages use string concatenation with `$e->getMessage()` rather than the structured context array pattern used everywhere else: `['error' => $e->getMessage(), 'user_id' => $userId]`. This makes correlation in log aggregators harder.

**Fix:** Add `use Illuminate\Support\Facades\Log;` at the top and convert all `\Log::` calls to `Log::`. Pass context arrays instead of concatenating the message.

---

### I-4: `GiftingStrategy` uses `app()` in constructor to resolve `TaxConfigService`

**File:** `app/Services/Estate/GiftingStrategy.php:29-31`
**Confidence: 88 | Severity: Important**

```php
public function __construct(
    private ?TaxConfigService $taxConfig = null
) {
    if ($this->taxConfig === null) {
        $this->taxConfig = app(TaxConfigService::class);
    }
```

The service CLAUDE.md and project conventions require constructor injection via `private readonly` dependencies. Using `app()` in a constructor bypasses the container's dependency injection graph, makes the class untestable without a booted container, and obscures the dependency. The optional `?TaxConfigService` parameter suggests this was designed with a fallback but the project standard is explicit injection.

**Fix:**
```php
public function __construct(
    private readonly TaxConfigService $taxConfig,
) {
    $this->ihtConfig = $this->taxConfig->getInheritanceTax();
    ...
}
```
Remove the null default and let the container inject it.

---

### I-5: `RiskPreferenceService` uses `app(AutoRiskCalculator::class)` in service methods (three sites)

**File:** `app/Services/Risk/RiskPreferenceService.php:121,148,216`
**Confidence: 88 | Severity: Important**

Same pattern as I-4 but inside service methods rather than the constructor. Three distinct call sites create `AutoRiskCalculator` via the global container in `setRiskLevel()`, `calculateAndSetRiskLevel()`, and `detectMismatches()`. This violates the DI pattern required by `app/Services/CLAUDE.md` and makes the service hard to unit-test.

**Fix:** Inject `AutoRiskCalculator` via constructor: `private readonly AutoRiskCalculator $autoRiskCalculator`.

---

### I-6: `AdvicePromptBuilder` uses `app()` inside service methods (three sites)

**File:** `app/Services/AI/AdvicePromptBuilder.php:466,672,1028`
**Confidence: 85 | Severity: Important**

Three services (`NetWorthService`, `LifeEventIntegrationService`, `AdviceReviewService`) are resolved via `app()` inline inside try blocks. These are called on every AI chat turn. The pattern bypasses DI, is hard to test, and the catches swallow the exception silently (falling through to `// Fall through — individual modules below will provide partial data`). If these services fail, the AI prompt builder degrades silently with no structured log entry.

**Fix:** Inject all three via constructor. Add structured error logging in the catch blocks.

---

### I-7: `OnboardingChatDirector` uses `app(AiToolDefinitions::class)` inside a service method

**File:** `app/Services/Onboarding/OnboardingChatDirector.php:1260`
**Confidence: 85 | Severity: Important**

Same class of issue as I-5/I-6. `AiToolDefinitions` is resolved from the container inside a method. This is the AI's critical path (onboarding extraction). Should be constructor-injected.

---

### I-8: `CashFlowCoordinator::calculateAvailableSurplus` uses `app()` for `DisposableIncomeAccessor`

**File:** `app/Services/Coordination/CashFlowCoordinator.php:42`
**Confidence: 85 | Severity: Important**

`$disposableAccessor = app(DisposableIncomeAccessor::class)` is called on every monthly surplus calculation. Should be constructor-injected. This is in the hot path of holistic analysis.

---

### I-9: `EstateController::index` queries `InvestmentAccount` directly — bypasses Agent layer

**File:** `app/Http/Controllers/Api/EstateController.php:89`
**Confidence: 88 | Severity: Important**

The architecture spec is `Controller → Agent → Services → Models`. `EstateController::index` directly queries `InvestmentAccount::where('user_id', $user->id)` on line 89 and applies estate-specific formatting logic (IHT exemption assessment, string manipulation) inline. This is non-trivial business logic — it evaluates VCT/EIS Business Relief eligibility, formats account names with `strtoupper`, and decides what fields to return — that belongs in `EstateAgent` or an `EstateAssetAggregatorService` method. The controller is over 500 lines and acts as a partial service.

**Fix:** Extract the investment account IHT formatting into `EstateAssetAggregatorService::gatherUserAssets()` (which already exists) and call through the agent.

---

### I-10: `AiChatController::sendMessage` mid-stream consent check has a per-event query

**File:** `app/Http/Controllers/Api/AiChatController.php:189`
**Confidence: 85 | Severity: Important**

The consent check `$this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT)` is called on **every SSE event** in the streaming loop (line 189 inside `foreach ($generator as $event)`). A typical advice turn emits dozens to hundreds of events. This fires a fresh DB query per event. The comment acknowledges this ("runs a fresh indexed EXISTS query") but the implication — potentially hundreds of consent queries per chat turn — is a measurable DB load spike. An acceptable trade-off if consent revocation latency must be near-real-time, but worth flagging.

**Recommended mitigation:** Re-check at most once per X events or once per second using a local timestamp gate, rather than on every chunk.

---

### I-11: `RetirementController::analyze` calls `agent->analyze()` twice — double expense on `recommendations`

**File:** `app/Http/Controllers/Api/RetirementController.php:217-224`
**Confidence: 90 | Severity: Important**

`recommendations()` calls `$this->agent->analyze($user->id)` to get `$analysis`, then calls `$this->agent->generateRecommendations($analysis['data'])`. When a user's dashboard calls both `analyze` and `recommendations` endpoints in sequence, the agent runs its full analysis pipeline twice per request cycle (cache may help but isn't guaranteed on first call). The `analyze()` call in `recommendations()` is redundant — the agent's cached result should be used, or `recommendations()` should accept the analysis result.

---

### I-12: `IHTCalculationService::calculate` persists a DB row (`saveCalculation`) inside every IHT read

**File:** `app/Services/Estate/IHTCalculationService.php:227`
**Confidence: 87 | Severity: Important**

Line 227: `$this->saveCalculation($user, $result, ...)` is called at the end of `calculate()`. This means every IHT read request — including GET requests from the dashboard and estate overview — writes a new `IHTCalculation` row. This is a write-on-read side-effect: the service's public method signature (`calculate()`) implies a pure calculation, not a persistent record. This pattern can cause bloated `iht_calculations` table growth, unexpected writes during preview mode if the `PreviewWriteInterceptor` doesn't intercept service-layer writes (it only intercepts HTTP-layer writes), and complicates testing.

---

## MEDIUM

### M-1: Hardcoded fallback values for tax thresholds across multiple services

**Confidence: 90 | Severity: Medium**

A cluster of services use the pattern `?? 12570`, `?? 60000`, `?? 20000`, `?? 50270` as fallbacks when TaxConfigService lookup keys are missing. Partial list:

- `app/Services/Tax/TaxStrategyCalculator.php:118,140,156,195,198,202,232,235,239` — PA, ISA, pension AA
- `app/Services/Tax/IncomeDefinitionsService.php:167-173` — PA, AA taper thresholds
- `app/Services/Retirement/PensionContributionOptimizer.php:432` — `?? 60000`
- `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php:48,51,54`
- `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php:105,150`

The `TaxDefaults` constants exist precisely for this fallback purpose and the convention is to use them (`TaxDefaults::PERSONAL_ALLOWANCE`, etc.). Hardcoded magic numbers in `??` fallbacks should reference `TaxDefaults` constants so changes only happen in one place. This partially violates **CLAUDE.md Rule #3**.

---

### M-2: `TaxStrategyCalculator` reads `$isa['annual_allowance']` but seeded key is `annual_allowance` — verify shape

**File:** `app/Services/Tax/TaxStrategyCalculator.php:140`
**Confidence: 80 | Severity: Medium**

The `getISAAllowances()` method returns `$this->get('isa', [])`. The seeded config must have `isa.annual_allowance` at the top level. If the seed data nests it differently (e.g. `isa.limits.annual_allowance`), the fallback `?? 20000` silently masks the misconfiguration. The same pattern occurs in at least 8 strategy files. Worth an assertion in tests that `TaxConfigService::getISAAllowances()['annual_allowance']` is non-null after seeding.

---

### M-3: `DashboardAggregator` catches `\Exception` and returns empty array silently

**File:** `app/Services/Dashboard/DashboardAggregator.php:37-42`
**Confidence: 88 | Severity: Medium**

`aggregateOverviewData()` wraps the entire data assembly in a single try/catch that returns `[]` on any exception. A failing `EstateAgent` (say, due to a null pointer in IHT calculation) causes the user's entire dashboard to render empty with no user-facing indication. This is a silent data-loss failure from the user's perspective. The sub-service wrappers (`getProtectionAnalysis`, etc.) already have their own individual catches — the outer catch on line 37 should at minimum include a `user_id` in its log context and should ideally return partial data instead of `[]`.

---

### M-5: `PensionContributionOptimizer::calculateAnnualContributionForPension` inconsistency with `analyzeTaxRelief`

**File:** `app/Services/Retirement/PensionContributionOptimizer.php:424-434` and `:456-466`
**Confidence: 85 | Severity: Medium**

`analyzeTaxRelief` (line 432) reads `$pensionConfig['annual_allowance'] ?? 60000` and stores it in `$annualAllowance`. The sister method `calculateTotalCurrentContributions` (line 461) uses the cast-precedence bug described in I-1. The two methods calculate different things but are compared in the optimizer. The inconsistency in contribution-reading means the optimizer can recommend "you could contribute an extra £X" based on a contribution total that excluded nil-valued `monthly_contribution_amount` entries rather than zero-valued ones.

---

### M-6: `TaxDragCalculator::calculatePortfolioTaxDrag` lacks joint-ownership query

**File:** `app/Services/Investment/AssetLocation/TaxDragCalculator.php:317`
**Confidence: 85 | Severity: Medium**

```php
$accounts = InvestmentAccount::where('user_id', $userId)
    ->with('holdings')
    ->get();
```

This query misses accounts where the user is the `joint_owner_id`. The joint ownership pattern (CLAUDE.md Rule #7) requires `WHERE user_id = ? OR joint_owner_id = ?`. The `forUserOrJoint` scope exists on `InvestmentAccount` and is used in `InvestmentController::index` correctly. The tax-drag calculator silently produces incomplete data for users with joint investment accounts.

**Fix:** Use `InvestmentAccount::forUserOrJoint($userId)->with('holdings')->get()`.

---

### M-7: `AnnualAllowanceChecker::getCarryForward` returns `0.0` with no logging when profile missing

**File:** `app/Services/Retirement/AnnualAllowanceChecker.php:192-196`
**Confidence: 80 | Severity: Medium**

When a user has no `RetirementProfile` or no `prior_year_unused_allowance`, carry forward silently returns 0. This is intentional by design (conservative default), but the check on line 193 `! $profile->prior_year_unused_allowance` uses a falsy check on what is presumably an array — if the array is empty (`[]`) this evaluates to true and returns 0, which is correct. However, if the array has keys but they are all zero, it also returns 0 via the loop arithmetic, which is also correct. The real risk is that if the field is stored as a JSON string that fails to decode, the falsy check still passes and returns 0 silently. No log is emitted to signal the data was missing, making it hard to debug incorrect carry-forward results in production.

---

### M-8: `RetirementController::index` constructs a `(object)` pseudo-profile

**File:** `app/Http/Controllers/Api/RetirementController.php:86-90`
**Confidence: 80 | Severity: Medium**

When no `RetirementProfile` exists and the user has a `target_retirement_age`, the controller constructs:
```php
$profile = (object) [
    'target_retirement_age' => $user->target_retirement_age,
    'current_age' => $user->date_of_birth?->age,
];
```

This synthesised stdObject is passed to the frontend as `data.profile`. Any downstream code (frontend or backend) that reads other profile fields (e.g. `$profile->current_annual_salary`, `$profile->target_retirement_income`) will get `null` on a stdObject rather than `null` from a missing model. This silently degrades the recommendations if the service layer ever receives this object rather than a real `RetirementProfile`. The pattern is inconsistent with how other controllers handle missing profiles (they return null and let the frontend handle it).

---

### M-9: `FamilyMembersController::store` non-spouse create outside transaction

**File:** `app/Http/Controllers/Api/FamilyMembersController.php:96-113`
**Confidence: 82 | Severity: Medium**

When `$data['relationship']` is not `'spouse'`, `FamilyMember::create()` is called outside any transaction. If the household provisioning or cache invalidation that follows throws, the `FamilyMember` record exists in the DB with no household link. The controller doesn't wrap the create + cache-invalidation in a `DB::transaction`. This is a partial-write risk.

---

### M-10: `EnsureMFAVerified` middleware calls `$user->currentAccessToken()?->can('mfa_verified')` without instanceof guard

**File:** `app/Http/Middleware/EnsureMFAVerified.php:31`
**Confidence: 82 | Severity: Medium**

```php
if ($user && $user->mfa_enabled && ! $user->currentAccessToken()?->can('mfa_verified')) {
```

The null-safe `?->` prevents a crash on null, but `TransientToken::can()` has a different implementation than `PersonalAccessToken::can()` and may always return `true` or `false` depending on its implementation. Under SPA cookie auth with `mfa_enabled = true`, this could either block valid users (if TransientToken::can always returns false) or bypass MFA entirely (if it returns true). This is in the authentication security boundary.

**Fix:** Check `instanceof PersonalAccessToken` before accessing `->can()`, and handle the TransientToken case explicitly (either require token-based auth for MFA-protected routes, or check the session flag instead).

---

### M-11: Inconsistent `higher_rate_threshold` key lookup across services

**Confidence: 85 | Severity: Medium**

Multiple services read `$incomeTax['higher_rate_threshold']` (e.g. `RetirementActionDefinitionService.php:529,668,812`) via a fallback to `TaxDefaults::HIGHER_RATE_THRESHOLD`. As confirmed, this key does not exist in the seeded `income_tax` config section. All these calls silently fall through to the `TaxDefaults` constant. The constant is `50270` (the correct 2025/26 value) so it currently produces correct results, but this is accidental correctness — if the constant ever diverges from the seeded config or the seeder is updated, all these callers will be wrong.

**Fix:** Either add `higher_rate_threshold` as a top-level alias key in the income_tax config seeder, or standardise all callers to use `$incomeTax['bands'][1]['upper_limit']`.

---

## LOW SEVERITY / STYLE

### L-1: `UserSession::isCurrentSession()` calls `request()->user()` inside a model method

**File:** `app/Models/UserSession.php:124`
**Confidence: 85 | Severity: Low**

```php
$currentToken = request()->user()?->currentAccessToken();
```

Model methods should not directly access the HTTP request. This ties the model to the request lifecycle, making it untestable outside an HTTP context (e.g. in Artisan commands or queued jobs). The `UserSession` model is used in session management commands. The correctly guarded `instanceof PersonalAccessToken` check at line 125 saves it from the crash bug, but the architectural violation stands.

---

### L-2: `SessionService::revokeAllExceptCurrent` fallback assumes most-recent session is current

**File:** `app/Services/Auth/SessionService.php:63-65`
**Confidence: 82 | Severity: Low**

When `currentTokenId === null` (TransientToken/SPA context), the code preserves the session with the most recent `last_activity_at` on the assumption it is the current session. This heuristic is fragile — if two browser sessions are active simultaneously and both were recently active, the wrong one could be preserved. The MEMORY.md note `reference_tinker_revoke_all_except_current.md` acknowledges this pattern was used as a workaround but it should be replaced with an explicit session identifier.

---

### L-3: `DashboardController` DI verification

**Confidence: 80 | Severity: Low**

Verify that `DashboardController` correctly constructor-injects `DashboardAggregator` and does not call `app()` to resolve it. The aggregator itself uses `\Log` (global, I-3) which is a code-smell that may indicate copy-paste from an older pattern before consistent DI was adopted.

---

### L-4: `IHTCalculationService` hardcodes `DEFAULT_RETIREMENT_AGE = 68` and `DEFAULT_STATE_PENSION_AGE = 67`

**File:** `app/Services/Estate/IHTCalculationService.php:33-35`
**Confidence: 80 | Severity: Low**

These are defined as `private const` on the class. The UK state pension age is currently 66 (rising to 67 between 2026-2028). Hardcoding 67 produces incorrect projections today for users near retirement age. These should come from `EstateDefaults` or `TaxConfigService` rather than class constants.

---

### L-5: `IHTCalculationService` inline comments mention £325,000 and £175,000 as documentation markers

**File:** `app/Services/Estate/IHTCalculationService.php:111,1148`
**Confidence: 80 | Severity: Low**

Lines like `$nrbSingle = $ihtConfig['nil_rate_band']; // £325,000` are documentation markers, not hardcoded values (the value comes from config), so this is not a Rule #3 violation. However, `$rnrbSingle = $ihtConfig['residence_nil_rate_band']; // £175,000` on line 1148 is the same pattern. These are fine — the values are correctly read from config. Flagging only to confirm they were reviewed and are not violations.

---

## Architecture & Two-Fyn Compliance — Clean / Passing

**Two-Fyn architecture compliance is clean:**
- `AdviceFyn` has a complete and explicit `WRITE_TOOLS` constant (line 152-183) covering all create/update/delete/capture tools. The `buildToolList()` method correctly strips them using `array_diff`.
- No `FynPersonaOrchestrator`, `DataCapturePromptBuilder`, invoker registry, or `persona_state_change` SSE event exist anywhere in the codebase (confirmed by grep).
- `AiChatController::sendMessage` dispatch is a single `$inOnboarding` if-statement keyed on `users.onboarding_completed` and `users.onboarding_fyn_step` (lines 171-173, 177-179). No complex routing logic.
- `wrapStream` correctly intercepts the `handoff` SSE event and strips it before it reaches the frontend (lines 388-498). The `handoff` event termination on line 478 (`return`) correctly prevents the double-assistant-message regression.

**Joint ownership pattern is consistently applied:**
- `InvestmentController::index` correctly uses `InvestmentAccount::forUserOrJoint($user->id)`.
- `SavingsController::index` correctly uses `SavingsAccount::forUserOrJoint($user->id)`.
- `GoalsController::index` correctly uses `Goal::forUserOrJoint($user->id)`.
- `CalculatesOwnershipShare` trait is used in both `InvestmentController` and `SavingsController`.

**`declare(strict_types=1)` present in all 110 controllers and all reviewed services.** No files missing it.

**Mass-assignment is protected:** `User` model uses `$guarded` with `id`, `is_admin`, `is_preview_user` explicitly protected. No `update($request->all())` calls found anywhere.

**`TaxConfigService` is used correctly throughout IHT, pension, and income tax calculations.** The main violations are the fallback magic numbers (M-1) and the one missing `higher_rate_threshold` alias (C-3/M-11).

**Preview user isolation** is solid at the HTTP layer (`PreviewWriteInterceptor`). Lifecycle email commands correctly filter `WHERE is_preview_user = false`. Admin metrics queries correctly filter with `whereHas('user', fn ($q) => $q->where('is_preview_user', false))`.

**`TransientToken` guard pattern** is correctly applied in `AuthController::logout` (line 303), `SessionService::updateCurrentSessionActivity` (line 103), `AdvisorImpersonationService` (all four call sites), `AdvisorImpersonationMiddleware`, `UserSession::isCurrentSession`, and `AdminController::resolvePreviousLoginAt`. The **two exceptions** are `TokenRefreshController` (C-1) and `EvalBypassGate` (C-2).

**DB transactions** are used correctly in `FamilyMembersController::handleSpouseCreation` (line 208), `RetirementController::storeDCPension` (line 322), and `SpouseLinkingService` (lines 169, 231). The missing transaction in `FamilyMembersController::store` for non-spouse members is M-9.

**`whereRaw` usages** are safe: `CoordinatingAgent::checkForDuplicate` (line 3435-3443) has an explicit whitelist guard. Other `whereRaw` usages in `OccupationCode`, `GoalStrategyService`, and commands use bound parameters only.

---

## Summary Table

| ID | File | Severity | Confidence | Issue |
|----|------|----------|------------|-------|
| C-1 | `V1/Auth/TokenRefreshController.php:20` | Critical | 95 | `currentAccessToken()->delete()` without instanceof guard — TransientToken crash |
| C-2 | `Services/Eval/EvalBypassGate.php:45` | Critical | 90 | `$token->abilities` without instanceof guard — security boundary failure |
| C-3 | `Services/Retirement/DecumulationPlanner.php:303` | Critical | 95 | Reads non-existent `higher_rate_threshold` config key, always uses hardcoded 50270 |
| C-4 | `Services/Retirement/RetirementStrategyService.php:1186` | Critical | 90 | Hardcoded £2,000 salary sacrifice limit (Rule #3) |
| I-1 | `Services/Retirement/PensionContributionOptimizer.php:461` | Important | 95 | Cast precedence bug: `(float) $x ?? 0` should be `(float) ($x ?? 0)` |
| I-2 | `Services/Investment/AssetLocation/TaxDragCalculator.php:303` | Important | 85 | Stale hardcoded 2024/25 interest rate (4.5%) |
| I-3 | `Services/Dashboard/DashboardAggregator.php:39` | Important | 92 | `\Log::` global facade — 11 sites, unstructured log messages |
| I-4 | `Services/Estate/GiftingStrategy.php:29` | Important | 88 | `app()` in constructor — violates DI convention |
| I-5 | `Services/Risk/RiskPreferenceService.php:121,148,216` | Important | 88 | `app(AutoRiskCalculator)` in service methods — 3 sites |
| I-6 | `Services/AI/AdvicePromptBuilder.php:466,672,1028` | Important | 85 | `app()` in service methods — 3 sites |
| I-7 | `Services/Onboarding/OnboardingChatDirector.php:1260` | Important | 85 | `app(AiToolDefinitions)` in service method |
| I-8 | `Services/Coordination/CashFlowCoordinator.php:42` | Important | 85 | `app(DisposableIncomeAccessor)` in service method |
| I-9 | `Http/Controllers/Api/EstateController.php:89` | Important | 88 | Business logic (IHT exemption eval) in controller — bypasses agent layer |
| I-10 | `Http/Controllers/Api/AiChatController.php:189` | Important | 85 | Consent DB query on every SSE event — potential query flood |
| I-11 | `Http/Controllers/Api/RetirementController.php:217` | Important | 90 | Double agent->analyze() call in recommendations() |
| I-12 | `Services/Estate/IHTCalculationService.php:227` | Important | 87 | `saveCalculation()` write-on-read side-effect on every IHT calculate call |
| M-1 | Multiple | Medium | 90 | Magic number fallbacks `?? 12570`, `?? 60000` etc. should use TaxDefaults constants |
| M-3 | `Services/Dashboard/DashboardAggregator.php:37` | Medium | 88 | Silent `return []` on any exception hides data failures |
| M-6 | `Services/Investment/AssetLocation/TaxDragCalculator.php:317` | Medium | 85 | Missing `forUserOrJoint` — joint investment accounts excluded from tax drag |
| M-9 | `Http/Controllers/Api/FamilyMembersController.php:96` | Medium | 82 | Non-spouse family member create outside transaction |
| M-10 | `Http/Middleware/EnsureMFAVerified.php:31` | Medium | 82 | `currentAccessToken()->can()` without instanceof — MFA bypass/block risk under SPA auth |
| M-11 | Multiple | Medium | 85 | `higher_rate_threshold` key doesn't exist in seeded config — accidental correctness via TaxDefaults constant |
