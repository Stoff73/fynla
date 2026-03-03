# 10. Configuration & Deployment

This chapter documents every configuration file, environment variable, service provider, seeder, dependency, build step, and deployment procedure in Fynla v0.7.0.

---

## 10.1 Configuration Files

The `config/` directory contains 16 PHP files. Each returns an associative array that Laravel merges into the global config at boot time. Values read from `.env` via `env()` at the top level; nested lookups use `config('file.key')`.

### app.php

Core application identity and behaviour.

| Setting | Value | Notes |
|---------|-------|-------|
| Timezone | `Europe/London` | Read from `APP_TIMEZONE` env, defaults to `Europe/London` |
| Locale | `en` | Hardcoded, no env override |
| Fallback locale | `en` | Used when translation key missing |
| Faker locale | `en_US` | For database seeding with Faker |
| Cipher | `AES-256-CBC` | Encryption algorithm for all `encrypt()`/`decrypt()` calls |
| Debug | `false` default | Set `true` only in development; exposes stack traces |
| Maintenance driver | `file` | Uses `storage/framework/down` file |

Registered service providers (loaded in order):

1. `AppServiceProvider`
2. `AuthServiceProvider`
3. `EventServiceProvider`
4. `RouteServiceProvider`

`BroadcastServiceProvider` is commented out (broadcasting disabled).

### database.php

| Setting | Value |
|---------|-------|
| Default connection | `mysql` |
| MySQL charset | `utf8mb4` |
| MySQL collation | `utf8mb4_unicode_ci` |
| Strict mode | `true` |
| SSL | Optional via `MYSQL_ATTR_SSL_CA` |
| Redis default port | `6379`, database `0` |
| Redis cache database | `1` |

SQLite, PostgreSQL, and SQL Server connections are defined but only MySQL is active. The MySQL connection reads host, port, database name, username, and password from environment variables.

### auth.php

| Setting | Value |
|---------|-------|
| Default guard | `web` (session driver, Eloquent provider) |
| User model | `App\Models\User` |
| Password reset token expiry | 60 minutes |
| Password reset throttle | 60 seconds between requests |
| Password confirmation timeout | 10,800 seconds (3 hours) |

Progressive login lockout thresholds:

| Failed Attempts | Lockout Duration |
|----------------|-----------------|
| 3 | 1 minute |
| 5 | 5 minutes |
| 10 | 30 minutes |
| 15+ | 24 hours (1,440 minutes) |

IP-level blocking triggers after 50 failed attempts per hour.

Audit log retention:

| Category | Retention |
|----------|-----------|
| Standard logs | 90 days |
| GDPR compliance logs | 2,555 days (~7 years) |

### sanctum.php

| Setting | Value |
|---------|-------|
| Token expiry | 480 minutes (8 hours), configurable via `SANCTUM_TOKEN_EXPIRATION` |
| Guards | `web` |
| Stateful domains | `localhost`, `localhost:3000`, `localhost:5173`, `127.0.0.1`, `127.0.0.1:8000`, `127.0.0.1:5173`, `::1`, plus auto-detected from `APP_URL` |

Production overrides stateful domains via `SANCTUM_STATEFUL_DOMAINS` (e.g. `fynla.org,www.fynla.org`).

### cors.php

| Setting | Value |
|---------|-------|
| Paths | `api/*`, `sanctum/csrf-cookie` |
| Allowed methods | `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` |
| Allowed origins | Merged from `ALLOWED_ORIGINS`, `FRONTEND_URL`, `APP_URL` |
| Allowed headers | `Accept`, `Authorization`, `Content-Type`, `X-Requested-With`, `X-XSRF-TOKEN` |
| Exposed headers | `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` |
| Preflight cache | 3,600 seconds (1 hour) |
| Credentials | `true` |

Production requires explicit domain whitelisting.

### session.php

| Setting | Value |
|---------|-------|
| Driver | `file` (from `SESSION_DRIVER` env) |
| Lifetime | 120 minutes (from `SESSION_LIFETIME` env) |
| Expire on close | `false` |
| Encryption | `false` |
| Storage path | `storage/framework/sessions` |
| Cookie name | Auto-generated from app name (`{app_name}_session`) |
| HTTP-only | `true` |
| SameSite | `lax` |
| Secure cookie | From `SESSION_SECURE_COOKIE` env (`true` in production) |
| Sweep lottery | 2 in 100 requests |
| Partitioned | `false` |

