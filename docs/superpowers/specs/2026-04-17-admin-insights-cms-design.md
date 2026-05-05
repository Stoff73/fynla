# Admin Insights CMS — Design Spec

**Date:** 17 April 2026
**Status:** Amended — 17 April 2026 — conflicts resolved against codebase audit
**Scope:** An admin-facing CMS for authoring, editing, publishing, and featuring insight articles on fynla.org. The 8 existing hardcoded insight articles remain rendered by their bespoke Vue components; new articles are stored in the database and rendered through a generic block-based template. The insights hub page and the landing page's "Latest insights" section both become database-driven.

---

## Overview

Today the insights section of fynla.org is entirely hardcoded. The 8 articles each live in their own Vue component under `resources/js/views/Public/insights/`, with bespoke layouts and SEO metadata. The `InsightsHubPage.vue` hub page keeps a hand-maintained array of all 8 articles' metadata. The `LandingPage.vue` keeps a *separate* hand-maintained array of the 3 most recent articles for its "Latest insights" section. Every new article means new code, a build, and a deploy — and the two hardcoded arrays can drift out of sync.

This spec introduces a CMS that lets an admin upload an article through a form, pick a block-based template, add images, fill in structured content blocks, preview it, and publish it. Publishing writes the article to the database and makes it immediately visible on the hub page and the landing page's featured hero. No code deploy required for a new article.

The 8 existing articles stay exactly as they are — they have bespoke layouts and SEO meta tags that a generic template would visibly downgrade. Their metadata is migrated into the database so the hub page, landing hero, and admin list can treat them as first-class citizens. Their body content is not editable through the CMS; the admin UI displays a note pointing to the Vue file.

---

## Goals and non-goals

**Goals:**
- Admin can publish a new article in under 10 minutes without engineering help.
- Insights hub page and landing page's "Latest insights" section both render from one database source of truth — no more drift.
- Existing 8 bespoke articles keep their layouts and SEO meta tags, unchanged.
- Articles have structured content (not raw HTML or markdown) so rendering is fully controlled by the Fynla design system.
- Tax-year-sensitive article content (allowances, thresholds) stays accurate across tax-year rollovers through a `TaxYearStat` block that pulls from `TaxConfigService`.
- Scheduled publishing — admin writes on Sunday, article goes live at 9am Monday automatically.

**Non-goals:**
- No public comment system.
- No multi-author collaboration workflow (review queues, assignments, approvals). One admin owns an article end to end.
- No A/B testing of article variants.
- No automated content generation or AI-written copy.
- No migration of bespoke article bodies into the CMS — only their metadata.
- No WYSIWYG-style rich-text editing within paragraphs beyond bold, italic, and links.

---

## User flow

### Admin creates a new article

1. Admin logs in, navigates to `/admin/insights`.
2. Clicks **New article**. A template picker appears: "Blank" or any saved template (e.g. "Standard guide", "Tax-change announcement").
3. Admin picks a template. The editor opens with the template's blocks pre-populated.
4. Admin fills in the field panel on the left: title, subtitle, summary, category, tags, hero image (upload). SEO overrides are hidden in a collapsed "Search & sharing" section with sensible defaults.
5. Admin edits the blocks in the canvas on the right — drags to reorder, clicks "+ Add block" to insert a new one at a position, edits each block inline.
6. Admin clicks **Preview** — opens `/insights/:slug?preview=true` in a new tab, rendered with the current state. Only the admin's session can see preview content.
7. Admin clicks **Save draft** periodically. Each save creates a revision record.
8. When ready, admin clicks **Publish**. A confirmation shows the article's URL and featured status. The article is now live.
9. If the article is flagged as **Featured**, any previously featured article is automatically unfeatured. A confirmation shows which article was displaced.

### Admin schedules an article

Same as above through step 6. Instead of **Publish**, admin clicks the dropdown beside it and picks **Schedule…**. A date-time picker appears. On save, the article stays in draft but with `scheduled_at` set. A cron job runs every 5 minutes and promotes scheduled articles whose time has passed.

