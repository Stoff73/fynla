# Handover — Admin Insights CMS deploy

**Date:** 17 April 2026 (handover to 18 April session)
**Branch:** `feature/csj/insights-cms` (49 commits ahead of `main`, clean working tree)
**Your job:** PR → `dev` → smoke test → PR → `main` → deploy to fynla.org. Fix anything that breaks along the way.

You are NOT starting from scratch. The feature is fully built, tested, and documented. Read this in order:

1. This handover (5 min — get the shape).
2. `deploy/notes/2026-04-17-insights-cms.md` (5 min — the real deploy steps, file list, commands).
3. `docs/superpowers/specs/2026-04-17-admin-insights-cms-design.md` (reference only — lookup when something surprises you).
4. `docs/superpowers/plans/2026-04-17-admin-insights-cms.md` (reference only — 6,058 lines, the full task-by-task plan that built this).

Do not re-plan. Do not re-test features that are already browser-verified below. **Your scope is: merge, deploy, verify.**

---

## 1. What exists

Three new MySQL tables, a block-based content model, an admin CMS at `/admin/insights/*`, a public `/insights/:slug` renderer declared AFTER the 8 named bespoke Vue routes, a refreshed 2/3+1/3 hero on the landing page, server-side per-article SEO meta injection via a new `@stack('head')` in `app.blade.php`, and a feature flag `VITE_INSIGHTS_CMS_ENABLED` so production ships backend-first.

**Numbers:**
- 81 code files changed (see `deploy/notes/2026-04-17-insights-cms.md` §4 for the full list)
- 2 new dependencies: `intervention/image ^4.0` (composer), `dompurify ^3.4` (npm)
- 3 migrations + 1 new seeder (`ExistingInsightsMetadataSeeder` — 8 bespoke articles)
- 49 commits on the branch, one commit per task, clean history

**Tests:**
- 76 insights-specific tests (unit + feature + architecture) — all green
- Full Unit suite: 1483 passed; 1 pre-existing failure unrelated to this feature (`AutoRiskCalculatorTest` — risk_level enum truncation, flagged in the fynlaInternational handover of 16 Apr)
- Feature suite: 734 passed

---

## 2. What I've already verified in Playwright

End-to-end as `chris@fynla.org` on local dev:

**Public:**
- `/insights` — all 8 bespoke articles render with category filters
- Clicking a bespoke slug (e.g. `/insights/isa-guide-uk`) renders the ORIGINAL bespoke Vue page, not the generic renderer (named-route precedence works)
- `/` landing hero is the new 2/3 + 1/3 bento layout
- DB-authored article renders through all 11 block types (heading, paragraph w/ `<strong>`, callout, key takeaways, tax stat showing £20,000 from `taxConfig.js`, divider, CTA, image)
- Server-injected `<title>` is the first title tag (browsers pick the first; static Fynla title sits below as fallback)
- `og:type="article"` and JSON-LD `@type: Article` appear

**Admin:**
- Log in → `/admin/insights` shows 8 rows with Bespoke badges
- New article flow: template picker → blank → fill fields → add blocks → save → publish → public URL renders
- Reorder (↑ arrow), duplicate, delete block controls all work and persist
- Image upload: Choose File → real JPEG uploaded → WebP card/thumb derivatives written to `storage/app/public/insights/{slug}/` → preview shows in editor
- Save as template → `/admin/insights/templates` lists it → Delete removes it
- Editing a bespoke article: canvas replaced by BespokeArticleNotice, metadata still editable
- SEO meta injection: curl output confirms per-article `<title>`, description, OG, Twitter, JSON-LD push BEFORE the static tags

See `deploy/notes/2026-04-17-insights-cms.md` §7 for the same smoke-test list to re-run after deploy.

---

## 3. Known issues you will hit

### 3a. Empty image block trips validation on save

`BlockValidator` correctly rejects image blocks with empty `path` or `alt`. If an admin adds an image block and clicks Save before uploading, they get `"Save failed: The given data was invalid."`

