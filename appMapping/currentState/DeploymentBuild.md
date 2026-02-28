# Deployment & Build Configuration - Complete System Map

**Application:** Fynla v0.7.0
**Last Updated:** 19 February 2026

---

## 1. System Overview

Fynla supports two production deployment targets, both hosted on SiteGround shared hosting:

| Target | URL | Type | Server Path |
|--------|-----|------|-------------|
| **Primary** | `https://fynla.org` | Root deployment | `~/www/fynla.org/public_html/` |
| **Secondary** | `https://csjones.co/fynla` | Subdirectory deployment | `~/www/csjones.co/public_html/fynla/` |

**Key constraints:**
- The SiteGround server does not have enough memory to run `npm install` or `npm run build` (these require 1-2GB RAM).
- All frontend assets must be built locally on the developer's machine.
- File upload to the server is done manually via SiteGround File Manager (no CI/CD pipeline).
- PHP files are uploaded individually; there is no automated deployment package system.

**Stack:** Laravel 10 + Vue.js 3 + MySQL 8 + Apache (mod_rewrite) on SiteGround shared hosting.

---

## 2. Build Scripts

Both build scripts live in the `deploy/` directory and share the same structure. They differ only in environment variable values.

### 2.1 fynla.org Build Script

**File:** `/deploy/fynla-org/build.sh`
**Usage:** `./deploy/fynla-org/build.sh`

**Step-by-step execution:**

1. `set -e` -- exits immediately on any command failure.
2. Resolves `$PROJECT_ROOT` to the repository root (two directories above the script).
3. `cd "$PROJECT_ROOT"` -- changes to the project root.
4. Sets environment variables:
   ```
   NODE_ENV=production
   VITE_BASE_PATH=/build/
   VITE_ROUTER_BASE=/
   VITE_APP_NAME=Fynla
   VITE_API_BASE_URL=https://fynla.org
   ```
5. Runs `npm run build` (which invokes `vite build`).
6. Validates the build by checking for `public/build/manifest.json`. Exits with error if not found.
7. Reports the total size of `public/build/` directory.
8. Prints post-build instructions:
   - Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`
   - Upload any changed PHP files
   - SSH to server and clear caches

**Output directory:** `public/build/`

### 2.2 csjones.co/fynla Build Script

**File:** `/deploy/csjones-fynla/build.sh`
**Usage:** `./deploy/csjones-fynla/build.sh`

**Step-by-step execution:**

Identical structure to the fynla.org script, with different environment variables:

| Variable | fynla.org | csjones.co/fynla |
|----------|-----------|-------------------|
| `NODE_ENV` | `production` | `production` |
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `VITE_ROUTER_BASE` | `/` | `/fynla/` |
| `VITE_APP_NAME` | `Fynla` | `Fynla` |
| `VITE_API_BASE_URL` | `https://fynla.org` | `https://csjones.co/fynla` |

**Post-build upload target:** `~/www/csjones.co/public_html/fynla/public/build/`

**Note:** The csjones.co build script does not reference SSH credentials for cache clearing (the fynla.org script includes the full SSH command). For csjones.co, the cache clear instruction is `cd ~/www/csjones.co/public_html/fynla` followed by the artisan commands.

---

## 3. Vite Configuration

