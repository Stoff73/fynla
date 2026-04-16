# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Fynla** is a UK financial planning application (Laravel 10 + Vue.js 3 + MySQL 8) covering seven modules: Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination.

| Metric | Count |
|--------|-------|
| Vue Components | 663 |
| PHP Services | 234 |
| Controllers | 94 |
| Models | 94 |
| Vuex Stores | 32 |
| Agents | 9 |

**Production**: https://fynla.org | **Version**: v1.0

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

**CRITICAL: ALWAYS reseed after any operation that modifies or loses local database data.** This includes running migrations, schema dumps, database resets, table drops, or any other destructive database operation. After such operations, run `php artisan db:seed` to restore all seeded data (tax config, preview personas, market rates, etc.).

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
- `Services/{Module}/` - Domain calculations (214 services across 32 module directories)
- `Http/Controllers/Api/` - API endpoints (89 controllers)
- `Http/Requests/` - Form request validation (83 classes)
- `Http/Resources/` - API response transformation
- `Traits/` - Shared behaviours (`Auditable`, `HasJointOwnership`, `CalculatesOwnershipShare`, `FormatsCurrency`, `StructuredLogging`, `PolicyCRUDTrait`, `ResolvesExpenditure`, `ResolvesIncome`, `TracksGoalContributions`)
- `Constants/` - `TaxDefaults`, `ValidationLimits`, `EstateDefaults`
- `Observers/` - Risk recalculation observers, goal contribution trackers, Monte Carlo triggers (12 observers)
- `Exceptions/FinancialCalculationException` - Domain exception with factory methods

**Frontend** (`resources/js/`): See `resources/js/CLAUDE.md` for detailed conventions.
- `components/{Module}/` - Vue components (443 across 28 module directories)
- `views/` - Page-level route components (73 views)
- `store/modules/` - Vuex state management (31 namespaced modules)
- `services/` - API wrappers (44 services)
- `mixins/` - `currencyMixin` (formatting), `previewModeMixin` (preview blocking)
- `utils/` - `currency`, `dateFormatter`, `ownership`, `poller`, `logger`
- `constants/` - `designSystem`, `eventIcons`, `eventIconSvgs`, `goalIcons`, `taxConfig`
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

### 9. No Amber, Orange, or Non-Palette Colors
The amber (`amber-*`) and orange (`orange-*`) colors are banned from the application. Use violet (`violet-*`) for warnings and caution states, raspberry (`raspberry-*`) for errors/danger, spring (`spring-*`) for success. All colors must come from the palette defined in `fynlaDesignGuide.md` v1.2.0. Use Tailwind tokens (`raspberry-*`, `horizon-*`, `spring-*`, `violet-*`, `savannah-*`, `eggshell-*`) — never hardcode hex.

### 10. No Acronyms in User-Facing Text
All acronyms must be spelled out in user-facing text. Write "Annual Allowance" not "AA", "Stocks & Shares" not "S&S", "Defined Benefit" not "DB", "Defined Contribution" not "DC", "Money Purchase Annual Allowance" not "MPAA", etc. The only exception is **ISA**, which may remain abbreviated.

### 11. Design System Compliance
**CRITICAL:** Before changing, updating, or implementing anything related to the UI, you MUST read and follow `fynlaDesignGuide.md` (v1.2.0). This includes:
- Colors: Raspberry (CTAs), Horizon (text/nav), Spring (success), Violet (warnings/focus), Savannah (hover/subtle), Eggshell (page bg)
- Typography: Segoe UI (primary), Inter (fallback), font weights 900 (display/h1), 700 (h2-h5)
- Component patterns (buttons, cards, forms, modals)
- Badges and status indicators
- Charts and data visualisation (use `designSystem.js` constants)

The design system is the single source of truth for all visual decisions. Never introduce new colors, spacing values, or component patterns without checking `fynlaDesignGuide.md` first.

### 12. CSS Governance
- **No duplicated CSS patterns** — check `app.css` before adding scrollbar, animation, spinner, range slider, accordion, badge, or back-button styles to `<style scoped>`. Use the global classes instead.
- **No hardcoded hex in style blocks** — use Tailwind `@apply` directives (e.g. `@apply text-horizon-500` not `color: #1F2A44`). For dynamic chart colours, import from `designSystem.js`.
- **All colors from palette** — use only tokens from `fynlaDesignGuide.md` v1.2.0: `raspberry-*`, `horizon-*`, `spring-*`, `violet-*`, `savannah-*`, `eggshell-*`, `neutral-*`, `light-gray`, `light-blue-*`, `light-pink-*`. Never use old tokens (`primary-*`, `secondary-*`, `gray-*` for general UI).
- **Use global classes** for: `.scrollbar-hide`, `.scrollbar-thin`, `.animate-fade-in`, `.animate-fade-in-slide`, `.detail-inline-back`, `.expand-*` transitions, `animate-spin`, badge classes (`.badge-isa`, `.badge-active`, etc.), card variants (`.card`, `.card-lg`, `.card-sm`).
- **Spinners**: Use `<div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>` — never define custom `@keyframes spin`.

