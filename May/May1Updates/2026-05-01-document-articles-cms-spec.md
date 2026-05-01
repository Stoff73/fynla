---
title: Document Articles CMS — Design Spec
date: 2026-05-01
branch: CMSFix
status: spec-approved
authors: [CSJ, Claude Opus 4.7]
related:
  - April/April24Updates/spec/00-canonical.md
  - resources/js/views/Admin/AdminPanel.vue
  - app/Models/Insights/InsightArticle.php (existing CMS — NOT touched by this spec)
---

# Document Articles CMS — Design Spec

## Purpose

A new admin module that lets an administrator drag a `.docx` file into a drop zone and have a fully-formed article materialise — title, body, images, tables, headings, and SEO metadata all extracted automatically from the Word document. The article enters a `draft` state, can be edited in a WYSIWYG canvas alongside its metadata, and then published to a public route at `/articles/{slug}` rendered as a real, share-able, SEO-complete page.

This module is **completely isolated** from the existing Insights CMS at `/admin/insights`. New table, new controllers, new Vuex store, new public route. Nothing in the existing CMS is touched.

The admin sidebar entry is labelled **"Documents"** — neutral, treats this as a real product surface, not a test.

## Scope (in)

- New admin tab "Documents" at `/admin/documents`
- Drag-and-drop `.docx` import (single file at a time, max 10 MB)
- Browser-side conversion via `mammoth.js` (HTML + image blobs sent to backend)
- Server-side HTML sanitisation via `mews/purifier`
- Server-side metadata extraction from `docProps/core.xml` (defence-in-depth + verification)
- Edit screen: form (metadata, slug, author byline, cover image) + Tiptap WYSIWYG canvas (HTML body)
- Draft / published / archived workflow with signed-token preview URLs for drafts
- Public route `/articles/{slug}` — full SEO meta tags, OpenGraph, Twitter card, JSON-LD `Article` schema
- Pest unit + feature tests, Playwright browser test

## Scope (out — see Future Iterations)

YAGNI items the user has flagged for **future iterations** but explicitly excluded from v1.

## Architecture

```
[Admin browser]
    docx file
       ↓ (drag-drop)
    mammoth.js (browser)         ← extracts HTML + image Blobs + core.xml metadata
       ↓ multi-part POST
[Laravel: DocumentArticleController@store]
       ↓
    DocumentArticleImporter
       ├─ DocxMetadataExtractor   ← re-verifies core.xml server-side from raw docx
       ├─ HTMLPurifier             ← sanitises body
       └─ writes images to storage/app/public/document-articles/{id}/
       ↓
    document_articles row (status=draft)
       ↓
[Admin browser]
    DocumentEditor.vue           ← form + Tiptap canvas
       ↓ PUT
[Laravel: DocumentArticleController@update]
       ↓
    document_articles row (updated)

       │ admin clicks Publish
       ↓
[Laravel: DocumentArticleController@publish]
    status=published, published_at=now()
       ↓
[Public visitor]
    GET /articles/{slug}
       ↓
[Laravel: PublicDocumentArticleController@show]
    Blade: articles/show.blade.php
    ← rendered HTML page with full SEO chrome
```

## Data Model

### New table `document_articles`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint` PK | |
| `slug` | `string(255)` UNIQUE | auto from title at first save, editable, collision-suffixed (`-2`, `-3`) |
| `title` | `string(255)` | extracted from `<dc:title>` → first `<h1>` → filename, editable |
| `subtitle` | `string(255)` NULL | from `<dc:subject>`, editable |
| `description` | `text` NULL | meta description, from `<dc:description>`, editable, truncated to 160 chars on render |
| `keywords` | `string(500)` NULL | csv from `<cp:keywords>`, editable |
| `author_name` | `string(255)` NULL | from `<dc:creator>`, displayed but not used as byline |
| `author_byline` | `string(255)` NULL | admin-editable, defaults to `author_name` |
| `cover_image_path` | `string(500)` NULL | relative storage path, e.g. `document-articles/12/img-0.png` |
| `html_body` | `longtext` | sanitised mammoth output |
| `status` | `enum('draft','published','archived')` | default `draft` |
| `published_at` | `timestamp` NULL | set on publish, cleared on unpublish |
| `imported_by` | `bigint` FK `users.id` | admin who dropped the file |
| `original_filename` | `string(255)` | audit |
| `original_doc_hash` | `char(64)` NULL | sha256 of uploaded file, indexed |
| `created_at` / `updated_at` | timestamps | |

