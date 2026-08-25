# Deploy Guide — News Hub + Newsletter Subscribe + Lifecycle Email System

**Branch:** `feature/phailanx/news-rss-lifecycle-emails`
**Target:** `dev` → `csjones.co/fynla` (staging)
**Date:** 2026-04-28 (last revised after modal commit `8bd205f`)
**Commits ahead of `origin/dev`:** 31
**Files changed:** 93 (+5,537/−94 lines)

This deploy bundles **three streams of work** that have not yet reached production:

1. **PR-237 work** (squashed to `fa6d6c6`) — news hub, RSS feeds, lifecycle email infrastructure, landing-page restoration. Reviewed in `April/April28Updates/PR-237-review.md`.
2. **News-subscribe-fix work** (`b56a341` … `0a68657`) — public newsletter signup form + double opt-in flow + admin retrieval UI. Implementation plan: `April/April28Updates/news-subscribe-fix-plan.md`.
3. **Newsletter-confirm-modal work** (`8bd205f`) — replaces the standalone `newsletter/confirmed` and `newsletter/unsubscribed` blade pages with a `NewsletterStatusModal.vue` opened on `/news` via query param (`?subscribed=1`, `?subscribed=already`, `?unsubscribed=1`). Controller now redirects instead of rendering a view.

Generated from `git diff origin/dev..HEAD --name-status`. **Do not edit this guide from memory** — re-run the diff if the branch advances after this is written.

---

## 0. Prerequisites — before you build

- [ ] Local browser tests passed (5 paths, see `news-subscribe-fix-plan.md` Task 19-20).
- [ ] Full Pest suite runs: `./vendor/bin/pest tests/Unit/Mail/Newsletter/ tests/Feature/Api/Public/NewsSubscriberControllerTest.php tests/Feature/NewsletterActionControllerTest.php tests/Feature/Api/Admin/NewsSubscriberControllerTest.php` → 23 passed (80 assertions).
- [ ] PR `feature/phailanx/news-rss-lifecycle-emails` → `dev` opened, self-reviewed, **merged on GitHub**.
- [ ] `git checkout dev && git pull` — your local `dev` is at the new merge SHA.
- [ ] Confirm dev SMTP relay is configured in `~/www/csjones.co/public_html/fynla/.env` and can send from `marketing@fynla.org`. If not, the confirmation email will never arrive (queue runs `sync` on dev) and the double-opt-in flow looks broken from the user's perspective.

**Do not skip any checkbox.** Lessons from prior sessions (see `CSJTODO.md` Known Issues): build script path echo is outdated, .htaccess collisions break Revolut/fonts, missing files surface as 500s with no obvious cause.

---

## 1. Database changes