**Workflow workaround:** upload image before saving. OR delete the block if you changed your mind.

**If you want a better UX,** options (NOT my recommendation for this deploy — ship as-is and improve later):
- Defer image validation until publish (allow draft saves with empty image blocks)
- Auto-delete empty image blocks on save
- Inline validation feedback in the editor before the request fires

### 3b. AutoRiskCalculatorTest pre-existing failure

`tests/Unit/Services/Risk/AutoRiskCalculatorTest.php` fails with `Data truncated for column 'risk_level'` — the test writes `'medium_low'` but the enum doesn't include it. This was broken before this feature landed (documented in fynlaInternational handover 16 Apr). Don't let it block your deploy gate; it's noise.

### 3c. AdminPanel tab navigation

Insights lives on its own route stack (`/admin/insights`) rather than as an embedded tab in `AdminPanel.vue`'s `activeTab` switcher. Clicking "Insights" in the AdminPanel sidebar router-pushes instead of setting `activeTab`. Works cleanly, just different from the other admin tabs. Don't "fix" it to match — the editor needs the full page.

### 3d. `chris@fynla.org` locally was not admin before session 59

Commit `12e7dda` assigns ROLE_ADMIN to chris in `ChrisUserSeeder.php`. Production chris IS admin already; this just brings local parity. If you re-seed production, chris won't get "elevated" — `updateOrCreate` keyed by email preserves whatever role is there. Safe.

### 3e. `.env` feature flag

Local dev uses `VITE_INSIGHTS_CMS_ENABLED=true` (I added it to `.env`, which is gitignored). When you rebuild the `./dev.sh` process, restart Vite so it picks the flag up.

---

## 4. Deploy plan (TL;DR — full detail in `deploy/notes/2026-04-17-insights-cms.md`)

```
Feature branch → PR → dev → verify → PR → main → verify
```

### Step 1 — PR to `dev`

```bash
gh pr create --base dev --head feature/csj/insights-cms \
  --title "feat(insights): admin CMS for insight articles" \
  --body-file deploy/notes/2026-04-17-insights-cms.md
```

Merge after review (you are the reviewer; @Stoff73 required per CODEOWNERS).

### Step 2 — Deploy to dev (csjones.co/fynla)

**IMPORTANT:** Before building for dev, ASK the user which branch is currently deployed on the dev server. Per `feedback_dev_server_is_separate.md`, the dev server may be running a different branch (e.g. `onboardingFyn`) even when git `dev` is ahead. Building from `dev` and uploading when something else is deployed **wipes the current deployed build**.

```bash
git checkout dev && git pull
./deploy/csjones-fynla/build.sh     # sets VITE_INSIGHTS_CMS_ENABLED=true
# Upload PHP + Blade files to ~/www/csjones.co/fynla-app/ (see reference_csjones_sibling_dir.md)
# Upload public/build/ contents to ~/www/csjones.co/fynla-app/public/build/
```

SSH in (see `reference_csjones_ssh_access.md` — key `~/.ssh/fynlaDev`, must already be in ssh-agent):

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan optimize
```

### Step 3 — Smoke-test dev

Work through `deploy/notes/2026-04-17-insights-cms.md` §7. If anything fails, fix on the feature branch, re-merge to dev, re-deploy.

### Step 4 — PR to `main`

```bash
gh pr create --base main --head dev \
  --title "Release: admin insights CMS + backend-only first" \
  --body "See deploy/notes/2026-04-17-insights-cms.md"