### Admin saves a template

Admin builds an article, gets the layout and block structure right, then clicks **Save as template** in the editor toolbar. A modal asks for a name and optional description. On save, the blocks (not the content, but the structure and any placeholder text) are saved to `insight_templates`. The article keeps its `template_id` reference.

### Admin edits a bespoke article's metadata

The 9 pre-existing articles appear in the admin list with a small "Bespoke" badge. Clicking one opens the editor with the field panel visible but the block canvas replaced by a notice:

> This article is rendered by a bespoke Vue component (`StocksSharesIsaUkPage.vue`). To edit the article's content, update the component file directly. You can still edit the title, summary, tags, hero image, and featured status here.

The admin can update metadata and click Save. The bespoke Vue component stays untouched.

### Visitor reads an article

1. Visitor lands on `https://fynla.org/insights/my-new-article`.
2. Vue Router matches the `/insights/:slug` catch-all (declared after the 9 named routes, so existing articles take precedence).
3. `InsightArticlePage.vue` fetches the article by slug from `GET /api/insights/my-new-article`.
4. If `is_bespoke` is true on the fetched article, the frontend returns a 404 — the route should have been caught by a named route. This is a safety net; not a normal flow.
5. Otherwise, `InsightArticlePage.vue` renders the title, subtitle, summary, hero image, and delegates `body_blocks` to `ArticleBlockRenderer.vue`.
6. `ArticleBlockRenderer.vue` iterates the blocks and delegates each to its type-specific component.
7. Server-rendered SEO meta tags (injected by a Laravel middleware) are already in the page `<head>` so crawlers and social-share previews work without JavaScript.

### Visitor sees the landing page

`LandingPage.vue` replaces its hardcoded `latestInsights` array and "Latest insights" section with a bento-style layout matching `InsightsHubPage.vue`: one large featured tile on the left (2/3 width) and two smaller tiles stacked on the right (1/3 width). Data comes from `GET /api/insights/featured`, which returns the `is_featured` article as the first entry (or the most recent published article as a fallback) and the two next most recent published articles.

---

## Architecture

### Data model

Three new tables.

#### `insight_articles`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `slug` | string, unique | URL path segment. Auto-generated from title, editable. |
| `title` | string | Article h1. |
| `subtitle` | string, nullable | Short tagline shown under the h1. |
| `summary` | text | 1-2 sentences. Shown in hub/landing cards; default for meta description. |
| `category` | enum | `tax-changes`, `pensions`, `savings-isa`, `estate-planning`, `platform-updates`. Matches existing `InsightsHubPage.vue` filter categories. |
| `tags` | json | Array of strings. |
| `hero_image_path` | string, nullable | Full-size image (original format). |
| `hero_image_card_path` | string, nullable | 800×450 WebP for hub/landing cards. |
| `hero_image_thumb_path` | string, nullable | 200×200 WebP for admin lists. |
| `body_blocks` | json | Array of block objects. Ignored when `is_bespoke = true`. |
| `template_id` | bigint, nullable, FK → `insight_templates.id` | Which template was used. Kept for re-sync and filtering. |
| `status` | enum | `draft`, `published`, `archived`. |
| `is_featured` | boolean, default false | Drives the landing page hero's big tile. At most one article may have this true at a time (enforced in the service layer, not by DB constraint). |
| `is_bespoke` | boolean, default false | Set true for the 9 pre-existing hardcoded articles. Hides the block editor and body rendering; all rendering routes to `bespoke_component`. |
| `bespoke_component` | string, nullable | Vue component name (e.g. `StocksSharesIsaUkPage`). Only set when `is_bespoke = true`. |
| `published_at` | timestamp, nullable | Used for sort order and the displayed date. Set when `status` transitions to `published`. |
| `scheduled_at` | timestamp, nullable | Future timestamp for scheduled publishing. |
| `author_id` | bigint, FK → `users.id` | The admin who created the article. |
| `meta_title` | string, nullable | SEO override for `<title>`. Falls back to `title`. |
| `meta_description` | string, nullable | SEO override. Falls back to `summary`. |
| `canonical_url` | string, nullable | For syndicated content. |
| `created_at`, `updated_at`, `deleted_at` | timestamps | Soft deletes. |

