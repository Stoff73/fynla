# CLAUDE.md

Development guidelines for Claude Code when working with the Fynla financial planning application.

---

## CRITICAL RULES

### 1. DEPLOYMENT AND BUILD PROCESS

**NEVER INCLUDE `public/build/` IN TAR FILES WITHOUT EXPLICIT USER PERMISSION**

When creating deployment tarballs or packages:
- **DO NOT** automatically include `public/build/` directory
- **DO NOT** assume frontend assets should be in deployment packages
- **NEVER** instruct user to run `npm run build` on production server
- **ALWAYS ASK** the user before including compiled frontend assets
- **ONLY INCLUDE** source files (Vue components, PHP files, migrations) unless explicitly told otherwise
- **ALWAYS build on LOCAL machine**: `NODE_ENV=production npm run build`

**Why**:
- Production servers often lack Node.js or have wrong versions
- Build process is resource-intensive and can crash shared hosting
- Including `public/build/` causes wrong asset paths if Vite base configuration changed
- Massive tarball sizes (build/ can be 10-50MB+)
- Cache issues with old compiled assets

**Correct deployment workflow**:
1. Upload source files ONLY (Vue components, PHP controllers, migrations, etc.)
2. Build locally: `NODE_ENV=production npm run build`
3. Upload `public/build/` separately IF explicitly requested
4. User clears caches on production server

### 2. MAINTAIN APPLICATION FUNCTIONALITY

**THE APPLICATION MUST REMAIN FULLY FUNCTIONAL AT ALL TIMES.**

- **DO NOT REMOVE** existing features, functionality, pages, views, components, or working code
- **ONLY WORK** on the specific section explicitly being modified
- **IF OTHER AREAS ARE AFFECTED**: STOP, explain impact, ASK PERMISSION, wait for approval before proceeding
- **BEFORE MAKING CHANGES**: Understand full scope, identify all affected files, ensure backward compatibility

### 3. DATABASE BACKUP PROTOCOL

**ALWAYS CHECK FOR AND MAINTAIN DATABASE BACKUPS BEFORE ANY DESTRUCTIVE OPERATIONS.**

- **BEFORE Database Wipe**: Create backup via admin panel, verify it exists in `storage/app/backups/`
- **NEVER** run `migrate:fresh` or `migrate:refresh` without explicit user approval
- **Admin Account**: `admin@fps.com` / `admin123` (ID: 1016)

**Commands to AVOID Without Backup:**
- `php artisan migrate:fresh`, `migrate:refresh`, `db:wipe`
- `php artisan migrate:rollback` (except single recent migration)

**Safe Commands:**
- `php artisan migrate` (forward only)
- Admin panel backup/restore system

### 4. NEVER HARDCODE USER INPUT VALUES

**CRITICAL: ALWAYS USE DATABASE AND USER-PROVIDED VALUES - NEVER OVERRIDE WITH HARDCODED DEFAULTS**

- **NEVER** hardcode values from user inputs unless explicitly instructed to do so
- **ALWAYS** use the actual data provided by users in forms
- **ALWAYS** store and retrieve values exactly as entered by the user
- **NEVER** manipulate, override, or replace user input with default values like 'To be completed', 'Unknown', etc.

**Examples:**

WRONG - Hardcoding values:
```php
$mortgageData = [
    'lender_name' => 'To be completed',  // NEVER DO THIS
    'mortgage_type' => 'repayment',      // NEVER DO THIS
];
```

CORRECT - Using actual user input:
```php
$mortgageData = [
    'lender_name' => $validated['lender_name'] ?? 'To be completed',
    'mortgage_type' => $validated['mortgage_type'] ?? 'repayment',
];
```

**The only acceptable time to use default values is when the user has NOT provided any input at all.**

### 5. USE AVAILABLE SKILLS

**BEFORE STARTING ANY TASK, CHECK IF THERE IS A RELEVANT SKILL AVAILABLE.**

Available Skills:
1. **systematic-debugging** - For ALL bug reports, unexpected behavior, troubleshooting
2. **fps-module-builder** - For creating new modules (full-stack)
3. **fps-feature-builder** - For adding/extending features in existing modules
4. **fps-component-builder** - For creating Vue 3 components

Available Agents:
1. **laravel-stack-deployer** - For Laravel + MySQL + Vue.js + Vite deployment to production/staging/development environments
2. **code-quality-auditor** - For comprehensive code quality audits after significant development