```

### Step 5 — Deploy to production (fynla.org) — backend only

Production `build.sh` currently sets `VITE_INSIGHTS_CMS_ENABLED=false`. This is intentional: backend goes up first; public hub and landing continue to render the legacy arrays until the flag flips.

```bash
git checkout main && git pull
./deploy/fynla-org/build.sh         # flag=false
# Upload PHP + Blade + public/build/ to ~/www/fynla.org/public_html/
```

SSH (use `mcp__ssh-fynla__ssh_exec` — this is production):

```bash
cd ~/www/fynla.org/public_html
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=ExistingInsightsMetadataSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan optimize
```

### Step 6 — Verify `/api/insights` and `/api/insights/featured` respond with seeded data

```bash
curl -s https://fynla.org/api/insights | jq '.data | length'
# expect: 8
curl -s https://fynla.org/api/insights/featured | jq '.data.featured.slug'
# expect: a slug string (fallback to most-recent since nothing is featured yet)
```

### Step 7 — Flip the flag, rebuild, redeploy frontend only

```bash
# In deploy/fynla-org/build.sh, change:
#   export VITE_INSIGHTS_CMS_ENABLED=false  →  =true
./deploy/fynla-org/build.sh
# Upload public/build/ only — no PHP changes.
php artisan cache:clear   # via ssh-fynla
```

### Step 8 — Production smoke

Same list as §7 of the deploy guide. Specifically confirm:
1. `https://fynla.org/insights` still shows 8 articles (now DB-driven, same outcome)
2. Click a bespoke slug — bespoke Vue renders (NOT generic)
3. `https://fynla.org/` landing hero shows 2/3+1/3 layout
4. `/admin/insights` reachable as admin (chris@fynla.org)

Monitor `storage/logs/laravel.log` for ~10 min after the flag flip.

### Step 9 — Update CSJTODO, close the loop

Add entry to `CSJTODO.md` under a new session header noting what landed and any carried-over work.

---

## 5. Carry-overs not related to this feature

Per session 59's CSJTODO:
- [ ] **Test Fyn chat fixes on dev** — deployed in session 58, not browser-tested. Unrelated to this feature but still outstanding.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.

Deal with these after the CMS is live if you have time.

---

## 6. Files you should not touch

Don't edit these during deploy — they're deliberate:

- `resources/js/router/index.js` — the `/insights/:slug` catch-all MUST come after the 8 named routes; architecture test enforces this.
- `routes/web.php` — `/insights/{slug}` middleware route MUST come before `/{any}` SPA catch-all; architecture test enforces this.
- `app/Http/Middleware/SanitizeInput.php` — `body_blocks` path allow is load-bearing; test `SanitizeInputInsightsTest` will fail loudly if reverted.
- `resources/views/app.blade.php` — `@stack('head')` placement is BEFORE the static `<title>`, not after, so per-article meta wins the first-title rule.
- `app/Observers/InsightArticleObserver.php` — revisions are written here, NOT in the service. Don't add a second write in the service.
- `app/Providers/AppServiceProvider.php` — observer is registered in `boot()`.

---

## 7. What "done" looks like

- Production fynla.org running the CMS with flag on
- `/admin/insights` functional as chris@fynla.org
- Hub/landing showing DB-driven content
- Smoke tests §7 of the deploy guide all green
- CSJTODO updated
- Laravel log clean for 10+ min after flag flip
- No regressions in the 8 bespoke articles (still render original Vue layouts)

Do not declare done before the production flag is on AND smoke-tested. Backend-only on prod with flag=false is **half-deployed**, not done.

---

## 8. If something breaks

- **/api/insights returns 500:** check `composer install` ran (intervention/image missing → class-not-found). Check migrations ran (`php artisan migrate:status`).
- **Hub page shows the legacy fallback on dev (flag=true):** check `import.meta.env.VITE_INSIGHTS_CMS_ENABLED === 'true'` — rebuild Vite picks up env changes.
- **`/insights/{slug}` SEO meta missing:** InsightsSeoMetaInjector middleware didn't fire. Check `routes/web.php` ordering (insights route before `/{any}`) and `app/Http/Kernel.php` alias registration.
- **Architecture test suddenly fails:** you've changed route ordering. Read its failure message — it tells you exactly which constraint broke.
- **Revisions test fails in suite but passes in isolation:** `auth()->logout()` in `beforeEach` fixes it; already applied (commit `f28c300`).
- **Publish button does nothing:** check console errors. PreviewWriteInterceptor may be catching the POST if the admin has `is_preview_user=true`. Chris was reseeded with ROLE_ADMIN (commit `12e7dda`) but make sure `is_preview_user=false`.