Indexes: `slug` (unique), `status`, `is_featured`, `published_at`, `category`.

#### `insight_templates`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `name` | string, unique | Human-readable. Shown in the picker. |
| `description` | string, nullable | Optional context shown in the picker. |
| `body_blocks` | json | The block structure, including any placeholder text. |
| `created_by` | bigint, FK → `users.id` | The admin who created it. |
| `created_at`, `updated_at` | timestamps | |

#### `insight_article_revisions`

Append-only history. Every save writes a row.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK | |
| `article_id` | bigint, FK → `insight_articles.id` | |
| `title` | string | |
| `subtitle` | string, nullable | |
| `summary` | text | |
| `body_blocks` | json | |
| `saved_by` | bigint, FK → `users.id` | |
| `saved_at` | timestamp | |

Indexes: `article_id`, `saved_at`.

No change to `users` table — uses the existing `is_admin` flag for authorisation.

### Block schema

Each block is a JSON object with a `type` field and type-specific properties. The `body_blocks` column is an array of these. The block renderer dispatches on `type`.

| Type | Shape |
|------|-------|
| `heading` | `{ type: 'heading', level: 2\|3\|4, text: string }` |
| `paragraph` | `{ type: 'paragraph', html: string }` — html is a restricted subset allowing only `<strong>`, `<em>`, `<a href>`. Sanitised on save. |
| `list` | `{ type: 'list', ordered: boolean, items: string[] }` |
| `image` | `{ type: 'image', path: string, alt: string, caption?: string, alignment: 'full'\|'left'\|'right' }` |
| `pull_quote` | `{ type: 'pull_quote', text: string, attribution?: string }` |
| `callout` | `{ type: 'callout', variant: 'info'\|'tip'\|'warning'\|'success', html: string }` — colours map to `horizon` (info), `spring` (tip/success), `violet` (warning). |
| `divider` | `{ type: 'divider' }` |
| `cta_button` | `{ type: 'cta_button', label: string, href: string, style: 'primary'\|'secondary' }` |
| `tax_year_stat` | `{ type: 'tax_year_stat', stat_key: string, label: string }` — `stat_key` is a symbolic reference (e.g. `isa_annual_allowance`, `iht_nil_rate_band`). Rendered values come live from `TaxConfigService` (backend) or `@/constants/taxConfig` (frontend). |
| `related_articles` | `{ type: 'related_articles', article_ids: number[] }` — admin picks 2-4 other articles; renders as inline cards. |
| `key_takeaways` | `{ type: 'key_takeaways', bullets: string[] }` — highlighted box at the top of the article. |

Validation on save: `StoreInsightArticleRequest` and `UpdateInsightArticleRequest` use a JSON schema validator to reject malformed blocks. The service layer additionally validates that referenced `article_ids` and `stat_key`s exist.

### Backend

All files under `app/`.

**Models** (`app/Models/Insights/`):
- `InsightArticle` — uses `Auditable` trait, `SoftDeletes`, casts `tags`, `body_blocks` to array, `is_featured`, `is_bespoke` to boolean, `published_at`, `scheduled_at` to datetime.
- `InsightTemplate` — casts `body_blocks` to array.
- `InsightArticleRevision` — append-only; no update or delete operations allowed through the model.