**File:** `/vite.config.js`

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
```

### Plugins

| Plugin | Configuration |
|--------|---------------|
| `laravel-vite-plugin` | Input: `resources/css/app.css`, `resources/js/app.js`. `refresh: true`. `buildDirectory: 'build'`. |
| `@vitejs/plugin-vue` | Default configuration (no options). |

### Base Path

The `base` property is dynamically set from the `VITE_BASE_PATH` environment variable:

```js
base: process.env.VITE_BASE_PATH || '/'
```

| Context | VITE_BASE_PATH | Resulting base |
|---------|----------------|----------------|
| Local development | Not set | `/` (default) |
| fynla.org production | `/build/` | `/build/` |
| csjones.co/fynla production | `/fynla/build/` | `/fynla/build/` |

### Dev Server

```js
server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
}
```

The dev server binds to `127.0.0.1:5173` with `strictPort: true` (fails if port is already in use rather than incrementing).

### Path Aliases

```js
resolve: {
    alias: {
        '@': path.resolve(__dirname, 'resources/js'),
    },
},
```

The `@` alias maps to `resources/js/`, allowing imports like `import Foo from '@/components/Foo.vue'`.

### Build Options

```js
build: {
    manifest: 'manifest.json',
    outDir: 'public/build',
    rollupOptions: {
        input: {
            app: 'resources/js/app.js',
            css: 'resources/css/app.css',
        },
    },
},
```

| Setting | Value | Notes |
|---------|-------|-------|
| `manifest` | `manifest.json` | Placed at build root, not in `.vite` subdirectory. Laravel uses this to resolve asset URLs. |
| `outDir` | `public/build` | All built assets go here. |
| Rollup inputs | `app.js` + `app.css` | Two entry points compiled separately. |

### Entry Points

| Entry | Source File | Description |
|-------|------------|-------------|
| `app` | `resources/js/app.js` | Main JavaScript entry. Imports bootstrap, Vue, Vue Router, Vuex, VueApexCharts, custom directives, and session lifecycle service. |
| `css` | `resources/css/app.css` | Main CSS entry. Imports Google Fonts (Inter, Plus Jakarta Sans, JetBrains Mono), Tailwind directives, and base layer styles. |

---

## 4. Tailwind CSS Configuration

**File:** `/tailwind.config.js`

### Content Paths

Tailwind scans these paths for class usage (tree-shaking):

```js
content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
],
```

### Safelist

Classes that are always included regardless of whether they appear in scanned files (used for dynamically-generated risk level colours):

```
bg-green-50, bg-green-100, bg-green-600, text-green-700, text-green-800, border-green-200, ring-green-400
bg-teal-50, bg-teal-100, bg-teal-600, text-teal-700, text-teal-800, border-teal-200, ring-teal-400
bg-blue-50, bg-blue-100, bg-blue-600, text-blue-700, text-blue-800, border-blue-200, ring-blue-400
bg-red-50, bg-red-100, bg-red-600, text-red-700, text-red-800, border-red-200, ring-red-400
```

**Note:** Orange/amber classes are explicitly excluded per the design system rules.

### Custom Colour Palette

**Primary (Deep Navy & Slate):**

| Token | Hex | Usage |
|-------|-----|-------|
| `primary-50` | `#FFFFFF` | Lightest background |
| `primary-100` | `#F1F5F9` | Slate 100 |
| `primary-200` | `#E2E8F0` | Slate 200 |
| `primary-300` | `#CBD5E1` | Slate 300 |
| `primary-400` | `#94A3B8` | Slate 400 |
| `primary-500` | `#3B82F6` | Default Blue (bright accent) |
| `primary-600` | `#1257A0` | Trust Blue (Main Brand Colour) |
| `primary-700` | `#0E3A66` | Deep Navy |
| `primary-800` | `#0B2C4F` | Darker Navy |
| `primary-900` | `#051B33` | Darkest Navy |
| `primary-950` | `#020617` | Near black |

**Secondary (Neutrals/Slate):**

| Token | Hex |
|-------|-----|
| `secondary-500` | `#64748B` |
| `secondary-600` | `#475569` |
| `secondary-700` | `#334155` |
| `secondary-800` | `#1E293B` |
| `secondary-900` | `#0F172A` |

**Semantic Colours:**

| Group | Key Shade | Hex |
|-------|-----------|-----|
| `success` | 500 | `#15803D` (solid green) |
| `error` | 500 | `#EF4444` (solid red) |
| `warning` | 500 | `#3B82F6` (blue -- not amber/orange per design system) |
| `info` | 500 | `#0EA5E9` (sky blue) |

**Chart Colours:**

| Token | Hex | Label |
|-------|-----|-------|
| `chart-1` | `#1257A0` | Trust Blue |
| `chart-2` | `#475569` | Slate |
| `chart-3` | `#15803D` | Green |
| `chart-4` | `#60A5FA` | Blue |
| `chart-5` | `#B91C1C` | Red |
| `chart-6` | `#7C3AED` | Purple (charts only) |
| `chart-7` | `#3B82F6` | Blue tertiary |
| `chart-8` | `#0F172A` | Navy |

### Typography

**Font families:**

| Token | Fonts |
|-------|-------|
| `font-sans` | Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif |
| `font-display` | Plus Jakarta Sans, Inter, sans-serif |
| `font-mono` | JetBrains Mono, Courier New, monospace |

**Font sizes (custom scale):**

