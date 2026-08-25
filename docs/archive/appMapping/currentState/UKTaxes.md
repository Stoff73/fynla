# UK Taxes Module - Current State Documentation

**Last updated:** 2026-02-19
**Module status:** Operational (reference + calculation layer, no dedicated agent)
**Tax year:** 2025/26 (active)

---

## 1. System Overview

The UK Taxes module is a cross-cutting infrastructure layer rather than a user-facing CRUD module. It provides:

1. **Tax Configuration Storage** - A database-backed system (`tax_configurations` table) storing comprehensive UK tax rules as a JSON blob (`config_data`), managed by admins via `TaxSettingsController`.
2. **Tax Configuration Service** - `TaxConfigService` is a request-scoped singleton that loads the active tax year's config_data and provides dot-notation access plus module-specific getters. It is consumed by 47+ services across all modules.
3. **Tax Calculation Engine** - `UKTaxCalculator` + `TaxBandTracker` provide income tax, NI, dividend tax, interest tax, and trust income tax calculations using a stack-based band allocation approach.
4. **Tax Product Reference Data** - Static reference data (`tax_product_reference` table) documenting the tax treatment of each investment/savings product type.
5. **Child Benefit Calculations** - `ChildBenefitService` calculates annual benefit and High Income Child Benefit Charge.
6. **ISA Allowance Tracking** - Per-user, per-tax-year ISA usage tracking (`isa_allowance_tracking` table).
7. **Frontend Reference Dashboard** - A read-only reference view (`UKTaxesDashboard.vue`) with 7 tabs showing all tax rates, bands, and calculation methodologies. **Uses entirely hardcoded data - does NOT fetch from the API.**
8. **Dashboard Cards** - Three dashboard cards (`UKTaxesAllowancesCard`, `UKTaxesOverviewCard`, `TaxOptimisationCard`) providing at-a-glance tax information and live allowance tracking.
9. **Frontend Constants** - `taxConfig.js` provides named exports of key tax values as fallbacks.

**Architecture distinction:** Unlike other modules, UK Taxes has no dedicated Agent. The `TaxConfigService` acts as the central hub, consumed directly by other module agents (EstateAgent, InvestmentAgent, CoordinatingAgent) and their services.

**Critical architecture gap:** Admin changes to tax rates via `TaxSettingsController` flow through `TaxConfigService` to PHP-side calculations BUT DO NOT automatically update Vue reference dashboards/cards which all have hardcoded values. The frontend is completely disconnected from the backend tax configuration.

---

## 2. Database Schema

### tax_configurations