**FAILURE TO USE AVAILABLE SKILLS AND AGENTS IS UNACCEPTABLE.**

### 6. UNIFIED FORM COMPONENTS

**THE APPLICATION USES ONE FORM FOR ALL INPUTS ACROSS ALL AREAS**

**CRITICAL**: All data input forms MUST be reusable across the entire application:
- The SAME form component is used whether adding data during onboarding, from the module dashboard, or editing existing data
- Forms are located in module-specific component folders (e.g., `components/Protection/PolicyFormModal.vue`)
- Forms accept a data prop and a mode/isEditing prop
- Forms emit `@save` and `@close` events (NEVER use `@submit` which causes double submission)

**Pattern to Follow:**
```vue
<!-- Parent Component -->
<ItemForm
  v-if="showForm"
  :item="editingItem"
  :is-editing="!!editingItem"
  @save="handleItemSaved"
  @close="closeForm"
/>
```

**DO NOT:**
- Create separate forms for onboarding vs. dashboard
- Duplicate form logic across components
- Use `@submit` event name (causes double submission bug)

### 7. ENVIRONMENT VARIABLE CONTAMINATION

**THE #1 CAUSE OF DEVELOPMENT ENVIRONMENT FAILURES IS ENVIRONMENT VARIABLE POLLUTION**

**NEVER** export production environment variables in development sessions:
- **NEVER** run: `export $(cat .env.production | xargs)`
- **NEVER** run: `source .env.production`

**SOLUTION - Always use the startup script:**
```bash
./dev.sh
```

**DIAGNOSIS - Check for contamination:**
```bash
printenv | grep -E "^APP_|^DB_|^VITE_|^CACHE_"
```

**COMMON SYMPTOMS:**
1. CORS errors from production URL
2. Database errors with production DB user
3. Cache errors: "This cache store does not support tagging"
4. Vite shows wrong URL in output

### 8. CANONICAL DATA TYPES - ONE SOURCE OF TRUTH

**ALL ASSET/LIABILITY/PROPERTY TYPES MUST USE CANONICAL VALUES DEFINED BY DATABASE MIGRATIONS**

#### Property Types (3 types)

| Value | Display Label |
|-------|---------------|
| `main_residence` | Main Residence |
| `secondary_residence` | Secondary Residence |
| `buy_to_let` | Buy to Let |

**FORBIDDEN**: `second_home`, `commercial`, `land`

#### Ownership Types (4 types - ALL MODULES)

| Value | Display Label | Notes |
|-------|---------------|-------|
| `individual` | Individual | Default for ISAs (UK tax rule) |
| `joint` | Joint Ownership | Two owners with joint rights |
| `tenants_in_common` | Tenants in Common | Ownership by percentage, separate wills |
| `trust` | Trust | Property held in trust |

**FORBIDDEN**: Never use `sole` - always use `individual`

#### Liability Types (9 types)

| Value | Display Label |
|-------|---------------|
| `mortgage` | Mortgage |
| `secured_loan` | Secured Loan |
| `personal_loan` | Personal Loan |
| `credit_card` | Credit Card |
| `overdraft` | Bank Overdraft |
| `hire_purchase` | Hire Purchase / Car Finance |
| `student_loan` | Student Loan |
| `business_loan` | Business Loan |
| `other` | Other |

**Database Field Names (CRITICAL)**:
- `liability_name` (NOT `description`)
- `current_balance` (NOT `amount`)
- `liability_type` (NOT `type`)

#### Investment Account Types (8 types)

| Value | Display Label | ISA Eligible |
|-------|---------------|--------------|
| `isa` | ISA (Stocks & Shares) | Yes - £20k limit |
| `gia` | General Investment Account | No |
| `nsi` | NS&I (National Savings & Investments) | No |
| `onshore_bond` | Onshore Bond | No |
| `offshore_bond` | Offshore Bond | No |
| `vct` | Venture Capital Trust (VCT) | No |
| `eis` | Enterprise Investment Scheme (EIS) | No |
| `other` | Other | No |

**Note**: For `other` type, use `account_type_other` field for description.

#### Holding Asset Types (10 types)

| Value | Display Label |
|-------|---------------|
| `uk_equity` | UK Equity |
| `us_equity` | US Equity |
| `international_equity` | International Equity |
| `fund` | Fund |
| `etf` | ETF |
| `bond` | Bond |
| `cash` | Cash |
| `alternative` | Alternative |
| `property` | Property |
| `equity` | Equity (generic/legacy) |