### mail.php

| Setting | Value |
|---------|-------|
| Default mailer | `log` (development); `smtp` in production |
| SMTP host default | `smtp.mailgun.org` |
| SMTP port default | `587` with TLS |
| From address | From `MAIL_FROM_ADDRESS` env |

Production (fynla.org) uses `mail.fynla.org` on port 465 with SSL encryption.

Available mailers: `smtp`, `ses`, `postmark`, `mailgun`, `sendmail`, `log`, `array`, `failover` (smtp then log), `roundrobin` (ses then postmark).

### queue.php

| Setting | Value |
|---------|-------|
| Default connection | `sync` |

All jobs execute synchronously in the request cycle. The `database`, `beanstalkd`, `sqs`, and `redis` drivers are configured but unused. Failed jobs table: `failed_jobs` with UUID driver.

### cache.php

| Setting | Value |
|---------|-------|
| Default store | `file` |
| File path | `storage/framework/cache/data` |
| Key prefix | Auto-generated from app name |

Redis cache is configured (connection `cache`, database `1`) but the file driver is active.

### logging.php

| Setting | Value |
|---------|-------|
| Default channel | `stack` |
| Stack channels | `single` |
| Single file path | `storage/logs/laravel.log` |
| Default log level | `debug` (development), `error` (production) |
| Deprecations channel | `null` (suppressed) |

The `daily` channel is available but not active; it rotates logs every 14 days. Production uses the `single` channel directly to avoid log rotation complexity on shared hosting.

### filesystems.php

| Setting | Value |
|---------|-------|
| Default disk | `local` |
| Local root | `storage/app` |
| Public disk root | `storage/app/public` |
| Public URL | `{APP_URL}/storage` |
| Symlink | `public/storage` -> `storage/app/public` |

S3 is configured but unused.

### hashing.php

| Setting | Value |
|---------|-------|
| Driver | `bcrypt` |
| Rounds | 12 (production), 4 (testing via `phpunit.xml`) |

### services.php

Two third-party integrations:

| Service | Purpose | Env Variable |
|---------|---------|-------------|
| Anthropic | AI-powered document extraction | `ANTHROPIC_API_KEY` |
| GetAddress | UK postcode address lookups | `GETADDRESS_API_KEY` |

Standard Laravel services (Mailgun, Postmark, SES) are defined but only used if their respective mailers are active.

### broadcasting.php

| Setting | Value |
|---------|-------|
| Default driver | `null` (disabled) |

Broadcasting is not used. The `BroadcastServiceProvider` is commented out in `app.php`.

### vite.php

| Setting | Value |
|---------|-------|
| Build path | `build` (from `VITE_BUILD_PATH` env) |

Controls where Vite outputs compiled frontend assets within the `public/` directory.

### view.php

| Setting | Value |
|---------|-------|
| Template paths | `resources/views` |
| Compiled path | `storage/framework/views` |

Standard Laravel Blade configuration. The SPA uses Vue components; Blade serves only the root `app.blade.php` template.

---

## 10.2 Environment Variables

The `.env.example` file defines every environment variable the application recognises. Copy it to `.env` and fill in values for each environment.

### Application

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_NAME` | `Laravel` | Display name; used in emails and session cookie naming |
| `APP_ENV` | `local` | Environment identifier: `local`, `staging`, `production` |
| `APP_KEY` | (empty) | AES-256-CBC encryption key; generate with `php artisan key:generate` |
| `APP_DEBUG` | `true` | Show detailed errors; must be `false` in production |
| `APP_URL` | `http://localhost` | Base URL for link generation |
| `APP_TIMEZONE` | `Europe/London` | PHP timezone |
| `ASSET_URL` | (empty) | Override asset URL for subdirectory deployments |