**Services** (`app/Services/Insights/`):
- `InsightArticleService` — CRUD, `publish()`, `archive()`, `unarchive()`, `setFeatured()` (auto-unfeatures the previous featured article), `restoreFromRevision()`, `resyncFromTemplate()` (replaces `body_blocks` with the current template's, warns caller of any overrides lost).
- `InsightTemplateService` — list, `saveFromArticle()`, rename, delete.
- `InsightImageService` — accepts an uploaded file, writes the original to `storage/app/public/insights/{slug}/{timestamp}-{filename}.{ext}`, generates a 800×450 card WebP and a 200×200 thumb WebP using Intervention Image, returns the three paths.
- `InsightSeoService` — given an `InsightArticle`, returns an array of `<meta>` tags and a JSON-LD Schema.org `Article` structured data payload.

**Controllers:**
- `app/Http/Controllers/Api/Admin/InsightArticleController.php` — admin CRUD under `/api/admin/insights/articles`. Routes: `index`, `store`, `show`, `update`, `destroy`, `publish`, `archive`, `setFeatured`, `resyncFromTemplate`, `revisions` (list), `restoreRevision`.
- `app/Http/Controllers/Api/Admin/InsightTemplateController.php` — `/api/admin/insights/templates`. Routes: `index`, `store` (save from article), `update` (rename), `destroy`.
- `app/Http/Controllers/Api/Admin/InsightImageController.php` — `/api/admin/insights/images`. Single endpoint: `store` (accepts multipart upload).
- `app/Http/Controllers/Api/Public/InsightController.php` — `/api/insights`. Routes: `index` (list published articles with pagination and category filter), `featured` (returns the featured article + 2 next most recent), `show` by slug.

**Form requests** (`app/Http/Requests/Admin/Insights/`): `StoreInsightArticleRequest`, `UpdateInsightArticleRequest`, `StoreInsightTemplateRequest`, `UploadInsightImageRequest`.

**Resources** (`app/Http/Resources/Insights/`): `InsightArticleResource`, `InsightArticleListResource` (lighter, for hub/landing lists), `InsightTemplateResource`.

**Observers** (`app/Observers/`):
- `InsightArticleObserver` — `created`, `updated` → write to `insight_article_revisions`; `saved`, `deleted` → bust featured/list caches via generation counter.

**Jobs** (`app/Jobs/`):
- `PublishScheduledInsightsJob` — runs every 5 minutes via the existing cron (`app/Console/Kernel.php`). Finds articles with `status = draft` and `scheduled_at <= now()`, promotes them to `published`.

**Middleware** (`app/Http/Middleware/`):
- `InsightsSeoMetaInjector` — applies to the `/insights/{slug}` route. Looks up the article by slug, calls `InsightSeoService`, and passes the meta tags and JSON-LD to the `app.blade.php` view so they render server-side. For bespoke articles, this middleware skips (existing bespoke Vue components already render their own meta tags via client-side `useHead`-style logic or hardcoded `<head>` content).

**Sitemap:** Sitemap integration is deferred to a follow-up task — no existing `SitemapController` to extend, and insight articles will be indexable via internal links from the hub and landing page in the meantime.

### Frontend

All files under `resources/js/`.

**Public components:**
- `views/Public/insights/InsightArticlePage.vue` — fetches the article by slug from the public API. Renders title, subtitle, hero image, and delegates `body_blocks` to `ArticleBlockRenderer`. Shows a 404 if the article is not found or is bespoke (named routes should have caught bespoke articles).
- `components/Insights/ArticleBlockRenderer.vue` — iterates the blocks array and delegates each to its type-specific component via `<component :is>`.
- `components/Insights/blocks/` — one component per block type:
  - `HeadingBlock.vue`
  - `ParagraphBlock.vue` — renders sanitised HTML via `v-html` after a frontend-side second sanitise pass (defence in depth).
  - `ListBlock.vue`
  - `ImageBlock.vue`
  - `PullQuoteBlock.vue`
  - `CalloutBlock.vue` — maps variant to design-system colours: info → horizon, tip/success → spring, warning → violet.
  - `DividerBlock.vue`
  - `CtaButtonBlock.vue`
  - `TaxYearStatBlock.vue` — reads from `@/constants/taxConfig` for mirrored values; falls back to a fetch if the stat isn't mirrored frontend-side.
  - `RelatedArticlesBlock.vue` — fetches 2-4 articles by id, renders as inline cards.
  - `KeyTakeawaysBlock.vue` — styled as a highlighted box at the top of an article.

**Public page updates:**
- `views/Public/insights/InsightsHubPage.vue` — removes the hardcoded `articles` array. `data()` becomes `{ articles: [], loading: true }`. `mounted()` dispatches `insights/fetchList` from a new Vuex module. Bento layout, category filter, and card rendering stay as-is (just iterate the fetched array instead of the hardcoded one).
- `views/Public/LandingPage.vue` — removes the hardcoded `latestInsights` array. The "Latest insights" section is restructured from 3 equal cards to a 2/3 + 1/3 bento layout matching the hub page. Fetches from `insights/fetchFeatured`.

**Admin components** (new `views/Admin/Insights/` directory):
- `ArticleListPage.vue` — table of all articles with filters (status, category, featured). Columns: title, category, status, featured, published date, author, actions (Edit, View, Archive, Delete).
- `ArticleEditor.vue` — split layout. Left panel (~35%): field form with title, subtitle, summary, category dropdown, tags input, hero image uploader, collapsed "Search & sharing" section for SEO overrides. Right panel (~65%): block canvas with drag-reorder, per-block inline controls, "+ Add block" button. Top toolbar: status pill, Save draft, Publish dropdown (Publish now / Schedule…), Preview, Save as template.
- `BlockPickerModal.vue` — modal showing the 11 block types as tiles with tiny previews. Selecting one inserts it at the target position in the canvas.
- `BespokeArticleNotice.vue` — shown in the editor when `is_bespoke = true`. Replaces the block canvas with the explanation text.
- `TemplateListPage.vue` — table of saved templates with rename and delete actions.
- Per-block edit components under `components/Admin/Insights/blocks/` — one per block type, each providing the inline edit interface used in the canvas.

**Router additions** (`resources/js/router/index.js`):
- Public catch-all: `{ path: '/insights/:slug', name: 'InsightArticle', component: InsightArticlePage, meta: { public: true } }` — declared **after** the 9 named insight routes so named routes take precedence.
- Admin routes under `/admin`:
  - `/admin/insights` → `ArticleListPage`
  - `/admin/insights/new` → `ArticleEditor`
  - `/admin/insights/:id/edit` → `ArticleEditor`
  - `/admin/insights/templates` → `TemplateListPage`
- All admin routes have `meta: { requiresAuth: true, requiresAdmin: true }`.

**Vuex module** (`resources/js/store/modules/insights.js`):
- State: `articles`, `featured`, `currentArticle`, `templates`, `loading`, `error`.
- Actions: `fetchList`, `fetchFeatured`, `fetchBySlug`, `fetchTemplates`, `fetchById` (admin), `create`, `update`, `publish`, `archive`, `setFeatured`, `uploadImage`, `saveAsTemplate`, `resyncFromTemplate`, `fetchRevisions`, `restoreRevision`.

**Service** (`resources/js/services/insightsService.js`): pure API wrapper following the existing pattern.

### Auth

The admin endpoints are gated by an existing `admin` middleware stack (checks `is_admin = true`). No new role system. The `PreviewWriteInterceptor` middleware already intercepts write operations from preview users; since admins are never preview users in practice, no new exclusion is needed. If a preview user somehow hits the admin URLs, the existing admin middleware returns 403.

All admin actions log to the existing `audit_logs` table via the `Auditable` trait on the models.

### Images

Upload flow:
1. Admin drops or selects a file in the hero image uploader or an image block.
2. Frontend `POST /api/admin/insights/images` with the file as multipart.
3. `InsightImageController::store` validates (max 10 MB, JPEG/PNG/WebP only) and hands off to `InsightImageService`.
4. `InsightImageService` writes the original, generates a 800×450 card WebP and a 200×200 thumb WebP using Intervention Image.
5. Returns `{ path, card_path, thumb_path }`.
6. Frontend stores the returned paths in the block JSON or article's hero image fields.

Files are served via Laravel's `public/storage` symlink. No CDN for v1 (fynla.org is behind SiteGround's caching already; add CDN later if needed).

