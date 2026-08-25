# Admin Insights CMS — Deploy Guide

**Date:** 18 April 2026
**Branch:** `feature/csj/insights-cms`
**Target flow:** feature → `dev` (csjones.co/fynla) → `main` (fynla.org)
**Scope:** initial CMS rollout PLUS today's session 60 follow-ups (dashboard redesign, category expansion, summary/hero/image-block UX, preview auth fix).

This supersedes `April17Updates/deployCms.md`.

---

## 1. What ships

Three new MySQL tables, an admin CMS under `/admin/insights/*`, a DB-driven public `/insights/:slug` renderer declared AFTER the 8 existing bespoke Vue routes, a refreshed 2/3 + 1/3 landing-page hero, server-side per-article SEO meta via `@stack('head')`, and a feature flag (`VITE_INSIGHTS_CMS_ENABLED`) so production can ship backend-first.

Today's session 60 additions on top of that baseline:

- **Admin dashboard redesign** — two sections (Users, AI), 6 clickable summary cards, deltas since previous admin login (via `user_sessions`), tab navigation through `AdminPanel`. Layout fits within the page (right padding clears the floating Fyn chat button).
- **Categories expanded** — added `ai`, `fintech`, `developer`, `financial-planning`, `international`; renamed `tax-changes` → `tax`. 10 categories total. Migration widens then narrows the enum and rewrites legacy rows.
- **CMS rename** — AdminPanel sidebar tab "Insights" → "CMS"; admin article list heading "Content Management System".
- **Summary UX** — no longer rendered in the article footer; helper copy under the Summary textarea explains it only shows on hub/landing cards.
- **Image block UX** — inline helper copy for upload formats, alt-text purpose, caption behaviour and alignment semantics. Block-level validation errors now surface in the save-fail alert. `BlockValidator` rejects empty/whitespace `path` or `alt`.
- **Public article layout** — hero image is full-width with title + subtitle overlaid bottom-left (gradient for legibility, category chip top-right). Falls back to plain header when no hero image. Floated images are contained by `flow-root`; wrapping paragraph top aligns with the image top.
- **Preview auth** — `InsightController::show` resolves the admin via the Sanctum guard so `?preview=true` actually returns drafts for logged-in admins. Unauthenticated requests still 404 on drafts.
- **Featured endpoint** — `/api/insights/featured` no longer falls back to the most-recently-published article. Returns `featured: null` unless an article has been explicitly flagged `is_featured=true`; supporting list still populates from the two most-recent published articles.
- **Multi-author byline** — new nullable `authors` JSON column on `insight_articles` (allow-listed to Brett Isenberg, Azlan Raj, Chris Slater-Jones). Admin editor adds a checkbox group under Tags; public article renders "By X", "By X and Y", or "By X, Y and Z" inline with the publish date.
- **Rich-text toolbar** — new Tiptap-based `RichTextEditor.vue` shared across heading, paragraph, list, pull quote, callout, and key takeaway editors. Exposes bold, italic, underline, inline link, preset font size (Small/Normal/Large) and preset text colour (raspberry, horizon, spring, violet, neutral). Formatting is emitted as Tailwind classes on a span allow-list — no inline styles, no arbitrary fonts/sizes/colours — so the design system stays locked down. Backend sanitiser scrubs `<span>` attributes to the same allow-list; frontend DOMPurify mirrors it (defence in depth).
- **Block chrome stripped** — pull quote, callout, and key takeaways no longer render with tinted backgrounds, left accent borders, or rounded card corners. Typography and callout variant icon tints stay. Blocks flow as plain inline content.
- **CTA button + related articles** — intentionally excluded from the rich-text toolbar (they're labels/pickers, not text-content blocks).

---

## 2. Order of operations (both environments)

```
1. Upload all PHP + Blade + config files (§4)
2. composer install --no-dev --optimize-autoloader       (intervention/image)
3. php artisan migrate --force                           (5 migrations)
4. php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
5. php artisan storage:link                              (symlink for uploaded images)
6. Build frontend locally (§5) and upload public/build/
7. Clear caches (§6)
8. Smoke tests (§7)
```

If the symlink already exists the command is a no-op. Without it, `/storage/insights/...` URLs return the SPA HTML and image previews silently fail.

---

## 3. Database migrations

Run in timestamp order (Laravel enforces):

```
database/migrations/2026_04_17_090001_create_insight_templates_table.php
database/migrations/2026_04_17_090002_create_insight_articles_table.php
database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php
database/migrations/2026_04_18_090000_expand_insight_article_categories.php
database/migrations/2026_04_18_100000_add_authors_to_insight_articles_table.php
```

The authors migration is additive (nullable JSON column guarded by `Schema::hasColumn`), safe to re-run. Validation keeps the field restricted to the three allow-listed names.

The category-expansion migration widens the `insight_articles.category` enum to include both old and new values, rewrites any row with `category='tax-changes'` → `'tax'`, then tightens the enum to the final set:

```
tax, pensions, savings-isa, estate-planning,
financial-planning, ai, fintech, developer, international, platform-updates
```

`ExistingInsightsMetadataSeeder` is idempotent — safe to re-run. After step 4, verify:

```bash
php artisan tinker --execute="echo \App\Models\Insights\InsightArticle::where('is_bespoke',true)->count();"
# expected: 8
```

---

## 4. Files to upload

86 PHP/Blade/JS-config files changed (plus two new ones: `RichTextEditor.vue` and `insightsSanitize.js`). Upload the directories in bulk or as listed below. Paths are repo-relative.

### 4a. Framework + bootstrap

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
package.json
package-lock.json
tailwind.config.js
```

### 4b. Insights domain (backend)

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
```

### 4c. Other touched backend files (admin dashboard)

```
app/Http/Controllers/Api/AdminController.php
```

### 4d. Database

```
database/migrations/2026_04_17_090001_create_insight_templates_table.php
database/migrations/2026_04_17_090002_create_insight_articles_table.php
database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php
database/migrations/2026_04_18_090000_expand_insight_article_categories.php
database/migrations/2026_04_18_100000_add_authors_to_insight_articles_table.php
database/factories/Insights/InsightArticleFactory.php
database/factories/Insights/InsightArticleRevisionFactory.php
database/factories/Insights/InsightTemplateFactory.php
database/seeders/DatabaseSeeder.php
database/seeders/ExistingInsightsMetadataSeeder.php
database/seeders/ChrisUserSeeder.php
```

### 4e. Frontend — admin CMS

```
resources/js/views/Admin/AdminPanel.vue
resources/js/views/Admin/Insights/ArticleEditor.vue
resources/js/views/Admin/Insights/ArticleListPage.vue
resources/js/views/Admin/Insights/TemplateListPage.vue
resources/js/components/Admin/AdminDashboard.vue
resources/js/components/Admin/Insights/BespokeArticleNotice.vue
resources/js/components/Admin/Insights/BlockPickerModal.vue
resources/js/components/Admin/Insights/RichTextEditor.vue
resources/js/components/Admin/Insights/blocks/EditCalloutBlock.vue
resources/js/components/Admin/Insights/blocks/EditCtaButtonBlock.vue
resources/js/components/Admin/Insights/blocks/EditDividerBlock.vue
resources/js/components/Admin/Insights/blocks/EditHeadingBlock.vue
resources/js/components/Admin/Insights/blocks/EditImageBlock.vue
resources/js/components/Admin/Insights/blocks/EditKeyTakeawaysBlock.vue
resources/js/components/Admin/Insights/blocks/EditListBlock.vue
resources/js/components/Admin/Insights/blocks/EditParagraphBlock.vue
resources/js/components/Admin/Insights/blocks/EditPullQuoteBlock.vue
resources/js/components/Admin/Insights/blocks/EditRelatedArticlesBlock.vue
resources/js/components/Admin/Insights/blocks/EditTaxYearStatBlock.vue
```

### 4f. Frontend — public insights

```
resources/js/views/Public/insights/InsightArticlePage.vue
resources/js/views/Public/insights/InsightsHubPage.vue
resources/js/views/Public/LandingPage.vue
resources/js/components/Insights/ArticleBlockRenderer.vue
resources/js/components/Insights/blocks/CalloutBlock.vue
resources/js/components/Insights/blocks/CtaButtonBlock.vue
resources/js/components/Insights/blocks/DividerBlock.vue
resources/js/components/Insights/blocks/HeadingBlock.vue
resources/js/components/Insights/blocks/ImageBlock.vue
resources/js/components/Insights/blocks/KeyTakeawaysBlock.vue
resources/js/components/Insights/blocks/ListBlock.vue
resources/js/components/Insights/blocks/ParagraphBlock.vue
resources/js/components/Insights/blocks/PullQuoteBlock.vue
resources/js/components/Insights/blocks/RelatedArticlesBlock.vue
resources/js/components/Insights/blocks/TaxYearStatBlock.vue
```

### 4g. Frontend — store / services / router

```
resources/js/router/index.js
resources/js/services/insightsService.js
resources/js/store/index.js
resources/js/store/modules/insights.js
resources/js/utils/insightsSanitize.js
```

### 4h. Deploy scripts (feature-flag injection)

```
deploy/csjones-fynla/build.sh
deploy/fynla-org/build.sh
```

---

## 5. Frontend build

New composer dep: `intervention/image ^4.0`. New npm deps: `dompurify ^3.4`, `@tiptap/core`, `@tiptap/vue-3`, `@tiptap/starter-kit`, `@tiptap/extension-underline`, `@tiptap/extension-link` (all `^3.22`). All pinned in `composer.lock` / `package-lock.json` — `composer install` and the local `npm ci` via `deploy/{env}/build.sh` pick them up.

### Dev (csjones.co/fynla) — CMS on

```bash
./deploy/csjones-fynla/build.sh
```

Sets `VITE_INSIGHTS_CMS_ENABLED=true`. The `/insights/:slug` catch-all is registered; hub and landing read from the DB.

### Production (fynla.org)

```bash
./deploy/fynla-org/build.sh
```

Ship this with `VITE_INSIGHTS_CMS_ENABLED=true`. The landing's `Latest insights` section was refactored to be DB-only — a flag=false deploy leaves that section blank. If you *must* stage flag=false first (e.g. during emergency rollback), accept that the landing insights block is hidden until the flag flips.

Before you build: confirm the line in `deploy/fynla-org/build.sh` reads `export VITE_INSIGHTS_CMS_ENABLED=true`.

Upload `public/build/` to the host. `.htaccess` needs no changes — existing rewrites cover the new routes.

---

## 6. Cache clear + finalise (both hosts)

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
php artisan storage:link      # non-destructive; safe if already linked
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### Hero images for bespoke articles

The seeder now self-heals: on first run it copies the bundled bespoke hero images from `resources/js/assets/insights/*.jpg` into `storage/app/public/insights/bespoke/`, then sets each article's `hero_image_*_path` to that location. Idempotent — existing files in the target are not overwritten, so a CMS image upload that replaces a bespoke hero survives re-seeds.

If `public/storage` is missing, the JPGs won't be web-reachable — run `php artisan storage:link` first.

For DB-authored (non-bespoke) articles, upload hero images via the admin CMS (`/admin/insights/:id/edit` → Hero image). That route uses `InsightImageService` which produces WebP card + thumb renditions at `storage/app/public/insights/<slug>/`.

### Featured slot

`/api/insights/featured` returns `featured: null` when no article is explicitly flagged — the landing's Featured card hides in that case. After first deploy, pick one article in the admin (`/admin/insights` → Feature button) so the landing hero populates. Only one article can be featured at a time.

---

## 7. Smoke tests

Run against the deployed host URL.

### Public

1. `/insights` — hub loads with category chips `All / Tax / Pensions / Savings & ISA / Estate planning / Financial planning / Artificial intelligence / Fintech / Developer / International / Platform updates`; counts match DB.
2. `/insights/isa-guide-uk` — bespoke Vue page renders (NOT the generic block renderer). Same for the other 7 named bespoke slugs.
3. `/insights/<any-db-slug>` — full article page renders. Hero image (if uploaded) is full-width with title + subtitle overlaid bottom-left and the category chip top-right. No hero → plain header with category chip inline right of the title.
4. Summary text **does not** appear as a trailing footer inside the article.
5. Floated image + next paragraph: paragraph's first line top aligns with the image top; no bleed into footer.
6. Landing page (`/`) hero shows the new 2/3 + 1/3 bento layout. Before flipping the flag on prod, verify `curl -s https://<host>/api/insights/featured` returns `data.featured = null` (or the one article you've explicitly flagged) — it no longer auto-promotes the latest published.
7. View-source confirms per-article `<title>`, `<meta name="description">`, `og:`, `twitter:`, and JSON-LD appear **before** the static Fynla `<head>` fallback tags.

### Admin (chris@fynla.org)

1. `/admin` — new dashboard shows 6 summary cards (Users / AI), each clickable and navigating to the relevant tab (User Management, User Metrics, AI Audit). Delta indicators render where `user_sessions` has >1 row for the admin.
2. `/admin/insights` — sidebar tab is labelled **CMS**; page heading **Content Management System**; article list shows 8 bespoke + any authored rows.
3. New article flow: pick template → blank → fill Title/Subtitle/Summary/Category/Tags → tick one or more Authors (Brett Isenberg / Azlan Raj / Chris Slater-Jones) → Add block → image upload → save.
   - Image block helper copy visible (formats, alt purpose, alignment hint).
   - Saving with an empty alt shows the specific error `block N: image alt is required`.
   - Public article byline reads `By <names> · <date>` (1 / 2 / 3 authors formatted as `X`, `X and Y`, `X, Y and Z`); date-only when no authors selected.
   - Rich-text toolbar appears on heading, paragraph, list item, pull quote, callout, and key takeaway editors. Applying bold/italic/underline/link round-trips through save → reload. Font-size and text-colour swatches emit Tailwind classes (`text-sm`, `text-lg`, `text-raspberry-500` etc.) — never inline styles. CTA button and related articles remain plain.
4. Image upload preview: file uploads, `<img>` renders in the block (confirms `storage:link` is in place).
5. `Preview` button: opens `/insights/<slug>?preview=true` in a new tab; logged-in admin sees the draft.
6. Editing a bespoke slug: canvas shows `BespokeArticleNotice`; metadata fields still editable.
7. Save as template → `/admin/insights/templates` lists it → rename/delete work.

---

## 8. Rollback

If the API misbehaves in production after the backend deploy:

- Set `VITE_INSIGHTS_CMS_ENABLED=false` in `deploy/fynla-org/build.sh`, rebuild, reupload `public/build/`. The frontend reverts to legacy arrays; the admin CMS at `/admin/insights` still works for authors.
- The tables and routes are additive; no data teardown is required.
- Image uploads live at `storage/app/public/insights/<slug>/` — keep them; they're referenced by `body_blocks.path` and article `hero_image_*_path`.

If the category migration needs reverting, `php artisan migrate:rollback --step=1` runs the `down()` which re-widens the enum, rewrites `tax` → `tax-changes`, demotes any `financial-planning / ai / fintech / developer / international` rows to `platform-updates`, then narrows the enum back to the pre-CMS set. Run in production only if you have a specific reason — there is no risk to existing data on forward migration.

---

## 9. Post-deploy follow-ups (carry-overs)

- Flip `VITE_INSIGHTS_CMS_ENABLED` to `true` in `deploy/fynla-org/build.sh` and do a frontend-only re-upload once smoke tests on the backend API are clean.
- `public/storage` symlink exists on each host (dev + prod) — verify once; it was missing in local dev, which would fail silently on production too.
- Monitor `storage/logs/laravel.log` for 10–15 min after flag flip.
- Empty-image-block UX: admins now see an explicit validation error on save, plus inline helper copy. If we still get reports of "image not attached" after deploy, check the storage symlink first.
