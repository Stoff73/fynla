# Admin Insights CMS — Deploy Guide

**Date:** 17 April 2026
**Branch:** `feature/csj/insights-cms` → PR → `dev` → smoke test → PR → `main`
**Env targets:** dev (`csjones.co/fynla`) then production (`fynla.org`)

---

## 1. What ships

Three new tables (`insight_templates`, `insight_articles`, `insight_article_revisions`) with an admin CMS under `/admin/insights/*`, a DB-driven `/insights/:slug` public renderer (behind the 8 existing bespoke Vue routes), and a refreshed 2/3+1/3 hero on the landing page.

**New dependencies:**
- `intervention/image ^4.0` (composer) — image resize pipeline
- `dompurify ^3.4` (npm) — frontend HTML sanitisation

---

## 2. Order of operations (both environments)

```
1. Upload PHP + Blade + config files (section 4)
2. php artisan migrate --force
3. php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
4. Verify /api/insights returns the 8 bespoke rows
5. Build frontend locally (section 5) and upload public/build/
6. Clear caches (section 6)
7. Smoke tests (section 7)
```

---

## 3. Database

Three migrations to apply in order. Timestamps enforce the FK dependency (templates before articles).

```
database/migrations/2026_04_17_090001_create_insight_templates_table.php
database/migrations/2026_04_17_090002_create_insight_articles_table.php
database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php
```

The `ExistingInsightsMetadataSeeder` seeds metadata for the 8 existing bespoke articles so the hub and landing hero can surface them alongside CMS-authored content. Idempotent (`updateOrCreate` by slug); safe to re-run.

```bash
php artisan migrate --force
php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
php artisan tinker --execute="echo \App\Models\Insights\InsightArticle::where('is_bespoke',true)->count();"
# expect: 8
```

---

## 4. PHP / Blade files to upload

81 files changed. Upload relative to the server's public_html root (fynla.org) or `fynla-app/` (csjones.co).

**Framework + bootstrap:**
```
app/Console/Kernel.php
app/Http/Kernel.php
app/Http/Middleware/SanitizeInput.php
app/Http/Middleware/InsightsSeoMetaInjector.php
app/Providers/AppServiceProvider.php
resources/views/app.blade.php
routes/api.php
routes/web.php
composer.json
composer.lock
```

**Insights domain (all new):**
```
app/Models/Insights/InsightArticle.php
app/Models/Insights/InsightArticleRevision.php
app/Models/Insights/InsightTemplate.php
app/Observers/InsightArticleObserver.php
app/Jobs/PublishScheduledInsightsJob.php
app/Services/Insights/BlockValidator.php
app/Services/Insights/InsightArticleService.php
app/Services/Insights/InsightImageService.php
app/Services/Insights/InsightSeoService.php
app/Services/Insights/InsightTemplateService.php
app/Http/Controllers/Api/Admin/InsightArticleController.php
app/Http/Controllers/Api/Admin/InsightImageController.php
app/Http/Controllers/Api/Admin/InsightTemplateController.php
app/Http/Controllers/Api/Public/InsightController.php
app/Http/Requests/Admin/Insights/StoreInsightArticleRequest.php
app/Http/Requests/Admin/Insights/StoreInsightTemplateRequest.php
app/Http/Requests/Admin/Insights/UpdateInsightArticleRequest.php
app/Http/Requests/Admin/Insights/UploadInsightImageRequest.php
app/Http/Resources/Insights/InsightArticleResource.php
app/Http/Resources/Insights/InsightArticleListResource.php
app/Http/Resources/Insights/InsightTemplateResource.php
database/migrations/2026_04_17_090001_create_insight_templates_table.php
database/migrations/2026_04_17_090002_create_insight_articles_table.php
database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php
database/factories/Insights/InsightArticleFactory.php
database/factories/Insights/InsightArticleRevisionFactory.php
database/factories/Insights/InsightTemplateFactory.php
database/seeders/DatabaseSeeder.php
database/seeders/ExistingInsightsMetadataSeeder.php
database/seeders/ChrisUserSeeder.php
```

**Install new PHP dependency on each host:**
```bash
composer install --no-dev --optimize-autoloader
```

(intervention/image is in `composer.json`; running `composer install` after uploading `composer.lock` pulls it in.)

---

## 5. Frontend build