### SEO

`InsightsSeoMetaInjector` middleware, applied to the `/insights/{slug}` route, server-renders meta tags into the Blade view so crawlers and social-share previews work without JS. For each article:

- `<title>` from `meta_title ?? title`
- `<meta name="description">` from `meta_description ?? summary`
- Open Graph: `og:title`, `og:description`, `og:image` (card path, prefixed with `APP_URL`), `og:type=article`, `og:url`
- Twitter Card: `summary_large_image` with the same data
- JSON-LD: Schema.org `Article` with `headline`, `datePublished`, `dateModified`, `author`, `image`, `publisher`

Bespoke articles skip this middleware — their own Vue components already handle SEO.

Sitemap: deferred. No existing `SitemapController` exists in Fynla today (only a static `public/sitemap.xml`). Adding Laravel-controlled sitemap generation is out of scope for this feature.

---

## Migration of existing 8 articles

A new seeder at `database/seeders/ExistingInsightsMetadataSeeder.php`.

Behaviour:
- For each of the 8 articles, `updateOrCreate` a row keyed by `slug`, setting:
  - `title`, `subtitle`, `summary`, `category`, `tags`, `hero_image_card_path` — from `InsightsHubPage.vue`'s existing hardcoded array.
  - `status = 'published'`.
  - `published_at` — the date string from the hardcoded array, parsed as the first day of the month where only month + year is present.
  - `is_bespoke = true`.
  - `bespoke_component` — the Vue component name (derived from the route component mapping in `router/index.js`).
  - `is_featured = false` (admin can feature one later if desired).
  - `body_blocks = []` (empty; never rendered for bespoke articles).
  - `author_id` — the admin who runs the seeder (defaults to user id 1 in dev, configurable in production).
  - `meta_title`, `meta_description`, `canonical_url` — null (bespoke components handle their own SEO).

