# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Fynla** is a UK financial planning application (Laravel 10 + Vue.js 3 + MySQL 8) covering seven modules: Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination.

| Metric | Count |
|--------|-------|
| Vue Components | 315 |
| PHP Services | 149 |
| Controllers | 68 |
| Models | 70 |
| Vuex Stores | 21 |
| Agents | 8 |

**Production**: https://fynla.org | **Version**: v0.8.0

## Commands

```bash
# Development
./dev.sh                             # Start Laravel + Vite (recommended)

# Testing
./vendor/bin/pest                    # Run all tests
./vendor/bin/pest tests/Unit/...     # Single file

# Database - Reseed (PRESERVES existing data)
php artisan db:seed                  # Reseed all data

# Database - Fresh install only (runs pending migrations)
php artisan migrate && php artisan db:seed

# Code formatting
./vendor/bin/pint                    # PSR-12 format
```

**CRITICAL: NEVER use `migrate:fresh` or `migrate:refresh` when asked to reseed. These commands DROP ALL TABLES and destroy user data. Use `php artisan db:seed` instead.**

**Reseed specific data:**

| Issue | Command |
|-------|---------|
| Tax calculations failing | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Tax Status tab empty | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| Preview personas broken | `php artisan db:seed --class=PreviewUserSeeder --force` |
| Life expectancy errors | `php artisan db:seed --class=ActuarialLifeTablesSeeder --force` |
| Savings market rates missing | `php artisan db:seed --class=SavingsMarketRatesSeeder --force` |

**Custom artisan commands:**

| Command | Purpose |
|---------|---------|
| `php artisan preview:reset` | Reset all preview persona data |
| `php artisan audit:purge` | Purge old audit log entries |
| `php artisan trials:expire` | Expire ended trial subscriptions |
| `php artisan sessions:cleanup` | Clean up orphaned user sessions |
| `php artisan registrations:cleanup` | Remove stale pending registrations |

## Architecture

```
Vue Component → API Service → Controller → Agent → Services → Models → DB
```

**Backend** (`app/`): See `app/Services/CLAUDE.md` and `app/Http/CLAUDE.md` for detailed conventions.
- `Agents/` - Module orchestrators (ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, EstateAgent, GoalsAgent, CoordinatingAgent)
- `Services/{Module}/` - Domain calculations (141+ services across 15 module directories)
- `Http/Controllers/Api/` - API endpoints (68 controllers)
- `Http/Requests/` - Form request validation (150+ classes)
- `Http/Resources/` - API response transformation
- `Traits/` - Shared behaviours (`Auditable`, `HasJointOwnership`, `CalculatesOwnershipShare`, `FormatsCurrency`, `StructuredLogging`, `PolicyCRUDTrait`, `ResolvesExpenditure`)
- `Constants/` - `TaxDefaults`, `ValidationLimits`, `EstateDefaults`
- `Observers/` - Risk recalculation observers (auto-trigger on model changes)
- `Exceptions/FinancialCalculationException` - Domain exception with factory methods

**Frontend** (`resources/js/`): See `resources/js/CLAUDE.md` for detailed conventions.
- `components/{Module}/` - Vue components (315 across 28 modules)
- `views/` - Page-level route components (53 views)
- `store/modules/` - Vuex state management (21 namespaced modules)
- `services/` - API wrappers (35 services)
- `mixins/` - `currencyMixin` (formatting), `previewModeMixin` (preview blocking)
- `utils/` - `currency`, `dateFormatter`, `dates`, `ownership`, `poller`, `asyncAction`, `logger`
- `constants/` - `designSystem`, `eventIcons`, `taxConfig`
- `directives/` - `v-preview-disabled` (blocks actions in preview mode)
- `layouts/` - `AppLayout` (authenticated), `PublicLayout` (public pages)
- `router/index.js` - Routes with lazy loading, guards, meta flags (`requiresAuth`, `public`, `previewMode`)

**Database** (`database/`): See `database/CLAUDE.md` for detailed conventions.

**Tests** (`tests/`): See `tests/CLAUDE.md` for detailed conventions.

## Key Rules

### 1. Manual File Upload Only
Never create ZIP files or deployment packages. The user uploads files manually via SiteGround File Manager. When deploying, list the specific files that changed so the user knows what to upload.

### 2. Preview User Isolation
Preview users (`is_preview_user = true`) are seeded test personas, completely separate from real users. When debugging preview issues, only query `WHERE is_preview_user = true`.

### 3. No Hardcoded Tax Values
Use `TaxConfigService` for all UK tax values:
```php
$nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'];
```

### 4. Form Modal Events
Form modals emit `save` (not `submit`) to prevent double submission:
- Internal: `<form @submit.prevent="handleSubmit">` → `this.$emit('save', formData)`
- Parent: `<AccountForm @save="handleAccountSave" @close="closeModal" />`
- Parent handles API call and closes modal on success; keeps modal open on error

### 5. Canonical Enums
| Type | Values |
|------|--------|
| Ownership | `individual`, `joint`, `tenants_in_common`, `trust` |
| Property | `main_residence`, `secondary_residence`, `buy_to_let` |
| Mortgage | `repayment`, `interest_only`, `mixed` |

Never use `sole` (use `individual`).

### 6. Currency Formatting
Always use `currencyMixin` - never define local `formatCurrency()` methods.