#### Savings Account Access Types (3 types)

| Value | Display Label | Related Fields |
|-------|---------------|----------------|
| `immediate` | Immediate | - |
| `notice` | Notice | `notice_period_days` |
| `fixed` | Fixed | `maturity_date` |

#### Life Insurance Policy Types (5 types)

| Value | Display Label |
|-------|---------------|
| `term` | Term Insurance |
| `decreasing_term` | Decreasing Life Policy |
| `level_term` | Level Term Life Policy |
| `whole_of_life` | Whole of Life Policy |
| `family_income_benefit` | Family Income Benefit |

#### Critical Illness Policy Types (3 types)

| Value | Display Label |
|-------|---------------|
| `standalone` | Standalone |
| `accelerated` | Accelerated |
| `additional` | Additional |

#### Disability Policy Coverage Types (2 types)

| Value | Display Label |
|-------|---------------|
| `accident_only` | Accident Only |
| `accident_and_sickness` | Accident & Sickness |

#### Premium/Benefit Frequency Types

| Value | Display Label | Used In |
|-------|---------------|---------|
| `monthly` | Monthly | All policies |
| `quarterly` | Quarterly | All policies |
| `annually` | Annual | All policies |
| `weekly` | Weekly | IP/Disability benefit |
| `lump_sum` | Lump Sum | Sickness/Illness benefit |

**Note**: Vue forms may show 'annual' but database uses 'annually'.

#### DC Pension Types (Dual Classification)

**Scheme Type** (original field):
| Value | Display Label |
|-------|---------------|
| `workplace` | Occupational (Workplace) |
| `sipp` | SIPP |
| `personal` | Personal Pension |

**Pension Type** (newer field):
| Value | Display Label |
|-------|---------------|
| `occupational` | Occupational |
| `sipp` | SIPP |
| `personal` | Personal Pension |
| `stakeholder` | Stakeholder Pension |

**Field Names (CRITICAL)**:
- Workplace: `employee_contribution_percent`, `employer_contribution_percent`, `annual_salary`
- Others: `monthly_contribution_amount` (fixed £)
- **NOT** `employee_contribution_amount`

#### DB Pension Types (3 types)

| Value | Display Label |
|-------|---------------|
| `final_salary` | Final Salary |
| `career_average` | Career Average (CARE) |
| `public_sector` | Public Sector |

**Inflation Protection Options**: `cpi`, `rpi`, `fixed`, `none`

#### Mortgage Types (3 types)

| Value | Display Label |
|-------|---------------|
| `repayment` | Repayment |
| `interest_only` | Interest Only |
| `mixed` | Mixed (Part Repayment / Part Interest Only) |

**FORBIDDEN**: `part_and_part` - replaced by `mixed`

**Rate Type Options**: `fixed`, `variable`, `tracker`, `discount`, `mixed`

#### Trust Types (9 types)

| Value | Display Label |
|-------|---------------|
| `bare` | Bare Trust |
| `interest_in_possession` | Interest in Possession |
| `discretionary` | Discretionary Trust |
| `accumulation_maintenance` | Accumulation & Maintenance |
| `life_insurance` | Life Insurance Trust |
| `discounted_gift` | Discounted Gift Trust |
| `loan` | Loan Trust |
| `mixed` | Mixed Trust |
| `settlor_interested` | Settlor-Interested Trust |

#### Gift Types (5 types - IHT Planning)

| Value | Display Label | 7-Year Rule |
|-------|---------------|-------------|
| `pet` | Potentially Exempt Transfer (PET) | Yes |
| `clt` | Chargeable Lifetime Transfer (CLT) | Yes + immediate tax |
| `exempt` | Exempt Gift | No |
| `small_gift` | Small Gift Exemption (£250/person/year) | No |
| `annual_exemption` | Annual Exemption (£3,000/year) | No |

**Gift Status**: `within_7_years`, `survived_7_years`

#### Bequest Types (4 types)

| Value | Display Label |
|-------|---------------|
| `percentage` | Percentage of Estate |
| `specific_amount` | Specific Amount |
| `specific_asset` | Specific Asset |
| `residuary` | Residuary |

#### User Profile Enums

**Gender**: `male`, `female`, `other`