**Indexes:** unique on `slug`, index on `status`, index on `published_at`, index on `original_doc_hash`.

**Migration is additive only.** No FKs to existing CMS tables, no shared columns, no observer hooks, no events fired.

### Image storage

- Path: `storage/app/public/document-articles/{document_article_id}/img-{n}.{ext}`
- Public URL: `/storage/document-articles/{id}/img-N.png` (via Laravel symlink)
- HTML `<img src>` MUST start with `/storage/document-articles/` — anything else is stripped by the purifier
- No images table — files on disk, referenced by URL inside `html_body`

## Components

### Frontend (Vue + Tiptap)

| File | Role |
|---|---|
| `resources/js/views/Admin/Documents/DocumentListPage.vue` | Sidebar landing page. Drop-zone at top + table of imports below (title, status, imported_at, imported_by, edit/publish/delete actions). |
| `resources/js/views/Admin/Documents/DocumentEditor.vue` | Two-pane edit screen. Left: metadata form (title, slug, subtitle, description, keywords, author byline, cover-image picker). Right: Tiptap canvas mounted on `html_body`. Footer: Save draft / Preview / Publish or Unpublish / Delete. |
| `resources/js/components/Admin/Documents/DropZone.vue` | Reusable drag-drop area with mammoth.js conversion + multi-part upload. |
| `resources/js/components/Admin/Documents/CoverImagePicker.vue` | Lets admin pick from extracted images or upload a new one. |
| `resources/js/store/modules/documentArticles.js` | Vuex namespace `documentArticles` — `list`, `get`, `import`, `update`, `publish`, `unpublish`, `delete`. |
| `resources/js/services/documentArticleService.js` | API wrapper. |

**Tiptap extensions used:** `StarterKit` (paragraphs, headings, lists, bold, italic), `Underline`, `Link`, `Image`, `Table`, `TableRow`, `TableCell`, `TableHeader`, `TextStyle`, `Color`, `Highlight`.

**Sidebar entry** — added to `navItems` in `resources/js/views/Admin/AdminPanel.vue`:

```js
{ id: 'documents', label: 'Documents', shortLabel: 'Docs', path: '/admin/documents' }
```

Same router-push pattern as the existing `CMS` entry — opens its own route stack rather than switching `activeTab`.

**Router** — new routes in `resources/js/router/index.js`:

```js
{ path: '/admin/documents', component: DocumentListPage, meta: { requiresAuth: true, requiresAdmin: true } },
{ path: '/admin/documents/:id/edit', component: DocumentEditor, meta: { requiresAuth: true, requiresAdmin: true } },
```

### Backend (Laravel)

