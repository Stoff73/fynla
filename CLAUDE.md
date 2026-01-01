# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Fynla** is a UK-focused comprehensive financial planning application (Laravel 10 + Vue.js 3 + MySQL 8). It covers five integrated modules: Protection, Savings, Investment, Retirement, and Estate Planning.

**Production URL**: https://csjones.co/fynla
**Version**: v0.4.5

## Essential Commands

### Development Servers (MUST run both)

```bash
# Recommended - handles everything automatically
./dev.sh

# Manual alternative (3 terminals)
php artisan serve                    # Laravel backend (port 8000)
npm run dev                          # Vite frontend (port 5173)
php artisan queue:work database      # Queue worker (optional, for Monte Carlo)
```

### Testing

```bash
./vendor/bin/pest                    # Run all tests
./vendor/bin/pest --testsuite=Unit   # Unit tests only
./vendor/bin/pest tests/Unit/Services/Protection/AdequacyScorerTest.php  # Single file
```

**IMPORTANT:** Pest tests may truncate the database. After running tests, reseed required data:
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Code Quality

```bash
./vendor/bin/pint                    # Format code (PSR-12)
./vendor/bin/pint --test             # Check without fixing
```

### Database

```bash
php artisan migrate                  # Run migrations
php artisan db:seed                  # Seed all data (reference + users in dev)
```

**IMPORTANT:** After migrations, always ensure required data is seeded. See **`/seedMigration.md`** for full documentation.

**Required seeders (MUST run for app to function):**
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

Quick reference for common issues:

| Issue | Solution |
|-------|----------|
| Tax Status tab empty | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| Tax calculations failing | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Preview personas broken | `php artisan db:seed --class=PreviewUserSeeder --force` |
| Admin login not working | `php artisan db:seed --class=AdminUserSeeder --force` |
| Life expectancy errors | `php artisan db:seed --class=UKLifeExpectancySeeder --force` |

## Architecture

### Agent-Based System

Each module has an Agent orchestrating business logic:
- **ProtectionAgent** - Life/CI/IP coverage analysis
- **SavingsAgent** - Emergency fund & ISA tracking
- **InvestmentAgent** - Portfolio analysis & Monte Carlo
- **RetirementAgent** - Pension projections & readiness
- **CoordinatingAgent** - Cross-module holistic planning

**Note**: Estate module uses direct service architecture (IHTCalculationService).

### Request Flow

```
Vue Component → JS Service → API → Controller → Agent → Services → Models → DB
```

### Directory Structure

**Backend** (`app/`):
- `Agents/` - Business logic orchestrators
- `Services/{Module}/` - Domain calculations (63 services)
- `Http/Controllers/Api/` - API endpoints (48 controllers)
- `Http/Requests/` - Form validation
- `Models/` - Eloquent models

**Frontend** (`resources/js/`):
- `views/{Module}/` - Dashboard views
- `components/{Module}/` - Module components
- `services/` - API wrappers
- `store/modules/` - Vuex stores (17 modules)

## Critical Rules

### 1. Keep It Simple

**ALWAYS use the simplest solution.** Do not over-engineer, over-complicate, or add unnecessary validation/checks. Write minimal, clean code that does exactly what's needed - nothing more.

Before writing code, ask: "What's the simplest way to do this?" If your solution has excessive error handling, verbose logging, or redundant checks - simplify it.

### 2. Use Available Skills

Check for relevant skills before starting any task:
- **systematic-debugging** - For ALL bugs and troubleshooting
- **fps-module-builder** - Creating new full-stack modules
- **fps-feature-builder** - Adding features to existing modules
- **fps-component-builder** - Creating Vue 3 components

Agents available:
- **laravel-stack-deployer** - Production deployments
- **code-quality-auditor** - Code quality audits

### 3. Never Hardcode Tax Values

All UK tax values come from database via `TaxConfigService`:

```php
use App\Services\TaxConfigService;

public function __construct(private TaxConfigService $taxConfig) {}

public function calculate()
{
    $nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'];  // £325,000
    $isaLimit = $this->taxConfig->getISAAllowances()['annual_allowance'];  // £20,000
}
```

**Never use** `config('uk_tax_config')` - it's deprecated.

### 4. Unified Form Components

One form serves all contexts (onboarding, dashboard, edit):

```vue
<PolicyFormModal
  :policy="editingPolicy"
  :is-editing="!!editingPolicy"
  @save="handleSave"
  @close="closeModal"
/>
```

**Critical**: Use `@save` not `@submit` (causes double submission bug).

### 5. Canonical Data Types

Use exact values from database enums:

| Type | Values |
|------|--------|
| Ownership | `individual`, `joint`, `tenants_in_common`, `trust` |
| Property | `main_residence`, `secondary_residence`, `buy_to_let` |
| Mortgage | `repayment`, `interest_only`, `mixed` |
| Liability | `mortgage`, `secured_loan`, `personal_loan`, `credit_card`, `overdraft`, `hire_purchase`, `student_loan`, `business_loan`, `other` |

**Never use** `sole` (use `individual`), `second_home`, `part_and_part`.

### 6. Environment Separation