### 13. No Scores in User-Facing UI
Scores (numerical ratings like "75/100", adequacy scores, diversification scores, portfolio health scores) must never appear in user-facing UI. This includes score badges, score metric cards, score-formatted values, and score-based narrative text. Scores oversimplify complex financial positions and can mislead users. Instead, use descriptive text, specific metrics (currency values, percentages, time periods), and actionable guidance.

## Vault Reference (fynlaBrain)

The project knowledge base is at `/Users/CSJ/Desktop/fynlaBrain/` (693 Obsidian docs). **Before working on any module, read the relevant vault docs.**

| Module | Architecture Doc | Current State Doc |
|--------|-----------------|-------------------|
| Investment | `v083/09-MODULES.md` | `Investment.md` |
| Estate | `v083/09-MODULES.md` | `EstatePlanning.md` |
| Protection | `v083/09-MODULES.md` | `Protection.md` |
| Retirement | `v083/09-MODULES.md` | `Retirement.md` |
| Savings | `v083/09-MODULES.md` | `Savings.md` |
| Goals | `v083/09-MODULES.md` | `GoalsLifeEvents.md` |
| Property | `v083/09-MODULES.md` | `Property.md` |
| Auth/Security | `v083/03-AUTH-SECURITY.md` | `Auth.md` |
| Database | `v083/02-DATABASE.md` | — |
| Frontend | `v083/05-FRONTEND.md` | — |
| Backend | `v083/04-BACKEND.md` | — |
| Deployment | `v083/11-CONFIG-DEPLOY.md` | `DeploymentBuild.md` |
| AI Chat | `v083/10-NEW-SYSTEMS.md` | — |
| Tax/Financial | `v083/08-FINANCIAL-CALCS.md` | `UKTaxes.md` |
| Payments | `v083/10-NEW-SYSTEMS.md` | `PaymentSubscription.md` |

### Sub-Agent Vault Context (MANDATORY)

When dispatching ANY agent to work on module code:
1. Read the relevant vault docs for the module first
2. Include in the agent prompt: architecture patterns, recent fixes, feedback rules

Never dispatch an agent with just "fix X" or "build Y". Always include:
- What module this is in and its patterns
- Recent bugs/fixes in this area (from vault deploy/fix docs)

## Deployment

### Two environments

Fynla runs on two environments, isolated database, code, and credentials:

| Env | URL | Purpose | Branch | Server path | SSH alias |
|-----|-----|---------|--------|-------------|-----------|
| **Production** | `https://fynla.org` | Live customers — real charges, real emails | `main` | `~/www/fynla.org/public_html/` | `ssh.fynla.org:18765` as `u2783-hrf1k8bpfg02` |
| **Dev / staging** | `https://csjones.co/fynla` | Pre-production testing — Revolut sandbox, throwaway DB | `dev` | `~/www/csjones.co/public_html/fynla/` | `ssh.csjones.co:18765` as `u163-ptanegf9edny` |

**Work always flows `feature → dev → main`, never skipping the dev gate.** See the branch workflow section below.

**⚠️ Never** deploy `dev` to fynla.org or `main` to csjones.co — the build scripts target different `VITE_BASE_PATH` / `RewriteBase` paths and the wrong combination breaks routing silently.

### Branch workflow

```
feature/<owner>/<short-task>   ──PR──►   dev   ──PR──►   main
```

- `main` = exactly what's running on `fynla.org`. Protected. Only `@Stoff73` can merge.
- `dev` = exactly what's running on `csjones.co/fynla`. Protected. Only `@Stoff73` can merge.
- `feature/<owner>/<task>` = working branches. Naming is **mandatory**:
  - `feature/csj/<task>` — your own work
  - `feature/icecube/<task>` — `icecube-acc`
  - `feature/phailanx/<task>` — `Phailanx`
  - Any other prefix is wrong and the PR will be closed.
- **All PRs target `dev`**, never `main` directly (except the periodic `dev → main` release PR which only `@Stoff73` opens).
- `.github/CODEOWNERS` forces `@Stoff73` as a required reviewer on every PR.

### Build scripts (per environment)

**Build locally** — server lacks memory for npm:

```bash
./deploy/fynla-org/build.sh        # Build for fynla.org (root deployment)
./deploy/csjones-fynla/build.sh    # Build for csjones.co/fynla (subdirectory)
```