The seeder is idempotent (`updateOrCreate` on slug) so it's safe to re-run after each deploy. Added to `DatabaseSeeder::run()` so `php artisan db:seed` runs it automatically.

After the seeder runs, `InsightsHubPage.vue` and `LandingPage.vue` are switched over to the Vuex-driven data. The hardcoded arrays in those two files are removed in the same commit.

---

## Testing

**Unit (Pest):**
- `InsightArticleServiceTest` — CRUD, publish transitions, feature toggle auto-unfeatures previous, re-sync-from-template warns of overrides, revisions written on save.
- `InsightImageServiceTest` — resize produces correct dimensions, WebP output is valid, unsupported format rejected.
- `InsightSeoServiceTest` — meta tag output, JSON-LD shape, falls back to defaults when overrides absent.
- `InsightTemplateServiceTest` — save from article copies blocks not content references.

**Feature (Pest):**
- `Admin\InsightArticleControllerTest` — non-admin returns 403, CRUD happy paths, validation errors, publish flow, archive flow.
- `Admin\InsightImageControllerTest` — upload happy path, file-size limit, format rejection.
- `Public\InsightControllerTest` — list returns only published, featured endpoint returns correct shape, show-by-slug returns 404 for drafts, returns the article for published, returns 404 for archived.
- `PublishScheduledInsightsJobTest` — promotes articles whose `scheduled_at` has passed, leaves future ones alone.
- `InsightsSeoMetaInjectorTest` — middleware injects correct tags, skips bespoke articles.

**Architecture (Pest):**
- All new services use constructor injection.
- All new models declare `$fillable`.
- No hardcoded tax values in any new file (the skill-wide rule).

---

## Design system compliance

