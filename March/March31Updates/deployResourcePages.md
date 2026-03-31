# Deploy Notes — resources-pages branch (31 March 2026)

**Branch:** `resources-pages` → PR #177
**Changes:** 421 files changed, 14,742 insertions, 4,616 deletions
**Build required:** Yes — 80+ Vue/JS files changed

## Pre-merge check

This branch has diverged significantly from main. Verify after merge:
- `routes/api.php` — new contact form route added
- `app/Http/Middleware/PreviewWriteInterceptor.php` — contact route excluded

## PHP files to upload

```
app/Http/Controllers/Api/ContactFormController.php     (NEW)
app/Http/Middleware/PreviewWriteInterceptor.php         (MODIFIED)
routes/api.php                                         (MODIFIED)
resources/views/app.blade.php                          (MODIFIED — GA tag)
```

## Frontend build

```bash
./deploy/fynla-org/build.sh
```

Upload entire `public/build/` directory — 80+ Vue components changed.

## Other files to upload

```
public/sitemap.xml                                     (MODIFIED — 60+ URLs)
```

## New pages created

- `AdvisorsPage.vue` — `/advisors`
- `ContactFormController.php` — `POST /api/contact`
- `constants/faqData.js` — centralised FAQ data

## Deleted files

- `resources/js/views/Public/LearningCentre.vue` — route redirects to `/learn`

## URL changes (redirects needed if indexed)

| Old URL | New URL |
|---------|---------|
| `/learning-centre` | `/learn` (redirect in router) |
| `/compare/fynla-vs-moneyhub` | `/compare/fynla-vs-financial-centralisation-platform` |
| `/compare/fynla-vs-projectionlab` | `/compare/fynla-vs-financial-planning-platform` |
| `/compare/fynla-vs-voyant` | `/compare/fynla-vs-financial-investment-platform` |

## SSH commands after upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No migrations needed

No new database migrations in this branch.

## Warnings

- Contact form sends email via `Mail::raw()` — ensure mail config is set on production
- Old comparison page URLs should be monitored for 404s if they were indexed by Google
- `sitemap.xml` updated — submit to Google Search Console after deploy
