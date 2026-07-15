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
4. SSH in, enter maintenance mode and drain the old queue worker:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
php artisan down
php artisan queue:restart                       # old workers exit after their current job
# Confirm no pre-deploy queue job is still running before continuing.
```

5. With maintenance mode still active, upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/` (SiteGround File Manager or `scp -r`). `public/build/` is gitignored so `git pull` won't manage it.
6. Pull source and finalise:

```bash
set -euo pipefail
git pull origin dev                          # pulls all PHP / JS source / .htaccess templates
php artisan subscriptions:audit-tier-collapse --json  # required only for the Free/Premium collapse release; must report safe_to_collapse=true
php artisan migrate --force
php artisan db:seed --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && composer dump-autoload -o && php artisan config:cache
php artisan up
```

For the Free/Premium identity-collapse release, do not run the migration until
the audit reports zero current paid entitlements and `safe_to_collapse=true`.
Maintenance mode must remain active from before `git pull` until migrations,
seeding where required, and cache rebuilds finish. Queue workers started by a
supervisor remain paused by Laravel maintenance mode; verify the old worker has
finished its current job before the migration acquires the exclusive cutover lock.

**NEVER `php artisan optimize` or `route:cache` on this app.** The compiled route
matcher lets the SPA catch-all shadow the server-rendered `/` homepage (despite the
`.+` constraint), so guests — and the `/m` iframe, which loads `/` — get the bare
SPA shell instead of `public/pages/index.php`. Found live 2026-06-11: the public
landing "regressed" to the old SPA `LandingPage.vue` design. Config caching is
still required (SiteGround .env re-parse races — see prod notes) which is why the
chain ends with an explicit `config:cache`, never `optimize`.

7. Smoke test `https://csjones.co/fynla` — **check content, not just 200**: `curl -s https://csjones.co/fynla/ | grep -c "Get started for free"` must be ≥1 (server-rendered homepage, not the SPA shell).
8. If a dev DB reset is needed: `php artisan db:seed --force` (NEVER `migrate:fresh`)

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
5. SSH in, enter maintenance mode and drain the old queue worker:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan down
php artisan queue:restart                       # old workers exit after their current job
# Confirm no pre-deploy queue job is still running before continuing.
```

6. With maintenance mode still active, upload `public/build/` + changed PHP files to `~/www/fynla.org/public_html/`.
7. Finalise over SSH:

```bash
set -euo pipefail
php artisan subscriptions:audit-tier-collapse --json  # Free/Premium collapse release only; must report safe_to_collapse=true
php artisan migrate --force
php artisan db:seed --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan config:cache
php artisan up
```

When PHP files are uploaded rather than pulled atomically, enter maintenance
mode before replacing the first file. For the Free/Premium collapse release,
the same worker-drain and zero-entitlement audit gate used on dev is mandatory.
If either check fails, keep the site down and do not run the migration.

8. Smoke test `https://fynla.org`
9. Monitor `storage/logs/laravel.log` for errors for the next 10-15 minutes

## CoALA memory subsystem — post-deploy (coala branch and later)

Once the coala line ships, every deploy — dev **and** prod — appends the CoALA
memory command chain to the finalise steps above. Dev deploys today (pre-coala
`dev`) don't run them; the commands don't exist on that branch yet.

Order matters. `fyn:episodic:backfill-blobs` runs immediately after
`php artisan migrate --force` (it depends on the Phase 2 episode-columns
migration); the three corpus validators run after the cache-clear chain:

```bash
php artisan migrate --force
php artisan fyn:episodic:backfill-blobs    # one-time after the Phase 2 episode-columns migration; idempotent thereafter
# ... cache-clear chain as above (ends config:cache — never optimize) ...
php artisan fyn:semantic:reindex && php artisan fyn:pointers:reindex && php artisan fyn:procedural:validate
```

What each command does:

| Command | What it validates / does |
|---------|--------------------------|
| `fyn:episodic:backfill-blobs` | Backfills episodic `.md` blobs for legacy `ai_messages` rows (writes the blob, populates `blob_md_path`/`blob_md_sha256`). Idempotent — skips rows already backfilled, so it's safe in every deploy even though it only does real work once. |
| `fyn:semantic:reindex` | Validates the Fyn semantic corpus and writes the cached index (sparse, no embeddings) to the path in `config('fyn.memory.semantic_index')`. |
| `fyn:pointers:reindex` | Validates the Fyn pointer corpus by loading every pointer through the registry. Writes no index file — the registry reads the corpus at runtime; this is the deploy-time safety net. |
| `fyn:procedural:validate` | Strict-loads the Fyn procedural corpus and lists the active procedures per kind/module. |

**Fail-closed semantics: a non-zero exit from any of the three validators is a
deploy gate failure.** Each returns exit 1 when its corpus fails validation —
don't smoke test or call the deploy done until all three exit 0; fix the corpus
(or roll the pull back) first. Runtime serving degrades rather than crashes on
a bad corpus, which is exactly why the gate lives in the deploy chain — without
it a broken corpus ships silently.

These commands don't disturb the route:cache/optimize ban above — they touch
only the Fyn corpora, the semantic index file, and episodic blobs, never the
route or config caches.

Not part of the deploy chain (for completeness): `fyn:episodic:reconcile`
(scheduler, daily) and `fyn:episodic:cold-archive` (scheduler, weekly) run on
their own; `fyn:episodic:purge --force` (6-year FCA retention) and
`fyn:user:erase {user} --force` (GDPR erasure) are operator-run and dry-run
without `--force`.

## Environment config templates

- `deploy/csjones-fynla/.env.production` — template for the dev `.env`. Has `APP_ENV=staging`, `REVOLUT_SANDBOX=true`, `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`.
- `deploy/fynla-org/.env.production` — template for the production `.env`. Has `APP_ENV=production`, `REVOLUT_SANDBOX=false`, no test recipient override.

Real credentials (DB password, mail password, Revolut keys, Anthropic key) live only in each server's `.env` — never in the repo, never echoed in chat.