Two new migrations land in this deploy. Both have `Schema::hasTable()` guards so re-running is a no-op (lesson: PR-237-review Finding #4).

| File | Creates table | Notes |
|---|---|---|
| `database/migrations/2026_04_27_120000_create_news_articles_table.php` | `news_articles` | Composite index `[status, published_at]`. |
| `database/migrations/2026_04_28_120000_create_news_subscribers_table.php` | `news_subscribers` | Composite index `[unsubscribed_at, confirmed_at]`. Unique on `email` and `confirmation_token`. |

Seeders to run after migrate:

| Seeder | Result |
|---|---|
| `NewsArticleSeeder` (idempotent — `updateOrCreate`) | Inserts the "Launching Fynla" announcement at slug `launching-fynla`. Without this the `/news` page shows the empty state. |

`DatabaseSeeder.php` was updated to include `NewsArticleSeeder` in Phase 1, so a full `php artisan db:seed --force` will pick it up. If you only want the news article: `php artisan db:seed --class=NewsArticleSeeder --force`.

**No data loss risk.** No existing tables are touched. **Never run `migrate:fresh` or `migrate:refresh`** (CLAUDE.md Rule).

---

## 2. Environment variables — add to `~/www/csjones.co/public_html/fynla/.env`

These are NEW and NOT in git. SSH in and add them before the first request hits the new code:

```env
# Newsletter (marketing) from-address — Group A of news-subscribe-fix
MAIL_MARKETING_FROM_ADDRESS="marketing@fynla.org"
MAIL_MARKETING_FROM_NAME="Fynla"
```

Confirm existing dev settings are intact:

```env
APP_ENV=staging
QUEUE_CONNECTION=sync     # mail queue runs synchronously on dev — no worker needed
MAIL_MAILER=smtp          # must be configured to relay marketing@ outbound
LIFECYCLE_TEST_RECIPIENT=chris@fynla.org   # PR-237 lifecycle test command override
APP_DEBUG=true
REVOLUT_SANDBOX=true
```

If any of these are missing, copy from `deploy/csjones-fynla/.env.production` (the template).

---

## 3. Files to upload

Categorized by directory. Source paths are relative to repo root; destination is `~/www/csjones.co/public_html/fynla/<same path>`.

### 3.1 Backend — PHP, **REQUIRED at runtime**

```
app/Console/Commands/SendLifecycleTestCommand.php                   (new)
app/Http/Controllers/Api/Admin/NewsSubscriberController.php         (new)
app/Http/Controllers/Api/Public/NewsController.php                  (new)
app/Http/Controllers/Api/Public/NewsSubscriberController.php        (new)
app/Http/Controllers/FeedController.php                             (new)
app/Http/Controllers/NewsletterActionController.php                 (new)
app/Http/Middleware/PreviewWriteInterceptor.php                     (modified)
app/Http/Resources/News/NewsArticleListResource.php                 (new)
app/Http/Resources/News/NewsArticleResource.php                     (new)
app/Mail/Lifecycle/CountdownMail.php                                (new)
app/Mail/Lifecycle/DontMissOutMail.php                              (new)
app/Mail/Lifecycle/EndOfTrialMail.php                               (new)
app/Mail/Lifecycle/GetStartedMail.php                               (new)
app/Mail/Lifecycle/GreatJobMail.php                                 (new)
app/Mail/Lifecycle/InsightsMail.php                                 (new)
app/Mail/Lifecycle/LifecycleMail.php                                (new)
app/Mail/Lifecycle/SubscribeInProgressMail.php                      (new)
app/Mail/Lifecycle/SubscribeMaxDiscountMail.php                     (new)
app/Mail/Lifecycle/WeHaventSeenYouMail.php                          (new)
app/Mail/Lifecycle/WelcomeMail.php                                  (new)
app/Mail/Lifecycle/WellDoneMail.php                                 (new)
app/Mail/Newsletter/NewsletterConfirmationMail.php                  (new)
app/Mail/Newsletter/NewsletterWelcomeMail.php                       (new)
app/Models/News/NewsArticle.php                                     (new)
app/Models/News/NewsSubscriber.php                                  (new)
config/mail.php                                                     (modified — adds 'marketing' from block)
```

### 3.2 Database — migrations + seeders + factories

```
database/factories/NewsArticleFactory.php                           (new)
database/factories/NewsSubscriberFactory.php                        (new)
database/migrations/2026_04_27_120000_create_news_articles_table.php       (new)
database/migrations/2026_04_28_120000_create_news_subscribers_table.php    (new)
database/seeders/DatabaseSeeder.php                                 (modified)
database/seeders/NewsArticleSeeder.php                              (new)
```

### 3.3 Frontend — Vue source (NOT served directly; built into `public/build/`)

```
resources/js/components/News/NewsSubscribeBanner.vue                (new)
resources/js/components/News/NewsletterStatusModal.vue              (new — confirm/unsubscribe modal)
resources/js/layouts/PublicLayout.vue                               (modified)
resources/js/router/index.js                                        (modified)
resources/js/services/newsService.js                                (new)
resources/js/services/newsSubscriberService.js                      (new)
resources/js/views/Admin/NewsSubscribersPage.vue                    (new)
resources/js/views/Public/AboutPage.vue                             (modified)
resources/js/views/Public/CampaignPage.vue                          (modified)
resources/js/views/Public/LandingPage.vue                           (modified)
resources/js/views/Public/NewsArticlePage.vue                       (new)
resources/js/views/Public/NewsHubPage.vue                           (new)
```

These get **compiled into `public/build/`** by the build script — you don't upload the `.vue` files themselves except as repo content. The Vite output IS what serves the SPA.

### 3.4 Email blades + standalone Blade pages — REQUIRED at runtime

```
resources/views/app.blade.php                                       (modified — meta tags, favicon path)
resources/views/emails/layouts/master.blade.php                     (new)
resources/views/emails/lifecycle/{11 templates}.blade.php           (new — see PR-237-review.md)
resources/views/emails/modules/{21 partials}.blade.php              (new — see email-template skill)
resources/views/emails/newsletter/confirm-subscription.blade.php    (new)
resources/views/emails/newsletter/welcome.blade.php                 (new)
```

> **Note:** `resources/views/newsletter/confirmed.blade.php` and `resources/views/newsletter/unsubscribed.blade.php` were introduced earlier on this branch but **deleted by commit `8bd205f`** (replaced by the modal). The whole `resources/views/newsletter/` directory should not exist on dev after upload — if rsync leaves it from a prior partial sync, ssh in and `rm -rf ~/www/csjones.co/public_html/fynla/resources/views/newsletter`. Laravel won't error if the directory is absent (the controller now redirects).

### 3.5 Routes — REQUIRED

```
routes/api.php                                                      (modified — public news + subscribe + admin endpoints)
routes/web.php                                                      (modified — RSS feeds + newsletter confirm/unsubscribe)
```

### 3.6 Vite-built assets — REQUIRED, regenerated by build script

```
public/build/                                                       (entire directory rebuilt)
public/build/manifest.json                                          (Vite asset manifest)
public/build/assets/{hashed-files}                                  (compiled JS/CSS/images)
```

**Do NOT upload the `public/build/` directory while the dev site is serving traffic** without the merge-on-upload trick: `cp -rn build.old/. build/` after upload to preserve any stragglers (lesson: `feedback_warn_before_spa_rebuild.md`).

### 3.7 Static binary — REQUIRED for landing page video

```
public/images/Homepage-Fynla-ProductVideov2.mp4                     (new — 14 MB)
```

This 14 MB MP4 is committed to the repo. SiteGround File Manager handles single files of this size fine. If using rsync it will transfer once and stay cached for future deploys.

### 3.8 Test files — NOT required for runtime, but ship with the codebase

```
tests/Feature/Api/Admin/NewsSubscriberControllerTest.php            (new)
tests/Feature/Api/Public/NewsSubscriberControllerTest.php           (new)
tests/Feature/NewsletterActionControllerTest.php                    (new)
tests/Pest.php                                                      (modified — Unit/Mail bind)
tests/Unit/Mail/Newsletter/NewsletterMailRenderTest.php             (new)
```

If you sync the whole repo, these come along; they don't affect runtime. If you cherry-pick uploads (file-manager style), you can skip them for the first deploy and add later.

### 3.9 Project metadata — NOT served at runtime, ship if you sync the repo

```
.claude/skills/email-template/SKILL.md                              (new — Claude Code skill, dev-only)
.env.example                                                        (modified — adds MAIL_MARKETING_FROM_*)
CSJTODO.md                                                          (modified — adds follow-ups)
```

---

## 4. Build (run locally — server lacks RAM)

```bash
cd /Users/CSJ/Desktop/fynla
./deploy/csjones-fynla/build.sh
```

This sets:
- `VITE_BASE_PATH=/fynla/build/`
- `VITE_ROUTER_BASE=/fynla/`
- `VITE_API_BASE_URL=https://csjones.co/fynla`
- `VITE_REVOLUT_SANDBOX=true`

After the script completes, `public/build/` contains the new bundle. **Verify before upload:**

```bash
ls -la public/build/manifest.json public/build/assets | head -10
```

Confirm `manifest.json` exists and `assets/` is non-empty.

**⚠️ NEVER use `npx vite build` directly for csjones.co/fynla** — it produces `/build/` paths instead of `/fynla/build/` and the SPA loads as a blank page on `csjones.co/fynla`.

---

## 5. Upload to server

You have two options:

### Option A — SiteGround File Manager (manual, safe)

1. Log in to SiteGround → Site Tools → File Manager → `csjones.co` → `public_html/fynla/`.
2. Upload the directories from §3.1–3.7 in order. The File Manager will overwrite when files exist.
3. For `public/build/`: upload the contents of your local `public/build/` into the server's `public/build/`. Older hashed assets will linger — that's fine and expected.
4. Upload `public/images/Homepage-Fynla-ProductVideov2.mp4` via the File Manager's "Upload File" option.

### Option B — `rsync` over SSH (faster, scriptable)

```bash
rsync -avz --exclude=node_modules --exclude=.git --exclude=storage/logs \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  ./ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/public_html/fynla/
```

This syncs the entire repo. Excludes `node_modules`, `.git`, and `storage/logs` so they don't churn.

**⚠️ Do NOT include `--delete`** — that would wipe production storage uploads, customer cache, etc.

Per the user's CLAUDE.md note: "the user uploads files manually via SiteGround File Manager" — Option A is the canonical path. Option B is for when you have SSH muscle memory.

---

## 6. SSH and finalise

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
```

Once on the server:

```bash
cd ~/www/csjones.co/public_html/fynla

# 1. Run new migrations (idempotent)
php artisan migrate --force

# 2. Seed the launch news article (idempotent — updateOrCreate)
php artisan db:seed --class=NewsArticleSeeder --force

# 3. Clear all caches (config picks up new MAIL_MARKETING_* env vars)
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize

# 4. Verify the new routes registered
php artisan route:list --path=news/subscribe
php artisan route:list --path=subscribe/news
php artisan route:list --path=unsubscribe/news
php artisan route:list --path=admin/news-subscribers

# 5. Verify mail config loaded
php artisan tinker --execute="echo config('mail.marketing.address');"
```

Expected outputs:
- 4 routes registered (`POST api/news/subscribe`, `GET subscribe/news/confirm/{token}`, `GET unsubscribe/news/{token}`, `GET api/admin/news-subscribers` + `/export`).
- `marketing@fynla.org` printed.

**If any cache step errors out** — investigate, do not skip. Cached config from before the .env edit will surface as `null`-from-address mail send failures.

---

## 7. Smoke tests on `https://csjones.co/fynla`

Drive each path in a real browser. Do not skip steps. Check for screenshot evidence if you're working from CSJTODO handover.

### 7.1 News hub renders + launch article visible

1. Visit `https://csjones.co/fynla/news` in incognito.
2. Page title `News | Fynla`.
3. Featured hero card shows "Launching Fynla: a new personal finance platform built to change your financial future" with the date "27 April 2026" and "By The Fynla Team".
4. Subscribe banner appears above the featured card with: "Subscribe to Fynla news" + email input + "Subscribe" button.
5. Bottom CTA section "Want to stay updated?" still has "Or subscribe via RSS" link (intentional, kept for tech users).

### 7.2 Subscribe happy path → confirm → welcome

1. On `/news`, type `dev-test-1@fynla.org` in the email input.
2. Click `Subscribe`. Inline message reads "Check your inbox to confirm your subscription."
3. Check the inbox at `dev-test-1@fynla.org` (or whatever forwarding rule dev uses) — confirmation email arrives **from** `marketing@fynla.org` (the From: header), with subject "Confirm your subscription to Fynla news", and a "Confirm subscription" CTA button.
4. Click the button → browser lands on `https://csjones.co/fynla/news?subscribed=1` (the controller redirects after confirming).
5. **Modal opens** on top of the news hub: raspberry header "You're subscribed", body "Thanks for confirming. You're now on the Fynla news list.", footer Close button. Click Close → URL returns to `/news` (query param cleared via `router.replace`).
6. Re-clicking the same confirm link redirects to `/news?subscribed=already` and the modal heading reads "You're already subscribed".
7. Welcome email arrives from `marketing@fynla.org` with subject "You're subscribed to Fynla news", containing an "Unsubscribe" link in the dark footer.

### 7.3 Subscribe → already-registered Fynla user

1. Submit an email that exists in `users` (e.g. a known dev test account).
2. Inline message: "You're already registered with Fynla — sign in to manage your news preferences." with a working `/login` link.
3. SSH check: `php artisan tinker --execute="echo \App\Models\News\NewsSubscriber::where('email', '<that-email>')->exists() ? 'BAD' : 'OK';"` → `OK` (no row created).

### 7.4 Subscribe → resend

1. Submit `dev-test-2@fynla.org` (fresh email) — get "Check your inbox".
2. Submit it again on a fresh page load — get "Confirmation email re-sent — check your inbox."
3. SSH: `php artisan tinker --execute="echo \App\Models\News\NewsSubscriber::where('email', 'dev-test-2@fynla.org')->first()->confirmation_token;"` — different from the first token.

### 7.5 Rate limit

1. From a fresh browser session, submit 4 different fresh emails on `/news` (reload between each because the form hides after success).
2. The 4th submit shows "Too many attempts. Please try again in a few minutes." in the alert region.
3. SSH: confirm only 3 rows in `news_subscribers` for that batch.

### 7.6 Unsubscribe

1. Click the Unsubscribe link in the welcome email from §7.2.
2. Browser lands on `https://csjones.co/fynla/news?unsubscribed=1` and a modal opens with horizon (dark blue) header "You've unsubscribed", body "We've removed you from the Fynla news list…", sub-body "Changed your mind? Just sign up again from the news page." Click Close → URL returns to `/news`.
3. SSH: confirm `unsubscribed_at` is set on that row.
4. Re-clicking the same unsubscribe link redirects again to `/news?unsubscribed=1` (idempotent — `unsubscribed_at` does not change).

### 7.7 Admin page

1. Sign in as Chris (or whichever dev admin you use).
2. Navigate to `https://csjones.co/fynla/admin/news-subscribers`.
3. Page loads with the rows from the smoke tests above.
4. Click the `Confirmed` filter chip → only the §7.2 confirmed test row shows.
5. Click `All` → search by `dev-test-1` → narrows to one row.
6. Click `Export CSV` → file downloads, first line is `email,status,source,...`.

### 7.8 RSS regression check (PR-237 work shouldn't break)

```bash
curl -sI https://csjones.co/fynla/feed/news.xml | head -2
curl -sI https://csjones.co/fynla/feed/insights.xml | head -2
```

Both should return `HTTP/1.1 200 OK` with `Content-Type: application/rss+xml; charset=UTF-8`.

### 7.9 Lifecycle email infrastructure (PR-237 work)

`php artisan mail:send-lifecycle-test chris@fynla.org --only=welcome` (using `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org` override for safety).
- Should send one test welcome email. Does NOT touch the new newsletter system.

### 7.10 Landing page (PR-237 work)

1. Visit `https://csjones.co/fynla/` (the homepage).
2. Stats bar reads "91% / UK adults don't get financial advice" + "1000's / of financial plans created for people like you".
3. Latest insights section either shows the three static fallback articles (if `VITE_INSIGHTS_CMS_ENABLED !== 'true'`) or DB-backed cards.
4. Hero video has the new `Homepage-Fynla-ProductVideov2.mp4` source with click-to-play overlay (no autoplay).

---

## 8. Post-deploy verification (within first 15 min)

Watch for errors:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co \
  "tail -f ~/www/csjones.co/public_html/fynla/storage/logs/laravel.log"
```

Common warning signs:
- `Mail::queue` errors with `null` from-address → `MAIL_MARKETING_FROM_ADDRESS` not set OR `php artisan config:clear` not run.
- 500 on `/news` → `news_articles` table missing OR `NewsArticleSeeder` not seeded.
- 404 on `/subscribe/news/confirm/{token}` → `routes/web.php` not uploaded OR cache not cleared.
- Confirm/unsubscribe link 302s correctly but `/news` shows the page **without** the modal → SPA build is stale (NewsletterStatusModal.vue not in the bundle). Re-build with `./deploy/csjones-fynla/build.sh` and re-upload `public/build/`.
- 419 (CSRF) on `POST /api/news/subscribe` → middleware order broken (this is a stateless POST, should not need CSRF — investigate `app/Http/Middleware/VerifyCsrfToken.php` exclusions).
- Blank `/admin/news-subscribers` page → SPA build mismatch (`/fynla/build/` paths wrong) — rebuild with `./deploy/csjones-fynla/build.sh` and re-upload `public/build/`.

---

## 9. Rollback plan

If smoke tests fail and root cause isn't obvious within 5 minutes:

```bash
# On the server
cd ~/www/csjones.co/public_html/fynla

# 1. Identify the previous good SHA (e.g., the previous deploy's SHA)
# Read .deploy-sha or whatever convention you use — or use the last
# commit before today's deploy from the dev branch's reflog locally.

# 2. Rolling back the migration is OPTIONAL — both new tables are
# additive and harmless. Only roll back if you specifically want a clean state:
php artisan migrate:rollback --step=2

# 3. Restore previous code via Git/SCP/SiteGround File Manager from your
# backup of public/, app/, resources/, routes/, config/.

# 4. Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear

# 5. Smoke test https://csjones.co/fynla still loads.
```

**The new tables (`news_articles`, `news_subscribers`) are additive — leaving them in place after a code rollback causes no errors.** The next forward deploy will find them and skip recreation thanks to `Schema::hasTable` guards.

---

## 10. After dev is green — promotion to production

After `csjones.co/fynla` runs cleanly for a sensible soak window:

1. Open PR `dev → main` (only `@Stoff73` opens this).
2. Merge after self-approval.
3. Build with `./deploy/fynla-org/build.sh` (production env vars: `VITE_BASE_PATH=/build/`, `VITE_ROUTER_BASE=/`, `VITE_REVOLUT_SANDBOX=false`).
4. Upload to `~/www/fynla.org/public_html/`.
5. SSH to fynla.org server, run the same migrate / seed / cache-clear sequence.
6. Add `MAIL_MARKETING_FROM_ADDRESS=marketing@fynla.org` + `MAIL_MARKETING_FROM_NAME="Fynla"` to the **production** `.env`.
7. Confirm production SMTP can deliver from `marketing@fynla.org` (DKIM, SPF, DMARC alignment for the marketing subdomain — escalate to whoever owns DNS if SMTP rejects with 530/550).
8. Smoke-test §7.1–7.10 against `https://fynla.org/...` paths.

---

## 11. Cross-references

| File | Purpose |
|---|---|
| `April/April28Updates/PR-237-review.md` | The original review of PR-237 (news hub + RSS + lifecycle emails). Findings #4, #5 already fixed in `fa6d6c6`. Finding #16 (zero new tests) partially closed by news-subscribe-fix. |
| `April/April28Updates/news-subscribe-fix-plan.md` | The 26-task implementation plan executed in this branch. |
| `CSJTODO.md` "Follow-ups from news-subscribe-fix (2026-04-28)" | Newsletter broadcast + remaining test coverage gaps. |
| `.claude/skills/email-template/SKILL.md` | Email template rules (Rule 1-7) — applied to the two newsletter blades. |
| `deploy/csjones-fynla/build.sh` | The build script for this target. |
| `deploy/csjones-fynla/.env.production` | The dev `.env` template. |

---

## 12. Final commit reference

```
8bd205f feat(news): replace newsletter confirm/unsubscribe pages with modal on news hub
b5d010c docs: session 72 end — CSJTODO handover, metrics, tech-debt report
0a68657 chore(news): hide 'Prefer RSS' link in subscribe banner
5c20a0d chore(news): apply pint formatting + add follow-ups to CSJTODO
7481aa2 feat(news): add admin page to list and export news subscribers
395d1ad feat(news): add CSV export endpoint for news subscribers
2e580b9 feat(news): add admin endpoint for listing news subscribers
007ec31 feat(news): replace RSS-XML link with NewsSubscribeBanner on news hub
2e2ccfd feat(news): add NewsSubscribeBanner Vue component with all signup states
9018249 feat(news): add newsSubscriberService API wrapper
188c79e fix(news): use correct Tailwind tokens and favicon path on newsletter pages
9094692 feat(news): add newsletter unsubscribe action and confirmation page
361c261 feat(news): add newsletter confirm action with welcome email send
a6291aa test(news): cover resubscribe-after-unsubscribe and email normalisation
62dc79c refactor(news): queue confirmation mail and rate-limit before validation
9eeb212 test(news): cover validation and rate-limit on subscribe endpoint
3c14e7a test(news): cover already-confirmed and pending-resend subscribe paths
8399d11 test(news): assert registered Fynla users are not added to subscriber list
b56a341 feat(news): add public newsletter subscribe endpoint with confirmation email
f08ed78 refactor(mail): expand rule-2 adjacency comments and drop unused rssUrl
e6e45db test(mail): assert newsletter mailables render confirm/unsubscribe URLs
5c276cf feat(mail): add newsletter welcome blade template with unsubscribe footer
04a1dad feat(mail): add NewsletterWelcomeMail mailable
3786f92 feat(mail): add confirm-subscription blade template
0dbc704 feat(mail): add NewsletterConfirmationMail mailable
2d5bd3b refactor(news): move NewsSubscriber to News namespace and use composite index
6a66ed5 feat(mail): add marketing from-address config for newsletter sends
16d2b84 feat(news): add NewsSubscriber model with confirmed/pending/unsubscribed scopes
efb803f feat(news): add news_subscribers table for newsletter signups
fa6d6c6 fix: address remaining PR #237 review findings 3, 6, 8-15
5b25f1a feat: news hub + RSS feeds + lifecycle email system + landing fixes
```

Last commit on dev before this deploy: see `git rev-parse origin/dev` immediately before merging the PR.