### 7. Joint Assets Pattern
Joint assets use a SINGLE record with `joint_owner_id` and `ownership_percentage` (primary owner's share). The spouse's share is `(100 - ownership_percentage)`. Use `CalculatesOwnershipShare` trait (backend) or `ownership.js` util (frontend) to calculate shares. Never create duplicate records for joint owners. Query with `WHERE user_id = ? OR joint_owner_id = ?`.

### 8. PreviewWriteInterceptor Middleware
When adding new auth-related POST routes, add them to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`. This middleware intercepts all write operations from preview users - any route that must work regardless of preview mode state (login, register, password reset) must be excluded.

### 9. No Amber or Orange Color
The amber (`amber-*`) and orange (`orange-*`) colors are banned from the application. Use blue (`blue-*`) instead for warnings and caution states. See `designStyle.md` for the full color system.

### 10. No Acronyms in User-Facing Text
All acronyms must be spelled out in user-facing text. Write "Annual Allowance" not "AA", "Stocks & Shares" not "S&S", "Defined Benefit" not "DB", "Defined Contribution" not "DC", "Money Purchase Annual Allowance" not "MPAA", etc. The only exception is **ISA**, which may remain abbreviated.

### 11. Design System Compliance
**CRITICAL:** Before changing, updating, or implementing anything related to the UI, you MUST read and follow `designStyle.md`. This includes:
- Colors (especially risk level colors, semantic colors, and forbidden colors)
- Typography and spacing
- Component patterns (buttons, cards, forms, modals)
- Badges and status indicators
- Charts and data visualisation

The design system is the single source of truth for all visual decisions. Never introduce new colors, spacing values, or component patterns without checking `designStyle.md` first.

## Deployment

**Build locally** (server lacks memory for npm):
```bash
./deploy/fynla-org/build.sh        # Builds public/build/ for fynla.org
./deploy/csjones-fynla/build.sh    # Builds for csjones.co/fynla
```

| Setting | fynla.org | csjones.co/fynla |
|---------|-----------|------------------|
| VITE_BASE_PATH | `/build/` | `/fynla/build/` |
| RewriteBase | `/` | `/fynla/` |

**Manual upload process:**
1. Run build script locally
2. Upload `public/build/` directory via SiteGround File Manager to `~/www/fynla.org/public_html/public/build/`
3. Upload any changed PHP files (listed in deployment notes)
4. SSH to clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Preview Mode

Test via landing page persona selector at http://localhost:8000, not direct URLs.

| Persona | Users | Focus |
|---------|-------|-------|
| young_family | James & Emily Carter | Mortgage, workplace pensions |
| peak_earners | David & Sarah Mitchell | Multiple properties, SIPP + NHS pension |
| widow | Margaret Thompson | Estate planning |
| entrepreneur | Alex Chen | SIPP, business interests |
| young_saver | John Morgan | Emergency fund, first-time savings |
| retired_couple | Robert & Patricia Williams | Decumulation, estate planning |

## UK Tax Context

- Tax Year: April 6 - April 5 (active: 2025/26)
- IHT: 40% above NRB (£325k) + RNRB (£175k)
- ISA: £20,000/year
- Pension AA: £60,000

## Authentication for Testing

When testing requires login:
1. Enter credentials: `chris@fynla.org` / `Password1!`
2. When the verification code screen appears, **ask the user for the code**
3. Enter the code provided and continue testing

## Troubleshooting

Don't suggest browser cache clearing - user tests in incognito.

| Error | Fix |
|-------|-----|
| Blank page with 127.0.0.1:5173 | `rm public/hot` on server |
| MIME type errors | Rebuild with `./deploy/fynla-org/build.sh` |
| 500 DirectoryMatch error | Upload `deploy/fynla-org/.htaccess` |
| 429 Too Many Requests | `php artisan cache:clear` |

Check routes: `php artisan route:list --path=endpoint`

## Coding Standards

**PHP (PSR-12)**
- `declare(strict_types=1);` in all files
- Classes: `PascalCase`, Methods: `camelCase`, Database: `snake_case`
- Type hints required

**Vue.js**
- Multi-word component names
- Always use `:key` with `v-for`
- Never `v-if` with `v-for` on same element

**Spelling**
- User-facing text: British (Optimisation, Customise)
- Code syntax: American (optimize, center)

## Testing

```bash
./vendor/bin/pest                                          # All tests (1,075+)
./vendor/bin/pest tests/Unit/Services/Estate/              # Module tests
./vendor/bin/pest --testsuite=Architecture                 # Code standards
./vendor/bin/pest --filter="calculateIHTLiability"         # By name
```

- **Framework**: Pest (PHPUnit-compatible) with `it()` / `describe()` syntax
- **Suites**: Unit (59), Feature (37), Architecture (6), Integration (2)
- **Database**: `RefreshDatabase` trait resets between tests; TaxConfiguration auto-seeded in `beforeEach()`
- **Auth**: `$this->actingAs($user)` or `Sanctum::actingAs($user)`
- **Factories**: 42 factories in `database/factories/` with state methods
- **Mocking**: Mockery for service dependencies; always `Mockery::close()` in `afterEach()`
- See `tests/CLAUDE.md` for full conventions

## Automatic Tool Usage

When working on this codebase, automatically use these without prompting:

**Skills** (invoke with `/command`):

- `/systematic-debugging` - For any bug, error, or unexpected behaviour investigation

**Agents** (invoke automatically when relevant):

- `database-optimizer` - When queries are slow or designing new tables/schemas
- `laravel-stack-deployer` - For production deployment tasks
- `product-manager` - When planning new features or creating user stories
- `premium-ui-designer` - When polishing UI, adding animations, or improving UX
- `Explore` - For codebase exploration and understanding
