# Deploy Runbook

Step-by-step deploy procedures for both environments. CLAUDE.md holds the rules (env table, branch flow, "never mix environments"); this file holds the commands.

## Build scripts (per environment)

Build **locally** — the servers lack the memory for npm:

```bash
./deploy/fynla-org/build.sh        # Build for fynla.org (root deployment)
./deploy/csjones-fynla/build.sh    # Build for csjones.co/fynla (subdirectory)
```

The scripts set different Vite env vars so SPA routing and asset paths match the target:

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

**Never mix environments.** Build with `csjones-fynla/build.sh` and upload to fynla.org and the Vue router base path is wrong — blank page or 404 loop, no nice error.

## Deploying to dev (csjones.co/fynla)

The csjones server is a real git checkout tracking `origin/dev` — every deploy pulls exactly what's on the remote. The only manual upload is the compiled `public/build/` bundle (gitignored).

1. Work on a feature branch off `dev`, open PR → `dev`
2. After merge, locally: `git checkout dev && git pull`
3. Build the SPA bundle locally: `./deploy/csjones-fynla/build.sh`
4. Upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/` (SiteGround File Manager or `scp -r`). `public/build/` is gitignored so `git pull` won't manage it.
5. SSH in and pull source + finalise:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
git pull origin dev                          # pulls all PHP / JS source / .htaccess templates
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && composer dump-autoload -o && php artisan config:cache
```

**NEVER `php artisan optimize` or `route:cache` on this app.** The compiled route
matcher lets the SPA catch-all shadow the server-rendered `/` homepage (despite the
`.+` constraint), so guests — and the `/m` iframe, which loads `/` — get the bare
SPA shell instead of `public/pages/index.php`. Found live 2026-06-11: the public
landing "regressed" to the old SPA `LandingPage.vue` design. Config caching is
still required (SiteGround .env re-parse races — see prod notes) which is why the
chain ends with an explicit `config:cache`, never `optimize`.

6. Smoke test `https://csjones.co/fynla` — **check content, not just 200**: `curl -s https://csjones.co/fynla/ | grep -c "Get started for free"` must be ≥1 (server-rendered homepage, not the SPA shell).
7. If a dev DB reset is needed: `php artisan db:seed --force` (NEVER `migrate:fresh`)

**Why this works without clobbering env config:**
- `.env` is gitignored — never touched.
- `public/.htaccess` has `git update-index --skip-worktree` set on csjones, so `git pull` ignores it. The dev `/fynla/` rewrite-base version stays in place. If routing rules change in the source template (`deploy/csjones-fynla/.htaccess`), copy it manually: `cp deploy/csjones-fynla/.htaccess public/.htaccess` after pull.
- `public/storage` is intentionally absent on csjones (Apache 403s symlinks there; Laravel `/storage/{path}` route in `routes/web.php` handles requests instead). Don't run `php artisan storage:link` on csjones.

**First-time dev setup** (one-time only): see `deploy/csjones-fynla/BOOTSTRAP.md`.

## Deploying to production (fynla.org)

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

## Environment config templates

- `deploy/csjones-fynla/.env.production` — template for the dev `.env`. Has `APP_ENV=staging`, `REVOLUT_SANDBOX=true`, `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`.
- `deploy/fynla-org/.env.production` — template for the production `.env`. Has `APP_ENV=production`, `REVOLUT_SANDBOX=false`, no test recipient override.

Real credentials (DB password, mail password, Revolut keys, Anthropic key) live only in each server's `.env` — never in the repo, never echoed in chat.