The project supports multiple deployment targets with clear separation:

```
deploy/
├── fynla-org/          # ROOT deployment at https://fynla.org
│   ├── .env.production
│   ├── .htaccess
│   └── build.sh
└── csjones-fynla/      # SUBDIRECTORY deployment at https://csjones.co/fynla
    ├── .env.production
    ├── .htaccess
    └── build.sh
```

**Build for specific target:**
```bash
./deploy/fynla-org/build.sh        # For fynla.org
./deploy/csjones-fynla/build.sh    # For csjones.co/fynla
```

**Key differences between environments:**

| Setting | fynla.org (ROOT) | csjones.co/fynla (SUBDIRECTORY) |
|---------|------------------|----------------------------------|
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `APP_URL` | `https://fynla.org` | `https://csjones.co/fynla` |
| `.htaccess RewriteBase` | `/` | `/fynla/` |

**Never export production env vars in development:**
```bash
# WRONG - causes CORS, DB, cache errors
export $(cat .env.production | xargs)

# CORRECT - always use
./dev.sh
```

### 7. Deployment

**Full deployment guides:**
- `DEPLOYMENT_FYNLA_ORG.md` - Step-by-step guide for fynla.org
- `deploy/README.md` - Environment configuration overview

**Never include `public/build/` in deployment packages without permission.**

## Key Patterns

### Database Field Names

Use exact field names from schema:
- Liabilities: `liability_name` (not `description`), `current_balance` (not `amount`)
- DC Pensions: `monthly_contribution_amount` (not `employee_contribution_amount`)
- DB Pensions: `accrued_annual_pension` (not `current_annual_pension`), `lump_sum_entitlement` (not `lump_sum_option`)
- Mortgages: `ownership_type`, `joint_owner_id`, `joint_owner_name`

### Reciprocal Records Pattern (Joint Assets)

Joint assets create TWO database records - one for each owner with their share:

```php
// Creating a joint property (£320,000 total, 50/50 split)
// Record 1: Primary user
Property::create([
    'user_id' => $user->id,
    'joint_owner_id' => $spouse->id,
    'current_value' => 160000,  // User's 50% share
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
]);

// Record 2: Spouse (reciprocal)
Property::create([
    'user_id' => $spouse->id,
    'joint_owner_id' => $user->id,
    'current_value' => 160000,  // Spouse's 50% share
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
]);
```

**Key principles:**
- Each record stores the owner's share in `current_value` (not the total)
- Services only query by `user_id` - no complex joint_owner_id logic needed
- Applies to: Properties, Mortgages, Savings, Investments
- Individual pensions are assigned to the correct spouse via owner detection

### Date Formatting

HTML5 date inputs require `yyyy-MM-dd`:

```javascript
formatDateForInput(date) {
  if (!date) return '';
  const d = new Date(date);
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
```

### Currency Formatting

**Always use the centralized `currencyMixin`** - never define local `formatCurrency()` methods in Vue components.

```javascript
// In Vue component
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  mixins: [currencyMixin],
  // Now use this.formatCurrency(), this.formatCurrencyWithPence(), etc.
}
```

Available methods from the mixin:
- `formatCurrency(value)` - £1,234 (no decimals)
- `formatCurrencyWithPence(value)` - £1,234.56 (2 decimals)
- `formatCurrencyCompact(value)` - £1.2M or £500K (compact notation)
- `parseCurrency(string)` - Converts "£1,234" back to number
- `formatPercentage(value, options)` - 12.5%

**Never define local formatCurrency methods** - this causes code duplication. The mixin is already included in 111+ components.

### Sync Related Form Data

When forms have parent-child relationships (property + mortgage):

```javascript
watch: {
  'form.ownership_type'(newVal) {
    this.mortgageForm.ownership_type = newVal;
  },
  'form.joint_owner_id'(newVal) {
    this.mortgageForm.joint_owner_id = newVal;
  }
}
```

### Coming Soon Banner

```vue
<div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
  <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
    <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
  </div>
</div>
```

## UK Tax Context

- **Tax Year**: April 6 to April 5
- **Active Year**: 2025/26
- **IHT**: 40% above NRB (£325,000) + RNRB (£175,000)
- **ISA Allowance**: £20,000 (individual ownership only)
- **Pension Annual Allowance**: £60,000

## Coding Standards

### PHP (PSR-12)
- `declare(strict_types=1);` in all files
- Classes: `PascalCase`, Methods: `camelCase`, Database: `snake_case`
- Type hints required

### Vue.js
- Multi-word component names
- Always use `:key` with `v-for`
- Never `v-if` with `v-for` on same element

### British vs American Spelling
- **User-facing text**: British (Optimisation, Customise)
- **Code syntax**: American (optimize, center) - follows framework conventions

## Demo Credentials

- **User**: demo@fps.com / password
- **Admin**: admin@fps.com / admin123

## Preview Mode

Preview mode uses database-backed personas with real user records (`is_preview_user=true`).

### Seeding Preview Users

```bash
# Delete and reseed all preview users
php artisan db:seed --class=PreviewUserSeeder --force
```

### Preview Personas