Read `storage/logs/laravel.log` first. Don't guess.

---

## 9. Commit list (reverse chronological)

```
4492e2e docs(insights): deploy guide for CMS rollout
f28c300 fix(insights): deterministic revision ordering + auth reset in test
b6d6efd test(insights): architecture guardrails for route ordering + tax hardcoding
12e7dda fix(seeders): assign ROLE_ADMIN to chris@fynla.org for local parity
9136d68 feat(insights): VITE_INSIGHTS_CMS_ENABLED flag for phased production rollout
5c5d2be feat(insights): add Insights tab to AdminPanel sidebar
2bfe221 feat(insights): admin router entries
23e5c2a feat(insights): admin template list page
a07c0d3 feat(insights): admin article editor with split layout + block canvas
5cbc0c3 feat(insights): block picker modal + bespoke article notice
984b3f4 feat(insights): 11 admin block edit components
7922632 feat(insights): admin article list page
29e8a1c chore(insights): safelist border-*-500 accents for CalloutBlock variants
2f7c8ba test(insights): backend contract — body_blocks round-trip
ab408a1 refactor(insights): landing page hero — 2/3 featured + 1/3 stacked supporting
19eb51a refactor(insights): hub page now DB-driven via Vuex
1df5d18 feat(insights): add /insights/:slug catch-all route (after named routes)
88b1e48 feat(insights): public article page with block rendering
cad92e8 feat(insights): block renderer dispatching to 11 per-block components
6644c2a feat(insights): 11 public block components (all design-system compliant)
78185bc chore(insights): add dompurify for frontend HTML sanitisation
10657fc feat(insights): Vuex module for public + admin state
dd733dd feat(insights): frontend API service wrapper
a626ac4 fix(insights): move @stack('head') above static meta so per-article SEO wins
6970ade feat(insights): server-render SEO meta tags for DB-driven articles
bd040e1 feat(insights): public API (list, featured, show-by-slug with admin preview)
2c62366 feat(insights): admin image upload endpoint
2802457 feat(insights): admin template controller (list, save-from-article, rename, delete)
ec4ba85 feat(insights): admin article controller with publish/feature/archive/revisions
981963a feat(insights): add API resources for article list/detail and template
1ccbe1a fix(insights): preserve HTML in body_blocks through SanitizeInput middleware
5b1cb33 feat(insights): add form requests for article/template/image endpoints
41b65d1 feat(insights): add BlockValidator covering all 11 block types
7065146 feat(insights): scheduled publish job running every 5 minutes
2a7d70e feat(insights): observer for revisions + featured cache busting
0cca273 feat(insights): add InsightSeoService for meta tags and JSON-LD
f9db4cc feat(insights): add InsightTemplateService
1ee393d feat(insights): add InsightImageService with WebP resize pipeline
9f897f0 chore(insights): add intervention/image for image resizing
fdc1ace feat(insights): add InsightArticleService with publish, feature, resync
78f5b88 feat(insights): seed metadata for 8 bespoke existing articles
bb68790 feat(insights): add factories for article, template, revision models
ce95472 feat(insights): add InsightArticleRevision append-only model
c7c031f feat(insights): add InsightTemplate model
14eeac0 feat(insights): add InsightArticle model
a483342 feat(insights): add insight_article_revisions table migration
36c6a74 feat(insights): add insight_templates table migration
497accd feat(insights): add insight_articles table migration
6e87c27 docs: baseline — admin insights CMS spec + plan
```

---

## 10. One-liner for when you pick this up

> "Picking up the Insights CMS deploy. I've read the handover, the deploy guide, and the commit list. Feature is fully built, tested, and browser-verified locally. My job is: PR to dev → deploy to csjones.co (ASK which branch is currently on the dev server first) → smoke test → PR to main → deploy backend to fynla.org with flag=false → verify API → flip flag → redeploy frontend → smoke test → update CSJTODO. Starting with the PR to dev unless you want me to do something else first."