| File | Role |
|---|---|
| `app/Models/DocumentArticle.php` | Eloquent model, casts `published_at` to datetime, has `scopePublished()`, `getPreviewTokenAttribute()`. |
| `app/Http/Controllers/Api/Admin/DocumentArticleController.php` | Admin CRUD: `index`, `show`, `store` (import), `update`, `destroy`, `publish`, `unpublish`. Guarded by `auth:sanctum` + `permission:admin.access`. |
| `app/Http/Controllers/PublicDocumentArticleController.php` | `show($slug)` — returns Blade view for `published` rows, supports `?preview_token=...` for drafts. 404 otherwise. |
| `app/Http/Requests/Admin/DocumentArticleImportRequest.php` | Validates upload: file mime `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, max 10 MB, `html` field, `images[]` array, metadata fields. |
| `app/Http/Requests/Admin/DocumentArticleUpdateRequest.php` | Validates edit: title required when publishing, slug uniqueness, html_body length cap (1 MB), etc. |
| `app/Services/Documents/DocumentArticleImporter.php` | Receives validated import payload, runs `DocxMetadataExtractor` server-side as defence-in-depth, sanitises HTML, writes images, persists row in a DB transaction. |
| `app/Services/Documents/DocxMetadataExtractor.php` | Reads docx as a ZIP via `ZipArchive`, parses `docProps/core.xml`, returns `['title' => ..., 'subject' => ..., 'description' => ..., 'keywords' => ..., 'creator' => ...]`. |
| `app/Services/Documents/HTMLBodySanitiser.php` | Wrapper around `mews/purifier` with this module's allow-list. Validates `<img src>` starts with `/storage/document-articles/`. |
| `resources/views/articles/show.blade.php` | Public render. Title + meta + OG + Twitter card + JSON-LD + body. |

### Routes

`routes/api.php` (inside the existing admin group):

```php
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin/documents')->group(function () {
    Route::get('/', [DocumentArticleController::class, 'index']);
    Route::post('/', [DocumentArticleController::class, 'store']);
    Route::get('{document}', [DocumentArticleController::class, 'show']);
    Route::put('{document}', [DocumentArticleController::class, 'update']);
    Route::delete('{document}', [DocumentArticleController::class, 'destroy']);
    Route::post('{document}/publish', [DocumentArticleController::class, 'publish']);
    Route::post('{document}/unpublish', [DocumentArticleController::class, 'unpublish']);
});
```

`routes/web.php`:

```php
Route::get('/articles/{slug}', [PublicDocumentArticleController::class, 'show'])
    ->name('document-articles.show');
```

## Data Flow

### Import flow

1. Admin drags `.docx` onto `DropZone.vue`.
2. Browser reads file as `ArrayBuffer`, runs `mammoth.convertToHtml({ arrayBuffer }, { convertImage: ... })`.
   - The custom `convertImage` callback emits each image as a `Blob` (kept in a Map keyed by index `0`, `1`, ...) and writes a placeholder `src` into the HTML: `data-pending-image="0"` (no real `src` yet). The placeholder is rewritten by the backend after image files are persisted to disk.
3. Browser also reads the docx as a Zip (via `JSZip`) to extract `docProps/core.xml` for metadata. This client-extracted metadata only populates the editor form pre-upload so the admin sees it immediately — the server **re-extracts and overwrites** on persist (see step 6.i).
4. Browser POSTs `multipart/form-data` to `/api/admin/documents` with: original `.docx` file, mammoth HTML (with `data-pending-image="N"` placeholders), image Blobs (named `image-0`, `image-1`, …), client-extracted metadata.
5. `DocumentArticleImportRequest` validates mime, size, fields.
6. `DocumentArticleImporter`:
   1. Re-extracts metadata server-side from the uploaded `.docx` via `DocxMetadataExtractor` — server values **override** client values (defence-in-depth).
   2. Sanitises HTML via `HTMLBodySanitiser`.
   3. Begins DB transaction.
   4. Persists `document_articles` row with `status=draft`, server metadata, sanitised HTML (still containing `data-pending-image="N"` placeholders at this point).
   5. Writes uploaded images to `storage/app/public/document-articles/{id}/img-{n}.{ext}`.
   6. Rewrites the persisted HTML: each `data-pending-image="N"` becomes a real `<img src="/storage/document-articles/{id}/img-N.{ext}">`. Updates the row.
   7. Commits transaction. Any failure rolls back row + deletes any partially-written image files.
7. Controller returns the new row. Frontend redirects to `/admin/documents/{id}/edit`.

### Edit flow

- Form fields: bound to model attributes, on save → `PUT /api/admin/documents/{id}`.
- Tiptap canvas: emits `update` events with new HTML, debounced 500 ms, persisted on save.
- Save button on footer commits all changes atomically.

### Publish flow

- `Publish` button → `POST /api/admin/documents/{id}/publish`.
- Server validates: `title` non-empty, `html_body` non-empty, `slug` unique among non-archived rows.
- Sets `status=published`, `published_at=now()`. Returns updated row.
- `Unpublish` reverses: `status=draft`, `published_at=null`.

### Preview flow

- Draft preview: admin clicks `Preview` → frontend calls `GET /api/admin/documents/{id}/preview-token` → server returns a signed URL `/articles/{slug}?preview_token={token}` valid for 30 minutes.
- Public controller verifies token signature and TTL when status is not `published`.

### Public render flow

- `GET /articles/{slug}` → `PublicDocumentArticleController@show`.
- Looks up by slug. If `status != published` AND no valid preview token → 404.
- Renders `articles/show.blade.php` with full SEO chrome (see Public Render section).

## Public Render — SEO chrome

`resources/views/articles/show.blade.php` head:

```html
<title>{{ $article->title }} — Fynla</title>
<meta name="description" content="{{ Str::limit($article->description ?? Str::limit(strip_tags($article->html_body), 160), 160) }}">
<meta name="keywords" content="{{ $article->keywords }}">
<meta name="author" content="{{ $article->author_byline ?? $article->author_name }}">
<link rel="canonical" href="{{ url('/articles/'.$article->slug) }}">