| Token | Size | Line Height | Weight |
|-------|------|-------------|--------|
| `display` | 3.75rem | 1.1 | 700 |
| `h1` | 2.25rem | 1.2 | 700 |
| `h2` | 1.875rem | 1.3 | 600 |
| `h3` | 1.5rem | 1.4 | 600 |
| `h4` | 1.25rem | 1.5 | 600 |
| `h5` | 1rem | 1.5 | 600 |
| `body-lg` | 1.125rem | 1.7 | 400 |
| `body` | 1rem | 1.6 | 400 |
| `body-sm` | 0.875rem | 1.5 | 400 |
| `caption` | 0.75rem | 1.4 | 400 |

### Extended Design Tokens

| Category | Token | Value |
|----------|-------|-------|
| Spacing | `128` | 32rem |
| Spacing | `144` | 36rem |
| Border Radius | `card` | 0.75rem |
| Border Radius | `button` | 0.5rem |
| Box Shadow | `card` | `0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06)` |
| Box Shadow | `card-hover` | `0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)` |
| Box Shadow | `modal` | `0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)` |

### Plugins

No Tailwind plugins are registered (`plugins: []`).

---

## 5. PostCSS Configuration

**File:** `/postcss.config.js`

```js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

Two plugins:
1. **tailwindcss** -- processes Tailwind directives and generates utility classes.
2. **autoprefixer** -- adds vendor prefixes for cross-browser compatibility.

---

## 6. Package Dependencies

### 6.1 Frontend (package.json)

**File:** `/package.json`
**Module type:** ES modules (`"type": "module"`)

#### Production Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `vue` | ^3.5.22 | Core UI framework |
| `vuex` | ^4.1.0 | State management (21 store modules) |
| `vue-router` | ^4.5.1 | Client-side routing |
| `@vitejs/plugin-vue` | ^5.2.4 | Vite plugin for Vue SFC compilation |
| `apexcharts` | ^5.3.5 | Charting library |
| `vue3-apexcharts` | ^1.9.0 | Vue 3 wrapper for ApexCharts |
| `html2pdf.js` | ^0.12.1 | Client-side PDF generation |
| `vuedraggable` | ^4.1.0 | Drag-and-drop list components |

#### Dev Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^5.0.0 | Build tool and dev server |
| `laravel-vite-plugin` | ^1.0.0 | Laravel integration for Vite |
| `tailwindcss` | ^3.4.18 | Utility-first CSS framework |
| `postcss` | ^8.5.6 | CSS transformation pipeline |
| `autoprefixer` | ^10.4.21 | CSS vendor prefixing |
| `axios` | ^1.6.4 | HTTP client for API calls |
| `vitest` | ^3.2.4 | Unit testing framework |
| `@vue/test-utils` | ^2.4.6 | Vue component testing utilities |
| `@playwright/test` | ^1.56.0 | End-to-end browser testing |
| `jsdom` | ^27.0.1 | DOM implementation for testing |

#### npm Scripts

| Script | Command | Description |
|--------|---------|-------------|
| `dev` | `vite` | Start Vite dev server with HMR |
| `build` | `vite build` | Production build to `public/build/` |
| `test` | `vitest` | Run tests in watch mode |
| `test:run` | `vitest run` | Run tests once (CI mode) |

### 6.2 Backend (composer.json)

**File:** `/composer.json`
**PHP requirement:** ^8.1

#### Production Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^10.10 | Core PHP framework |
| `laravel/sanctum` | ^3.3 | API authentication (SPA cookies + tokens) |
| `laravel/tinker` | ^2.8 | REPL for debugging |
| `guzzlehttp/guzzle` | ^7.2 | HTTP client |
| `pragmarx/google2fa-laravel` | ^2.3 | TOTP multi-factor authentication |
| `bacon/bacon-qr-code` | ^3.0 | QR code generation for MFA setup |
| `phpoffice/phpspreadsheet` | ^5.3 | Excel/spreadsheet generation |
| `smalot/pdfparser` | ^2.12 | PDF document parsing |

#### Dev Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `pestphp/pest` | ^2.36 | Testing framework (PHPUnit wrapper) |
| `pestphp/pest-plugin-laravel` | ^2.4 | Laravel-specific Pest helpers |
| `phpunit/phpunit` | ^10.1 | Underlying test runner |
| `laravel/pint` | ^1.0 | PSR-12 code formatter |
| `fakerphp/faker` | ^1.9.1 | Test data generation |
| `mockery/mockery` | ^1.4.4 | Mocking framework |
| `nunomaduro/collision` | ^7.0 | Better error output for CLI |
| `spatie/laravel-ignition` | ^2.0 | Error page in development |

#### Composer Scripts

| Script | Trigger | Action |
|--------|---------|--------|
| `post-autoload-dump` | After autoload dump | Runs `ComposerScripts::postAutoloadDump` and `package:discover` |
| `post-update-cmd` | After `composer update` | Publishes Laravel assets |
| `post-root-package-install` | After initial install | Copies `.env.example` to `.env` |
| `post-create-project-cmd` | After `create-project` | Generates application key |

#### Composer Configuration

```json
"config": {
    "optimize-autoloader": true,
    "preferred-install": "dist",
    "sort-packages": true,
    "allow-plugins": {
        "pestphp/pest-plugin": true,
        "php-http/discovery": true
    }
}
```

---

## 7. Apache/Server Configuration

### 7.1 .htaccess Files

There are five .htaccess-related files in the project:

#### 1. `public/.htaccess` (Active local/production file)

**File:** `/public/.htaccess`
**Configured for:** fynla.org (root deployment)
**Version tag:** v0.4.5

**Features:**
- `RewriteBase /` (root deployment)
- Forces HTTPS via 301 redirect
- Redirects `www.fynla.org` to `fynla.org` (prevents CORS issues)
- Redirects trailing slashes on non-directories
- Sends all non-file/non-directory requests to `index.php` (Laravel front controller)
- Security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`, removes `X-Powered-By`
- Blocks access to `.env`, `.git`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`
- Blocks direct access to `/storage/` (403)
- Disables directory listing
- DEFLATE compression for: JavaScript, JSON, XML, CSS, HTML, plain text, fonts (TTF, WOFF, WOFF2), SVG
- Browser caching: 1 year for images, CSS, JavaScript, fonts; 0 seconds for JSON and HTML
- MIME type declarations for CSS, JS, MJS, JSON, TTF, OTF, WOFF, WOFF2, SVG, SVGZ, WebP
- UTF-8 character encoding

#### 2. `deploy/fynla-org/.htaccess` (Production template for fynla.org)

**File:** `/deploy/fynla-org/.htaccess`

Identical to `public/.htaccess` with these additions:
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (HSTS header)
- `Content-Security-Policy` header: `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self' data:; frame-ancestors 'none';`