### Database

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_CONNECTION` | `mysql` | Database driver |
| `DB_HOST` | `127.0.0.1` | Database server address |
| `DB_PORT` | `3306` | Database server port |
| `DB_DATABASE` | `laravel` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | (empty) | Database password |

### Cache, Session & Queue

| Variable | Default | Purpose |
|----------|---------|---------|
| `CACHE_DRIVER` | `file` | Cache backend (`file`, `redis`, `array`) |
| `SESSION_DRIVER` | `file` | Session storage (`file`, `database`, `redis`) |
| `SESSION_LIFETIME` | `120` | Session timeout in minutes |
| `SESSION_SECURE_COOKIE` | (empty) | Set `true` in production for HTTPS-only cookies |
| `QUEUE_CONNECTION` | `sync` | Queue backend; `sync` runs jobs immediately |

### Mail

| Variable | Default | Purpose |
|----------|---------|---------|
| `MAIL_MAILER` | `smtp` | Mail transport driver |
| `MAIL_HOST` | `mailpit` | SMTP server host |
| `MAIL_PORT` | `1025` | SMTP server port |
| `MAIL_USERNAME` | `null` | SMTP authentication username |
| `MAIL_PASSWORD` | `null` | SMTP authentication password |
| `MAIL_ENCRYPTION` | `null` | `tls` or `ssl` |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Sender address |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Sender display name |

### Security

| Variable | Default | Purpose |
|----------|---------|---------|
| `SANCTUM_TOKEN_EXPIRATION` | `480` | API token lifetime in minutes |
| `SANCTUM_STATEFUL_DOMAINS` | (auto-detected) | Comma-separated domains for cookie auth |
| `ALLOWED_ORIGINS` | (empty) | CORS whitelist, comma-separated |
| `FRONTEND_URL` | (empty) | SPA URL for CORS |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost factor |

### Frontend (Vite)

| Variable | Default | Purpose |
|----------|---------|---------|
| `VITE_APP_NAME` | `${APP_NAME}` | App name exposed to frontend |
| `VITE_BASE_PATH` | `/` | Base path for built assets |
| `VITE_ROUTER_BASE` | `/` | Vue Router base path |
| `VITE_API_BASE_URL` | (empty) | API URL; empty means same-origin in development |

### Third-Party APIs

| Variable | Purpose |
|----------|---------|
| `ANTHROPIC_API_KEY` | Anthropic Claude API for document extraction |
| `GETADDRESS_API_KEY` | GetAddress.io for UK postcode lookups |

### Logging

| Variable | Default | Purpose |
|----------|---------|---------|
| `LOG_CHANNEL` | `stack` | Active log channel |
| `LOG_LEVEL` | `debug` | Minimum log level (`debug`, `info`, `error`) |

---

## 10.3 Service Providers

Five provider files exist in `app/Providers/`. Four are registered; one is disabled.

### AppServiceProvider

File: `app/Providers/AppServiceProvider.php`

Single responsibility: enables `Model::preventLazyLoading()` in non-production environments. This throws an exception whenever code triggers a lazy-loaded relationship, catching N+1 query problems during development.

```php
Model::preventLazyLoading(! app()->isProduction());
```

No service container bindings or singletons are registered.

### RouteServiceProvider

File: `app/Providers/RouteServiceProvider.php`

Defines five named rate limiters:

| Limiter | Limit | Scope | Purpose |
|---------|-------|-------|---------|
| `api` | 1,000/min (local), 300/min (production) | Per user ID or IP | General API access |
| `auth` | 5/min | Per IP | Login, registration, password reset |
| `export` | 3/hour | Per user ID or IP | GDPR data export, PDF generation |
| `sensitive` | 3/min | Per user ID or IP | Data erasure, password change |
| `bug-reports` | 5/hour | Per user ID or IP | Bug report submissions |

Registers two route groups:

- `routes/api.php` with `api` middleware and `/api` prefix
- `routes/web.php` with `web` middleware

### EventServiceProvider

File: `app/Providers/EventServiceProvider.php`

One event listener mapping:

| Event | Listener |
|-------|----------|
| `Registered` | `SendEmailVerificationNotification` |

Six model observers for automatic risk recalculation:

| Model | Observer | Trigger |
|-------|----------|---------|
| `User` | `UserRiskObserver` | Profile changes (DOB, income, employment) |
| `FamilyMember` | `FamilyMemberRiskObserver` | Dependant additions or removals |
| `SavingsAccount` | `SavingsAccountRiskObserver` | Savings balance or type changes |
| `InvestmentAccount` | `InvestmentAccountRiskObserver` | Investment value or allocation changes |
| `DCPension` | `DCPensionRiskObserver` | Pension contribution or fund changes |
| `Property` | `PropertyRiskObserver` | Property value or mortgage changes |

When any observed model is created, updated, or deleted, its observer queues a risk profile recalculation for the owning user. This keeps risk scores current without manual intervention.

Event auto-discovery is disabled (`shouldDiscoverEvents()` returns `false`).

### AuthServiceProvider

File: `app/Providers/AuthServiceProvider.php`

Empty. No policies or gates defined. Authorisation is handled through middleware and controller-level checks rather than Laravel's policy system.

### BroadcastServiceProvider (disabled)

File: `app/Providers/BroadcastServiceProvider.php`

Commented out in `config/app.php`. Exists as a scaffold for future WebSocket support. If enabled, it would register broadcast routes and load `routes/channels.php`.

---

## 10.4 Database Seeders

Twelve seeder files in `database/seeders/`. The `DatabaseSeeder` orchestrates them in a specific order with environment-aware phases.

### DatabaseSeeder (orchestrator)

File: `database/seeders/DatabaseSeeder.php`

Runs in two phases:

**Phase 1 -- Required Data (all environments):**

| Order | Seeder | Purpose |
|-------|--------|---------|
| 1 | `TaxConfigurationSeeder` | UK tax rates, allowances, thresholds |
| 2 | `TaxProductReferenceSeeder` | Tax treatment rules for financial products |
| 3 | `ActuarialLifeTablesSeeder` | UK mortality tables for life expectancy |
| 4 | `AdminUserSeeder` | Demo admin accounts |
| 5 | `PreviewUserSeeder` | Six test personas with full financial data |

**Phase 2 -- Development Data (local/development/staging only):**

| Order | Seeder | Purpose |
|-------|--------|---------|
| 6 | `HouseholdSeeder` | Multi-user household structures |
| 7 | `TestUsersSeeder` | Additional test accounts |

Phase 2 seeders are skipped in production. The environment check uses `app()->environment(['local', 'development', 'staging'])`.

### TaxConfigurationSeeder

Seeds five complete UK tax years (2021/22 through 2025/26). Each year includes:

- Income tax: personal allowance, basic/higher/additional rate bands and rates, personal allowance taper
- National Insurance: primary thresholds, employee/employer rates, Class 2/4 thresholds
- Capital gains tax: annual exempt amount, basic/higher rates, residential property rates
- Dividend tax: allowance and three-tier rates
- ISA allowances: GBP 20,000 annual, GBP 4,000 Lifetime ISA, GBP 9,000 Junior ISA
- Pension annual allowance: GBP 60,000 with tapered and money purchase limits
- Inheritance tax: NRB (GBP 325,000), RNRB (GBP 175,000), standard rate 40%, reduced rate 36% for charitable estates, PET taper schedule, CLT rules, trust periodic and exit charges
- Stamp duty land tax: standard bands, additional property surcharge (+5%), first-time buyer relief thresholds
- Investment growth assumptions: cash 1%, equities 5%, property 3%, inflation 2.5%
- Child benefit: weekly rates and high income charge threshold
- Trust taxation: entry charge 20%, periodic charge 6%, exit charge 6%, discretionary income rate 45%

### TaxProductReferenceSeeder

Seeds tax treatment metadata for all supported financial product types:

**Investment products:** ISA (tax-exempt), GIA (fully taxable), Onshore Bond (tax-deferred with 5% annual withdrawal), Offshore Bond (tax-deferred), VCT (30% income tax relief), EIS (30% relief plus business property relief), NS&I (tax-free prizes for Premium Bonds).

**Savings products:** Cash ISA (tax-free), Junior ISA (tax-free), Easy Access (taxable), Notice Account (taxable), Fixed Rate (taxable), Premium Bonds (tax-free prizes), NS&I (various), Lifetime ISA (25% government bonus, tax-free growth).

### ActuarialLifeTablesSeeder

Seeds UK Office for National Statistics mortality data. Used by the retirement module for income projection durations and by the estate module for IHT planning horizons. Tables provide age-specific mortality rates for males and females.

### PreviewUserSeeder

1,597 lines. Creates six fully populated financial personas:

| Persona Key | Names | Focus Areas |
|-------------|-------|-------------|
| `young_family` | James & Emily Carter | Mortgage, workplace pensions, young children |
| `peak_earners` | David & Sarah Mitchell | Multiple properties, SIPP + NHS pension, higher-rate tax |
| `widow` | Margaret Thompson | Transferred NRB, estate planning, single income |
| `entrepreneur` | Alex Chen | SIPP, business interests, variable income |
| `young_saver` | John Morgan | Emergency fund, first-time savings, no dependants |
| `retired_couple` | Robert & Patricia Williams | Decumulation, annuities, estate planning |

Each persona creates a complete data set: user account with spouse (where applicable), family members, properties with mortgages, savings accounts, investment accounts with holdings, DC pensions, DB pensions, state pensions, insurance policies, liabilities, risk profiles, retirement profiles, wills, bequests, trusts, gifts, IHT profiles, business interests, chattels, goals, life events, and letters to spouse.

All preview users have `is_preview_user = true`. The seeder deletes all existing preview data before recreating it, so re-running is safe and idempotent.

### AdminUserSeeder

Creates two demo admin accounts: `demo@fps.com` and `admin@fps.com`.

### Other Seeders

| Seeder | Purpose |
|--------|---------|
| `HouseholdSeeder` | Creates multi-user household structures for testing shared financial views |
| `TestUsersSeeder` | Creates additional test accounts beyond the admin and preview users |
| `RolesPermissionsSeeder` | Seeds role and permission records for RBAC |
| `OccupationCodeSeeder` | Seeds UK Standard Occupational Classification codes |
| `DemoUserSeeder` | Creates demo user data |
| `ComprehensiveDemoDataSeeder` | Creates a detailed demo scenario with full financial data |

### Reseed Commands

| Scenario | Command |
|----------|---------|
| Full reseed (preserves existing user data) | `php artisan db:seed` |
| Tax calculations failing | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Tax Status tab empty | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| Preview personas broken | `php artisan db:seed --class=PreviewUserSeeder --force` |
| Life expectancy errors | `php artisan db:seed --class=ActuarialLifeTablesSeeder --force` |

**Never use `migrate:fresh` or `migrate:refresh`.** These commands drop all tables and destroy user data. Use `php artisan db:seed` to repopulate reference data without affecting existing records.

---

## 10.5 Dependencies

### PHP Dependencies (composer.json)

**Runtime:**

| Package | Version | Purpose |
|---------|---------|---------|
| `php` | ^8.1 | Minimum PHP version |
| `laravel/framework` | ^10.10 | Core framework |
| `laravel/sanctum` | ^3.3 | SPA authentication and API tokens |
| `laravel/tinker` | ^2.8 | REPL for debugging |
| `guzzlehttp/guzzle` | ^7.2 | HTTP client for API integrations |
| `phpoffice/phpspreadsheet` | ^5.3 | Excel/spreadsheet generation for data exports |
| `smalot/pdfparser` | ^2.12 | PDF text extraction for document uploads |
| `pragmarx/google2fa-laravel` | ^2.3 | TOTP-based two-factor authentication |
| `bacon/bacon-qr-code` | ^3.0 | QR code generation for MFA setup |

**Development:**

| Package | Version | Purpose |
|---------|---------|---------|
| `pestphp/pest` | ^2.36 | Test framework (wraps PHPUnit) |
| `pestphp/pest-plugin-laravel` | ^2.4 | Laravel-specific Pest helpers |
| `phpunit/phpunit` | ^10.1 | Underlying test runner |
| `laravel/pint` | ^1.0 | PSR-12 code formatting |
| `mockery/mockery` | ^1.4.4 | Test doubles and mocking |
| `fakerphp/faker` | ^1.9.1 | Fake data generation for tests and seeds |
| `nunomaduro/collision` | ^7.0 | Pretty error reporting in CLI |
| `spatie/laravel-ignition` | ^2.0 | Error page in development |

### JavaScript Dependencies (package.json)

**Runtime:**

| Package | Version | Purpose |
|---------|---------|---------|
| `vue` | ^3.5.22 | Frontend framework |
| `vue-router` | ^4.5.1 | Client-side routing |
| `vuex` | ^4.1.0 | Centralised state management |
| `apexcharts` | ^5.3.5 | Chart rendering engine |
| `vue3-apexcharts` | ^1.9.0 | Vue 3 wrapper for ApexCharts |
| `vuedraggable` | ^4.1.0 | Drag-and-drop list reordering |
| `html2pdf.js` | ^0.12.1 | Client-side PDF generation |

**Development:**

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^5.0.0 | Build tool and dev server |
| `laravel-vite-plugin` | ^1.0.0 | Laravel integration for Vite |
| `@vitejs/plugin-vue` | ^6.0.1 | Vue SFC compilation |
| `tailwindcss` | ^3.4.18 | Utility-first CSS framework |
| `postcss` | ^8.5.6 | CSS processing pipeline |
| `autoprefixer` | ^10.4.21 | Vendor prefix injection |
| `axios` | ^1.6.4 | HTTP client for API calls |
| `@vue/test-utils` | ^2.4.6 | Vue component testing utilities |
| `vitest` | ^3.2.4 | Frontend test runner |
| `@playwright/test` | ^1.56.0 | End-to-end browser testing |
| `jsdom` | ^27.0.1 | DOM simulation for unit tests |

---

## 10.6 Build Process

The production server (SiteGround shared hosting) lacks sufficient memory to run `npm install` or `npm run build`. All frontend builds happen locally.

### Build Scripts

Two build scripts exist, one per deployment target:

| Script | Target | Command |
|--------|--------|---------|
| `deploy/fynla-org/build.sh` | `https://fynla.org` (root) | `./deploy/fynla-org/build.sh` |
| `deploy/csjones-fynla/build.sh` | `https://csjones.co/fynla` (subdirectory) | `./deploy/csjones-fynla/build.sh` |