**Marital Status**: `single`, `married`, `divorced`, `widowed`

**Health Status**: `yes`, `yes_previous`, `no_previous`, `no_existing`, `no_both`

**Smoking Status**: `never`, `quit_recent`, `quit_long_ago`, `yes`

**Employment Status**: `employed`, `part_time`, `self_employed`, `retired`, `unemployed`, `other`

**Education Level**: `secondary`, `a_level`, `undergraduate`, `postgraduate`, `professional`, `other`

#### Family Member Relationships

| Value | Display Label |
|-------|---------------|
| `spouse` | Spouse |
| `child` | Child |
| `step_child` | Step Child |
| `parent` | Parent |
| `other_dependent` | Other Dependent |

**ENFORCEMENT RULES:**
1. **Database is Source of Truth**: Always check migration files first
2. **No Variations**: If migration says `secondary_residence`, NEVER use `second_home`
3. **Backend Validation**: All Form Requests must validate against canonical values only
4. **Frontend Forms**: All `<select>` options must use canonical values exactly

---

## Project Overview

**Fynla** - UK-focused comprehensive financial planning application covering five integrated modules: Protection, Savings, Investment, Retirement, and Estate Planning.

**Current Version**: v0.2.17 (Production)
**Tech Stack**: Laravel 10.x (PHP 8.2+) + Vue.js 3 + MySQL 8.0+ + Memcached
**Status**: All core modules complete, Document Upload with AI Extraction feature added
**Production URL**: https://csjones.co/tengo

---

## Architecture Overview

### Agent-Based System

Each module has an **Agent** that orchestrates analysis:
- **ProtectionAgent**: Life/CI/IP coverage analysis
- **SavingsAgent**: Emergency fund & ISA tracking
- **InvestmentAgent**: Portfolio analysis & Monte Carlo simulations
- **RetirementAgent**: Pension projections & readiness scoring
- **CoordinatingAgent**: Cross-module holistic planning

**Note**: Estate module uses direct service architecture (EstateAgent deprecated in favor of IHTCalculationService).

### Three-Tier Architecture

```
Vue.js 3 Frontend (174 components)
        | REST API (378 routes)
Laravel 10.x Application Layer (6 Agents + 63 Services)
        | Eloquent ORM
MySQL 8.0+ (50+ tables) + Memcached
```

### Service Layer Organization (63 Services)

| Module | Services | Key Services |
|--------|----------|--------------|
| Estate Planning | 18 | IHTCalculationService, FutureValueCalculator, TrustService |
| Investment | 23+ | PortfolioAnalyzer, MonteCarloSimulator, EfficientFrontierCalculator |
| Coordination | 5 | HolisticPlanner, CashFlowCoordinator, PriorityRanker |
| Protection | 5 | CoverageGapAnalyzer, AdequacyScorer, RecommendationEngine |
| Retirement | 5 | PensionProjector, DecumulationPlanner, ReadinessScorer |
| Savings | 5 | ISATracker, EmergencyFundCalculator, GoalProgressCalculator |
| Property | 3 | PropertyService, MortgageService, PropertyTaxService |
| User Profile | 4 | UserProfileService, PersonalAccountsService |
| Shared | 2+ | TaxConfigService, NetWorthService |

---

## Essential Development Commands

### Running Development Servers

**CRITICAL**: You must run **BOTH** servers simultaneously.

**Recommended:**
```bash
./dev.sh
```

**Manual:**
```bash
# Terminal 1 - Laravel Backend (REQUIRED)
php artisan serve

# Terminal 2 - Vite Frontend (REQUIRED)
npm run dev

# Terminal 3 - Queue Worker (Optional, for Monte Carlo)
php artisan queue:work database
```

### Testing

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest --testsuite=Unit
```

### Code Formatting

```bash
# Run Laravel Pint (PSR-12 formatter)
./vendor/bin/pint
```

---

## Key Implementation Patterns

### 1. Centralized Tax Configuration (DATABASE-DRIVEN)

**CRITICAL**: All UK tax values are stored in `tax_configurations` table, NOT hardcoded.

**Usage Pattern**:
```php
use App\Services\TaxConfigService;

class MyService
{
    public function __construct(private TaxConfigService $taxConfig) {}