New npm dependency (`dompurify`) is already pinned in `package-lock.json` — `npm ci` on the local build machine will install it. **Don't** run npm on the SiteGround host; it lacks memory.

### Dev (csjones.co/fynla) — full CMS on

```bash
./deploy/csjones-fynla/build.sh
```

This sets `VITE_INSIGHTS_CMS_ENABLED=true`, enabling the `/insights/:slug` catch-all, the DB-backed hub, and the DB-backed landing hero.

### Production (fynla.org) — CMS flag OFF initially

```bash
./deploy/fynla-org/build.sh
```

This sets `VITE_INSIGHTS_CMS_ENABLED=false`. The build:
- does not register the `/insights/:slug` catch-all
- hub + landing fall back to their legacy arrays

Ship the backend first with the flag OFF, verify `/api/insights` and `/api/insights/featured` return seeded data, then flip the flag to `true` in `deploy/fynla-org/build.sh`, rebuild, and upload the new `public/build/`.

In both cases, upload `public/build/` to the host.

**`.htaccess`:** no changes — existing rules cover the new routes.

---

## 6. Cache clear (both hosts)

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

---

## 7. Smoke tests

1. `https://{host}/insights` loads the hub with 8 bespoke articles and category filter counts.
2. Click an existing bespoke article (e.g. `/insights/isa-guide-uk`) — original bespoke Vue page renders (NOT the generic block renderer).
3. `https://{host}/` — "Latest insights" section shows 2/3 featured + 1/3 stacked (if flag is on).
4. Log in as an admin, visit `/admin/insights` — list loads, 8 Bespoke badges visible.
5. Click "+ New article", pick "Blank", add a heading + paragraph block, Save draft, Publish.
6. Visit the new article's public URL — heading + paragraph render through `ArticleBlockRenderer`.
7. `curl -s https://{host}/insights/{new-slug} | grep '<title>'` — the FIRST `<title>` is the article's, confirming `InsightsSeoMetaInjector` ran.
8. `php artisan tinker --execute="echo \App\Models\Insights\InsightArticleRevision::count();"` — each save/publish wrote a revision.

---

## 8. Scheduler

The `PublishScheduledInsightsJob` runs every 5 minutes via the existing `schedule:run` cron. No extra setup.

Verify after deploy:
```bash
php artisan schedule:list | grep PublishScheduled
```

---

## 9. Storage symlink

Image uploads land in `storage/app/public/insights/{slug}/`. `public/storage` must symlink to `storage/app/public/`:

```bash
ls -la public/storage   # should show → storage/app/public
```

If missing: `php artisan storage:link`.

---

## 10. Enabling the CMS on production

Once steps 1-9 are green on fynla.org with `VITE_INSIGHTS_CMS_ENABLED=false`:

1. Edit `deploy/fynla-org/build.sh`:
   ```bash
   export VITE_INSIGHTS_CMS_ENABLED=true
   ```
2. Rebuild: `./deploy/fynla-org/build.sh`
3. Upload `public/build/` only (no PHP changes needed).
4. Clear caches.
5. Re-run smoke tests 3 + 5–7.

To disable in an emergency, reverse steps 1-4 with the flag set to `false`.

---

## 11. Rollback

Frontend only:
```bash
# Flip flag to false, rebuild, upload public/build/, cache clear.
```

Everything:
```bash
php artisan migrate:rollback --step=3
```
Drops the 3 new tables. Any CMS-authored articles are lost; the 8 bespoke articles continue to render via their Vue files (the DB rows exist only as metadata). Also remove the new PHP files, restore the previous `routes/api.php`, `routes/web.php`, `app/Http/Kernel.php`, `app/Http/Middleware/SanitizeInput.php`, and `resources/views/app.blade.php` from git.

---

## 12. Known quirks

- **Empty image block saves fail validation.** BlockValidator rejects image blocks with no `path`/`alt`. Admin workflow: add image → upload first → save. A future nicer UX could defer image validation until publish.
- **Bespoke article routes.** `/insights/:slug` catch-all is declared AFTER the 8 named bespoke routes in `router/index.js`; reordering will silently regress to the generic renderer. Architecture test `InsightsArchitectureTest` enforces this.
- **Laravel `routes/web.php` ordering matters.** `/insights/{slug}` (middleware route) must come BEFORE the `/{any}` SPA catch-all or `InsightsSeoMetaInjector` never fires. Same architecture test covers this.