### Build Environment Variables

| Variable | fynla.org | csjones.co/fynla |
|----------|-----------|-------------------|
| `NODE_ENV` | `production` | `production` |
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `VITE_ROUTER_BASE` | `/` | `/fynla/` |
| `VITE_APP_NAME` | `Fynla` | `Fynla` |
| `VITE_API_BASE_URL` | `https://fynla.org` | `https://csjones.co/fynla` |

### Build Output

Both scripts produce the same structure in `public/build/`:

- `manifest.json` -- Asset manifest mapping source files to hashed output filenames
- Hashed JavaScript bundles (`.js`)
- Hashed CSS files (`.css`)
- Copied static assets (fonts, images)

The build script verifies `manifest.json` exists after running `npm run build`. If the file is missing, the script exits with an error. It also reports the total size of the `public/build/` directory.

### Development Server

For local development, run `./dev.sh` or `npm run dev` to start the Vite dev server on port 5173 with hot module replacement. Laravel serves the backend on port 8000. Vite proxies API requests to Laravel.

---

## 10.7 Deployment Process

Fynla uses manual file upload to SiteGround. There is no CI/CD pipeline, no automated deployment, and no ZIP packaging.

### Step 1: Build Locally

```bash
./deploy/fynla-org/build.sh
```

This compiles all Vue components, Tailwind CSS, and JavaScript into hashed production bundles in `public/build/`.