| Persona | Primary | Spouse | Key Data |
|---------|---------|--------|----------|
| young_family | James Carter | Emily Carter | Joint property, workplace pensions |
| peak_earners | David Mitchell | Sarah Mitchell | Multiple properties, SIPP + NHS DB pension |
| widow | Margaret Thompson | Robert (deceased) | Estate planning focus |
| entrepreneur | Alex Chen | None | SIPP, business interests |

### Owner Detection for Pensions/Accounts

The seeder uses multiple detection methods to assign pensions and accounts to the correct spouse:

1. **Explicit owner flag**: `'owner' => 'spouse'` in JSON
2. **Name matching**: Account/pension name contains spouse's first name
3. **Employer matching**: Scheme name contains spouse's employer
4. **Salary matching**: Pension salary matches spouse's income (within 1%)

### Frontend Components in Preview Mode

**Important**: Preview users are real database users. Frontend components should NOT bypass API calls in preview mode.

```javascript
// WRONG - bypasses API in preview mode
async loadData() {
    if (this.$store.getters['preview/isPreviewMode']) {
        this.computePreviewData();  // Client-side calculation
        return;
    }
    // API call...
}

// CORRECT - all users use API
async loadData() {
    // Preview users are real DB users - use normal API
    const response = await api.get('/endpoint');
    // ...
}
```

Write operations can still be blocked in preview mode using `PreviewWriteInterceptor` middleware.

## API Testing

### Authentication
```bash
# Login with preview persona (returns token)
curl -X POST "http://localhost:8000/api/preview/login/young_family" -H "Accept: application/json"

# Use token for authenticated requests
curl -H "Authorization: Bearer TOKEN" -H "Accept: application/json" "http://localhost:8000/api/endpoint"
```

### Key API Routes

| Module | Endpoint | Method | Description |
|--------|----------|--------|-------------|
| **Auth** | `/api/preview/login/{persona}` | POST | Login as preview persona |
| **Profile** | `/api/user/profile` | GET | User profile with income/tax |
| **Profile** | `/api/user/family-members` | GET | Family members list |
| **Profile** | `/api/user/spouse` | GET | Spouse data |
| **Net Worth** | `/api/properties` | GET | Properties list |
| **Net Worth** | `/api/liabilities` | GET | Liabilities list |
| **Savings** | `/api/savings` | GET | Savings accounts |
| **Savings** | `/api/savings/accounts` | POST | Create savings account |
| **Investment** | `/api/investment` | GET | Investment accounts |
| **Investment** | `/api/investment/risk/profile` | GET | Risk profile |
| **Retirement** | `/api/retirement` | GET | All pension data |
| **Retirement** | `/api/retirement/dc-pensions` | GET | DC pensions |
| **Retirement** | `/api/retirement/db-pensions` | GET | DB pensions |
| **Protection** | `/api/protection` | GET | All policies |
| **Protection** | `/api/protection/policies/life` | POST | Create life policy |
| **Protection** | `/api/protection/adequacy` | GET | Adequacy analysis |
| **Estate** | `/api/estate` | GET | Estate overview |
| **Estate** | `/api/estate/calculate-iht` | POST | Calculate IHT |
| **Dashboard** | `/api/dashboard` | GET | Dashboard summary |
| **Tax** | `/api/tax-info/investment/isa` | GET | ISA tax info |
| **Tax** | `/api/tax-settings/current` | GET | Current tax settings (admin) |

### CRUD Pattern for Protection Policies
```bash
# Create
POST /api/protection/policies/life
POST /api/protection/policies/critical-illness
POST /api/protection/policies/income-protection

# Update
PUT /api/protection/policies/life/{id}

# Delete
DELETE /api/protection/policies/life/{id}
```

### CRUD Pattern for Savings
```bash
POST   /api/savings/accounts        # Create
GET    /api/savings/accounts/{id}   # Read
PUT    /api/savings/accounts/{id}   # Update
DELETE /api/savings/accounts/{id}   # Delete
```

## Troubleshooting

### Common API Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `No active tax configuration found` | TaxConfigurationSeeder not run | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| `Preview user not found` | PreviewUserSeeder not run | `php artisan db:seed --class=PreviewUserSeeder --force` |
| `401 Unauthenticated` | Missing/invalid Bearer token | Get fresh token from `/api/preview/login/{persona}` |
| `403 Admin access required` | Endpoint requires admin role | Use admin credentials or different endpoint |
| `405 Method Not Allowed` | Wrong HTTP method | Check route with `php artisan route:list --path=endpoint` |
| Tax Status tab empty | TaxProductReferenceSeeder not run | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |

### After Running Pest Tests

Pest tests may clear required data. Always reseed after running tests:
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Checking Routes

```bash
# List all routes
php artisan route:list

# Filter by path
php artisan route:list --path=protection
php artisan route:list --path=estate
php artisan route:list --path=savings
```

### Response Headers

All API responses include rate limiting headers:
- `X-RateLimit-Limit`: Maximum requests allowed (1000 for most endpoints, 5-10 for auth)
- `X-RateLimit-Remaining`: Remaining requests in window
- `Content-Type: application/json`
- `Cache-Control: no-cache, private`
