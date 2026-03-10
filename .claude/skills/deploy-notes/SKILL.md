---
name: deploy-notes
description: Auto-generate deployment documentation from git diff. Categorises changed files, determines if a frontend build is needed, generates SiteGround upload checklist with server paths, SSH commands, and warnings. Saves to both project and Obsidian vault. Use when the user says "deploy notes", "generate deploy", "what needs deploying", "deployment docs", or after completing a feature that needs production deployment.
disable-model-invocation: true
---

# Deploy Notes Generator

Auto-generate deployment documentation by analysing git changes. Saves to both the project and Obsidian vault.

## Step 1: Determine Comparison Range

Find what has changed since the last deployment or merge to main:

```bash
# If on a feature branch, compare against main
git diff --name-only origin/main...HEAD 2>/dev/null

# If on main, compare against the last merge commit or tag
git diff --name-only HEAD~1...HEAD 2>/dev/null
```

If the user specifies a range (e.g., "since last deploy", "last 3 commits"), use that instead.

If there are no changed files, tell the user and stop.

## Step 2: Categorise Changed Files

Read the full list of changed files and sort into categories:

| Category | Patterns | Deploy Action |
|----------|----------|---------------|
| **PHP Backend** | `app/**/*.php`, `config/*.php`, `routes/*.php` | Upload via SiteGround File Manager |
| **Frontend** | `resources/js/**`, `resources/css/**` | Run `./deploy/fynla-org/build.sh` then upload `public/build/` |
| **Migrations** | `database/migrations/*.php` | Upload + SSH `php artisan migrate --force` |
| **Seeders** | `database/seeders/*.php` | Upload + SSH `php artisan db:seed --class=XSeeder --force` |
| **Deploy Config** | `deploy/**`, `.htaccess` | Upload to server |
| **Composer** | `composer.json`, `composer.lock` | SSH `composer install --no-dev` on server |
| **Docs Only** | `*.md`, `docs/**`, `.claude/**`, `March/**` | No deployment needed |
| **Tests Only** | `tests/**` | No deployment needed |

Count files per category. If ALL changes are docs/tests only, report "No deployment needed" and stop.

## Step 3: Determine Build Requirements

**Frontend build needed?**
Check if ANY file matches: `resources/js/**` or `resources/css/**`

If yes:
```
Build command: ./deploy/fynla-org/build.sh
Upload: public/build/ → ~/www/fynla.org/public_html/public/build/
```

**NEVER suggest `npx vite build`, `npm run build`, or raw vite commands.**

## Step 4: Generate Upload List

For each PHP/config file, map the local path to the server path:

```
Base server path: ~/www/fynla.org/public_html/
```

Example:
```
app/Services/Estate/IHTCalculator.php
  → ~/www/fynla.org/public_html/app/Services/Estate/IHTCalculator.php
```

Group files by directory for easier batch uploading.

## Step 5: Generate SSH Commands

Always include the connection and cache clear:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

Add conditional commands:

- If migrations present: `php artisan migrate --force`
- If seeders present: list each specific seeder command
- If composer changed: `composer install --no-dev --optimize-autoloader`

## Step 6: Check for Warnings

Flag any of these:

| Warning | Condition |
|---------|-----------|
| Migration requires SSH | `database/migrations/*.php` changed |
| Environment config change | `config/*.php` changed (may need `.env` update on server) |
| New composer dependencies | `composer.json` or `composer.lock` changed |
| Route changes | `routes/*.php` changed (must clear route cache) |
| Middleware changes | `app/Http/Middleware/*.php` changed |
| PreviewWriteInterceptor | `PreviewWriteInterceptor.php` changed (verify excluded routes) |
| Seeder changes | `database/seeders/*.php` changed (must run specific seeder on server) |
| Observer changes | `app/Observers/*.php` changed (verify no side effects) |

## Step 7: Generate and Save Deploy Notes

Determine today's date and generate the filename:

```bash
date +"%d"  # Day of month for folder name
```

**Output format:**

```markdown
# Deployment Notes - [DD Month YYYY]

## Summary
- **Files changed:** X
- **Frontend build required:** Yes/No
- **Migrations:** Yes/No
- **Seeders:** [list or None]

## PHP Files to Upload

### [Directory Group]
- [ ] `path/to/file.php` → `~/www/fynla.org/public_html/path/to/file.php`

## Frontend Build
- [ ] Run `./deploy/fynla-org/build.sh`
- [ ] Upload `public/build/` → `~/www/fynla.org/public_html/public/build/`

## Migrations
- [ ] `database/migrations/xxxx_migration.php`

## Seeders
- [ ] `php artisan db:seed --class=XSeeder --force`

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
[additional commands if needed]
```

## Warnings
- [Any warnings from Step 6]

## Verification
- [ ] Visit https://fynla.org and verify no blank page
- [ ] Check a preview persona loads correctly
- [ ] Verify changed feature works as expected
```

**Save to both locations:**

1. Project: `March/March[DD]Updates/deploy.md`
2. Vault: `/Users/CSJ/Desktop/fynlaBrain/March/March[DD]Updates/deploy.md`

Create directories if they don't exist:
```bash
mkdir -p "March/March$(date +%d)Updates"
mkdir -p "/Users/CSJ/Desktop/fynlaBrain/March/March$(date +%d)Updates"
```

## Step 8: Display Summary

After saving, show a concise summary to the user:

```
Deploy notes saved to:
  - March/March[DD]Updates/deploy.md
  - fynlaBrain/March/March[DD]Updates/deploy.md

Summary: X PHP files, [build needed/not needed], [N migrations], [N seeders]
Warnings: [list any]
```

## Important

- NEVER suggest `npx vite build` or `npm run build` — always use `./deploy/fynla-org/build.sh`
- NEVER include docs, tests, or `.claude/` files in the upload list — they don't go to production
- NEVER include `vendor/` or `node_modules/` — these are managed on server via composer
- Always include the verification checklist at the end
- If the user asks to deploy to csjones.co/fynla instead, use `./deploy/csjones-fynla/build.sh` and adjust the base path to `~/www/csjones.co/public_html/fynla/`