### Step 2: Upload Frontend Assets

Open SiteGround File Manager. Upload the entire `public/build/` directory to:

```
~/www/fynla.org/public_html/public/build/
```

Replace all existing files in the `build/` directory.

### Step 3: Upload Changed PHP Files

Check the deployment notes (tracked in files like `Feb5Updates/deploy5.md` and `Feb6Updates/deploy6.md`) for the list of changed PHP files. Upload each file to its corresponding path on the server. Common targets:

- `app/Http/Controllers/Api/` -- Controller changes
- `app/Services/` -- Service logic changes
- `app/Agents/` -- Agent orchestrator changes
- `app/Models/` -- Model changes
- `app/Http/Resources/` -- API resource changes
- `database/seeders/` -- Seeder updates
- `routes/api.php` -- New or modified routes
- `config/` -- Configuration changes

### Step 4: Clear Server Caches

SSH into the production server and clear all caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

Three caches must be cleared:

| Cache | Command | What It Clears |
|-------|---------|----------------|
| Application cache | `cache:clear` | Cached tax configs, computed values, rate limit counters |
| Route cache | `route:clear` | Compiled route definitions |
| Config cache | `config:clear` | Compiled configuration files |

### Step 5: Verify

Load the application in a browser. Check the version number on the landing page. Test the specific features that were changed.