The scripts set different Vite environment variables so the SPA routing and asset paths match the target:

| Setting | fynla.org (main) | csjones.co/fynla (dev) |
|---------|------------------|------------------------|
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `VITE_ROUTER_BASE` | `/` | `/fynla/` |
| `VITE_API_BASE_URL` | `https://fynla.org` | `https://csjones.co/fynla` |
| `VITE_REVOLUT_SANDBOX` | `false` | `true` |
| `.htaccess` `RewriteBase` | `/` | `/fynla/` |
| `APP_ENV` | `production` | `staging` |
| `APP_DEBUG` | `false` | `true` |
| `REVOLUT_SANDBOX` | `false` | `true` |
| `LIFECYCLE_TEST_RECIPIENT` | unset | `chris@fynla.org` |

**Never mix environments.** If you build with `csjones-fynla/build.sh` and upload to fynla.org, the Vue router base path will be wrong and the app won't load. There's no nice error — you'll just see a blank page or a 404 loop.

### Deploying to dev (csjones.co/fynla)

1. Work on a `feature/<owner>/<task>` branch, open PR → `dev`
2. After merge: `git checkout dev && git pull`
3. Build: `./deploy/csjones-fynla/build.sh`
4. Upload `public/build/` + changed PHP files to `~/www/csjones.co/public_html/fynla/` via SiteGround File Manager or `rsync`
5. Upload `deploy/csjones-fynla/.htaccess` to `~/www/csjones.co/public_html/fynla/public/.htaccess` (only if routing rules changed)
6. SSH in and finalise:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

7. Smoke test `https://csjones.co/fynla`
8. If a dev DB reset is needed: `php artisan db:seed --force` (NEVER `migrate:fresh` — see rule above)

**First-time dev setup** (one-time only): see `deploy/csjones-fynla/BOOTSTRAP.md` for the full provision-and-deploy guide.

### Deploying to production (fynla.org)

Only after dev is tested and green:

1. Open PR `dev → main` (you open and approve this yourself)
2. Merge
3. `git checkout main && git pull`
4. Build: `./deploy/fynla-org/build.sh`
5. Upload `public/build/` + changed PHP files to `~/www/fynla.org/public_html/`
6. SSH in and finalise:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

7. Smoke test `https://fynla.org`
8. Monitor `storage/logs/laravel.log` for errors for the next 10-15 minutes

### Environment config templates

- `deploy/csjones-fynla/.env.production` — template for the dev `.env`. Has `APP_ENV=staging`, `REVOLUT_SANDBOX=true`, `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`.
- `deploy/fynla-org/.env.production` — template for the production `.env`. Has `APP_ENV=production`, `REVOLUT_SANDBOX=false`, no test recipient override.

Real credentials (DB password, mail password, Revolut keys, Anthropic key) live only in each server's `.env` — never in the repo, never echoed in chat.

## Mobile App (Capacitor iOS)

**Build:** `./deploy/mobile/build-ios.sh` (builds web assets + `npx cap sync ios`). NEVER use `npx vite build` alone for mobile — changes won't reach the iOS app.

**After any mobile change:** Clear server cache (`php artisan cache:clear`) — mobile dashboard is cached 5 min per user.

**CRITICAL — vite.config.js rules for iOS (blank screen prevention):**
- **NEVER** add `external` to `rollupOptions` for image/asset paths — causes `'image/png' is not a valid JavaScript MIME type'` → blank screen. Rollup leaves `/images/*` as JS module imports, WKWebView rejects PNGs served as JavaScript.
- **ALWAYS** keep `transformAssetUrls: false` in the `vue()` plugin template config
- **ALWAYS** keep `!disablePWA && VitePWA(...)` — PWA must be conditionally disabled for iOS

**Biometric (Face ID) login:**
- Mobile logout MUST use `auth/mobileLogout` (clears local state only) — NEVER `auth/logout` (revokes server token, breaks Face ID)
- Token stored in iOS Keychain via `@capgo/capacitor-native-biometric`, separate from `@capacitor/preferences`
- `app.js` calls `attemptBiometricLogin()` on startup; `SettingsList.vue` has the toggle; `BiometricPrompt.vue` is the setup modal

**Capacitor gotchas:**
- `window.location.origin` = `capacitor://localhost` — use `import.meta.env.VITE_API_BASE_URL || 'https://fynla.org'` for Browser.open() and fetch() URLs
- Vue Router child routes do NOT inherit parent `meta` — use `to.matched.some(r => r.meta.requiresAuth)` not `to.meta.requiresAuth`
- `fetch()` cross-origin needs `credentials: 'omit'` to avoid CORS cookie issues
- WKWebView may not support `response.body` for streaming — always add a fallback reader
- API returns `fyn_insight` (snake_case), not `insight` — watch for key name mismatches