All UI follows `fynlaDesignGuide.md` v1.4.0:
- Callout block colour variants map to `horizon-*` (info), `spring-*` (tip/success), `violet-*` (warning) — no amber or orange.
- CTA button block uses `raspberry-500` for primary, `horizon-500` border for secondary.
- Admin editor uses the standard card/form patterns (`.card`, standard form modal structure).
- Typography: Segoe UI → Inter fallback, weights 900 for h1/display, 700 for h2–h4.
- All user-facing text uses British spelling (Optimisation, Customise) and spells out acronyms except ISA.
- No scores anywhere in the UI.

Tax values in article content (in `TaxYearStat` blocks) come live from `TaxConfigService` — no hardcoded tax year strings or allowance amounts in the CMS code.

---

## Risks and considerations

- **Route precedence fragility.** The catch-all `/insights/:slug` must be declared after the 9 named routes in `router/index.js`. If someone re-orders them, bespoke articles render through the generic template and lose their layout. A comment in the router file and an architecture test can catch this.
- **Featured article mismatch across caches.** Setting `is_featured` must invalidate any cached response on the `/api/insights/featured` endpoint. Using Laravel's cache with a simple tag-based bust on `InsightArticleService::setFeatured()`.
- **Image storage growth.** Each article holds original + 2 WebP derivatives. At ~2 MB per article, 100 articles ≈ 200 MB. Not a problem in v1 but worth monitoring.
- **Preview mode.** `InsightArticlePage.vue` accepting `?preview=true` is admin-only. The public API endpoint for the slug must verify the requester is an admin before returning a draft; otherwise, guests could craft the query string and see unpublished content. The admin check uses Laravel's `auth()->user()->is_admin` — no new auth surface.
- **Sanitising paragraph HTML.** `body_blocks.paragraph.html` accepts a restricted HTML subset. Sanitisation on save uses PHP's `strip_tags($html, '<strong><em><a>')` plus a manual attribute allowlist (only `href`, `target`, `rel` on `<a>`). Rendering runs the same content through `DOMPurify` on the frontend. Defence in depth matters here because admin accounts can be compromised. Note: the global `SanitizeInput` middleware must be updated to add `body_blocks` (or the admin insights route prefix) to its `htmlAllowedFields` list, otherwise paragraph HTML is silently stripped before reaching form request validation.
- **`PreviewWriteInterceptor` ordering.** The interceptor runs *before* `auth:sanctum` (it resolves the user from the Bearer token itself). In practice preview users are never admins, so a preview user hitting an admin insights endpoint would receive a benign fake-success response from the interceptor before the admin permission check fires. No functional issue and no new `EXCLUDED_ROUTES` entry is required, but the reasoning differs from other admin routes where the admin middleware intercepts first.
- **Migrating the 8 bespoke articles' metadata accurately.** The seeder depends on the hardcoded array in `InsightsHubPage.vue` staying accurate until the seeder runs. Removing that array must happen in the same commit as the seeder run, otherwise the two will drift briefly.
- **Scheduled publish reliability.** The cron job runs every 5 minutes. An article scheduled for 09:00 may go live any time in the 09:00–09:05 window. Acceptable for this use case; if tighter timing is needed later, reduce the interval.

---

## Success criteria

- Admin can publish a new article in under 10 minutes (from `/admin/insights/new` to the article being visible at `/insights/:slug`).
- Insights hub page and landing page both render from the database. Removing an article from the database removes it from both immediately (after cache bust).
- The 8 existing articles render exactly as they do today — same layout, same SEO tags, same `<title>`, no visible regressions.
- All 11 block types render correctly and match the design system.
- Tax-year stat block values update automatically on tax-year rollover via `TaxConfigService`.
- Scheduled articles publish within 5 minutes of their target time.
- All new endpoints have Pest test coverage; feature tests pass.
- No hardcoded tax values anywhere in the new code (stop-hook scan passes).

---

## Open questions

None at spec-writing time — all decisions closed in the brainstorm. Any new questions that arise during planning should be returned here and resolved before implementation.