<!-- OpenGraph -->
<meta property="og:type" content="article">
<meta property="og:title" content="{{ $article->title }}">
<meta property="og:description" content="{{ Str::limit($article->description, 160) }}">
<meta property="og:url" content="{{ url('/articles/'.$article->slug) }}">
@if($article->cover_image_path)
<meta property="og:image" content="{{ asset('storage/'.$article->cover_image_path) }}">
@endif
<meta property="article:published_time" content="{{ $article->published_at?->toIso8601String() }}">
<meta property="article:author" content="{{ $article->author_byline ?? $article->author_name }}">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $article->title }}">
<meta name="twitter:description" content="{{ Str::limit($article->description, 160) }}">
@if($article->cover_image_path)
<meta name="twitter:image" content="{{ asset('storage/'.$article->cover_image_path) }}">
@endif

<!-- JSON-LD Article schema -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "{{ $article->title }}",
  "description": "{{ Str::limit($article->description, 160) }}",
  "author": { "@type": "Person", "name": "{{ $article->author_byline ?? $article->author_name }}" },
  "datePublished": "{{ $article->published_at?->toIso8601String() }}",
  "image": "{{ $article->cover_image_path ? asset('storage/'.$article->cover_image_path) : null }}"
}
</script>
```

Body:

```html
<article>
  <h1>{{ $article->title }}</h1>
  @if($article->subtitle) <p class="subtitle">{{ $article->subtitle }}</p> @endif
  <div class="byline">
    <span>{{ $article->author_byline ?? $article->author_name }}</span>
    <time datetime="{{ $article->published_at?->toIso8601String() }}">
      {{ $article->published_at?->format('j F Y') }}
    </time>
  </div>
  @if($article->cover_image_path)
    <img src="{{ asset('storage/'.$article->cover_image_path) }}" alt="">
  @endif
  <div class="article-body">{!! $article->html_body !!}</div>