    public function calculate()
    {
        $personalAllowance = $this->taxConfig->getIncomeTax()['personal_allowance'];
        $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'];
    }
}
```

**Available Methods**:
- `getIncomeTax()` - Income tax bands and personal allowance
- `getNationalInsurance()` - NI thresholds and rates
- `getCapitalGainsTax()` - CGT rates and exemptions
- `getDividendTax()` - Dividend tax allowances and rates
- `getISAAllowances()` - ISA annual limits
- `getPensionAllowances()` - Pension annual allowance, MPAA, taper rules
- `getInheritanceTax()` - IHT rates, NRB, RNRB, gifting rules
- `getStampDuty()` - SDLT bands and rates
- `get(string $key)` - Get any nested value using dot notation

**DO NOT**:
- Use `config('uk_tax_config')` - DEPRECATED
- Hardcode tax values in services

**DO**:
- Inject TaxConfigService via constructor
- Mock TaxConfigService in unit tests

### 2. ISA Allowance Tracking (Cross-Module)

**Total Allowance**: £20,000 per tax year (April 6 - April 5)

**Implementation**: `app/Services/Savings/ISATracker.php`
- Aggregates Cash ISAs from Savings module
- Aggregates S&S ISAs from Investment module
- Warns when approaching or exceeding limit

### 3. Asset Ownership Patterns

**Ownership Types**: Individual, Joint, Tenants in Common, Trust

**Database Pattern**:
```php
$table->enum('ownership_type', ['individual', 'joint', 'tenants_in_common', 'trust'])->default('individual');
$table->unsignedBigInteger('joint_owner_id')->nullable();
$table->foreignId('trust_id')->nullable();
```

**CRITICAL**:
- Use **'individual'** NOT 'sole' in forms
- ISAs MUST be 'individual' only (UK tax rule)
- Joint ownership creates reciprocal records automatically

### 4. Spouse Account Management

- **Auto-Creation**: New email creates account with random password, sends welcome email
- **Account Linking**: Existing email links accounts bidirectionally, sets `marital_status = 'married'`
- **Permissions**: Granular view/edit permissions via `spouse_permissions` table
- **Joint Accounts**: Audit trail via `joint_account_logs` table

### 5. Polymorphic Holdings System

**Pattern**: Holdings can belong to either InvestmentAccount OR DCPension

**Database**:
```php
$table->morphs('holdable'); // Creates holdable_type and holdable_id
```

**Usage**:
```php
// Investment Account holdings
$holdings = $investmentAccount->holdings;