**Mobile dashboard data flow:**
```
Backend (MobileDashboardAggregator) → raw module fields (total_coverage, portfolio_value, etc.)
→ Vuex store normaliseModule() → normalised shape (metric_type, metric_value, hero_metric, details)
→ ModuleSummaryCard + ModuleSummary components
```

**Key mobile files:**
- `resources/js/mobile/` — All mobile Vue components
- `resources/js/store/modules/mobileDashboard.js` — Dashboard state + normaliseModule()
- `resources/js/store/modules/mobileNotifications.js` — Push notification state
- `app/Services/Mobile/MobileDashboardAggregator.php` — Backend aggregator
- `deploy/mobile/build-ios.sh` — iOS build script
- `ios/` — Capacitor iOS project (open `ios/App/App.xcworkspace` in Xcode)

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

**Production (fynla.org):**
1. Enter credentials: `chris@fynla.org` / `Password1!`
2. When the verification code screen appears, **ask the user for the code**
3. Enter the code provided and continue testing

**Local dev (localhost:8000) — get the code yourself:**
1. Enter credentials: `john@example.com` / `password` (or any seeded test user)
2. When the verification code screen appears, fetch it from the database:
```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code ?? 'none';"
```
3. Enter the code and continue — do NOT ask the user for local dev codes

**Test user credentials (local dev):**

| Email | Password | Notes |
|-------|----------|-------|
| `john@example.com` | `password` | Test user with full data |
| `jane@example.com` | `password` | Spouse of John |
| `sarah@example.com` | `password` | Additional test user |
| `chris@fynla.org` | `Password1!` | Admin user |

## Troubleshooting

Don't suggest browser cache clearing - user tests in incognito.

| Error | Fix |
|-------|-----|
| Blank page with 127.0.0.1:5173 | `rm public/hot` on server |
| MIME type errors (web) | Rebuild with `./deploy/fynla-org/build.sh` |
| iOS blank screen / `'image/png' is not a valid JavaScript MIME type'` | Check `vite.config.js`: remove any `external` from `rollupOptions`, ensure `transformAssetUrls: false` in vue() plugin. Run `grep -r 'import("/images' public/build/assets/` to verify no image imports in built JS. Delete app from device, clean build in Xcode. |
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

### CRITICAL — Browser Testing Rules (NON-NEGOTIABLE)

**These rules override everything. Violating them is an absolute failure.**

1. **"Browser tested" means you INTERACTED with the element in Playwright.** You CLICKED it. You FILLED the form. You SUBMITTED it. You verified the RESULT. Reading a code diff is NOT a browser test. Taking a snapshot without interacting is NOT a test.

2. **You are NOT DONE until every form has been FILLED and SUBMITTED in the browser.** If login fails on production — ASK THE USER for the verification code. On local dev — fetch the code from the database yourself (see Authentication for Testing). Do NOT skip. Do NOT defer. Do NOT write "requires user assistance" and move on.

3. **NEVER write a completion report until ALL testing is actually complete.** Reports are the LAST thing you do. Writing a report before testing is LYING.

4. **Test EVERY journey end-to-end.** Register/login → select stage → fill EVERY field on EVERY step → submit → verify dashboard shows ALL entered data → verify sidebar → verify cards. No shortcuts.

5. **If you cannot test something, say "I COULD NOT TEST THIS."** NEVER say "verified", "pass", or "confirmed" for untested items.

6. **When you hit a blocker, STOP AND ASK THE USER.** Do not give up. Do not skip.

7. **After ANY code change, re-test from Step 1.** Fixes break other things.

8. **Every checkbox you mark [x] must have a corresponding Playwright interaction.**

### Pest Tests

```bash
./vendor/bin/pest                                          # All tests (940+)
./vendor/bin/pest tests/Unit/Services/Estate/              # Module tests
./vendor/bin/pest --testsuite=Architecture                 # Code standards
./vendor/bin/pest --filter="calculateIHTLiability"         # By name
```

- **Framework**: Pest (PHPUnit-compatible) with `it()` / `describe()` syntax
- **Suites**: Unit (81), Feature (46), Architecture (6), Integration (2)
- **Database**: `RefreshDatabase` trait resets between tests; TaxConfiguration auto-seeded in `beforeEach()`
- **Auth**: `$this->actingAs($user)` or `Sanctum::actingAs($user)`
- **Factories**: 46 factories in `database/factories/` with state methods
- **Mocking**: Mockery for service dependencies; always `Mockery::close()` in `afterEach()`
- See `tests/CLAUDE.md` for full conventions

