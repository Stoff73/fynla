---
type: deploy-note
date: 2026-05-06
session: 3
environment: csjones (dev)
deployed_by: claude
---

# Deploy — 2026-05-06 session 3 (csjones dev)

## What shipped

Two related fixes after CSJ reported "click publish, nothing happens, no article shows up on /insights":

1. **DocumentArticle publishes never busted the merged insights cache** — fixed via new observer.
2. **CDN edge had a stale `text/html` response cached for `/api/insights`** (from before Fynla was deployed at this URL) — fixed via Apache `Cache-Control: no-store` on `/api/*`.
3. **Bespoke article cover images returned 403** — SiteGround restricts symlink traversal regardless of `+FollowSymLinks` / `+SymLinksIfOwnerMatch`. Replaced with a Laravel `/storage/{path}` route that streams from `Storage::disk('public')`.

## Files uploaded to csjones

| File | Path on server | Why |
|---|---|---|
| `app/Observers/DocumentArticleObserver.php` (new) | `~/www/csjones.co/fynla-app/app/Observers/` | Bust `insights.featured` + `insights.list_version` cache on DocumentArticle save/delete |
| `app/Providers/AppServiceProvider.php` (mod) | `~/www/csjones.co/fynla-app/app/Providers/` | Register DocumentArticleObserver |
| `routes/web.php` (mod) | `~/www/csjones.co/fynla-app/routes/` | New `/storage/{path}` route (before SPA catch-all) |
| `deploy/csjones-fynla/.htaccess` (mod) | `~/www/csjones.co/fynla-app/public/.htaccess` | (a) removed `RedirectMatch 403 ^/fynla/storage/`, (b) added `RewriteRule ^api/ - [E=FYNLA_API:1]` in main rewrite block, (c) added `Header always set Cache-Control "no-store..." env=FYNLA_API` (and REDIRECT_FYNLA_API) |
| `public/build/` (rebuilt) | `~/www/csjones.co/fynla-app/public/build/` | New SPA bundle `app-DvOc0GPe.js` with one-line `_t=Date.now()` cachebuster on `insightsService.list()` (belt-and-braces while the legacy CDN entry expires) |

The existing `public/storage` symlink on csjones was REMOVED — Apache 403s symlink traversal there. The new Laravel route serves the same content via PHP. On hosts where the symlink works (local, fynla.org), Apache still serves directly and the route is a no-op.

## Server-side commands run

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
composer dump-autoload -o            # for the new observer class
php artisan cache:clear              # flush stale insights list cache
php artisan route:clear              # pick up the new /storage/{path} route
php artisan config:clear
php artisan view:clear
php artisan optimize
rm -f public/storage                 # remove the symlink Apache won't traverse
```

## Verification (csjones live)

- `https://csjones.co/fynla/insights?_cb=N` — `nootropic_stack` (CSJ's published article) renders as Featured hero. `Rich Sample Title` in side panel. All 8 bespoke insights in Browse all (7) grid. **Zero console errors** (was 8 × 403 image errors).
- `https://csjones.co/fynla/insights/nootropic-stack` — article body loads end-to-end.
- `curl https://csjones.co/fynla/api/insights?_=N` → `cache-control: no-store, no-cache, private, must-revalidate, max-age=0`.
- `curl https://csjones.co/fynla/?_=N` → `cache-control: private, must-revalidate` (Laravel default — only `/api/*` is overridden).
- `curl https://csjones.co/fynla/storage/insights/bespoke/how-much-to-retire-uk.jpg?_=N` → 200, `image/jpeg`, 67402 bytes, `cache-control: max-age=31536000, public, s-maxage=31536000`.

## NOT YET deployed to fynla.org

The same three .htaccess templates were updated (`deploy/fynla-org/.htaccess` and `public/.htaccess`) and `routes/web.php` change applies. Production has the same `RedirectMatch 403 ^/storage/` issue — it will also affect bespoke article cover images on fynla.org once they go live. CSJ to deploy when convenient as part of the next `dev → main` release.

## Legacy CDN entry — one-time cleanup

The OLD poisoned cache entry for the bare `https://csjones.co/fynla/api/insights` URL still serves text/html "Workflow – Immerse in the AI Era" content (`x-proxy-cache: HIT`). With `no-store` now in force, no NEW entries can form. Three options for the legacy entry:
1. Wait for SiteGround dynamic-cache TTL to expire naturally.
2. Purge once via Site Tools → Speed → Caching → Dynamic Cache → Purge.
3. Leave it — the SPA's cachebuster (`?_t=Date.now()` on `insightsService.list()`) sidesteps it transparently for all users.

After the legacy entry is gone, the SPA cachebuster can be reverted (one-line removal in `resources/js/services/insightsService.js`).