// DC Pension holdings
$holdings = $dcPension->holdings;
```

This enables shared portfolio optimization services across Investment and Retirement modules.

---

## Critical Vue.js Patterns

### 1. Form Modal Event Naming Bug

**WRONG - Causes double submission:**
```vue
<FormModal @submit="handleSubmit" />
```

**CORRECT - Use 'save' event:**
```vue
<FormModal @save="handleSubmit" @close="closeModal" />
```

### 2. Date Field Formatting

**HTML5 date inputs REQUIRE yyyy-MM-DD format**

**ALWAYS add a formatDateForInput() helper to components with date fields:**

```javascript
formatDateForInput(date) {
  if (!date) return '';
  if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    return date;
  }
  const dateObj = new Date(date);
  if (isNaN(dateObj.getTime())) return '';
  const year = dateObj.getFullYear();
  const month = String(dateObj.getMonth() + 1).padStart(2, '0');
  const day = String(dateObj.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
```

### 3. Currency Formatting

**ALWAYS** use the `formatCurrency()` method:

```javascript
formatCurrency(value) {
  if (value === null || value === undefined) return '£0';
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}
```

**Usage**:
```vue
<!-- CORRECT -->
<p>Total: {{ formatCurrency(totalValue) }}</p>

<!-- WRONG -->
<p>Total: £{{ totalValue.toLocaleString() }}</p>
```

### 4. Unified Form Pattern for Multiple Entity Types

**Pattern**: When a single action can create multiple types of entities (e.g., DC/DB/State pensions), use a unified form with type selection.

**Implementation**: `UnifiedPensionForm.vue`

```vue
<template>
  <div>
    <!-- Type Selection Modal (shows first) -->
    <div v-if="!selectedType">
      <button @click="selectedType = 'type_a'">Type A</button>
      <button @click="selectedType = 'type_b'">Type B</button>
    </div>

    <!-- Specific Form Components (shown after selection) -->
    <TypeAForm v-if="selectedType === 'type_a'" @close="handleClose" @save="handleSave" />
    <TypeBForm v-if="selectedType === 'type_b'" @close="handleClose" @save="handleSave" />
  </div>
</template>

<script>
export default {
  data() {
    return {
      selectedType: null,
    };
  },
  methods: {
    handleClose() {
      this.selectedType = null;
      this.$emit('close');
    },
    handleSave(data) {
      this.selectedType = null;
      this.$emit('save', data);
    },
  },
};
</script>
```

### 5. British vs. American Spelling

**CRITICAL RULE**: British English for users, American English for code

#### User-Facing Text (Use British Spelling)
- Headings: `<h1>Portfolio Optimisation</h1>`
- Button labels: `<button>Customise</button>`
- Form placeholders: `placeholder="Analyse your data"`

#### Code Syntax (Use American Spelling - DO NOT CHANGE)
- CSS classes: `class="items-center"` (Tailwind convention)
- API routes: `/api/retirement/analyze` (Laravel convention)
- Variable names: `optimizationResult`, `colorScheme`
- Method names: `analyzePortfolio()`, `optimizeAllocation()`

### 6. Syncing Related Form Data (CRITICAL)

**CRITICAL**: When forms have related parent-child data (e.g., property + mortgage), ALWAYS sync the child form data with the parent using watchers.

**Problem Example**: PropertyForm has `form.ownership_type` and `mortgageForm.ownership_type`. User changes property to joint, but mortgage stays individual.

**CORRECT - Use watchers to sync:**

```javascript
watch: {
  // Sync mortgage ownership with property ownership
  'form.ownership_type'(newVal) {
    this.mortgageForm.ownership_type = newVal;
  },

  'form.joint_owner_id'(newVal) {
    this.mortgageForm.joint_owner_id = newVal;
  },

  'form.joint_owner_name'(newVal) {
    this.mortgageForm.joint_owner_name = newVal;
  },
}
```

**Also initialize in populateForm():**

```javascript
// If no existing mortgage, sync ownership from property
if (!this.property.mortgages?.length) {
  this.mortgageForm.ownership_type = this.form.ownership_type || 'individual';
  this.mortgageForm.joint_owner_id = this.form.joint_owner_id || null;
  this.mortgageForm.joint_owner_name = this.form.joint_owner_name || '';
}
```

**Why This Matters**:
- Backend logic (e.g., reciprocal record creation) depends on correct ownership_type
- Prevents silent data inconsistency bugs that are hard to debug
- Ensures frontend and backend state remain synchronized

### 7. Coming Soon Watermark Pattern

**ALWAYS use the consistent amber box pattern for Coming Soon watermarks:**

```vue
<template>
  <div class="relative">
    <!-- Coming Soon Watermark -->
    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
      <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
        <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
      </div>
    </div>

    <!-- Actual content with reduced opacity -->
    <div class="opacity-50">
      <!-- Component content here -->
    </div>
  </div>
</template>
```

**Key Elements**:
- `bg-amber-100` - Light amber background
- `border-2 border-amber-400` - Visible amber border
- `rounded-lg px-8 py-4` - Rounded corners with padding
- `transform -rotate-12` - Slight rotation for visual interest
- `shadow-lg` - Drop shadow for depth
- `text-2xl font-bold text-amber-700` - Bold amber text
- `opacity-50` on content - Dims underlying content

**DO NOT use plain gray text watermarks like:**
```vue
<!-- WRONG -->
<div class="text-6xl font-bold text-gray-300 opacity-50 transform -rotate-12">
  Coming Soon
</div>
```

---

## Coding Standards

### PHP (PSR-12 Compliant)

**Naming**:
- Classes: `PascalCase` (e.g., `ProtectionAgent`)
- Methods/Properties: `camelCase` (e.g., `calculateGap()`)
- Database: `snake_case` (e.g., `user_id`, `sum_assured`)

**Key Rules**:
- Always use `declare(strict_types=1);`
- 4 spaces indentation (no tabs)
- Type hints for parameters and return types
- Visibility declared for all properties/methods

### MySQL Standards

**Naming**:
- Tables: `snake_case` (e.g., `life_insurance_policies`)
- Columns: `snake_case` (e.g., `user_id`, `sum_assured`)
- Primary keys: `id` (BIGINT UNSIGNED AUTO_INCREMENT)
- Foreign keys: `{table}_id` (e.g., `user_id`)

**Data Types**:
- IDs: `BIGINT UNSIGNED`
- Currency: `DECIMAL(15,2)` or `DOUBLE` for computed values
- Percentages/rates: `DECIMAL(5,4)`
- Dates: `DATE`, `TIMESTAMP` for timestamps

### Vue.js 3 Standards

**Component Naming**:
- Multi-word names (e.g., `AssetForm.vue`, `IHTPlanning.vue`)
- PascalCase in SFC, kebab-case in templates

**Key Rules**:
- Always use `:key` with `v-for`
- Never use `v-if` with `v-for` on same element
- Use shorthand syntax (`:` for `v-bind`, `@` for `v-on`)

---

## UK Tax Configuration

**Active Tax Year**: 2025/26 (configurable via admin panel)
**Historical Years Available**: 2021/22, 2022/23, 2023/24, 2024/25

**Key Values (2025/26)**:
- **IHT Rate**: 40% on estate above nil rate band
- **NRB**: £325,000 (transferable to spouse)
- **RNRB**: £175,000 (residence nil rate band, transferable)
- **Pension Annual Allowance**: £60,000 (tapered for high earners)
- **ISA Annual Allowance**: £20,000 (April 6 - April 5)
- **Personal Allowance**: £12,570
- **Tax Year Period**: April 6 to April 5

---

## Caching Strategy

**Cache TTLs** based on data volatility:
- Tax config: 1 hour
- Monte Carlo results: 24 hours
- Dashboard data: 30 minutes
- Agent analysis: 1 hour

**Pattern**:
```php
Cache::remember("estate_analysis_{$userId}", 3600, function() {
    return $this->service->analyze($userId);
});
```

**Invalidation**: After writes
```php
Cache::forget("estate_analysis_{$userId}");
```

---

## Testing Pattern

### Unit Test (Pest)

```php
use App\Services\Estate\IHTCalculationService;

test('calculates IHT liability correctly for single person', function () {
    $user = User::factory()->create();
    $profile = IHTProfile::factory()->create([
        'user_id' => $user->id,
        'available_nrb' => 325000,
    ]);

    $assets = collect([(object)['current_value' => 500000]]);

    $service = new IHTCalculationService($taxConfig);
    $result = $service->calculateIHTLiability($assets, collect(), $profile);

    // Estate: £500k - NRB: £325k = £175k taxable
    // IHT: £175k x 40% = £70k
    expect($result['iht_liability'])->toBe(70000.0);
});
```

### Mocking TaxConfigService

```php
use App\Services\TaxConfigService;
use Mockery;

test('calculates tax correctly', function () {
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('getIncomeTax')
        ->andReturn([
            'personal_allowance' => 12570,
            'bands' => [
                ['name' => 'Basic Rate', 'threshold' => 0, 'rate' => 0.20],
            ],
        ]);

    $service = new MyCalculatorService($mockTaxConfig);
    $result = $service->calculateTax(50000);

    expect($result['personal_allowance'])->toBe(12570.0);
});
```

---

## Module Structure

Each module follows consistent pattern:

**Backend**:
```
app/
├── Agents/[Module]Agent.php         # Business logic orchestrator
├── Services/[Module]/               # Domain-specific calculations
├── Http/Controllers/Api/[Module]Controller.php
├── Http/Requests/[Module]/          # Form validation
└── Models/[Module]/                 # Eloquent models
```

**Frontend**:
```
resources/js/
├── views/[Module]/[Module]Dashboard.vue
├── components/[Module]/             # Module-specific components
├── services/[module]Service.js      # API wrapper
└── store/modules/[module].js        # Vuex store
```

---

## Key Development Principles

1. **UK-Specific**: Follow UK tax rules, tax year April 6 - April 5
2. **Agent Pattern**: Encapsulate module logic in Agent classes (except Estate)
3. **Centralized Config**: Never hardcode tax rates; use TaxConfigService
4. **Data Isolation**: Users only access their own data; always filter by `user_id`
5. **Caching**: Cache expensive calculations with appropriate TTLs
6. **Testing**: Write Pest tests for all financial calculations

---

## Version History

### v0.2.17 (8 December 2025) - Latest

**Document Upload with AI Extraction**:
- AI-powered document extraction using Claude Sonnet 4.5
- Support for PDF, images (PNG, JPG, WebP), and Excel files (XLSX, XLS, CSV)
- Field mappers for DC Pension, DB Pension, Life Insurance, Investment Account
- Image compression for large files
- Excel parsing via PhpSpreadsheet
- Review and confirm workflow before saving extracted data
- Confidence scores for extracted fields

### v0.2.16 (27 November 2025)

**UI/UX Improvements**:
1. Income Protection Display Fix - Benefit amount now displays correctly on dashboard card
2. Investment & Savings Plan - Added scaffold warning banner
3. Emergency Fund Data - Fixed data not pulling through (SavingsAgent fallback to User model)
4. Demo Mode - Disabled edit buttons for Property, Investment, and Savings accounts
5. Family Member Form - Made Date of Birth a required field
6. Onboarding UI - Property cards now use PropertyCard component from Net Worth
7. Onboarding UI - Retirement cards styled to match RetirementReadiness.vue
8. Onboarding UI - Investment cards styled to match PortfolioOverview.vue
9. Onboarding UI - Cash/Savings cards styled to match CurrentSituation.vue
10. Will Planning - Added "Preview Mode" notice with legal disclaimer
11. Business Interests - Updated Coming Soon banner to match app style
12. Chattels & Valuables - Updated Coming Soon banner to match app style

### v0.2.15 (26 November 2025)

**Linked Account Fixes and UI Improvements**:
- Welcome screen updates
- Feedback button added to navbar
- Joint expenditure display fix
- Breadcrumb removal
- Various UI polish

### v0.2.14 (25 November 2025)

**10 Major Fixes**:
1. Family Member/Spouse Account Linking - User Profile now uses same service as onboarding
2. Joint Property Sync - Joint property editing shows full values and syncs with audit trail
3. Joint Property Cost/Income Split - Monthly costs and rental income split by ownership percentage
4. Joint Account History UI - New "Joint History" tab in Net Worth dashboard
5. Code Quality Fixes - 11 issues resolved (1 critical: division by zero prevention)
6. Spouse Modal Logout Fix - Fixed flash/logout issue when adding spouse
7. DC Pension Provider Nullable - Migration for provider column
8. Joint Mortgage Payment Split - Correctly splits by ownership percentage
9. Spouse Expenditure Display - Fixed double `/api` prefix bug
10. Life Policy Validation - Made policy_end_date optional for term policies

### v0.2.13 (24 November 2025)

**Critical Pension and Protection Fixes**:
- DC pension creation (all types including stakeholder)
- DB pension creation (field name mapping)
- Protection policy dates made optional
- Financial commitments tracking (Disability/Sickness premiums)
- All database nullable constraints fixed

### v0.2.10 "Boma Build" (20 November 2025)

**28 Bug Fixes**:
- Financial Commitments API (DC Pension namespace, field names)
- Expenditure Form (property expenses, spouse totals)
- Rental Income Display
- Spouse Account Linking validation
- Dashboard Card enhancements

---

## Previously Known Issues - NOW RESOLVED

### Joint Mortgage Reciprocal Creation (Fixed November 15, 2025)

**Issue**: Joint property with mortgage only created ONE mortgage record instead of TWO.
**Solution**: Added watchers in PropertyForm.vue to sync mortgage ownership with property ownership.
**Status**: FIXED

### Estate Plan Spouse Data & IHT Liability Display (Fixed November 15, 2025)

**Issues**:
1. Estate Plan not showing spouse assets when data sharing enabled
2. IHT Planning tab not displaying non-mortgage liabilities

**Solutions**:
1. Updated ComprehensiveEstatePlanService to gather spouse data
2. Fixed field names in IHTController (`current_balance` not `amount`, `liability_name` not `description`)

**Status**: FIXED

### All Liability Display Issues (Fixed November 17, 2025)

- Net Worth Card now shows all 9 liability types
- IHT Planning displays all liabilities correctly
- Liability types expanded from 4 to 9 types
- Interest rates display correctly

**Status**: FIXED

---

For any bugs encountered, please use the `systematic-debugging` skill to investigate before implementing fixes.

---

## Demo Credentials

- **User**: `demo@fps.com` / `password`
- **Admin**: `admin@fps.com` / `admin123`

---

**Current Version**: v0.2.17 (Production)
**Production URL**: https://csjones.co/tengo
**Last Updated**: December 10, 2025
**Status**: Production Ready - All Core Features Complete

---

Built with [Claude Code](https://claude.com/claude-code)
