---
type: deploy-note
target: production (fynla.org)
date: 2026-05-06
session: 4 (context-clear)
status: READY — execute in next session
source_branch: dev (origin/dev @ 18558c5)
target_branch: main (origin/main @ fe77a774, ~59 commits behind)
---

# Production Deploy Spec — `dev → main → fynla.org`

This deploy was prepared at the end of session 4 (2026-05-06). All work has been verified live on csjones.co/fynla. **Local and dev source trees are byte-identical** (verified 100/100 files post-sync earlier this session). Next session should execute this deploy.

## Pre-flight (do these once at the start of next session)

```bash
cd /Users/CSJ/Desktop/fynla
git checkout dev && git pull origin dev
git log --oneline origin/main..origin/dev | wc -l   # expect 59
```

If anything other than 59 commits, surface to CSJ — something landed unexpectedly.

## Step 1: Open + merge PR `dev → main`

CSJ opens this PR themselves (only `@Stoff73` can merge to main per branch protection). Title:

```
Release: dev → main — May 6 release (insights cache fix, /storage route, csjones git checkout)
```

PR body should reference the highlights below. Don't auto-write a PR body; CSJ will.

After merge:
```bash
git checkout main && git pull origin main
git log -1 --oneline   # should be the merge commit
```

## Step 2: Build production SPA bundle

```bash
./deploy/fynla-org/build.sh
```

This sets `VITE_BASE_PATH=/build/`, `VITE_ROUTER_BASE=/`, `VITE_API_BASE_URL=https://fynla.org`, `VITE_REVOLUT_SANDBOX=false`. Output goes to `public/build/`.

**Verify** `public/build/manifest.json` references `app-<hash>.js` paths starting with `/build/` (not `/fynla/build/`). If they start with `/fynla/`, the wrong build script ran — re-run `./deploy/fynla-org/build.sh` from a clean tree.

## Step 3: Files to upload to `~/www/fynla.org/public_html/`

Production fynla.org is **not** yet a git checkout — it still uses the manual upload pattern. Convert it next session via the same recipe BOOTSTRAP.md §12 documents for csjones (see "Optional follow-up" below).

For this release, upload via SiteGround File Manager or `rsync`:

### Build artefacts (always)
- `public/build/` (entire directory — replace; preserve old chunks if you want zero-downtime asset rotation, otherwise straight replace)

### .htaccess (mandatory — contains the new `FYNLA_API` env-var pattern + Cache-Control no-store on `/api/*`)
- `public/.htaccess` (the production root template — already correct in repo HEAD on `main` post-merge)

### Backend code (59 commits' worth — easiest is rsync of `app/`, `routes/`, `database/`, `config/` from local)
**App (335 files):** rsync `app/` to server `app/`.
**Routes (3 files):** `routes/api.php`, `routes/api_v1.php`, `routes/web.php` (web.php has the new `/storage/{path}` route — production may not need it functionally because Apache symlinks work there, but keeping route in sync with dev costs nothing and serves as a fallback).
**Config (10 files):** `config/app.php`, `config/auth.php`, `config/database.php`, `config/fyn_eval.php`, `config/lifecycle.php`, `config/mail.php`, `config/onboarding.php`, `config/purifier.php`, `config/sanctum.php`, `config/services.php`.
**Frontend source (62 files in `resources/js/`):** not strictly required because `public/build/` is what's served, but keep in sync to avoid the same drift problem we fixed on csjones today.

### Database migrations (34 new — `php artisan migrate --force` on server runs them in order)
Critical irreversible migrations to flag for CSJ:
- `2026_05_06_000001_drop_is_eval_user_from_users.php` — DROPS a column
- `2026_05_06_000002_rename_eval_user_id_to_preview_user_id.php` — RENAMES a column
- `2026_05_05_000002_add_charitable_donations_to_users.php` — ALTERs users (large table)
- All `2026_04_25_*` ai_* tables — NEW tables, safe
- `2026_04_27_120000_create_news_articles_table.php` — NEW
- `2026_04_28_120000_create_news_subscribers_table.php` — NEW
- `2026_05_01_120000_create_document_articles_table.php` — NEW (the CMS work)

Full list (34): see `git diff --name-only origin/main...origin/dev | grep migrations/`.

**Recommend** taking a DB snapshot in SiteGround Site Tools → MySQL → Backups before running migrations.

### Database seeders (10 modified — only run if you want fresh seed data; production already has live data, so be selective)
Only run seeders that ADD reference data (e.g. `TaxConfigurationSeeder`, `DiscountCodeSeeder`, `SavingsActionDefinitionSeeder`, `NewsArticleSeeder`). Do NOT run `TestUsersSeeder`, `ChrisUserSeeder`, `PreviewUserSeeder`, `LifecycleTestSeeder`, `AdminUserSeeder` on production — they create test/preview accounts.