### Subdirectory Deployment (csjones.co/fynla)

The process is identical except:

- Use `./deploy/csjones-fynla/build.sh` for the build
- Upload to `~/www/csjones.co/public_html/fynla/public/build/`
- SSH cache clear runs from `~/www/csjones.co/public_html/fynla`

---

## 10.8 .htaccess Configuration

Two .htaccess files handle web server configuration. A third root-level .htaccess redirects requests from `public_html/` into the `public/` subdirectory.

### Root Redirect (.htaccess.root)

File: `deploy/fynla-org/.htaccess.root`

Placed at `public_html/.htaccess`. Contains a single rewrite rule that forwards all requests to the `public/` subdirectory:

```
RewriteRule ^(.*)$ public/$1 [L]
```

### Application .htaccess

Both deployment targets share the same feature set with one difference: the `RewriteBase` directive.

| Feature | fynla.org | csjones.co/fynla |
|---------|-----------|-------------------|
| RewriteBase | `/` | `/fynla/` |

**Rewrite rules:**

1. **HTTPS enforcement** -- 301 redirect from HTTP to HTTPS
2. **Authorization header passthrough** -- Sets `HTTP_AUTHORIZATION` environment variable for Sanctum
3. **Trailing slash removal** -- 301 redirect to remove trailing slashes (unless the path is a directory)
4. **Front controller** -- All requests that do not match a file or directory route to `index.php`