</article>
```

Styled with the existing design system (eggshell page background, horizon-blue h1, Segoe UI typography, raspberry CTAs, no scores, no decorative icons — see CLAUDE.md Rules #11, #13, #14).

## HTML Sanitisation Allow-list

`mews/purifier` config — register a new profile `document_article` in `config/purifier.php`:

| Element class | Allowed |
|---|---|
| **Block tags** | `h1, h2, h3, h4, h5, h6, p, ul, ol, li, blockquote, table, thead, tbody, tr, td, th, hr, figure, figcaption, div` |
| **Inline tags** | `strong, em, u, a[href|target|rel], img[src|alt|width|height], br, span[style], code` |
| **Attributes** | `style` on `span` and inline elements only |
| **CSS properties** | `color, background-color, text-align, font-weight, font-style, text-decoration` only |
| **`a[href]`** | Must start with `http://`, `https://`, or `/` |
| **`img[src]`** | **MUST** start with `/storage/document-articles/` — anything else stripped |
| **Stripped always** | `<script>`, `<iframe>`, `<object>`, `<embed>`, all `on*` event handlers, `javascript:` URLs |

## Error Handling

| Scenario | Behaviour |
|---|---|
| File >10 MB | 413 with friendly message; client-side guard before upload |
| Wrong mime | 422, "Only .docx files are supported" |
| Mammoth conversion fails browser-side | Toast error, no upload attempted |
| `DocxMetadataExtractor` cannot read core.xml | Logs warning, falls back to client-supplied metadata, import proceeds |
| Image fails to write to storage | Whole import fails atomically — DB transaction rolled back, partial files unlinked |
| Duplicate `original_doc_hash` | Import allowed (admin may re-import a corrected version); list shows "duplicate of #N" notice |
| Slug collision on save | Append `-2`, `-3`, … |
| Publishing without title or body | Blocked client-side AND server-side (422) |
| Public URL hit on draft without valid `preview_token` | 404 (treats it as if it doesn't exist) |
| Preview token expired | 404 (no specific "expired" error — security through obscurity) |

## Testing

### Pest unit (`tests/Unit/Services/Documents/`)

- `DocxMetadataExtractorTest` — given fixture `.docx` files, asserts extracted metadata.
- `HTMLBodySanitiserTest` — sample inputs cover tables, images, scripts, event handlers, malicious `img src`.
- `DocumentArticleSlugTest` — slug collision logic, `-2`/`-3` suffix.

### Pest feature (`tests/Feature/Documents/`)

- `DocumentArticleImportTest` — POST a fixture docx, assert row + image files exist + html_body sanitised.
- `DocumentArticleControllerTest` — index/show/update/destroy/publish/unpublish, permission checks.
- `PublicDocumentArticleTest` — 404 on draft, 200 on published, signed preview token works on draft, expired token returns 404.

### Browser (Playwright, `tests/Browser/scenarios/`)

`document-articles-end-to-end.php`:

1. Login as admin.
2. Navigate to `/admin/documents`.
3. Drop a fixture `.docx` (`tests/fixtures/documents/sample-with-images-and-tables.docx`).
4. Verify redirect to editor.
5. Verify form fields are populated (title, author byline, etc.).
6. Verify Tiptap canvas shows the body with table + image rendered.
7. Edit title.
8. Click Publish.
9. Open `/articles/{slug}` in a new tab.
10. Verify `<title>`, `<meta name="description">`, `og:image`, JSON-LD Article schema all present.
11. Verify body renders with table + cover image.
12. Verify `view-source:` shows no `<script>` tags injected.

### Test fixtures (`tests/fixtures/documents/`)

- `sample-with-images-and-tables.docx` — comprehensive fidelity test.
- `sample-minimal.docx` — title-only, no images.
- `sample-with-malicious-html.docx` — contains `<script>` tags hidden in styled spans (for sanitiser test).

## Security Notes

1. **HTMLPurifier is mandatory** — the import pipeline never persists raw mammoth output; everything passes through `HTMLBodySanitiser`.
2. **Image src allow-list** — `<img src>` must start with `/storage/document-articles/`. Stops a malicious docx from injecting `<img src="https://attacker.example/track.gif">`.
3. **File size cap** — 10 MB enforced both client-side (DropZone) and server-side (FormRequest).
4. **Mime check** — server validates the actual mime type, not just the extension.
5. **Server-side metadata re-extraction** — even though the browser sends `client-extracted` metadata, the server re-reads `docProps/core.xml` from the uploaded file and **overwrites** client values. Client metadata is treated as untrusted.
6. **Preview tokens are signed** — Laravel's `URL::temporarySignedRoute()` with 30-minute TTL.
7. **Permissions** — every admin endpoint guarded by `permission:admin.access`. Public endpoint open as expected for `published` rows.
8. **Storage symlink** — `php artisan storage:link` must be in place (it already is for the existing app).

## Future Iterations (deferred YAGNI)

These items are explicitly **out of scope for v1** but flagged for future sprints. Each is a viable follow-up.

| # | Item | Why deferred |
|---|---|---|
| FI-1 | **Comments / footnotes / track-changes preservation** | Mammoth strips these by default. Preserving them needs a custom mammoth options config + custom Tiptap extensions. Big effort, niche need. |
| FI-2 | **Public listing index at `/articles`** | Users currently reach articles by direct slug. A listing page with pagination, category filter, search, sort by date is its own design. |
| FI-3 | **RSS feed for `/articles/...`** | Mirror of `feeds/insights.xml` pattern but for document_articles. Easy to bolt on once FI-2 is in (gives the listing query). |
| FI-4 | **Sitemap entry** | Add `/articles/{slug}` rows to whatever sitemap.xml the site already generates. Easy add. |
| FI-5 | **Scheduled / future publish** | New column `scheduled_for`, scheduler job that flips draft→published when due. |
| FI-6 | **Revision history** | New `document_article_revisions` table mirroring `insight_article_revisions`. Auto-snapshot on every save. |
| FI-7 | **Multi-author / collaborative editing** | Real-time co-edit via Yjs or similar; way out of scope. |
| FI-8 | **i18n / translations** | Per-locale `document_articles_translations` table; out of scope. |
| FI-9 | **Categories / tags / related articles** | Match the Insights CMS feature surface — taxonomy table + UI. |
| FI-10 | **Bulk import (multi-file drop)** | v1 is single-file. Multi-file would need a queued job + progress UI. |
| FI-11 | **Track-changes-aware diff view** | Compare two versions of the same docx side-by-side. Needs FI-6 first. |
| FI-12 | **PDF / Google Docs / Markdown source formats** | Currently `.docx` only. Other formats need separate parsers + ingest pipelines. |
| FI-13 | **Embedded media beyond images** (video, audio, oEmbed) | Mammoth doesn't carry these; need explicit support. |
| FI-14 | **Author byline → real `users` FK** | `author_byline` is a free-text string in v1. Could later FK to a `users` row for richer profile pages. |
| FI-15 | **Move parsing server-side** | Currently mammoth.js runs in the admin browser. If file sizes grow or we need queued processing, move to a Node child process or a pure-PHP port. |
| FI-16 | **Public listing performance / caching** | If listing/feed pages get traffic, add view caching + ETag. |
| FI-17 | **Admin analytics on articles** | Page views, time on page, top articles. Needs an analytics pipeline. |
| FI-18 | **A/B testing on titles or covers** | Way out of scope. |
| FI-19 | **Undo deleted articles (soft delete)** | v1 is hard delete. Easy to flip later by adding `SoftDeletes`. |
| FI-20 | **Custom URL structure / nested slugs** | v1 is flat `/articles/{slug}`. Could later support `/articles/{category}/{slug}`. |

## Acceptance Criteria

A v1 ship is GREEN when:

1. Admin can navigate to **Documents** in the sidebar (label "Documents", no mention of "test").
2. Admin can drop a `.docx` and see a fully populated editor within 5 seconds for a 1 MB file.
3. The auto-extracted title, author, description, keywords, and cover image are all present and correct against the source docx.
4. Tables and images from the docx render correctly in the Tiptap canvas.
5. Admin can edit any field (title, slug, body, cover, author byline, description, keywords) and save.
6. Admin can publish; clicking through `/articles/{slug}` in an incognito tab returns 200 with all SEO meta tags present.
7. `view-source:/articles/{slug}` shows no `<script>` tag, no `<iframe>`, no `on*` event handlers.
8. JSON-LD `Article` schema validates against schema.org via Google's Rich Results Test.
9. Pest unit + feature tests pass: `./vendor/bin/pest tests/Unit/Services/Documents tests/Feature/Documents`.
10. Playwright `document-articles-end-to-end.php` scenario passes from clean state.
11. Existing Insights CMS at `/admin/insights` is **completely untouched** — same routes, same models, same UI.
12. Code formatted with `./vendor/bin/pint`, no design-system violations (CLAUDE.md Rules #9–#14).

## Files Created (estimated)

**Backend (8):**
- `app/Models/DocumentArticle.php`
- `app/Http/Controllers/Api/Admin/DocumentArticleController.php`
- `app/Http/Controllers/PublicDocumentArticleController.php`
- `app/Http/Requests/Admin/DocumentArticleImportRequest.php`
- `app/Http/Requests/Admin/DocumentArticleUpdateRequest.php`
- `app/Services/Documents/DocumentArticleImporter.php`
- `app/Services/Documents/DocxMetadataExtractor.php`
- `app/Services/Documents/HTMLBodySanitiser.php`

**Frontend (6):**
- `resources/js/views/Admin/Documents/DocumentListPage.vue`
- `resources/js/views/Admin/Documents/DocumentEditor.vue`
- `resources/js/components/Admin/Documents/DropZone.vue`
- `resources/js/components/Admin/Documents/CoverImagePicker.vue`
- `resources/js/store/modules/documentArticles.js`
- `resources/js/services/documentArticleService.js`

**Migration (1):**
- `database/migrations/2026_05_01_120000_create_document_articles_table.php`

**Public view (1):**
- `resources/views/articles/show.blade.php`

**Config (1 edit):**
- `config/purifier.php` — add `document_article` profile

**Tests (8):**
- `tests/Unit/Services/Documents/DocxMetadataExtractorTest.php`
- `tests/Unit/Services/Documents/HTMLBodySanitiserTest.php`
- `tests/Unit/Services/Documents/DocumentArticleSlugTest.php`
- `tests/Feature/Documents/DocumentArticleImportTest.php`
- `tests/Feature/Documents/DocumentArticleControllerTest.php`
- `tests/Feature/Documents/PublicDocumentArticleTest.php`
- `tests/Browser/scenarios/document-articles-end-to-end.php`
- 3× `tests/fixtures/documents/*.docx`

**Config edits to existing files (4):**
- `routes/api.php` — admin/documents block
- `routes/web.php` — `/articles/{slug}` route
- `resources/js/views/Admin/AdminPanel.vue` — sidebar `Documents` entry
- `resources/js/router/index.js` — `/admin/documents` and `/admin/documents/:id/edit` routes

**npm dependencies (new — verified absent from `package.json`):**
- `mammoth`
- `jszip`
- `@tiptap/extension-image`
- `@tiptap/extension-table`
- `@tiptap/extension-table-row`
- `@tiptap/extension-table-cell`
- `@tiptap/extension-table-header`
- `@tiptap/extension-text-style`
- `@tiptap/extension-color`
- `@tiptap/extension-highlight`

(`@tiptap/core`, `@tiptap/starter-kit`, `@tiptap/extension-underline`, `@tiptap/extension-link`, `@tiptap/vue-3` already present at 3.22.x — pin new extensions to `^3.22.3` for compatibility.)

**composer dependencies (new — verified absent from `composer.json`):**
- `mews/purifier` — Laravel wrapper around HTMLPurifier for the sanitiser allow-list above.

---

**Spec status:** approved by CSJ on 2026-05-01. Next step: invoke `superpowers:writing-plans` to produce an implementation plan.