```sql
CREATE TABLE `tax_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_year` varchar(10) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date NOT NULL,
  `config_data` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_configurations_tax_year_unique` (`tax_year`),
  KEY `tax_configurations_tax_year_index` (`tax_year`),
  KEY `tax_configurations_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### tax_configuration_audits

```sql
CREATE TABLE `tax_configuration_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_configuration_id` bigint unsigned NOT NULL,
  `changed_by_user_id` bigint unsigned DEFAULT NULL,
  `change_type` varchar(255) NOT NULL,
  `before_state` json DEFAULT NULL,
  `after_state` json NOT NULL,
  `changed_fields` json DEFAULT NULL,
  `rationale` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_configuration_audits_tax_configuration_id_index` (`tax_configuration_id`),
  KEY `tax_configuration_audits_changed_by_user_id_index` (`changed_by_user_id`),
  KEY `tax_configuration_audits_change_type_index` (`change_type`),
  KEY `tax_configuration_audits_created_at_index` (`created_at`),
  CONSTRAINT `tax_configuration_audits_changed_by_user_id_foreign` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_configuration_audits_tax_configuration_id_foreign` FOREIGN KEY (`tax_configuration_id`) REFERENCES `tax_configurations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### tax_product_reference

```sql
CREATE TABLE `tax_product_reference` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_category` varchar(255) NOT NULL,
  `product_type` varchar(255) NOT NULL,
  `tax_aspect` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `status` varchar(255) NOT NULL,
  `status_icon` varchar(255) DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_product_reference_product_category_product_type_index` (`product_category`,`product_type`),
  KEY `tax_product_reference_product_type_tax_aspect_index` (`product_type`,`tax_aspect`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### isa_allowance_tracking

```sql
CREATE TABLE `isa_allowance_tracking` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tax_year` varchar(255) NOT NULL,
  `cash_isa_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stocks_shares_isa_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `lisa_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_allowance` decimal(10,2) NOT NULL DEFAULT '20000.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isa_allowance_tracking_user_id_tax_year_unique` (`user_id`,`tax_year`),
  KEY `isa_tracking_tax_year_idx` (`tax_year`),
  CONSTRAINT `isa_allowance_tracking_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Models

### TaxConfiguration (`app/Models/TaxConfiguration.php`)

**Table:** `tax_configurations`

**Fillable:** `tax_year`, `effective_from`, `effective_to`, `config_data`, `is_active`, `notes`

**Casts:**
- `effective_from` => `date`
- `effective_to` => `date`
- `config_data` => `array`
- `is_active` => `boolean`

**Relationships:** None defined (audit relationship is one-directional from TaxConfigurationAudit)

**Static Methods:**
- `getActive(): ?self` - Returns the single active tax configuration (`WHERE is_active = true`)
- `getByTaxYear(string $taxYear): ?self` - Returns config for a specific tax year

**Instance Methods:**
- `activate(): void` - Sets this config as active and deactivates all others (non-transactional)

---

### TaxConfigurationAudit (`app/Models/TaxConfigurationAudit.php`)

**Table:** `tax_configuration_audits`

**Fillable:** `tax_configuration_id`, `changed_by_user_id`, `change_type`, `before_state`, `after_state`, `changed_fields`, `rationale`, `ip_address`

**Casts:**
- `before_state` => `array`
- `after_state` => `array`
- `changed_fields` => `array`

**Constants:**
```php
CHANGE_TYPES = ['created', 'updated', 'activated', 'deactivated', 'duplicated']
```

**Relationships:**
- `taxConfiguration(): BelongsTo` -> `TaxConfiguration`
- `changedBy(): BelongsTo` -> `User` (via `changed_by_user_id`)

**Static Methods:**
- `log(TaxConfiguration $config, string $changeType, ?array $beforeState, ?int $userId, ?string $rationale, ?string $ipAddress): self` - Creates audit record with recursive diff

**Private Static Methods:**
- `calculateChangedFields(array $before, array $after, string $prefix = ''): array` - Recursive diff producing `[{field, type, from?, to?}]` entries where type is `added`, `modified`, or `removed`

**Accessors:**
- `getSummaryAttribute(): string` - Human-readable summary using match expression on change_type

---

### TaxProductReference (`app/Models/TaxProductReference.php`)

**Table:** `tax_product_reference`

**Fillable:** `product_category`, `product_type`, `tax_aspect`, `title`, `summary`, `status`, `status_icon`, `display_order`, `is_active`

**Casts:**
- `display_order` => `integer`
- `is_active` => `boolean`

**Constants:**
```php
STATUS_EXEMPT    = 'exempt'
STATUS_TAXABLE   = 'taxable'
STATUS_DEFERRED  = 'deferred'
STATUS_RELIEF    = 'relief'
STATUS_LIMIT     = 'limit'

CATEGORY_INVESTMENT = 'investment'
CATEGORY_SAVINGS    = 'savings'
```

**Static Methods:**
- `getForProductType(string $category, string $productType): Collection` - Active records for a specific product, ordered by display_order
- `getAllInvestmentTaxInfo(): Collection` - All active investment tax info
- `getAllSavingsTaxInfo(): Collection` - All active savings tax info
- `getProductTypes(string $category): array` - Distinct product types for a category
- `hasTaxExemptAspects(string $category, string $productType): bool` - Whether product has any exempt aspects
- `getTaxStatusSummary(string $category, string $productType): array` - Count of items by status `{exempt, taxable, deferred, relief}`

---

### ISAAllowanceTracking (`app/Models/ISAAllowanceTracking.php`)

**Table:** `isa_allowance_tracking`

**Fillable:** `user_id`, `tax_year`, `cash_isa_used`, `stocks_shares_isa_used`, `lisa_used`, `total_used`, `total_allowance`

**Casts:** All monetary fields cast to `decimal:2`

**Relationships:**
- `user(): BelongsTo` -> `User`

---

## 4. Controllers

### UKTaxesController (`app/Http/Controllers/Api/UKTaxesController.php`)

**Middleware:** `auth:sanctum`, `admin`
**Prefix:** `/api/uk-taxes`

A thin stub controller that returns a hardcoded confirmation response. Does not interact with any service or model.

| Method | Route | Parameters | Response |
|--------|-------|------------|----------|
| `index(): JsonResponse` | `GET /api/uk-taxes` | None | `{success: true, message: "...", data: {tax_year: "2025/26", note: "..."}}` |

---

### TaxSettingsController (`app/Http/Controllers/Api/TaxSettingsController.php`)

**Middleware:** `auth:sanctum`, `admin`
**Prefix:** `/api/tax-settings`
**Trait:** `SanitizedErrorResponse`

Full admin CRUD for tax configurations with audit logging.

| Method | Route | Parameters | Response |
|--------|-------|------------|----------|
| `getCurrent(): JsonResponse` | `GET /current` | None | `{success, data: {id, tax_year, effective_from, effective_to, is_active, ...config_data_flattened}}` |
| `getAll(): JsonResponse` | `GET /all` | None | `{success, data: [TaxConfiguration...]}` ordered by effective_from desc |
| `getCalculations(): JsonResponse` | `GET /calculations` | None | `{success, data: {income_tax, national_insurance, inheritance_tax, capital_gains_tax, pension_allowances, isa_allowances}}` - hardcoded calculation explanations |
| `create(StoreTaxConfigurationRequest): JsonResponse` | `POST /create` | Request body validated | `{success, data: TaxConfiguration}` 201 |
| `update(Request, int $id): JsonResponse` | `PUT /{id}` | `tax_year?`, `effective_from?`, `effective_to?`, `config_data?`, `is_active?`, `rationale?` | `{success, data: TaxConfiguration}` |
| `setActive(Request, int $id): JsonResponse` | `POST /{id}/activate` | `rationale?` | `{success, data: TaxConfiguration}` |
| `duplicate(Request, int $id): JsonResponse` | `POST /{id}/duplicate` | `new_tax_year` (required, format YYYY/YY, unique), `effective_from`, `effective_to` | `{success, data: TaxConfiguration}` 201 |
| `delete(int $id): JsonResponse` | `DELETE /{id}` | None | `{success}` - blocks deletion of active config |

**Private Methods:**
- `logAudit(TaxConfiguration, string $changeType, ?array $beforeState, ?string $rationale): void` - Delegates to `TaxConfigurationAudit::log()`

**Key behaviour:**
- `getCurrent()` flattens `config_data` into the top-level response via `array_merge`
- `update()` and `setActive()` both deactivate all other configs when activating
- `delete()` returns 403 if attempting to delete the active configuration
- All mutations are wrapped in `DB::transaction()`
- `getCalculations()` returns hardcoded tax calculation explanations (not dynamic)

---

### TaxProductInfoController (`app/Http/Controllers/Api/TaxProductInfoController.php`)

**Middleware:** `auth:sanctum`
**Prefix:** `/api/tax-info`
**Dependency:** Injects `TaxProductInfoService`

| Method | Route | Parameters | Response |
|--------|-------|------------|----------|
| `getInvestmentTaxInfo(string $accountType): JsonResponse` | `GET /investment/{accountType}` | URL param: accountType (isa, gia, onshore_bond, etc.) | `{success, data: {product_type, product_type_label, tax_year, tax_items: [...], current_rates}}` |
| `getSavingsTaxInfo(Request, string $accountType): JsonResponse` | `GET /savings/{accountType}` | URL param: accountType, query: `is_isa` (boolean) | Same shape as above |
| `getTaxSummary(Request): JsonResponse` | `GET /summary` | Query: `category` (default: investment), `product_type` (default: isa) | `{success, data: {product_type, is_tax_advantaged, status_counts, primary_status}}` |

---

## 5. Agent

This module has no dedicated Agent. The `TaxConfigService` is consumed directly by:

- **EstateAgent** - For IHT calculations (NRB, RNRB, taper relief, gifting exemptions)
- **InvestmentAgent** - For CGT allowances, ISA limits, dividend tax rates
- **CoordinatingAgent** - For cross-module tax optimisation
- **RetirementAgent** (via services) - For pension allowances, income tax bands

The `TaxConfigService` functions as a shared infrastructure service rather than an agent-orchestrated module.

---

## 6. Services

### TaxConfigService (`app/Services/TaxConfigService.php`)

**Registration:** Not explicitly registered as a singleton in AppServiceProvider - relies on Laravel's default resolution (new instance per resolve). However, the internal `$config` cache makes it effectively request-scoped if the same instance is reused.

**Core access methods:**
- `getAll(): array` - Full config_data array
- `get(string $key, mixed $default = null): mixed` - Dot-notation access via `Arr::get()`
- `has(string $key): bool` - Check key existence
- `getTaxYear(): string` - e.g., '2025/26'
- `getEffectiveFrom(): string` - e.g., '2025-04-06'
- `getEffectiveTo(): string` - e.g., '2026-04-05'
- `isInCurrentTaxYear($date): bool` - Date range check
- `clearCache(): void` - Reset cached config (for testing)
- `getModel(): ?TaxConfiguration` - Underlying Eloquent model

**Module-specific getters (all return arrays):**
- `getIncomeTax()` - personal_allowance, bands, scotland config
- `getNationalInsurance()` - class_1, class_2, class_4 rates
- `getISAAllowances()` - annual_allowance, lifetime_isa, junior_isa
- `getPensionAllowances()` - annual_allowance, MPAA, tapered_allowance, state_pension
- `getInheritanceTax()` - NRB, RNRB, rates, PETs, CLTs, trust_charges, reliefs
- `getCapitalGainsTax()` - annual_exempt_amount, rates, BADR, trust rates
- `getDividendTax()` - allowance, rates, trust rates
- `getStampDuty()` - residential/non_residential bands
- `getGiftingExemptions()` - annual_exemption, small_gifts, wedding_gifts, normal_expenditure
- `getTrusts()` - entry_charge, exit_charge, periodic_charge
- `getAssumptions()` - investment_growth, inflation, salary_growth
- `getDomicile()` - uk_domiciled, non_uk_domiciled rules
- `getChildBenefit()` - weekly/annual rates, HICBC thresholds (with fallback defaults)

**IHT-specific getters:**
- `getPETRules()` - inheritance_tax.potentially_exempt_transfers
- `getCLTRules()` - inheritance_tax.chargeable_lifetime_transfers
- `getFourteenYearRule()` - inheritance_tax.fourteen_year_rule
- `getTrustCharges()` - inheritance_tax.trust_charges
- `getTaperRelief(string $type = 'pet'): array` - PET or CLT taper relief schedule
- `getGiftTaxRate(int|float $yearsSurvived, string $type = 'pet'): float` - Tax rate for a gift based on years survived
- `getBusinessRelief()` - rates, min_ownership_years, excluded_businesses
- `getAgriculturalRelief()` - rates, ownership requirements, caps
- `getQuickSuccessionRelief()` - relief rates by years
- `getNormalExpenditureFromIncome()` - conditions, evidence requirements

**Property/leasehold helpers:**
- `getPropertyOwnership(): array` - joint_ownership_types, leasehold_reform, tenure_types
- `getJointOwnershipType(?string $type = null): ?array` - Specific or all joint ownership types
- `getLeaseholdReform(): array` - ground_rent_abolished_date, valuation_thresholds
- `getLeaseholdValuationWarnings(int $remainingYears): array` - Warnings based on remaining years
- `hasSurvivorshipRights(string $jointOwnershipType): bool`
- `allowsWillOverride(string $jointOwnershipType): bool`

**Internal:**
- `loadActiveConfig(): array` - Loads from DB once, caches in `$this->config` for request lifetime. Throws `RuntimeException` if no active config found.

---

### UKTaxCalculator (`app/Services/UKTaxCalculator.php`)

**Dependency:** `TaxConfigService` (constructor injection)

**Public methods:**

```php
calculateDetailedNetIncome(
    float $employmentIncome = 0,
    float $selfEmploymentIncome = 0,
    float $rentalIncome = 0,
    float $pensionIncome = 0,
    float $trustIncome = 0,
    float $interestIncome = 0,
    float $dividendIncome = 0,
    ?string $trustType = null,
    float $pensionContributions = 0,
    float $section24Credit = 0
): array
```

Returns detailed per-income-type breakdowns with tax bands, NI, and Section 24 credit:
```
{
  income_breakdowns: [{income_type, income_type_label, gross_amount, income_components?, taxable_income?, tax_breakdown, ni_breakdown?, total_deductions, net_income}],
  section_24: {annual_credit, applied_credit} | null,
  summary: {total_gross_income, total_income_tax_before_credits, section_24_credit, total_income_tax, total_national_insurance, total_deductions, net_income, effective_tax_rate, monthly_net_income},
  tax_year: string
}
```

```php
calculateNetIncome(
    float $employmentIncome = 0,
    float $selfEmploymentIncome = 0,
    float $rentalIncome = 0,
    float $dividendIncome = 0,
    float $interestIncome = 0,
    float $otherIncome = 0
): array
```

Returns simplified net income calculation:
```
{gross_income, income_tax, national_insurance, total_deductions, net_income, effective_tax_rate, breakdown: {employment_income, self_employment_income, rental_income, dividend_income, interest_income, other_income, class_1_ni, class_4_ni}}
```

**Private methods:**
- `calculateClass1NIDetailed(float $employmentIncome): array` - Detailed Class 1 NI breakdown (main_rate + additional_rate)
- `calculateClass4NIDetailed(float $selfEmploymentIncome): array` - Detailed Class 4 NI breakdown
- `calculateInterestTaxDetailed(float $interestIncome, TaxBandTracker $tracker): array` - Interest tax with PSA consideration
- `calculateDividendTaxDetailed(float $dividendIncome, TaxBandTracker $tracker): array` - Dividend tax with allowance and special rates (8.75%/33.75%/39.35%)
- `calculateTrustIncomeTax(float $trustIncome, ?string $trustType, TaxBandTracker $tracker): array` - Trust income tax based on trust type with reclaim/owe calculations
- `getBeneficiaryMarginalRate(TaxBandTracker $tracker): float` - Marginal rate from band position
- `getMarginalRateLabel(float $rate): string` - Human-readable rate label
- `calculateIncomeTax(float $nonDividendNonInterestIncome, float $interestIncome, float $dividendIncome): float` - Legacy simplified calculation
- `calculateClass1NI(float $employmentIncome): float` - Legacy simplified Class 1 NI
- `calculateClass4NI(float $selfEmploymentIncome): float` - Legacy simplified Class 4 NI

**Business logic:**
- Uses stack-order allocation: employment income uses Personal Allowance first, then subsequent income types (interest, dividends, trust) are taxed at the remaining band position
- Pension contributions are deducted from employment income before tax
- Section 24 credit reduces total tax liability (not income)
- Trust income tax varies by trust type: discretionary (45%), interest_in_possession (20%), bare/settlor_interested (beneficiary's marginal rate)

---

### TaxBandTracker (`app/Services/TaxBandTracker.php`)

**Constructor:** `__construct(array $taxConfig)` - Takes income_tax config array

Tracks Personal Allowance and tax band consumption as income sources are stacked.

**Public methods:**
- `getRemainingPersonalAllowance(): float`
- `getRemainingBasicBand(): float`
- `getRemainingHigherBand(): float`
- `allocateIncome(float $income): array` - Allocates income to bands, consuming capacity. Returns `{personal_allowance_used, basic_rate: {taxable, tax, rate}, higher_rate: {taxable, tax, rate}, additional_rate: {taxable, tax, rate}, total_income_tax}`
- `getCurrentBandPosition(): string` - Returns 'personal_allowance', 'basic', 'higher', or 'additional'
- `getTotalAllocated(): float`
- `getConfig(): array` - Returns band thresholds and rates

**Key design:** Band consumption is stateful - each call to `allocateIncome()` permanently consumes band capacity for subsequent allocations. This enables correct stacking of income types.

---

### TaxProductInfoService (`app/Services/Tax/TaxProductInfoService.php`)

**Dependency:** `TaxConfigService`

**Public methods:**
- `getInvestmentTaxInfo(string $accountType): array` - Tax info for investment account types
- `getSavingsTaxInfo(string $accountType, bool $isIsa = false): array` - Tax info for savings account types
- `getTaxSummary(string $category, string $productType): array` - Quick summary with status counts

**Private methods:**
- `mapInvestmentProductType(string $accountType): string` - Maps account types to tax product types:
  - `stocks_and_shares_isa`, `isa` -> `isa`
  - `general_investment_account`, `gia` -> `gia`
  - `onshore_bond`, `investment_bond` -> `onshore_bond`
  - `offshore_bond` -> `offshore_bond`
  - `vct`, `venture_capital_trust` -> `vct`
  - `eis`, `enterprise_investment_scheme`, `seis`, `seed_enterprise_investment_scheme` -> `eis`
  - `nsi`, `premium_bonds`, `ns_i` -> `nsi`
  - default -> `other`

- `mapSavingsProductType(string $accountType, bool $isIsa): string` - Maps savings types:
  - If ISA: `junior_isa` -> `junior_isa`, `lifetime_isa`/`lisa` -> `lifetime_isa`, default -> `cash_isa`
  - If not ISA: `premium_bonds` -> `premium_bonds`, `nsi` -> `nsi`, `notice` -> `notice`, `fixed`/`fixed_rate` -> `fixed_rate`, default -> `easy_access`

- `buildTaxInfoResponse(Collection $references, string $productType): array` - Combines reference data with current rates
- `getCurrentRates(): array` - Extracts rates from TaxConfigService for template interpolation
- `interpolateRates(string $summary, array $rates): string` - Replaces `{isa_allowance}`, `{cgt_allowance}`, etc. in summary text with formatted values
- `getProductTypeLabel(string $productType): string` - Human-readable labels
- `determinePrimaryStatus(array $statusCounts): string` - Returns 'tax_advantaged', 'tax_deferred', 'relief_available', or 'fully_taxable'

---

### ChildBenefitService (`app/Services/Benefits/ChildBenefitService.php`)

**Dependency:** `TaxConfigService`

**Public methods:**
- `calculateAnnualChildBenefit(User $user): array` - Returns `{annual_amount, eligible_children_count, breakdown: [{child_id, child_name, is_eldest, annual_amount, weekly_amount}], eldest_child_name}`
- `calculateHICBC(float $adjustedNetIncome, float $childBenefitAmount): array` - Returns `{applies, charge, net_benefit, clawback_percentage, threshold, full_clawback_threshold?, income_over_threshold}`
- `getEligibleChildren(User $user): Collection` - Filters family members for `relationship in ['child', 'step_child'] AND receives_child_benefit = true`
- `calculateChildBenefitPosition(User $user, ?float $adjustedNetIncome = null): array` - Convenience method returning `{benefit, hicbc, net_annual_benefit}`

**Private methods:**
- `calculateAdjustedNetIncome(User $user): float` - Sums all income fields. **Known gap:** Does not deduct pension contributions or Gift Aid donations.

**HICBC formula:**
```
clawbackPercentage = min(100, (adjustedNetIncome - threshold) / increment)
```
Where threshold = 60000, increment = 200. Full clawback at 80000.

---

## 7. Validation Requests

### StoreTaxConfigurationRequest (`app/Http/Requests/StoreTaxConfigurationRequest.php`)

**Authorization:** `$this->user() && $this->user()->is_admin` (admin only)

**Rules:**
```php
'tax_year'                                          => 'required|string|regex:/^\d{4}\/\d{2}$/|unique:tax_configurations,tax_year,' . $this->route('id')
'effective_from'                                    => 'required|date'
'effective_to'                                      => 'required|date|after:effective_from'
'is_active'                                         => 'sometimes|boolean'
'config_data'                                       => 'required|array'
'config_data.income_tax'                            => 'required|array'
'config_data.income_tax.personal_allowance'         => 'required|numeric|min:0'
'config_data.income_tax.bands'                      => 'required|array|min:3'
'config_data.income_tax.bands.*.name'               => 'required|string'
'config_data.income_tax.bands.*.threshold'          => 'required|numeric|min:0'
'config_data.income_tax.bands.*.rate'               => 'required|numeric|min:0|max:1'
'config_data.national_insurance'                    => 'required|array'
'config_data.national_insurance.class_1_employee'   => 'required|array'
'config_data.national_insurance.class_1_employee.primary_threshold'    => 'required|numeric|min:0'
'config_data.national_insurance.class_1_employee.upper_earnings_limit' => 'required|numeric|min:0'
'config_data.national_insurance.class_1_employee.main_rate'            => 'required|numeric|min:0|max:1'
'config_data.national_insurance.class_1_employee.additional_rate'      => 'required|numeric|min:0|max:1'
'config_data.isa'                                   => 'required|array'
'config_data.isa.annual_allowance'                  => 'required|numeric|min:0'
'config_data.isa.lifetime_isa.annual_allowance'     => 'required|numeric|min:0'
'config_data.isa.junior_isa.annual_allowance'       => 'required|numeric|min:0'
'config_data.pension'                               => 'required|array'
'config_data.pension.annual_allowance'              => 'required|numeric|min:0'
'config_data.pension.mpaa'                          => 'required|numeric|min:0'
'config_data.pension.tapered_annual_allowance.threshold_income'          => 'required|numeric|min:0'
'config_data.pension.tapered_annual_allowance.adjusted_income_threshold' => 'required|numeric|min:0'
'config_data.pension.tapered_annual_allowance.minimum_allowance'         => 'required|numeric|min:0'
'config_data.inheritance_tax'                       => 'required|array'
'config_data.inheritance_tax.nil_rate_band'         => 'required|numeric|min:0'
'config_data.inheritance_tax.residence_nil_rate_band' => 'required|numeric|min:0'
'config_data.inheritance_tax.standard_rate'         => 'required|numeric|min:0|max:1'
'config_data.inheritance_tax.reduced_rate_charity'  => 'required|numeric|min:0|max:1'
'config_data.gifting_exemptions'                    => 'required|array'
'config_data.gifting_exemptions.annual_exemption'   => 'required|numeric|min:0'
'config_data.gifting_exemptions.small_gifts_limit'  => 'required|numeric|min:0'
'config_data.capital_gains_tax'                     => 'required|array'
'config_data.capital_gains_tax.annual_exempt_amount' => 'required|numeric|min:0'
'config_data.capital_gains_tax.basic_rate'          => 'required|numeric|min:0|max:1'
'config_data.capital_gains_tax.higher_rate'         => 'required|numeric|min:0|max:1'
'config_data.dividend_tax'                          => 'required|array'
'config_data.dividend_tax.allowance'                => 'required|numeric|min:0'
'config_data.dividend_tax.basic_rate'               => 'required|numeric|min:0|max:1'
'config_data.dividend_tax.higher_rate'              => 'required|numeric|min:0|max:1'
'config_data.dividend_tax.additional_rate'          => 'required|numeric|min:0|max:1'
```

**Custom messages:**
- `tax_year.regex` => 'Tax year must be in format YYYY/YY (e.g., 2025/26)'
- `effective_to.after` => 'Effective to date must be after effective from date'
- `config_data.income_tax.bands.min` => 'Income tax must have at least 3 bands (basic, higher, additional)'
- `*.rate.max` => 'Tax rates must be between 0 and 1 (use 0.20 for 20%)'

**Custom attributes:** Maps config_data paths to friendly names (personal allowance, pension annual allowance, nil rate band, residence nil rate band)

**Known issue:** Validation expects `config_data.national_insurance.class_1_employee` but the seeder stores `config_data.national_insurance.class_1.employee`. This mismatch means the validation request does not correctly validate the seeded data structure.

---

## 8. Vuex Store

**There is no dedicated Vuex store for UK Taxes.**

The `TaxOptimisationCard` reads from other module stores:
- `savings` store: `accounts` (state)
- `investment` store: `accounts` (state as `investmentAccounts`), `totalISAContributions` (getter)
- `retirement` store: `annualAllowance`, `dcPensions` (state)
- `userProfile` store: `totalAnnualIncome`, `incomeOccupation` (getters)

Tax limits are hardcoded in component `data()`:
```javascript
isaLimit: 20000,
pensionLimit: 60000,
cgtLimit: 3000,
dividendLimit: 500,
```

---

## 9. API Service (Frontend)

### taxSettingsService (`resources/js/services/taxSettingsService.js`)

Admin-only tax configuration management:

```javascript
getCurrent()                    // GET /tax-settings/current
getAll()                        // GET /tax-settings/all
getCalculations()               // GET /tax-settings/calculations
create(configData)              // POST /tax-settings/create
update(configId, configData)    // PUT /tax-settings/{configId}
setActive(configId)             // POST /tax-settings/{configId}/activate
duplicate(configId, data)       // POST /tax-settings/{configId}/duplicate
delete(configId)                // DELETE /tax-settings/{configId}
```

### taxInfoService (`resources/js/services/taxInfoService.js`)

User-facing tax product information:

```javascript
async getInvestmentTaxInfo(accountType)           // GET /tax-info/investment/{accountType}
async getSavingsTaxInfo(accountType, isIsa=false)  // GET /tax-info/savings/{accountType}?is_isa=...
async getTaxSummary(category, productType)         // GET /tax-info/summary?category=...&product_type=...
```

---

## 10. Frontend Components

### UKTaxesDashboard (`resources/js/views/UKTaxes/UKTaxesDashboard.vue`)

**Route:** `/uk-taxes` (admin only, requiresAuth + requiresAdmin)
**Components:** `AppLayout`, `CalculationsTab`

A 7-tab reference dashboard displaying all UK tax rates and bands for 2025/26.

**Tabs:**
1. `income` - Income Tax & National Insurance (bands table, Class 1 employee/employer NI)
2. `cgt` - Capital Gains Tax & Dividends (CGT rates by asset type, dividend tax rates)
3. `iht` - Inheritance Tax (NRB, RNRB, taper threshold, PET taper relief, gifting exemptions)
4. `pensions` - Pensions (Annual Allowance, MPAA, tapered allowance, carry forward, state pension)
5. `isas` - ISAs (annual allowance, LISA details, Junior ISA)
6. `other` - Other Allowances (marriage allowance, savings allowance, blind person's allowance, child benefit)
7. `calculations` - Calculation methodologies (via CalculationsTab component)

**Data:** Contains a complete hardcoded `taxConfig` object (not fetched from API).

**Methods:**
- `formatNumber(num)` - `num.toLocaleString('en-GB')`
- `getReliefColour(rate)` - Returns CSS class based on relief rate

**CRITICAL: The taxConfig object is entirely hardcoded in the component's setup() function. It does NOT call any API endpoint. Changes made through the admin TaxSettingsController have zero effect on this view.**

---

### CalculationsTab (`resources/js/components/UKTaxes/CalculationsTab.vue`)

**Name:** `CalculationsTab`
**Props:** None
**Emits:** None

A pure display component documenting calculation methodologies used throughout the application. Contains no dynamic data.

**Sections documented:**
1. Income Tax Calculation (PA taper, tax bands, worked example)
2. Inheritance Tax Calculation (gross estate, allowances, taxable estate, IHT liability)
3. Capital Gains Tax Calculation (gain, annual exempt amount, rates, worked example)
4. Pension Annual Allowance Calculation (base allowance, tapering, carry forward)
5. Emergency Fund Runway Calculation (essential expenses, runway months)
6. Investment Portfolio Allocation (current vs target, rebalancing threshold)
7. Retirement Readiness Score (projected income, target income, score)
8. Protection Coverage Gap Analysis (human capital, required coverage, gap)

---

### UKTaxesAllowancesCard (`resources/js/components/Dashboard/UKTaxesAllowancesCard.vue`)

**Name:** `UKTaxesAllowancesCard`
**Props:** None
**Emits:** None

A dashboard card that opens a full-screen modal with the same 7-tab tax reference content as UKTaxesDashboard. Contains its own complete hardcoded `taxConfig` object (identical copy of the one in UKTaxesDashboard).

**State:**
- `showModal: ref(false)`
- `activeTab: ref('income')`

**Methods:**
- `openModal()` / `closeModal()` - Toggle modal visibility
- `formatNumber(num)` / `getReliefColour(rate)` - Same as UKTaxesDashboard

**CRITICAL: Contains a second complete copy of the hardcoded taxConfig. Any rate changes require updating BOTH this component AND UKTaxesDashboard.**

---

### UKTaxesOverviewCard (`resources/js/components/Dashboard/UKTaxesOverviewCard.vue`)

**Name:** `UKTaxesOverviewCard`
**Props:** None
**Emits:** None

A lightweight teaser card displaying key figures with hardcoded values:
- Tax Year: 2025/26
- Personal Allowance: 12,570
- Tax-Free Savings Allowance: 20,000
- Pension Annual Allowance: 60,000

Badges: Income Tax (success), Capital Gains Tax (info), Inheritance Tax (warning), Pensions (secondary)

**Methods:**
- `navigateToModule()` - `this.$router.push('/uk-taxes')`

---

### TaxOptimisationCard (`resources/js/components/Dashboard/TaxOptimisationCard.vue`)

**Name:** `TaxOptimisationCard`
**Mixins:** `currencyMixin`

The only tax-related dashboard card that uses LIVE data. Shows progress bars for allowance usage with click-through navigation.

**Displayed allowances:**
1. **ISA** (always shown) - Combines investment ISA contributions (`totalISAContributions` getter) + savings ISA contributions (`contributions_ytd` from cash_isa/isa accounts)
2. **Pension** (always shown) - From `annualAllowance.total_contributions` or calculated from DC pensions
3. **Pension Carry Forward** (conditional) - Shown only when exceeding standard allowance
4. **Capital Gains Tax** (conditional) - Shown only if user has non-ISA investments (GIA/trading). Currently hardcoded to 0 (no disposal tracking)
5. **Dividend** (conditional) - Shown only if user has dividend income from `incomeOccupation.annual_dividend_income`

**Hardcoded limits in data():**
```javascript
isaLimit: 20000,
pensionLimit: 60000,
cgtLimit: 3000,
dividendLimit: 500,
```

**Key computed properties:**
- `hasDividendIncome` - Checks `incomeOccupation?.annual_dividend_income > 0`
- `hasNonIsaInvestments` - Checks for GIA/trading account types
- `currentTaxYear` / `carryForwardTaxYear` - Calculated from current date with April 6 boundary
- `isNearTaxYearEnd` - True if within 3 months of April 5
- `hasExpiringAllowances` - True if near year end AND significant ISA/CGT/dividend allowance unused
- `expiringMessage` - Lists expiring allowances with amounts

**Methods:**
- `navigateTo(route)` - Router push
- `getProgressBarClass(percent)` - Returns CSS class: >=90% green, >=50% primary, >=25% blue, else gray

---

## 11. Frontend Routing

| Path | Name | Component | Guards | Breadcrumb |
|------|------|-----------|--------|------------|
| `/uk-taxes` | `UKTaxes` | `UKTaxesDashboard` (lazy loaded) | `requiresAuth`, `requiresAdmin` | Home > UK Taxes & Allowances |

The route is admin-only. Regular users cannot access the UK Taxes dashboard directly, but they see tax information through:
- `TaxOptimisationCard` on the main dashboard
- `UKTaxesOverviewCard` on the main dashboard
- Tax product info via the Investment and Savings module UIs

---

## 12. Cross-Module Integration

The UK Taxes module is the most cross-cutting module in the system. `TaxConfigService` is consumed by **47 files** across all modules:

### Estate Planning (15 consumers)
- `EstateAgent` - IHT calculation orchestration
- `IHTCalculationService` / `IHTCalculator` - Estate tax liability
- `IHTStrategyGeneratorService` - IHT mitigation strategies
- `ComprehensiveEstatePlanService` - Full estate plan
- `WillAnalysisService` - Will impact on tax
- `TrustService` - Trust taxation
- `PersonalizedTrustStrategyService` - Trust strategy recommendations
- `GiftingStrategyOptimizer` / `GiftingStrategy` / `PersonalizedGiftingStrategyService` - Gifting tax treatment
- `CashFlowProjector` / `FutureValueCalculator` - Estate projections
- `SpouseNRBTrackerService` - NRB transfer tracking
- `IHTPeriodicChargeCalculator` (Trust module) - 10-year trust charges

### Investment (10 consumers)
- `InvestmentAgent` - Investment strategy
- `TaxEfficiencyCalculator` - Tax-efficient wrapper selection
- `CGTHarvestingCalculator` - Capital gains tax loss harvesting
- `BedAndISACalculator` - Bed and ISA strategy
- `ISAAllowanceOptimizer` - ISA contribution optimization
- `ContributionOptimizer` / `ContributionEstimatorService` - Contribution planning
- `AssetLocationOptimizer` / `TaxDragCalculator` - Asset location
- `TaxOptimizationAnalyzer` - Tax optimization analysis
- `PortfolioStrategyService` - Portfolio strategy

### Retirement (4 consumers)
- `RetirementIncomeService` - Retirement income tax
- `RetirementStrategyService` - Retirement strategy
- `AnnualAllowanceChecker` - Pension annual allowance
- `ContributionOptimizer` (Retirement) - Pension contributions

### Other Modules
- `CoordinatingAgent` - Cross-module coordination
- `ConflictResolver` (Coordination) - Cross-module conflict resolution
- `PropertyTaxService` (Property) - Stamp duty, rental income tax
- `ChildBenefitService` (Benefits) - Child benefit and HICBC
- `BusinessInterestService` (Business) - Business Relief
- `ChattelCGTService` (Chattel) - Chattel CGT rules
- `GoalAssignmentService` (Goals) - Tax considerations for goals
- `ISATracker` (Savings) - ISA allowance tracking
- `TaxDefaults` / `ValidationLimits` (Constants) - Default values

---

## 13. Profile Completeness

The UK Taxes module does **not** contribute to profile completeness scoring. It is a reference/infrastructure module, not a user data collection module.

However, tax-related data collected in other modules (income, pension contributions, ISA contributions) indirectly affects the accuracy of tax calculations performed by this module's services.

---

## 14. Seeder Data

### TaxConfigurationSeeder (`database/seeders/TaxConfigurationSeeder.php`)

**Command:** `php artisan db:seed --class=TaxConfigurationSeeder --force`

Seeds 5 UK tax years with comprehensive tax configuration:

| Tax Year | Active | State Pension | NI Main Rate | CGT Exempt | ISA Allowance |
|----------|--------|---------------|--------------|------------|---------------|
| 2021/22 | No | varies | varies | 12,300 | 20,000 |
| 2022/23 | No | varies | varies | 12,300 | 20,000 |
| 2023/24 | No | varies | varies | 6,000 | 20,000 |
| 2024/25 | No | varies | varies | 3,000 | 20,000 |
| 2025/26 | **Yes** | 11,973 | **0.08** | 3,000 | 20,000 |

Uses `updateOrCreate` on `tax_year` - safe to re-run. Forces 2025/26 as active.

**Key 2025/26 values in the seeder:**
- Personal Allowance: 12,570
- Income Tax: 20% / 40% / 45%
- **Employee NI Class 1 main rate: 0.08 (8%)**
- Employer NI: 13.8% (secondary threshold 9,100)
- Class 4 self-employed: 9% / 2%
- Class 2: abolished
- CGT annual exempt: 3,000
- CGT rates: 10%/20% (18%/24% property)
- Dividend allowance: 500
- Dividend rates: 8.75%/33.75%/39.35%
- IHT NRB: 325,000 / RNRB: 175,000
- IHT rate: 40% (36% with charity)
- ISA: 20,000 (LISA: 4,000, JISA: 9,000)
- Pension AA: 60,000 / MPAA: 10,000
- State Pension: 11,973/year

The seeder includes extensive IHT sub-configuration (PETs, CLTs, 14-year rule, trust charges, business relief, agricultural relief, quick succession relief, gifting exemptions) and stamp duty bands.

### TaxProductReferenceSeeder (`database/seeders/TaxProductReferenceSeeder.php`)

**Command:** `php artisan db:seed --class=TaxProductReferenceSeeder --force`

**WARNING:** Uses `DB::table('tax_product_reference')->truncate()` - destroys and recreates all data.

Seeds 50 records across 15 product types:

**Investment products (8 types, ~33 records):**
- `isa` (4 records) - Income Tax, CGT, IHT, Allowances
- `gia` (4 records) - Dividends, CGT, Interest, IHT
- `onshore_bond` (4 records) - Income Tax, CGT, Withdrawals, IHT
- `offshore_bond` (4 records) - Income Tax, CGT, Withdrawals, IHT
- `vct` (4 records) - Income Tax, CGT, Dividends, Investment Limit
- `eis` (4 records) - Income Tax Relief, CGT, Loss Relief, Investment Limit
- `nsi` (6 records) - Premium Bonds, Income Bonds, Growth Bonds, Direct Saver, Green Bonds, ISA
- `other` (1 record)

**Savings products (7 types, ~17 records):**
- `cash_isa` (3 records)
- `junior_isa` (2 records)
- `lifetime_isa` (3 records)
- `easy_access` (3 records)
- `notice` (2 records)
- `fixed_rate` (2 records)
- `premium_bonds` (4 records)

---

## 15. API Routing

### Admin-Only Routes (auth:sanctum + admin middleware)

```
GET    /api/uk-taxes                      UKTaxesController@index
GET    /api/tax-settings/current          TaxSettingsController@getCurrent
GET    /api/tax-settings/all              TaxSettingsController@getAll
GET    /api/tax-settings/calculations     TaxSettingsController@getCalculations
POST   /api/tax-settings/create           TaxSettingsController@create
PUT    /api/tax-settings/{id}             TaxSettingsController@update
POST   /api/tax-settings/{id}/activate    TaxSettingsController@setActive
POST   /api/tax-settings/{id}/duplicate   TaxSettingsController@duplicate
DELETE /api/tax-settings/{id}             TaxSettingsController@delete
```

### Auth-Required Routes (auth:sanctum middleware)

```
GET    /api/tax-info/investment/{accountType}    TaxProductInfoController@getInvestmentTaxInfo
GET    /api/tax-info/savings/{accountType}       TaxProductInfoController@getSavingsTaxInfo
GET    /api/tax-info/summary                     TaxProductInfoController@getTaxSummary
```

### Tax-Adjacent Routes in Other Modules

```
GET    /api/savings/isa-allowance/{taxYear}      SavingsController@isaAllowance
```

---

## 16. Key Constants

### Backend Constants

**TaxConfigurationAudit::CHANGE_TYPES:**
```php
['created', 'updated', 'activated', 'deactivated', 'duplicated']
```

**TaxProductReference status constants:**
```php
STATUS_EXEMPT    = 'exempt'
STATUS_TAXABLE   = 'taxable'
STATUS_DEFERRED  = 'deferred'
STATUS_RELIEF    = 'relief'
STATUS_LIMIT     = 'limit'
```

**TaxProductReference category constants:**
```php
CATEGORY_INVESTMENT = 'investment'
CATEGORY_SAVINGS    = 'savings'
```

### Frontend Tax Constants (taxConfig.js)
Hardcoded fallback values used when the backend TaxConfiguration API is unavailable. See **SharedInfrastructure.md Section 16.2** for the complete constants table.

These are fallback values only — the application always attempts to load current values from the `tax_configurations` API endpoint first.

Also exports legacy `TAX_CONFIG` default object for backwards compatibility.

### Hardcoded Limits in TaxOptimisationCard

```javascript
isaLimit: 20000,
pensionLimit: 60000,
cgtLimit: 3000,
dividendLimit: 500,
```

---

## 17. Known Issues

### Critical: NI Rate Discrepancy Between Seeder and Frontend

The `TaxConfigurationSeeder` seeds the employee NI Class 1 main rate as **0.08 (8%)** for 2025/26:
```php
'main_rate' => 0.08,  // In seeder
```

Both `UKTaxesDashboard.vue` and `UKTaxesAllowancesCard.vue` hardcode the rate as **0.12 (12%)**:
```javascript
main_rate: 0.12,  // In Vue components
```

The correct 2025/26 rate is 8%. The frontend is wrong.

### Critical: Frontend Completely Disconnected from Backend Config

All three dashboard components (`UKTaxesDashboard`, `UKTaxesAllowancesCard`, `UKTaxesOverviewCard`) use hardcoded `taxConfig` objects. None of them call any API endpoint. The `TaxOptimisationCard` hardcodes its limits in `data()`. This means:

1. Admin changes to tax rates via `TaxSettingsController` have **zero effect** on the frontend reference display
2. Tax rate changes require manual updates to 3+ Vue components
3. There is no single source of truth for the frontend

### Critical: Validation Request Schema Mismatch

`StoreTaxConfigurationRequest` validates `config_data.national_insurance.class_1_employee.primary_threshold` but the seeder and TaxConfigService expect `config_data.national_insurance.class_1.employee.primary_threshold`. The validation would reject the actual data structure if used to create a new config that matches the seeder's format.

### Medium: State Pension Amount Mismatch

The seeder for 2025/26 stores `full_new_state_pension: 11973.00` but the hardcoded Vue components use `11502.40`. The seeder value (11,973) is the correct 2025/26 amount.

### Medium: CGT Usage Always Zero

`TaxOptimisationCard` always shows CGT used as 0 because capital gains disposals are not tracked:
```javascript
cgtUsed() { return 0; }  // "would come from actual capital gains data"
```

### Medium: Duplicate Hardcoded Config

`UKTaxesDashboard.vue` and `UKTaxesAllowancesCard.vue` contain **identical complete copies** of the hardcoded `taxConfig` object (~115 lines each). Any update must be made in both places.

### Medium: TaxConfigService Not Registered as Singleton

`TaxConfigService` is not explicitly registered as a singleton in any service provider. While the internal cache makes it request-scoped if the same instance is reused, if Laravel resolves it multiple times in a single request (e.g., via different dependency injection chains), it will load from the database multiple times. The docblock says "request-scoped singleton" but this is aspirational, not enforced.

### Low: UKTaxesController is a Stub

The `UKTaxesController::index()` returns a hardcoded response with no actual data:
```php
return response()->json([
    'success' => true,
    'message' => 'UK Taxes configuration access granted',
    'data' => ['tax_year' => '2025/26', 'note' => '...'],
]);
```

### Low: CalculationsTab Contains Cross-Module Content

`CalculationsTab.vue` documents calculations for emergency funds, portfolio allocation, retirement readiness, and protection coverage gap analysis - none of which are specific to UK Taxes. This content would be better suited to a general "How Calculations Work" reference page.

### Low: Gifting Exemptions Key Mismatch

The seeder uses `small_gifts_limit` but the Vue component accesses `small_gifts.amount`. The seeder includes backward-compatible aliases (`child`, `grandchild_great_grandchild`, `other`) alongside the canonical keys (`parent_to_child`, `grandparent_to_grandchild`, `other_person`).

### Low: ChildBenefitService Simplified Income Calculation

`calculateAdjustedNetIncome()` sums gross income fields but does not deduct pension contributions or Gift Aid donations, which would reduce adjusted net income and potentially lower or eliminate HICBC.

---

## 18. Deep Dive: Tax Configuration Architecture

### The config_data JSON Structure

Each `TaxConfiguration` record stores the entirety of a tax year's rules in a single `config_data` JSON column. The 2025/26 config_data contains these top-level sections:

```
config_data
  tax_year: "2025/26"
  effective_from: "2025-04-06"
  effective_to: "2026-04-05"
  notes: string
  income_tax
    personal_allowance: 12570
    personal_allowance_taper_threshold: 100000
    personal_allowance_taper_rate: 0.5
    bands: [{name, lower_limit, upper_limit, min, max, rate}, ...]
    scotland: {enabled: false, bands: []}
  national_insurance
    class_1
      employee: {primary_threshold, upper_earnings_limit, main_rate, additional_rate}
      employer: {secondary_threshold, rate}
    class_2: {abolished: true}
    class_4: {lower_profits_limit, upper_profits_limit, main_rate, additional_rate}
  capital_gains_tax
    annual_exempt_amount: 3000
    basic_rate, higher_rate
    residential_property_basic_rate, residential_property_higher_rate
    trust_rate, trust_annual_exempt_amount, trust_vulnerable_beneficiary_exempt_amount
    chattel_exemption_threshold, chattel_marginal_relief_limit, chattel_marginal_relief_multiplier
    business_asset_disposal_relief_rate, ..._lifetime_limit, ..._min_ownership_years
  dividend_tax
    allowance, basic_rate, higher_rate, additional_rate
    trust_dividend_rate, trust_other_income_rate, trust_de_minimis_allowance
    trust_management_expenses_dividend_rate, trust_management_expenses_other_rate
  isa
    annual_allowance: 20000
    lifetime_isa: {annual_allowance, max_age_to_open, government_bonus_rate, withdrawal_penalty}
    junior_isa: {annual_allowance, max_age}
  pension
    annual_allowance: 60000
    money_purchase_annual_allowance, mpaa
    lifetime_allowance_abolished: true
    carry_forward_years: 3
    tapered_annual_allowance: {threshold_income, adjusted_income, adjusted_income_threshold, minimum_allowance, taper_rate}
    tax_relief: {basic_rate, higher_rate, additional_rate}
    state_pension: {full_new_state_pension, qualifying_years, minimum_qualifying_years}
  inheritance_tax
    nil_rate_band, residence_nil_rate_band, rnrb_taper_threshold, rnrb_taper_rate
    standard_rate, reduced_rate_charity, charity_threshold_percent
    spouse_exemption, transferable_nil_rate_band, transferable_rnrb
    potentially_exempt_transfers
      years_to_exemption, immediate_charge, becomes_chargeable_on_death
      uses_donor_nrb, cumulation_period
      taper_relief: [{min_years, max_years, tax_rate, description}, ...]
      failed_pet_rules: {becomes_chargeable_transfer, affects_later_clt_nrb, affects_estate_nrb, calculation_order}
    chargeable_lifetime_transfers
      lookback_period, cumulation_period, lifetime_rate, lifetime_rate_grossed_up
      death_rate, additional_death_charge
      taper_relief_applies
      taper_relief: [{min_years, max_years, relief_percent, tax_percent}, ...]
    fourteen_year_rule
      applies_to, lookback_for_failed_pets, lookback_for_clts, maximum_window
      description, calculation_steps: [string, ...]
    trust_charges
      entry: {rate, rate_grossed_up, nrb_available, exemptions: {...}}
      periodic: {interval_years, max_rate, calculation_formula, lifetime_rate_multiplier, base_rate, nrb_available, exemptions: {...}}
      exit: {max_rate, calculation_basis, quarters_in_period, formula, no_charge_periods: {...}, exemptions: {...}}
    agricultural_relief: {min_ownership_years, min_occupation_years, rates: {...}, allowance_cap, allowance_cap_effective_date, notes}
    business_relief: {min_ownership_years, rates: {...}, excluded_businesses: [...], allowance_cap, allowance_cap_effective_date, notes}
    quick_succession_relief: {applies_when, max_years, relief_rates: [{max_years, relief}, ...]}
  gifting_exemptions
    annual_exemption, annual_exemption_can_carry_forward, carry_forward_years, annual_exemption_notes
    small_gifts_limit, small_gifts_unlimited_recipients, small_gifts_notes
    wedding_gifts: {parent_to_child, grandparent_to_grandchild, great_grandparent, other_person, child (alias), grandchild_great_grandchild (alias), other (alias), must_be_given_before_ceremony, must_be_conditional_on_marriage}
    normal_expenditure_from_income: {limit: null, immediately_exempt, conditions: {...}, evidence_required: [...], examples: [...], notes}
    maintenance_exemptions: {spouse_civil_partner, ex_spouse_maintenance, minor_children, adult_children_in_education, dependent_relatives}
    charity_exemption, political_party_exemption, housing_association_exemption, national_purposes_exemption
  stamp_duty
    residential
      standard: {bands: [{threshold, rate}, ...]}
      additional_properties: {surcharge, bands: [{threshold, rate}, ...]}
      (first_time_buyers, non_uk_residents likely present in full config)
    non_residential: {bands: [...]}
  (Additional sections may include: property_ownership, benefits, assumptions, domicile, trusts)
```

### How Admin Changes Flow to Backend Calculations

```
Admin UI (future) -> taxSettingsService.update() -> PUT /api/tax-settings/{id}
  -> TaxSettingsController::update()
    -> Captures before_state
    -> Updates TaxConfiguration model (config_data JSON)
    -> Logs TaxConfigurationAudit
    -> Returns updated config

Next request from ANY module:
  -> Service constructor injects TaxConfigService
    -> TaxConfigService::loadActiveConfig()
      -> Queries: SELECT * FROM tax_configurations WHERE is_active = true LIMIT 1
      -> Caches config_data in $this->config
    -> Service calls $this->taxConfig->get('income_tax.personal_allowance')
      -> Arr::get($this->config, 'income_tax.personal_allowance')
      -> Returns the DATABASE value (which admin changed)
```

**Result:** All PHP-side calculations immediately use the updated values. Services like `UKTaxCalculator`, `IHTCalculationService`, `ChildBenefitService`, etc. will produce calculations based on the new rates on their very next invocation.

### Why the Frontend is Disconnected

The frontend has three independent sources of tax values, none of which are connected to the backend:

1. **Hardcoded objects in Vue components** - `UKTaxesDashboard.vue` and `UKTaxesAllowancesCard.vue` each contain a ~115-line `taxConfig` object with all rates. These were manually written when the components were created and have never been connected to an API.

2. **Hardcoded limits in TaxOptimisationCard** - The `data()` function sets `isaLimit: 20000, pensionLimit: 60000, cgtLimit: 3000, dividendLimit: 500` as plain numbers.

3. **Frontend constants in taxConfig.js** - Named exports like `ISA_ANNUAL_ALLOWANCE = 20000` serve as fallbacks. These are documented as fallback values, but no component actually fetches from the API first and falls back to these.

**The disconnect exists because:**
- `taxSettingsService.js` exists and has `getCurrent()` method, but no component calls it
- The `/api/tax-settings/current` endpoint exists and works, but is admin-only
- The `/api/tax-info/*` endpoints exist for product-level tax info, but the main dashboard does not use them
- No Vuex store exists to hold tax configuration data for the frontend

**To fix this properly, one would need to:**
1. Create a Vuex store for tax configuration
2. Add a non-admin endpoint (or make the existing one accessible to all auth users) for fetching the active config
3. Have the store load on app initialization
4. Replace all hardcoded values in components with store getters
5. Remove the hardcoded `taxConfig` objects from the three components
