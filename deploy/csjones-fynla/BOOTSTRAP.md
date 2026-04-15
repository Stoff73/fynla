# csjones.co/fynla — One-Time Bootstrap Guide

This is the **first-time setup** for the dev environment at `https://csjones.co/fynla`. Run this once to stand up the dev site; every subsequent deploy is just upload + migrate + clear cache (see `deploy/README.md` for the ongoing flow).

**Ongoing dev deploys:** after bootstrap, use the standard `./deploy/csjones-fynla/build.sh` → upload `public/build/` → SSH clear-cache flow. This guide is for the one-time standing-up.

**Pre-requisite:** The `dev` branch must exist and be pushed to `origin`. See the root `README.md` / `CLAUDE.md` for the branch workflow.

---

## 0. Prerequisites

You need:
- SSH access to csjones.co (key at `~/.ssh/fynlaDev` or whatever you've configured)
- A new empty MySQL database on csjones.co (provision via SiteGround Site Tools → MySQL → Databases)
- Revolut sandbox API keys (see `/Users/CSJ/Desktop/fynla/revolut/implementation-plan.md`)
- Anthropic API key (can share with production, or rotate)

Record these in a password manager **before** starting — you'll paste them into the server `.env`.

---

## 1. Create the MySQL database on csjones.co

1. Log into SiteGround Site Tools for `csjones.co`
2. **MySQL → Databases → Create Database**
   - Database name: e.g. `dbXXXXXXXXXX` (SiteGround generates a prefix)
3. **MySQL → Databases → Users → Create User**
   - Username: e.g. `uXXXXXXXXXX`
   - Generate a strong password, save it
4. **MySQL → Databases → Add user to database**
   - Assign the new user to the new DB with **All Privileges**
5. Confirm the DB appears in the list and note `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

These go into the server `.env` in step 5 below. Never commit them.

---

## 2. Create the app directory on the server (sibling layout)

Fynla follows the same sibling-directory pattern as the existing `tengo-app`
on this server — Laravel code lives OUTSIDE `public_html/`, and only the
`public/` folder is exposed to the web via a symlink. This keeps app
source, vendor, storage etc. out of the document root.

Layout:

```
~/www/csjones.co/
  tengo-app/               ← existing sibling Laravel app (leave alone)
  fynla-app/               ← NEW: Fynla dev install goes here
     app/ config/ public/ ...
  public_html/
     tengo → ../tengo-app/public    ← existing symlink (leave alone)
     fynla → ../fynla-app/public    ← NEW symlink (created in step 7)
```

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
mkdir -p ~/www/csjones.co/fynla-app
cd ~/www/csjones.co/fynla-app
pwd  # should print /home/u163-.../www/csjones.co/fynla-app
```

Leave the SSH session open — you'll come back to it in step 6.

---

## 3. Build the frontend locally

From your Mac, in the fynla repo on the `dev` branch:

```bash
cd /Users/CSJ/Desktop/fynla
git checkout dev
git pull origin dev
./deploy/csjones-fynla/build.sh
```

Expected output: `public/build/` populated with hashed asset filenames. The build script sets `VITE_BASE_PATH=/fynla/build/` and `VITE_ROUTER_BASE=/fynla/` so the SPA routes correctly under the subdirectory.

---

## 4. Upload the code (first-time only — entire project)

For the **first** upload, send the whole project (minus vendor, node_modules, env, storage caches) to `~/www/csjones.co/fynla-app/`.

The easiest way from macOS is `rsync` over SSH:

```bash
cd /Users/CSJ/Desktop/fynla

rsync -avz --delete \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  --exclude=".git/" \
  --exclude="node_modules/" \
  --exclude="vendor/" \
  --exclude=".env" \
  --exclude=".env.backup" \
  --exclude="storage/logs/*" \
  --exclude="storage/framework/cache/*" \
  --exclude="storage/framework/sessions/*" \
  --exclude="storage/framework/views/*" \
  --exclude="bootstrap/cache/*.php" \
  --exclude="tests/" \
  --exclude="April/" \
  --exclude=".claude/" \
  ./ \
  u163-ptanegf9edny@ssh.csjones.co:www/csjones.co/fynla-app/
```

**Important:** the `-p 18765` non-standard port is passed via the inner `-e "ssh -p 18765 ..."` string. Test with a small file first if you're nervous — `rsync` respects `--dry-run`.

Alternatively, zip the project locally and upload via SiteGround File Manager, then extract on the server.

### Upload the correct .htaccess

The `public/.htaccess` in the repo is the **production** (root-deployment) version. You must overwrite it on the server with the **subdirectory** version:

```bash
# From your Mac, still in the fynla repo
scp -P 18765 -i ~/.ssh/fynlaDev \
    deploy/csjones-fynla/.htaccess \
    u163-ptanegf9edny@ssh.csjones.co:www/csjones.co/fynla-app/public/.htaccess
```

**Verify this happens.** The wrong `.htaccess` silently breaks routing.

---

## 5. Create the server `.env`

SSH back in:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
```

Copy the template and edit:

```bash
cp deploy/csjones-fynla/.env.production .env
chmod 600 .env
nano .env
```

Fill in every `YOUR_*` placeholder with real values from step 1, the Revolut sandbox, and your mail credentials. Double-check:

- `APP_ENV=staging`
- `APP_DEBUG=true`
- `APP_URL=https://csjones.co/fynla`
- `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` — the ones from step 1
- `REVOLUT_SANDBOX=true`
- `REVOLUT_API_KEY` / `REVOLUT_PUBLIC_KEY` / `REVOLUT_WEBHOOK_SECRET` — from the Revolut sandbox dashboard
- `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org` (safety net for lifecycle emails)
- `VITE_REVOLUT_SANDBOX=true`
- `SANCTUM_STATEFUL_DOMAINS=csjones.co,www.csjones.co`

Save and exit.

---

## 6. Install Composer dependencies and finalise Laravel

Still SSH'd in at `~/www/csjones.co/fynla-app`:

```bash
# Check PHP version — must be 8.2 or higher
php -v

# Install production dependencies (no dev packages)
composer install --no-dev --optimize-autoloader --no-interaction

# Generate the app key
php artisan key:generate --force

# Run the migrations (creates all tables in the dev DB)
php artisan migrate --force

# Seed the database (tax config, preview personas, test users, etc.)
php artisan db:seed --force

# Link storage so uploads are accessible via /fynla/storage/...
php artisan storage:link

# Build the autoloader and caches for speed
php artisan optimize

# Fix permissions so the web server can write to storage and caches
chmod -R 775 storage bootstrap/cache
```

### If `composer install` runs out of memory

SiteGround shared hosting sometimes kills composer with OOM. Workarounds:

```bash
# Option A: disable autoloader optimisation during install
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --no-autoloader --no-interaction
composer dump-autoload --optimize

# Option B: upload pre-built vendor/ from your Mac via rsync and skip composer install
```

Option B is sometimes necessary on tight memory limits. If you use it, run `composer install --no-dev` locally first, then rsync `vendor/` with the rest.

---

## 7. Create the public symlink

Expose the Laravel `public/` folder to the web by creating a symlink from
`public_html/fynla` to `fynla-app/public`. This matches the existing
`tengo-app` setup on the same server.

```bash
cd ~/www/csjones.co/public_html
ln -s ../fynla-app/public fynla
ls -la fynla
# expected: lrwxrwxrwx ... fynla -> ../fynla-app/public
```

Apache follows the symlink automatically — no SiteGround admin panel
changes needed. The URL `https://csjones.co/fynla` now serves
`~/www/csjones.co/fynla-app/public/index.php`, which is Laravel's
front controller.

**Verify the symlink target is correct** by listing the symlinked path:

```bash
ls ~/www/csjones.co/public_html/fynla/index.php
# expected: the file exists (symlink resolves)
```

If you ever need to remove the symlink (e.g. to clean up), delete it
without touching the target:

```bash
rm ~/www/csjones.co/public_html/fynla   # removes the symlink only
```

`rm -rf` would also work since `rm` doesn't follow the symlink, but
prefer the plain `rm` to keep the blast radius obvious.

---

## 8. Smoke test

From your browser:

1. Visit `https://csjones.co/fynla`
2. Expect: the Fynla landing page loads
3. Open DevTools Network → confirm JS/CSS come from `/fynla/build/*.js` and `/fynla/build/*.css` (not `/build/`)
4. Try registering a new user or logging in as a seeded test user
5. Verify you can reach the dashboard

Common failure modes:

| Symptom | Fix |
|---|---|
| Blank page, 500 Internal Server Error | Wrong `.htaccess` — re-upload `deploy/csjones-fynla/.htaccess` |
| 404 on `/fynla/build/*.js` | `VITE_BASE_PATH` wasn't `/fynla/build/` during build — re-run `./deploy/csjones-fynla/build.sh` |
| Login works but dashboard is blank | `VITE_ROUTER_BASE` wasn't `/fynla/` — re-run the build |
| DB errors on first visit | Migrations didn't run — rerun `php artisan migrate --force` |
| "No application encryption key" | `php artisan key:generate --force` wasn't run |
| 500 on every request | Check `storage/logs/laravel.log` on the server — almost always a missing env var or permission issue |

---

## 9. Register the dev Revolut webhook

In the sandbox merchant dashboard at `https://sandbox-merchant.revolut.com`:

1. Navigate to **Developer → API → Webhooks**
2. Click **Add webhook**
3. URL: `https://csjones.co/fynla/api/payment/webhook`
4. Events: select all subscription / order events
5. Copy the `signing_secret` (starts with `wsk_`)
6. SSH into csjones.co, edit `.env`, paste into `REVOLUT_WEBHOOK_SECRET=`
7. `php artisan config:clear`

Test the webhook by initiating a subscription checkout in dev — the Revolut sandbox will POST back on completion.

---

## 10. One-time dev-only guardrails

Before closing the bootstrap, run these checks on the dev server so you catch misconfigurations early:

```bash
# Confirm we're in staging mode
php artisan tinker --execute="echo app()->environment().PHP_EOL;"
# Expected: staging

# Confirm Revolut is in sandbox mode
php artisan tinker --execute="echo config('services.revolut.sandbox') ? 'sandbox' : 'PRODUCTION'.PHP_EOL;"
# Expected: sandbox

# Confirm lifecycle test recipient override is set
php artisan tinker --execute="echo config('lifecycle.test_recipient_override') ?: 'UNSET'.PHP_EOL;"
# Expected: chris@fynla.org (or whatever you configured)

# Confirm the scheduler can see the lifecycle command
php artisan schedule:list | grep lifecycle
# Expected: "30 8 * * * php artisan lifecycle:run-daily"
```

If any of these return the wrong value, fix the `.env` and `php artisan config:clear` before continuing.

---

## 11. Set up the system cron on csjones.co (required for scheduled commands)

Same fix as production — SiteGround doesn't run Laravel's scheduler automatically, you have to set up a system cron entry. The command that runs every minute is:

```
cd /home/u163-ptanegf9edny/www/csjones.co/public_html/fynla && /usr/local/php83/bin/php-cli artisan schedule:run >> /dev/null 2>&1
```

Set it via SiteGround Site Tools → **Devs → Cron Jobs** → **Create Cron Job**:
- Minute / Hour / Day / Month / Weekday: all `*`
- Command: (as above)

Save. Verify the next day that scheduled commands have fired (e.g. `trial_reminder_log` table gets new rows, `lifecycle_email_log` gets entries after 08:30 UTC).

---

## 12. You're done

Dev env is live. Going forward:

1. Feature work → feature branch → PR to `dev`
2. Merge `dev` locally
3. `./deploy/csjones-fynla/build.sh` → upload `public/build/` + changed PHP files
4. SSH → `php artisan migrate --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`
5. Test on `https://csjones.co/fynla`
6. When ready for production: `dev → main` merge → production deploy via `./deploy/fynla-org/build.sh`

See `CLAUDE.md § Deployment` for the ongoing flow.
