# Deploy: CMS articles into the /insights pipeline (csjones.co/fynla)

*Session 75 — 2026-05-04. Follows on from session 74's `deployCMS.md`, which is now superseded for the public-facing pieces.*

## What this deploy does

CSJ flagged that doc-imported articles were publishing to `/articles/{slug}` as a standalone Blade page with no top nav, no banner, no footer — bypassing the existing `/insights` SPA pipeline. PR [#240](https://github.com/Stoff73/fynla/pull/240) was reworked so that:

- CMS-published articles surface at `/insights/{slug}` via the existing Vue SPA + `PublicLayout`
- They appear in the `/insights` hub list alongside native insights
- The standalone Blade view, `/articles/{slug}` route, and `PublicDocumentArticleController` are gone
- A latent `SanitizeInput` middleware bug was fixed — it was stripping all HTML tags from the imported `html` / `html_body` request fields *before* `HTMLBodySanitiser` (HTMLPurifier) ran, so doc bodies were arriving as plain text

## What was actually shipped to csjones.co/fynla this session

### Files transferred via rsync

Via `rsync -az --relative` against `~/www/csjones.co/fynla-app/`:

```
app/Http/Controllers/Api/Public/InsightController.php           (M)
app/Http/Middleware/InsightsSeoMetaInjector.php                 (M)
app/Http/Middleware/SanitizeInput.php                           (M)  ← critical
app/Http/Resources/Insights/DocumentArticleAsInsightListResource.php  (A)
app/Http/Resources/Insights/DocumentArticleAsInsightResource.php      (A)
app/Http/Resources/Insights/InsightArticleResource.php          (M)
app/Models/DocumentArticle.php                                  (M)
app/Services/Insights/InsightSeoService.php                     (M)
routes/web.php                                                  (M)
```

### Files deleted server-side via SSH

```
app/Http/Controllers/PublicDocumentArticleController.php
resources/views/articles/show.blade.php
resources/views/articles/                  (empty dir, removed)
```

### `public/build/` rotation

Standard preserve-old-chunks pattern (see `feedback_warn_before_spa_rebuild.md`):

```bash
cd ~/www/csjones.co/fynla-app
rm -rf public/build.old
mv public/build public/build.old
# rsync local public/build/ → remote public/build/
cp -rn public/build.old/. public/build/      # merge old chunks back for in-flight sessions
```

Result: 92M new bundle + 85M old bundle merged in. Delete `public/build.old/` once confidence is high.

### Server-side cache + autoload

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload -q
php artisan optimize
```

## Pre-existing deploy gap that was also fixed

`php artisan route:list` was failing on the dev server with:

```
Class "App\Http\Controllers\Api\AgentInternalController" does not exist
```

Root cause: the previous CMSFix → csjones deploy (session 74) generated its file list from `git diff origin/dev` against CMSFix-only, missing files that had landed on `dev` *outside* the CMSFix branch and were referenced from the server's `routes/api.php`. Specifically:

- `app/Http/Controllers/Api/AgentInternalController.php`
- `app/Http/Middleware/AgentTokenAuth.php`

Both pushed via rsync this session. After `composer dump-autoload` + `php artisan route:clear` + `php artisan optimize`, `route:list --path=internal/agent` returns 6 routes cleanly.

## What was NOT touched (deliberate)

The csjones.co `~/www/csjones.co/fynla-app/` tree contains uncommitted server-side WIP that is not in `origin/dev` — visible during the rsync dry-run as `*deleting` entries on a `--delete` pass:

```
app/ValueObjects/CaptureContext.php
app/Support/XaiFunctionCallLeakStripper.php
app/Listeners/Eval/EvalTraceListener.php
app/Events/Eval/{GateChecked,EngineCalled,AgentDecision}.php
app/Enums/{StrategyPriority,StrategyCategory}.php
app/DataTransferObjects/{TaxStrategyOverridesDTO,TaxStrategyOutputDTO,StrategyRecommendation}.php
app/Console/Commands/{Eval*, BackfillAiDailyUsage, AiAuditVerifyChainCommand, SummariseStaleConversationsCommand}.php
app/Constants/UpdateRecordAllowlist.php
app/Exceptions/SpouseCollisionException.php
app/Http/Controllers/Api/{TaxStrategyController, EvalAuthController}.php
app/Http/Controllers/Api/Admin/EvalRecordingController.php
…and others (61 content-different files in app/ alone)
```

Per `feedback_dev_server_is_separate.md`, the dev server may run a different branch than `dev`. Rsync was executed **without `--delete`** so the WIP stays intact. None of the WIP files share paths with the CMSFix integration changes, so there's no overlap risk on this deploy.

If/when that WIP gets merged to dev, a future deploy will need to reconcile properly (e.g., by checking the changed paths against currently-on-server differences before pushing).

## Verification on https://csjones.co/fynla

End-to-end browser test (Playwright):

1. Login as `chris@fynla.org` (verification code fetched from server DB via SSH tinker)
2. Navigate to `/admin/documents` → drop `sample-with-images-and-tables.docx`
3. Editor canvas renders **structured** body (h1 "Big Heading", paragraph, 2-column table) — confirms `SanitizeInput` middleware fix is live
4. Editor sidebar shows `Public URL: /insights/rich-sample-title` (was `/articles/...`)
5. Click Publish → status flips to "published"
6. Visit `https://csjones.co/fynla/insights/rich-sample-title`:
   - Top nav: Fynla logo / Home / How it works / Resources / Why Fynla / Pricing / Chris dropdown ✓
   - Article title `<h1>` "Rich Sample Title" + byline "By Sam Author · 4 May 2026" ✓
   - Body renders inside `.article-html-body`: h1 "Big Heading", paragraph, full-width 2-col table with `Left | Right` cells styled per design system (savannah-300 header, eggshell-900 borders) ✓
   - Footer with 5 columns (About Fynla, Help centre, Terms, Tools, Advisers) + © Fynla 2026 + social icons ✓
   - **Zero console errors** for the article page itself
7. Visit `/insights` hub → `rich-sample-title` appears in `latestArticles` (first link in DOM)

The 8 console 403s on the hub are **pre-existing** — `/fynla/storage/insights/bespoke/*.jpg` hero images for native bespoke insights aren't on dev's storage. Out of scope for this deploy.

## HTTP smoke

```
curl https://csjones.co/fynla                                         → 301 (subdir redirect)
curl https://csjones.co/fynla/api/insights                            → 200
curl https://csjones.co/fynla/api/insights/rich-sample-title          → 200
curl https://csjones.co/fynla/insights/rich-sample-title              → 200 (SPA shell + injected SEO meta)
```

## Outstanding for production deploy (fynla.org)

When `dev → main` happens:

- Same files + `SanitizeInput.php` change need to ship to fynla.org production
- Production root is `~/www/fynla.org/public_html/` (no subdir layout)
- Build with `./deploy/fynla-org/build.sh`, not the csjones script — different `VITE_BASE_PATH`/`RewriteBase`
- No DB migrations
- The 2 missing files on csjones (`AgentInternalController` + `AgentTokenAuth`) need to be checked on fynla.org — if those routes are also referenced there and the controller is missing, `route:list` would fail there too
