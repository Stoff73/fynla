# Marketing Pipeline — Stage 5 Setup

Stage 5 = *"a marketing team member uploads a `.docx` (or writes a native Google Doc) into `Marketing Automation ▸ Articles`; overnight the pipeline auto-imports it as a draft InsightArticle; admin reviews + publishes locally; pushes to dev; when it looks right, a whitelisted publisher pushes it live on fynla.org — without a code deploy."*

Prerequisites: Stages 1 + 2 + 3 + 4 already set up (see the other `STAGE-*-SETUP.md` docs).

Governed by `fynlaDesignGuide.md` (CMS UI colours + typography). No new external SaaS dependencies.

---

## What you need

| Item | Where you get it | Notes |
|---|---|---|
| `Articles` subfolder in the Marketing Automation Drive folder | Create it manually in Drive alongside the existing `Videos` subfolder | The `ArticlesFolderLocator` finds it by name |
| PhpOffice/PhpWord | `composer require phpoffice/phpword` (already added) | Parses `.docx` |
| Fresh Google OAuth grant | `php artisan pipeline:authorise-google` | Analytics readonly scope was added — re-consent required (only once) |
| Shared sync token | Generate a random 40-char string with `php artisan tinker --execute="echo Str::random(48);"` | Same value goes into local .env and each target env's .env |

---

## 1. Re-consent Google OAuth (Analytics scope was added)

Same command as before:

```bash
php artisan pipeline:authorise-google
```

Sign in as `marketing@fynla.org`, Advanced → Go anyway → Allow. Note the new "See Google Analytics data" permission on the consent screen.

## 2. Create the `Articles` subfolder in Drive

Under Marketing Automation, create a new folder called `Articles` (case-sensitive). Marketing team members upload `.docx` files or create native Google Docs here.

## 3. Generate + set the sync token

Local:

```bash
php artisan tinker --execute="echo Str::random(48);"
# copy the output
```

Paste into `.env` on **your local machine**:

```
PIPELINE_SYNC_URL_DEV=https://csjones.co/fynla
PIPELINE_SYNC_TOKEN_DEV=<pasted-token>
PIPELINE_SYNC_URL_PROD=https://fynla.org
PIPELINE_SYNC_TOKEN_PROD=<same-or-different-pasted-token>
```

And on **each target env's `.env`** (csjones.co/fynla and fynla.org):

```
PIPELINE_SYNC_TOKEN=<value that matches what the local sends>
```

`config:clear` on all three.

## 4. Turn on the pipeline

`PIPELINE_ENABLED=true` in local `.env` + `config:clear`.

## 5. Grant yourself publisher permission

Publisher = able to push articles LIVE. Only you (an admin) can grant this via `/admin/pipeline/publishers`.

Open in the browser:

```
http://localhost:8000/admin/pipeline/publishers
```

Search your name → click your row → confirm. From now on, "Push to live" is enabled for you in the CMS.

Anyone else needs to be added to this list before they can push live.

---

## The full flow

### For the marketing team (no dev involvement)

1. **Write** an article in Word or Google Docs. Structure it with headings (Heading 2, 3, 4), paragraphs, bulleted lists. Bold, italic, links all work.
2. **Save** as `.docx` into Marketing Automation ▸ Articles (or leave it as a Google Doc — both are supported).
3. **Wait until the next morning** (or trigger `php artisan pipeline:detect-new-article-docs` manually). The article appears in the CMS as a draft.
4. **Open** `/admin/pipeline/articles`. Find the new draft.
5. **Edit**: fix category, add tags, link to a campaign (optional), tweak the summary. Save.
6. **Publish locally** — see it at `/insights/{slug}` on `localhost:8000`.
7. **Push to dev** — see it at `csjones.co/fynla/insights/{slug}`.
8. **Test it on dev.** Wait at least 1 hour (configurable via `PIPELINE_DEV_TO_PROD_MIN_HOURS`).
9. **Push to live** — visible at `fynla.org/insights/{slug}` immediately.

### The scheduler

The pipeline runs these commands automatically:

| Time | Command | Purpose |
|---|---|---|
| Daily 06:45 | `pipeline:detect-new-article-docs` | Word → draft InsightArticle |
| Daily 07:00 | `pipeline:detect-new-articles` | Stage 1 (video pipeline detects published articles) |
| Daily 07:30 | `pipeline:detect-new-videos` | Stage 3 |
| Hourly | `pipeline:schedule-ready-posts` | Stage 4 |
| Monday 06:00 | `pipeline:recalculate-optimal-times` | Stage 4 |
| Monday 09:00 | `pipeline:weekly-social-report` | Stage 4 |
| Quarterly 08:00 | `pipeline:audit-social-videos` | Stage 3 |

### Cross-env sync — what happens under the hood