**Security headers:**

| Header | Value |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-Powered-By` | Removed |

**File protection:**

- `.env` -- Blocked with `Deny from all`
- `.git` directories -- Blocked (RewriteRule on fynla.org, DirectoryMatch on csjones)
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json` -- Blocked
- `/storage/` directory -- Returns 403

**Compression (mod_deflate):**

Gzip compression for: JavaScript, JSON, XML, CSS, HTML, plain text, TTF/WOFF/WOFF2 fonts, SVG.

**Browser caching (mod_expires):**

| Content Type | Cache Duration |
|-------------|----------------|
| JPEG, PNG, GIF, SVG, WebP images | 1 year |
| CSS, JavaScript | 1 year |
| WOFF, WOFF2 fonts | 1 year |
| JSON, HTML | No cache (0 seconds) |

Hashed filenames in the Vite build output enable aggressive caching for static assets. JSON and HTML receive no cache headers because API responses and the SPA shell must always be fresh.

**MIME type declarations:** CSS, JavaScript (js, mjs), JSON, fonts (TTF, OTF, WOFF, WOFF2), SVG, WebP.

**Character encoding:** UTF-8 default for HTML, CSS, JS, JSON, and XML.

---

## 10.9 Testing

### Test Structure

```
tests/
  Architecture/           # Structural rules (4 files)
  E2E/                    # End-to-end browser tests (Playwright)
    helpers/
  Feature/                # HTTP integration tests
    Api/                  # Controller endpoint tests
    Auth/                 # Login, registration, MFA, session, logout
    Dashboard/            # Dashboard API tests
    Estate/               # Estate module integration
    Protection/           # Protection module integration
    Risk/                 # Risk API tests
    Savings/              # Savings module integration
    Security/             # Mass assignment, RBAC
  Integration/            # Cross-module workflow tests
  Unit/                   # Isolated unit tests
    Agents/               # Agent orchestrator tests
    Middleware/            # Middleware tests
    Models/               # Model logic tests
    Services/
      Audit/              # Audit logging
      Auth/               # Lockout, MFA, password, permission, session
      Coordination/       # Cross-module services
      Estate/             # IHT, intestacy, gifting, liquidity, net worth, trusts
      GDPR/               # Export, erasure, consent
      Investment/          # Fees, portfolio, allocation, diversification, tax efficiency
      Protection/         # Coverage gaps, recommendations, adequacy, scenarios
      Retirement/         # Projections, decumulation, annual allowance
      Risk/               # Auto risk calculation
      Savings/            # Liquidity, emergency fund, ISA tracker, goal progress
      Trust/              # Trust asset aggregation, periodic charges
      UserProfile/        # Financial commitments
  frontend/               # Vue component and API tests (Vitest)
    api/
    components/
      Dashboard/
      Estate/
      Investment/
      Protection/
      Retirement/
      Savings/
      Shared/
    views/
```