This is the canonical version to upload to production.

#### 3. `deploy/fynla-org/.htaccess.root` (Root redirect file)

**File:** `/deploy/fynla-org/.htaccess.root`
**Purpose:** Goes in `public_html/.htaccess` (the site root, one level above `public/`)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Redirects all requests from `public_html/` into the `public/` subdirectory. This is the standard Laravel shared hosting pattern where the web root cannot be set to `public/` directly.

#### 4. `deploy/csjones-fynla/.htaccess` (Production template for csjones.co/fynla)

**File:** `/deploy/csjones-fynla/.htaccess`
**Version tag:** v0.4.5

**Key differences from fynla.org version:**
- `RewriteBase /fynla/` (subdirectory prefix)
- No `www` redirect rule (not applicable for subdirectory deployment)
- `.git` blocking uses `<DirectoryMatch>` directive instead of `RewriteRule`
- Storage blocking path: `^/fynla/storage/` instead of `^/storage/`
- No HSTS or Content-Security-Policy headers
- No explicit MIME type declarations section
- No `AddCharset` directives for individual file types

#### 5. `public/.htaccess` vs `deploy/fynla-org/.htaccess`

The `public/.htaccess` file is used for local development and also serves as the active production file. The `deploy/fynla-org/.htaccess` is the enhanced production version with additional security headers (HSTS, CSP). When deploying, the `deploy/fynla-org/.htaccess` should be uploaded to `public_html/public/.htaccess` on the server.

### 7.2 Differences Between Targets