When you click "Push to dev":
1. Local Laravel wraps the article as JSON
2. Signs the request with `PIPELINE_SYNC_TOKEN_DEV` in `X-Pipeline-Sync-Token`
3. POSTs to `https://csjones.co/fynla/api/admin/pipeline/articles/sync-inbound`
4. Dev's `ArticleSyncInboundController` validates the token against its own `PIPELINE_SYNC_TOKEN`
5. Dev upserts the article into its DB and returns 200
6. Local stamps `dev_synced_at` and writes an `article_sync_log` row

"Push to live" is the same flow, targeting `fynla.org`. Guarded by:
- **Gate**: article must have `dev_synced_at` set + ≥ `PIPELINE_DEV_TO_PROD_MIN_HOURS` ago
- **Permission**: caller must be in `pipeline_publisher_users`

---

## Campaign linking

To use a campaign CTA on an article:

1. Create the campaign at `/admin/pipeline/campaigns` (or via `POST /api/admin/pipeline/campaigns`). Fields:
   - `name`, `slug`, `landing_url`
   - `cta_heading` — headline that appears on the article's bottom panel
   - `cta_subheading` — one-line supporting copy
   - `cta_button_text` — button label (max 60 chars)
2. In the article editor, pick the campaign from the "Campaign" dropdown.
3. The bottom CTA panel on `/insights/{slug}` now shows the campaign's copy and links to the campaign's landing URL.
4. Unlink the campaign — CTA reverts to the default "Register free" panel.

---

## What Word formatting maps to

| Word construct | InsightArticle block type |
|---|---|
| Heading 2 / 3 / 4 | `heading` |
| Body paragraph | `paragraph` (with `<strong>`, `<em>`, `<a>`) |
| Bulleted / numbered list | `list` |
| Simple table | flattened into a `paragraph` |
| Images | **skipped** (MVP — add via CMS if needed) |
| Comments, footnotes, tracked changes | ignored |
| Custom paragraph styles (Heading 1, Heading 5+, etc.) | flattened to `paragraph` |

If a marketing team member uses formatting the parser doesn't understand, it either flattens sensibly or logs the skip to `storage/logs/pipeline*.log`.

---

## Rollback / disable

Same as previous stages: `PIPELINE_ENABLED=false` in `.env` + `config:clear`. Auto-import stops. Publish buttons in the CMS still work — they don't check the flag.

To disable ONLY auto-import (keep the CMS operational for manually-created articles): comment out the `pipeline:detect-new-article-docs` line in `Kernel.php`.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Article doesn't appear after upload | Wait until 06:45 next day, or run `php artisan pipeline:detect-new-article-docs` manually |
| "Could not find an Articles subfolder" | Create the folder in Drive under Marketing Automation |
| Push to dev returns "sync endpoint not configured on this environment" | Target env's `.env` missing `PIPELINE_SYNC_TOKEN`; `config:clear` after adding |
| Push to dev returns "invalid sync token" | Local's `PIPELINE_SYNC_TOKEN_DEV` doesn't match dev's `PIPELINE_SYNC_TOKEN` |
| Push to live returns 403 | You aren't in `pipeline_publisher_users`. Go to `/admin/pipeline/publishers` |
| Push to live returns 422 "must be on dev for at least..." | Wait — the gate keeps prod safe from same-second mistakes |
| Re-import overwrites my manual edits | Expected — the Word doc is the source of truth. Edit the Word doc, not the article, if you'll want to re-import |
| Images from Word doc not showing | MVP skips images. Upload hero image via the existing InsightArticle admin editor |

---

## Files added by Stage 5

Roughly 20 new files + 4 modified. Full list:

**Migrations** (5)
- `2026_07_16_100000_add_pipeline_columns_to_insight_articles.php`
- `2026_07_16_100001_create_article_sync_logs_table.php`
- `2026_07_16_100002_create_pipeline_publisher_users_table.php`
- `2026_07_16_100003_add_cta_columns_to_pipeline_campaigns.php`
- `2026_07_16_100004_make_insight_article_author_id_nullable.php`

**Models** (2)
- `App\Models\Pipeline\ArticleSyncLog`
- `App\Models\Pipeline\PublisherUser`

**Services** (4)
- `App\Services\Pipeline\Content\WordDocxIngestor`
- `App\Services\Pipeline\Content\GoogleDocExporter`
- `App\Services\Pipeline\Content\ArticleImporter`
- `App\Services\Pipeline\Content\ArticleSyncService`
- `App\Services\Pipeline\Google\ArticlesFolderLocator`

**Commands** (1)
- `pipeline:detect-new-article-docs`

**Controllers** (3)
- `Api\Admin\Pipeline\PipelineArticlesController`
- `Api\Admin\Pipeline\PipelinePublishersController`
- `Api\Admin\Pipeline\ArticleSyncInboundController`

**Vue** (4)
- `views/Admin/Pipeline/ArticleManager.vue`
- `views/Admin/Pipeline/ArticleEditor.vue`
- `views/Admin/Pipeline/PublisherManager.vue`
- `components/Insights/InsightCtaPanel.vue`

**Frontend service** (1)
- `services/pipelineArticlesService.js`

**Tests** — 7 new assertions on top of the 30+ existing Stage 1-4 tests. Total: 38 pipeline tests passing.