Total: 101 PHP test files across Unit, Feature, Integration, and Architecture suites.

### Test Configuration (phpunit.xml)

Three test suites:

| Suite | Directory | Purpose |
|-------|-----------|---------|
| Unit | `tests/Unit` | Isolated service and model tests |
| Feature | `tests/Feature` | HTTP request tests with database |
| Architecture | `tests/Architecture` | Structural enforcement (class dependencies, naming) |

Test environment overrides:

| Variable | Test Value | Production Value |
|----------|-----------|-----------------|
| `APP_ENV` | `testing` | `production` |
| `BCRYPT_ROUNDS` | `4` | `12` |
| `CACHE_DRIVER` | `array` | `file` |
| `MAIL_MAILER` | `array` | `smtp` |
| `QUEUE_CONNECTION` | `sync` | `sync` |
| `SESSION_DRIVER` | `array` | `file` |
| `PULSE_ENABLED` | `false` | -- |
| `TELESCOPE_ENABLED` | `false` | -- |

Bcrypt rounds are reduced from 12 to 4 for faster test execution. Cache, mail, and session use in-memory `array` drivers to avoid filesystem side effects.

### Running Tests

**PHP tests (Pest):**

```bash
./vendor/bin/pest                              # All suites
./vendor/bin/pest tests/Unit/                  # Unit tests only
./vendor/bin/pest tests/Feature/               # Feature tests only
./vendor/bin/pest tests/Unit/Services/Estate/  # Specific directory
./vendor/bin/pest --filter=IHTCalculator       # Filter by name
```

**Frontend tests (Vitest):**

```bash
npm run test        # Watch mode
npm run test:run    # Single run
```

**End-to-end tests (Playwright):**

```bash
npx playwright test
```

### Code Formatting

```bash
./vendor/bin/pint    # PSR-12 auto-format all PHP files
```

Pint is the sole PHP formatting tool. It enforces PSR-12 standards across the codebase.

---

## 10.10 Production Environment Differences

The production `.env` files in `deploy/fynla-org/.env.production` and `deploy/csjones-fynla/.env.production` differ from development in these areas:

| Setting | Development | Production |
|---------|------------|------------|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://localhost` | `https://fynla.org` |
| `LOG_CHANNEL` | `stack` | `single` |
| `LOG_LEVEL` | `debug` | `error` |
| `MAIL_MAILER` | `log` | `smtp` |
| `MAIL_ENCRYPTION` | `null` | `ssl` |
| `MAIL_PORT` | `1025` | `465` |
| `SESSION_SECURE_COOKIE` | (unset) | `true` |
| `BCRYPT_ROUNDS` | `12` | `12` |
| API rate limit | 1,000/min | 300/min |
| Lazy loading prevention | Enabled (throws exceptions) | Disabled (silent) |

Production logging records only errors to a single file (`storage/logs/laravel.log`). Development logs everything at debug level through the stack channel.

---

## 10.11 Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Blank page with `127.0.0.1:5173` in source | Vite dev server hot file left on server | Delete `public/hot` on the server |
| MIME type errors for JS/CSS | Build was run with wrong environment | Rebuild with `./deploy/fynla-org/build.sh` |
| 500 Internal Server Error on fresh deploy | Missing or incorrect `.htaccess` | Upload the correct `.htaccess` from `deploy/fynla-org/` |
| 429 Too Many Requests | Rate limiter or cache issue | Run `php artisan cache:clear` on server |
| Tax calculations return wrong values | Stale tax configuration data | Run `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Preview users show no data | Preview seeder not run after schema change | Run `php artisan db:seed --class=PreviewUserSeeder --force` |
| Route not found (404 on API) | Route cache stale | Run `php artisan route:clear` on server |
| Config values not updating | Config cache stale | Run `php artisan config:clear` on server |

Do not suggest browser cache clearing. Testing is done in incognito mode.

To inspect registered routes:

```bash
php artisan route:list --path=api/savings    # Filter by path
php artisan route:list --name=api.estate     # Filter by name
```