Safe selective seed:
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=DiscountCodeSeeder --force
php artisan db:seed --class=SavingsActionDefinitionSeeder --force
php artisan db:seed --class=NewsArticleSeeder --force
```

## Step 4: Server-side finalise

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# composer (only if composer.lock changed — check first)
git diff origin/main...origin/dev -- composer.lock | head   # if non-empty, run:
# composer install --no-dev --optimize-autoloader --no-interaction

# autoload + migrations + caches
composer dump-autoload -o
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

## Step 5: Smoke test on https://fynla.org

1. Landing page loads, no 500
2. `curl -sI https://fynla.org/api/insights | grep -i cache-control` → expect `no-store, no-cache, private, must-revalidate, max-age=0`
3. Log in as `chris@fynla.org` (CSJ will need to provide the verification code from his email)
4. Dashboard renders all module cards
5. `/insights` renders bespoke articles + any published DocumentArticles
6. No JS console errors
7. Tail `storage/logs/laravel.log` for 10–15 min — watch for any new errors

## Step 6: Sandbox vs live verification

- `php artisan tinker --execute="echo app()->environment().PHP_EOL;"` → `production`
- `php artisan tinker --execute="echo config('services.revolut.sandbox') ? 'sandbox' : 'PRODUCTION'.PHP_EOL;"` → `PRODUCTION`
- Confirm `LIFECYCLE_TEST_RECIPIENT` is **unset** in `.env` (no test override on prod)

## Optional follow-up: convert production to a git checkout

Same recipe as `BOOTSTRAP.md §12` (which we wrote for csjones in session 4). After production deploy is green and you have ~24h of soak:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
rm -f .git
git init -b main
git remote add origin https://github.com/Stoff73/fynla.git
git fetch --depth=1 origin main
git update-ref refs/heads/main FETCH_HEAD
git symbolic-ref HEAD refs/heads/main
git reset --hard origin/main
git branch --set-upstream-to=origin/main main
# production .htaccess is the canonical root template — no skip-worktree needed
# .env is gitignored, stays in place
```

After this, future production deploys are: build locally → upload `public/build/` → SSH `git pull origin main` → migrate + cache:clear.

## Highlights — what's in this release

From `git log origin/main..origin/dev --oneline`:

- **Insights publish→hub bug closed (session 3, 2026-05-06):** `DocumentArticleObserver`, Laravel `/storage/{path}` route, scoped `Cache-Control: no-store` on `/api/*` (prevents CDN poisoning permanently)
- **csjones git checkout restored (session 4, 2026-05-06):** docs-only changes to `CLAUDE.md` and `deploy/csjones-fynla/BOOTSTRAP.md` documenting the new `git pull` deploy flow
- **Drag-only DropZones (session 2, 2026-05-06):** `Admin/Documents/DropZone.vue` and `Shared/UploadDropZone.vue` reduced to drag-and-drop only
- **Vite port pinned to 5173 (session 2, 2026-05-06):** prevents collision with sibling fynlaInternational
- **csjones↔dev reconciliation (sessions 2026-05-04 / 2026-05-05):** spec, plan, design, audit
- **Persona split merge (#242):** Eval HTTP-driven rewrite + Tax Strategy + AI Audit (16+ tasks)
- **Onboarding Fyn (#214):** backend state machine + grouped LLM extraction
- **Document Articles CMS (#240):** drag-drop `.docx` import + publish
- **CMS Upload UX polish (#241)**
- **News + Newsletter + RSS + Lifecycle emails (#238):** subscribe modal, admin list/export, lifecycle welcome email
- **Tax Strategy household inputs:** new tables, salary sacrifice, employer NI rebate, charitable donations
- **AI infra:** ai_daily_usage, ai_request_idempotency, ai_abort_events, ai_audit_events, eval recording sessions
- **Pension input history:** new audit table for pension changes
- **Civil partnership marital status added**
- **Insight article categories expanded**

## Rollback

If smoke fails badly post-deploy:
1. Revert merge commit on `main` locally, `git push origin main` (or use the GitHub UI)
2. On server: `git pull origin main` (or upload reverted files via SiteGround)
3. `php artisan migrate:rollback --step=N` — count the migrations that ran in this release (worst case 34, but most are additive and rolling back additive migrations is safe)
4. `php artisan cache:clear && php artisan optimize`

Pre-recon rollback tags exist on origin: `pre-recon/dev` (`dc335b3`), `pre-recon/persona-split` (`1bf89e8`). These predate this release.