| Configuration | fynla.org (Root) | csjones.co/fynla (Subdirectory) |
|---------------|------------------|---------------------------------|
| `RewriteBase` | `/` | `/fynla/` |
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `VITE_ROUTER_BASE` | `/` | `/fynla/` |
| `VITE_API_BASE_URL` | `https://fynla.org` | `https://csjones.co/fynla` |
| `APP_URL` | `https://fynla.org` | `https://csjones.co/fynla` |
| `SANCTUM_STATEFUL_DOMAINS` | `fynla.org,www.fynla.org` | `csjones.co,www.csjones.co` |
| `MAIL_FROM_ADDRESS` | `noreply@fynla.org` | `noreply@csjones.co` |
| `MAIL_HOST` | `mail.fynla.org` | (to be configured) |
| www redirect | Yes (`www.fynla.org` -> `fynla.org`) | No |
| HSTS header | Yes | No |
| CSP header | Yes | No |
| Storage block path | `^/storage/` | `^/fynla/storage/` |
| Root .htaccess needed | Yes (`.htaccess.root`) | Not specified |
| Upload path | `~/www/fynla.org/public_html/public/build/` | `~/www/csjones.co/public_html/fynla/public/build/` |

---

## 8. Environment Configuration

### 8.1 Vite Environment Variables

These are set at build time by the build scripts and baked into the compiled JavaScript:

| Variable | Purpose | Development | fynla.org | csjones.co/fynla |
|----------|---------|-------------|-----------|-------------------|
| `VITE_BASE_PATH` | Asset base path in `vite.config.js` | `/` (default) | `/build/` | `/fynla/build/` |
| `VITE_ROUTER_BASE` | Vue Router base path | `/` | `/` | `/fynla/` |
| `VITE_APP_NAME` | Application name | `Fynla` | `Fynla` | `Fynla` |
| `VITE_API_BASE_URL` | Backend API base URL | `http://localhost:8000` | `https://fynla.org` | `https://csjones.co/fynla` |

### 8.2 Server .env.production Files

**File:** `/deploy/fynla-org/.env.production`
**File:** `/deploy/csjones-fynla/.env.production`

These are templates -- placeholder values must be replaced with actual credentials on the server. They are NOT committed with real values.

**Common settings across both targets:**

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_TIMEZONE` | `Europe/London` | UK financial application |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `localhost` | |
| `DB_PORT` | `3306` | |
| `CACHE_DRIVER` | `file` | File-based caching (shared hosting) |
| `SESSION_DRIVER` | `file` | File-based sessions (shared hosting) |
| `SESSION_LIFETIME` | `120` | 2 hours |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS only |
| `SESSION_HTTP_ONLY` | `true` | No JavaScript access to session cookie |
| `QUEUE_CONNECTION` | `sync` | Synchronous (no queue worker on shared hosting) |
| `LOG_CHANNEL` | `single` | Single log file |
| `LOG_LEVEL` | `error` | Only errors logged in production |
| `MAIL_MAILER` | `smtp` | |
| `MAIL_PORT` | `465` | SSL |
| `MAIL_ENCRYPTION` | `ssl` | |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |

**Placeholder values that must be set on each server:**
- `APP_KEY` (generate with `php artisan key:generate`)
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `MAIL_PASSWORD`
- `ANTHROPIC_API_KEY` (for document extraction feature)

---

## 9. Deployment Process

### 9.1 Build Locally

**For fynla.org:**

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

**For csjones.co/fynla:**

```bash
cd /Users/Chris/Desktop/fynla
./deploy/csjones-fynla/build.sh
```

**What gets built:**
- All Vue components (313) are compiled and tree-shaken
- Tailwind CSS is purged to only used classes
- Output is hashed for cache-busting (e.g., `app-[hash].js`, `app-[hash].css`)
- A `manifest.json` is generated at `public/build/manifest.json` for Laravel to resolve asset URLs

**Output location:** `public/build/` directory (both scripts output to the same local path -- do not run both without uploading in between).

**Verification:** After build, check that `public/build/manifest.json` exists. The build script does this automatically.

### 9.2 Upload to SiteGround

All uploads are performed manually via **SiteGround Site Tools > File Manager**.

**For fynla.org:**

1. Navigate to `~/www/fynla.org/public_html/public/` in File Manager
2. Upload the entire `public/build/` directory (replace existing)
3. Ensure `deploy/fynla-org/.htaccess` is uploaded as `public_html/public/.htaccess`
4. Ensure `deploy/fynla-org/.htaccess.root` is uploaded as `public_html/.htaccess`

**For csjones.co/fynla:**

1. Navigate to `~/www/csjones.co/public_html/fynla/public/` in File Manager
2. Upload the entire `public/build/` directory (replace existing)
3. Ensure `deploy/csjones-fynla/.htaccess` is uploaded as the `public/.htaccess`

**Server directory structure (fynla.org):**

```
~/www/fynla.org/public_html/
    .htaccess                    <-- from deploy/fynla-org/.htaccess.root
    public/
        .htaccess                <-- from deploy/fynla-org/.htaccess
        index.php
        build/
            manifest.json
            assets/
                app-[hash].js
                app-[hash].css
                ...
    app/
    config/
    database/
    routes/
    vendor/
    storage/
    .env                         <-- from deploy/fynla-org/.env.production (with real values)
