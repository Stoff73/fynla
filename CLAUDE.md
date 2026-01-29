# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Fynla** is a UK financial planning application (Laravel 10 + Vue.js 3 + MySQL 8) covering five modules: Protection, Savings, Investment, Retirement, and Estate Planning.

| Metric | Count |
|--------|-------|
| Vue Components | 282 |
| PHP Services | 137 |
| Controllers | 62 |
| Models | 65 |
| Vuex Stores | 21 |
| Agents | 8 |

**Production**: https://fynla.org | **Version**: v0.6.2

## Commands

```bash
# Development
./dev.sh                             # Start Laravel + Vite (recommended)

# Testing
./vendor/bin/pest                    # Run all tests
./vendor/bin/pest tests/Unit/...     # Single file

# Database (run after tests or migrations)
php artisan migrate && php artisan db:seed

# Code formatting
./vendor/bin/pint                    # PSR-12 format
```

**Reseed specific data:**

| Issue | Command |
|-------|---------|
| Tax calculations failing | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Tax Status tab empty | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| Preview personas broken | `php artisan db:seed --class=PreviewUserSeeder --force` |
| Life expectancy errors | `php artisan db:seed --class=ActuarialLifeTablesSeeder --force` |

## Architecture

```
Vue Component → API Service → Controller → Agent → Services → Models → DB
```

**Backend** (`app/`):
- `Agents/` - Module orchestrators (ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, EstateAgent, GoalsAgent, CoordinatingAgent)
- `Services/{Module}/` - Domain calculations
- `Http/Controllers/Api/` - API endpoints

**Frontend** (`resources/js/`):
- `components/{Module}/` - Vue components
- `store/modules/` - Vuex state management
- `services/` - API wrappers

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
Form modals use a two-part pattern to prevent double submission:
1. **Internal form element**: Uses `@submit.prevent="handleSubmit"` to prevent page reload
2. **Emit to parent**: Handler calls `this.$emit('save', formData)` - always emit `save`, not `submit`
3. **Parent listens**: Uses `@save="handleSave"` to receive the event

```vue
<!-- Inside form modal component -->
<form @submit.prevent="handleSubmit">
  ...
</form>

<!-- In methods -->
handleSubmit() {
  this.$emit('save', this.formData);  // Emit 'save', not 'submit'
}

<!-- Parent component using the modal -->
<AccountForm @save="handleAccountSave" @close="closeModal" />
```

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
Joint assets use a SINGLE record with `joint_owner_id` and `ownership_percentage` representing the primary owner's share. The spouse's share is `(100 - ownership_percentage)`:
```php
Property::create([
    'user_id' => $user->id,
    'joint_owner_id' => $spouse->id,
    'ownership_type' => 'tenants_in_common',  // or 'joint'
    'ownership_percentage' => 70,  // Primary owner's share; spouse gets 30%
    'current_value' => 320000,
]);
```
When querying for the joint owner's share, invert the percentage:
```php
$userIsOwner = $property->user_id === $user->id;
$ownershipMultiplier = $userIsOwner
    ? ($property->ownership_percentage ?? 50) / 100
    : (100 - ($property->ownership_percentage ?? 50)) / 100;
```

### 8. PreviewWriteInterceptor Middleware
When adding new auth-related POST routes, add them to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`. This middleware intercepts all write operations from preview users - any route that must work regardless of preview mode state (login, register, password reset) must be excluded.

### 9. No Amber Color
The amber color (`amber-*`) is banned from the application. Use orange (`orange-*`) instead for warnings and caution states. See `designStyle.md` for the full color system.

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
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

## Preview Mode

Test via landing page persona selector at http://localhost:8000, not direct URLs.

| Persona | Users | Focus |
|---------|-------|-------|
| young_family | James & Emily Carter | Mortgage, workplace pensions |
| peak_earners | David & Sarah Mitchell | Multiple properties, SIPP + NHS pension |
| widow | Margaret Thompson | Estate planning |
| entrepreneur | Alex Chen | SIPP, business interests |

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

## Automatic Tool Usage

When working on this codebase, automatically use these without prompting:

**Skills** (invoke with `/command`):
- `/systematic-debugging` - For any bug, error, or unexpected behaviour investigation
- `/fps-component-builder` - When creating new Vue components
- `/fps-feature-builder` - When adding features to existing modules
- `/fps-module-builder` - When creating new full-stack modules

**Agents** (invoke automatically when relevant):
- `code-quality-auditor` - After completing multi-file feature work
- `database-optimizer` - When queries are slow or designing new tables/schemas
- `laravel-stack-deployer` - For production deployment tasks
- `product-manager` - When planning new features or creating user stories
- `premium-ui-designer` - When polishing UI, adding animations, or improving UX
- `Explore` - For codebase exploration and understanding