```

### 9.3 SSH Cache Clearing

**Connection command (fynla.org):**

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

**Standard cache clear (after frontend-only changes):**

```bash
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

**Full cache clear and rebuild (after backend changes):**

```bash
cd ~/www/fynla.org/public_html
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

**For csjones.co/fynla:** Same commands but from `~/www/csjones.co/public_html/fynla`.

### 9.4 PHP File Updates

When backend code changes:

1. Identify all changed PHP files (from deployment notes or git diff).
2. Upload each file individually via SiteGround File Manager, preserving the directory structure.
3. If new Composer dependencies were added, run `composer require <package>` via SSH (or upload the updated `vendor/` directory if the server lacks memory for Composer).
4. If new migrations exist, run `php artisan migrate --force` via SSH.
5. If seeders need to run, run them individually: `php artisan db:seed --class=SeederName --force`.
6. Clear caches (see Section 9.3).

**Critical:** Never run `migrate:fresh` or `migrate:refresh` on the server. These drop all tables and destroy user data. Use `php artisan migrate --force` for pending migrations only.

---

## 10. Server Environment

| Setting | Value |
|---------|-------|
| **Hosting Provider** | SiteGround |
| **Hosting Type** | Shared hosting |
| **PHP Version** | 8.1+ |
| **MySQL Version** | 8.x |
| **Web Server** | Apache (with mod_rewrite, mod_deflate, mod_expires, mod_headers, mod_mime) |
| **SSH Access** | Port 18765, key-based authentication |
| **SSH User** | `u2783-hrf1k8bpfg02` |
| **SSH Host** | `ssh.fynla.org` |
| **SSH Key** | `~/.ssh/production` |
| **Primary Domain** | `fynla.org` |
| **Document Root** | `~/www/fynla.org/public_html/` |
| **Cache Driver** | File (no Redis/Memcached on shared hosting) |
| **Session Driver** | File |
| **Queue Driver** | Sync (no queue workers on shared hosting) |
| **npm available** | No (insufficient memory, 1-2GB required) |
| **Composer available** | Limited (may fail on large installs due to memory) |

---

## 11. Troubleshooting

### Common Deployment Issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| **Blank page with `127.0.0.1:5173` in source** | Vite `hot` file left on server from development | Delete `public/hot` file on server: `rm public/hot` |
| **CSS/JS MIME type errors** | Build was run with wrong `VITE_BASE_PATH` | Rebuild with correct build script (`./deploy/fynla-org/build.sh` or `./deploy/csjones-fynla/build.sh`) |
| **500 Internal Server Error** | Wrong `.htaccess` uploaded (subdirectory version on root deployment, or `<DirectoryMatch>` directive which is not allowed in `.htaccess` on shared hosting) | Upload correct `.htaccess` from `deploy/fynla-org/.htaccess` to `public/.htaccess` |
| **500 `<DirectoryMatch not allowed here`** | csjones-fynla `.htaccess` uses `<DirectoryMatch>` which some shared hosting disallows in `.htaccess` context | Use the fynla-org version which uses `RewriteRule ^\.git - [F,L]` instead |
| **429 Too Many Requests** | Rate limiting cache | `php artisan cache:clear` |
| **Strategy card missing** | Stale cache data | `php artisan cache:clear` |
| **Preview personas broken** | Seeder data missing or stale | `php artisan db:seed --class=PreviewUserSeeder --force` |
| **Tax calculations failing** | Tax configuration not seeded | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| **Wrong `RewriteBase` path** | `.htaccess` from wrong target uploaded | Compare `RewriteBase` value: should be `/` for fynla.org, `/fynla/` for csjones.co |
| **CORS issues with API** | `www.fynla.org` not redirecting to `fynla.org`, or `SANCTUM_STATEFUL_DOMAINS` misconfigured | Check www redirect rule in `.htaccess` and `SANCTUM_STATEFUL_DOMAINS` in `.env` |
| **Assets loading from wrong path** | `VITE_BASE_PATH` mismatch between build and server | Verify that `base` in built `manifest.json` matches the expected path |

### Development-Specific Issues

| Symptom | Fix |
|---------|-----|
| Port 5173 already in use | `pkill -9 node` or use `./dev.sh` which auto-kills existing processes |
| Cannot connect to MySQL | `brew services start mysql` |
| .env file not found | `cp .env.example .env` then configure |

---

## 12. Deployment History

### Deployment Notes Files

| File | Date | Key Changes |
|------|------|-------------|
| `/deploy/DEPLOYMENT_v0.6.2.md` | 20 January 2026 | v0.6.2 release: TOTP MFA, GDPR compliance, RBAC, Goals module, Investment risk calculator, Balance Sheet/Income Statement, 4 preview personas, Strategy card bug fix. 16 migrations, 7 new controllers, 10 new models. |
| `/Feb5Updates/deploy5.md` | 5 February 2026 | Laravel best practices audit (code quality 85 to 94/100), 12 Form Request classes, 10 API Resource classes, IHT service extraction, Builder return types, readonly properties, PHP 8 property promotion, 204 responses for DELETE, Model::preventLazyLoading(). |
| `/Feb6Updates/deploy6.md` | 6 February 2026 | Document upload privacy disclaimer, Investment tab data provider info, Goals dashboard development notice. Frontend-only changes plus OccupationCodeSeeder and PreviewUserSeeder reseeds. |

### Version History

| Version | Notable |
|---------|---------|
| v0.4.5 | .htaccess version tags in deploy configs |
| v0.5.1 | Previous production version before v0.6.2 |
| v0.6.2 | Major release (MFA, GDPR, Goals, RBAC) |
| v0.7.0 | Current version |

---

## 13. Known Issues and Limitations

### Server Memory Constraints

- `npm install` and `npm run build` cannot run on the server. The shared hosting environment does not have the 1-2GB RAM required.
- `composer install` may also fail for large dependency updates. In such cases, the `vendor/` directory must be uploaded from the local machine.
- This means all frontend builds and potentially Composer installs are developer-machine-dependent.

### Manual Upload Process

- There is no CI/CD pipeline. Every deployment requires manual file uploads through SiteGround File Manager.
- Risk of uploading wrong `.htaccess` (root vs subdirectory) causing 500 errors.
- Risk of forgetting to upload specific changed files.
- No automated rollback mechanism beyond database backups and manual file replacement.

### Build Script Limitations

- Both build scripts output to the same `public/build/` directory. Building for one target overwrites the other. If deploying to both targets, build and upload for one target first before building for the second.
- The deploy/README.md references ZIP package creation, but the current build scripts no longer create ZIP files (they build assets only and print upload instructions).

### Shared Hosting Constraints

- No Redis or Memcached available (file-based cache and sessions only).
- No queue workers (synchronous queue processing only).
- No process supervisor (no Horizon, no scheduled task runner beyond cron).
- `.htaccess`-level configuration only (no access to Apache `httpd.conf` or virtual host configuration).
- `<DirectoryMatch>` directive may not work in `.htaccess` on shared hosting (the csjones-fynla `.htaccess` uses it for `.git` blocking, while the fynla-org version uses a `RewriteRule` instead).

### Development Environment

- The `dev.sh` script starts both Laravel (`php artisan serve` on port 8000) and Vite (port 5173) in background processes. It exports local environment variables including `CACHE_DRIVER=array` (in-memory cache for development).
- Laravel dev server is started with increased PHP limits: `upload_max_filesize=100M`, `post_max_size=110M`, `memory_limit=512M`, `max_execution_time=300`.
- The Vite dev server creates a `public/hot` file for HMR. This file must never be uploaded to the production server.

### Legacy Notes

- No `webpack.mix.js` file exists. The project has fully migrated from Laravel Mix to Vite.
- The deploy/README.md still mentions creating ZIP deployment packages, but the actual build scripts no longer do this. The current workflow is: build locally, upload files manually.
