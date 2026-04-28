# CSJTODO — Fynla

*Last updated: 28 April 2026 — session 72 (news subscriber email-list signup + PR #238)*
*Previous session: 27 April 2026 — session 71 (RSS news hub + landing-page restoration + PR #237)*

---

## Session 72 (28 April 2026) — News subscriber email-list signup

**Branch:** `feature/phailanx/news-rss-lifecycle-emails` (29 commits ahead of `origin/dev`, all pushed).
**PR:** [#238](https://github.com/Stoff73/fynla/pull/238) `feature/phailanx/news-rss-lifecycle-emails → dev` open, **replaces #237** (which was branch-naming-violation; squashed and rebased onto the convention-compliant branch in `fa6d6c6`). Self-review pending.
**Note:** today bundles two streams — PR-237 squash (`fa6d6c6`) carries the news hub + RSS feeds + lifecycle email infrastructure unchanged; commits since are the new news-subscribe-fix work.

### Completed this session

#### Bug discovery + plan
- [x] CSJ flagged that `/news` "Subscribe to our news feed" banner sent users to raw `/feed/news.xml` XML page instead of capturing emails — confirmed in browser. Root cause: `NewsHubPage.vue:21` was `<a href="/feed/news.xml" target="_blank">`.
- [x] Wrote `April/April28Updates/news-subscribe-fix-plan.md` (26-task implementation plan with file paths, code blocks, commit messages, test assertions, and explicit cross-references to PR-237-review.md findings #16, #8, #11, #3 and B2).
- [x] CSJ approved 5 design decisions: double opt-in, list-only (broadcast deferred), one-click unsubscribe, registered-Fynla-user gets sign-in inline link (no row created), `PreviewWriteInterceptor::EXCLUDED_ROUTES` exclusion.

#### Group A — DB schema + model + mail config (commits `efb803f`, `16d2b84`, `6a66ed5`, `2d5bd3b`)
- [x] Migration `2026_04_28_120000_create_news_subscribers_table.php` with `Schema::hasTable` guard + composite index `[unsubscribed_at, confirmed_at]`.
- [x] `App\Models\News\NewsSubscriber` model with `confirmed`/`pending`/`unsubscribed` scopes (typed `Builder $query): Builder` matching peer `NewsArticle::scopePublished`), `generateToken()` static helper, `isConfirmed()`/`isPending()` instance helpers.
- [x] `config/mail.php` adds `marketing` from-block reading `MAIL_MARKETING_FROM_ADDRESS`/`NAME` env vars; `.env.example` has the new keys.
- [x] Reviewer round 1 caught namespace mismatch (model was at `App\Models\NewsSubscriber`, peer `NewsArticle` is at `App\Models\News\NewsArticle`); single-column indexes vs composite. Both fixed in `2d5bd3b`.

#### Group B — Mailables + blades + factory + render tests (commits `0dbc704`, `3786f92`, `04a1dad`, `5c276cf`, `e6e45db`, `f08ed78`)
- [x] `NewsletterConfirmationMail` + `NewsletterWelcomeMail` Mailables (queueable, from `marketing@fynla.org`).
- [x] `confirm-subscription.blade.php` + `welcome.blade.php` extending `emails.layouts.master` per the `email-template` skill rules with Rule-2 adjacency walks documented inline.
- [x] `NewsSubscriberFactory` with `confirmed()` and `unsubscribed()` states using `fake()` (NOT `$this->faker`) per PR-237 Finding #5.
- [x] `tests/Pest.php` extended with `uses(Tests\TestCase::class)->in('Unit/Mail')` mirroring the `BaseAgentTest` precedent (no DB needed for render tests).
- [x] `App\Models\News\NewsSubscriber::newFactory()` resolver added because Laravel resolved `Database\Factories\News\NewsSubscriberFactory` first and never fell back.
- [x] 3 unit tests (`NewsletterMailRenderTest`) — confirm URL, unsubscribe URL, marketing from-address — all pass.
- [x] Reviewer round caught Rule-2 comment scope (extended to cover full eggshell band) + unused `rssUrl` view variable. Fixed in `f08ed78`.

#### Group C — Public subscribe controller + 8 feature tests (commits `b56a341`, `8399d11`, `3c14e7a`, `9eeb212`, `62dc79c`, `a6291aa`)
- [x] `Api\Public\NewsSubscriberController::subscribe()` with 5 response branches: rate_limited / pending_confirmation / already_registered / already_confirmed / 422 validation. IP-keyed `RateLimiter` (3 per 5 min) + route-level `throttle:5,1` belt-and-braces.
- [x] Route `POST /api/news/subscribe` added INSIDE existing `Route::prefix('news')` group, BEFORE `{slug}` (otherwise matched as a slug).
- [x] `'api/news/subscribe'` added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`.
- [x] 8 feature tests: happy path / already-registered / already-confirmed / pending-resend (token rotates) / 422 invalid / 429 rate-limit / resubscribe-after-unsubscribe / mixed-case email normalisation. All pass.
- [x] Reviewer round caught synchronous `Mail::send` on a public anonymous endpoint (timing-amplifies the user-enumeration oracle); fixed by switching to `Mail::queue()`. Also moved `RateLimiter::hit()` BEFORE `validate()` so spam can't bypass.
- [x] Discovery: `Mail::fake()` actually does separate `send` vs `queue` — `assertSent` does not catch queued mail. Switched all 5 existing assertions to `assertQueued`.
- [x] Discovery: MySQL `utf8mb4_unicode_ci` is case-insensitive — adapted normalisation test to assert via stricter PHP comparison instead of relying on `where()` to be case-sensitive.

#### Group D — Web confirm/unsubscribe controller + 6 tests (commits `361c261`, `9094692`, `188c79e`)
- [x] `NewsletterActionController::confirm($token)` and `unsubscribe($token)` — both idempotent, both `firstOrFail()` → 404 on bad token.
- [x] Routes `GET /subscribe/news/confirm/{token}` + `GET /unsubscribe/news/{token}` in `routes/web.php` BEFORE the SPA catch-all, with `where('token', '[A-Za-z0-9]{48}')` regex (matches `Str::random(48)` base62).
- [x] Standalone Blade pages `newsletter/confirmed.blade.php` + `newsletter/unsubscribed.blade.php` — full HTML, no SPA shell. After reviewer round: corrected hex tokens (`#f5f0eb` → `#F7F6F4` is the WEB Tailwind token; `#e74c6f` → `#E83E6D` is web; the email-template skill's `#f5f0eb`/`#e74c6f` are the EMAIL context tokens, distinct concept). Added favicon link `{{ asset('images/logos/favicon.png') }}`.
- [x] 6 feature tests: confirm-pending / 404-invalid-confirm / idempotent-confirm / unsubscribe-confirmed / 404-invalid-unsubscribe / idempotent-unsubscribe. All pass.
- [x] Discovery: `assertSee` HTML-escapes by default; needed `, false` second arg to match the literal `'` in "You're"/"You've" against the rendered Blade.

#### Group E — Frontend service + banner component + integration (commits `9018249`, `2e2ccfd`, `007ec31`, `0a68657`)
- [x] `newsSubscriberService.js` API wrapper (single `subscribe(email)` POST to `/news/subscribe`).
- [x] `NewsSubscribeBanner.vue` Vue component (Options API, multi-word name) with 5 UI states: idle/error/pending_confirmation/already_registered/already_confirmed. Status-string contract matches backend exactly. Accessibility: `sr-only` label, `aria-hidden` on decorative SVG, `role="alert"` errors, `role="status"` messages. `<router-link to="/login">` for sign-in CTA in already-registered state. Design-system tokens only (no hardcoded hex).
- [x] `NewsHubPage.vue` lines 20-33 broken `<a>` block replaced with `<NewsSubscribeBanner />`. Bottom "Want to stay updated?" CTA section UNCHANGED (PR-237 work, kept for tech users).
- [x] CSJ requested: hidden the in-banner "Prefer RSS? Subscribe via feed" link until newsletter broadcast lands. Done in `0a68657`.

#### Group F — Admin index + CSV export (commits `2e580b9`, `395d1ad`)
- [x] `Api\Admin\NewsSubscriberController` with `index` (paginated, status filter, email search) + `export` (streamed CSV, chunked 500 at a time).
- [x] Routes added to existing admin auth group `['auth:sanctum', 'permission:admin.access']` with prefix `admin/`. Constructor middleware `permission:admin.access` mirrors peer `InsightArticleController`.
- [x] 6 tests using `RolesPermissionsSeeder` + `Role::findByName(Role::ROLE_ADMIN)` + `role_id`+`is_admin=true` (the canonical admin auth pattern in this codebase, NOT `is_admin` alone). All pass.

#### Group G — Admin Vue page + router (commit `7481aa2`)
- [x] `resources/js/views/Admin/NewsSubscribersPage.vue` — `AppLayout`, `max-w-7xl mx-auto`, header with Export CSV button, 4 filter chips (All/Confirmed/Pending/Unsubscribed), email search with 250ms debounce, `card overflow-hidden` table with `bg-savannah-100` thead matching `ArticleListPage.vue`, status badges using `bg-spring-100 text-spring-700` / `bg-violet-100 text-violet-700` / `bg-light-gray text-neutral-500`, pagination, `formatDate` `en-GB` locale. CSV download via `responseType: 'blob'` + temporary `<a download>`.
- [x] Router entry `path: '/admin/news-subscribers', name: 'AdminNewsSubscribers', meta: { requiresAuth: true, requiresAdmin: true }` matching peer `AdminInsights` route shape.

#### Browser tests (5 paths verified end-to-end in Playwright on local)
- [x] Subscribe `playwright-test-1@example.com` → "Check your inbox" → DB row + token captured → navigate to confirm URL → "You're subscribed" page → `confirmed_at` set in DB.
- [x] Submit `john@example.com` (seeded user) → "You're already registered with Fynla — sign in" inline + `<router-link>` to `/login`. NO row created.
- [x] Resubmit `resend-test@example.com` after pending → "Confirmation email re-sent — check your inbox" + token rotated in DB.
- [x] 4× submits in 5 min from same IP → 4th gets "Too many attempts. Please try again in a few minutes." (alert role) + 4th email NOT in DB.
- [x] Visit `/unsubscribe/news/{token}` for confirmed subscriber → "You've unsubscribed" page + `unsubscribed_at` set.
- [x] Admin UI: 3 test rows render with status badges, `Confirmed` filter narrows to 1, `test2` search narrows to 1, `Export CSV` returns 200 with `text/csv; charset=UTF-8` + correct header row + 3 data rows.
- [x] Discovery: Pest's `RefreshDatabase` wiped users + news_articles after running the full feature suite. Ran `php artisan db:seed --force` to restore 14 users + launching-fynla article. (CLAUDE.md rule: "ALWAYS reseed after any operation that modifies or loses local database data".)

#### Final cleanup (commit `5c20a0d`)
- [x] Pint applied to all 13 new PHP files (4 style issues auto-fixed: `class_attributes_separation`, `single_line_empty_body`, `braces`).
- [x] Full new-test suite re-run after pint: **23 passing, 80 assertions, 0 failures**.
- [x] RSS feeds regression check: `/feed/news.xml` + `/feed/insights.xml` both still 200.
- [x] Two follow-ups added to `CSJTODO.md` (this file): Newsletter broadcast + PR-237 Finding #16 test coverage gap.

#### Deploy guide + PR
- [x] `April/April28Updates/deployNewsletter.md` written (12 sections, ~12 KB) — generated from `git diff origin/dev..HEAD --name-status` (NOT memory). Covers: prereqs, DB changes, env vars, 9 file categories to upload, build, upload options, SSH finalisation, 10 smoke-test paths, post-deploy log-watching, rollback plan, promotion-to-prod, cross-references, full commit reference.
- [x] PR #238 opened replacing #237. Title: "feat(news+emails): subscribe form + RSS hub + lifecycle emails (replaces #237)". Body covers all three streams + tests + browser verification + 17-item deploy/review checklist.
- [x] PR-237-review.md, news-subscribe-fix-plan.md, deployNewsletter.md all synced to `/Users/CSJ/Desktop/fynlaBrain/April/April28Updates/`.

### NOT Done — Outstanding for next session

#### Top priority — deploy PR #238 to dev (csjones.co/fynla)
- [ ] **Self-review and merge PR #238** on GitHub.
- [ ] **Add to dev `.env`**: `MAIL_MARKETING_FROM_ADDRESS=marketing@fynla.org`, `MAIL_MARKETING_FROM_NAME="Fynla"` (NEW — not in git).
- [ ] **Confirm SMTP relay can deliver from `marketing@fynla.org`** BEFORE first signup. Queue is `sync` on dev so a failing relay surfaces as a slow/erroring subscribe. Test: `php artisan tinker → Mail::raw('test', fn(\$m) => \$m->from('marketing@fynla.org')->to('chris@fynla.org')->subject('relay test'))->send();`
- [ ] **Build**: `./deploy/csjones-fynla/build.sh` (sets VITE_BASE_PATH=/fynla/build/, VITE_ROUTER_BASE=/fynla/, VITE_REVOLUT_SANDBOX=true).
- [ ] **Upload to** `~/www/csjones.co/fynla-app/` (NOT `public_html/fynla` — see `reference_csjones_sibling_dir.md` memory) per the 9 file categories in `deployNewsletter.md` §3.
- [ ] **SSH finalise**: `cd ~/www/csjones.co/fynla-app && php artisan migrate --force && php artisan db:seed --class=NewsArticleSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`
- [ ] **Verify routes**: `php artisan route:list --path=news/subscribe`, `--path=subscribe/news`, `--path=unsubscribe/news`, `--path=admin/news-subscribers` (4 expected, with correct middleware).
- [ ] **Smoke-test the 10 paths in `deployNewsletter.md` §7** on `https://csjones.co/fynla` — subscribe happy / registered / resend / rate-limit / unsubscribe / admin / RSS regression / lifecycle test / landing / video.
- [ ] **Watch `storage/logs/laravel.log`** for 15 min after first request.

#### After dev green — production deploy (fynla.org)
- [ ] PR `dev → main` opened (only `@Stoff73`).
- [ ] Build with `./deploy/fynla-org/build.sh` (production env vars: VITE_BASE_PATH=/build/, VITE_ROUTER_BASE=/, VITE_REVOLUT_SANDBOX=false).
- [ ] Upload to `~/www/fynla.org/public_html/`.
- [ ] Add `MAIL_MARKETING_FROM_*` to **production** `.env`. Confirm DKIM/SPF/DMARC alignment for `marketing@fynla.org` (escalate to whoever owns DNS if SMTP rejects).
- [ ] Same migrate/seed/cache-clear sequence on production server.
- [ ] Smoke-test all 10 paths against `https://fynla.org/...` URLs.
- [ ] Close PR #237 on GitHub with reference to PR #238 after production lands.

#### Tech debt items from this session (`tech-debt-report.md` — 9 issues, 0 critical)
- [ ] **Admin CSV export missing `throttle:export`** (`routes/api.php`, the export route streams subscriber emails + IPs, should be 3/hour-rate-limited per HTTP CLAUDE.md convention).
- [ ] **No AdminPanel sidebar entry** for `/admin/news-subscribers` — admins can only reach it by typing the URL. Add a link/card to `AdminPanel.vue`.
- [ ] **Standalone newsletter pages use raw `#555` for body text** instead of a design-system token. Defensible for non-Tailwind contexts but worth swapping to `#717171` (neutral-500) if you want palette purity.
- [ ] 6 other suggestions in `tech-debt-report.md` — none merge-blocking. Read for context if doing a polish pass.

#### Carried follow-ups added to CSJTODO this session
- [ ] **Newsletter broadcast** — when a `NewsArticle` flips to `status='published'`, fan out to confirmed `NewsSubscriber::confirmed()` rows. Queueable, paced (avoid SMTP 451 — see Session 67 lifecycle hotfix), skip subscribers who unsubscribe between queue + send.
- [ ] **PR-237 Finding #16** — News/RSS/lifecycle code from PR-237 (~1,000 lines) still has no tests. Open a separate PR with `NewsController`, `FeedController`, `NewsArticle::published()` scope, RSS XML schema validation, and Lifecycle Mailable construction tests.

### Context for next session

Branch: `feature/phailanx/news-rss-lifecycle-emails` (29 commits ahead of `dev`, all pushed). PR #238 is the merge target.

The user requested deployment to dev after PR review. They have the deploy guide at `April/April28Updates/deployNewsletter.md` — all steps are explicit. Most likely next-session ask: "merge #238 and deploy to dev". Read the deploy guide before doing anything.

The launch news article was missing on `/news` until `php artisan db:seed --class=NewsArticleSeeder --force` was run mid-session. Pest's `RefreshDatabase` wipes the DB whenever feature tests run — always reseed before browser-testing. CLAUDE.md "DB seed every session" lesson reinforced.

Two pieces of standing infrastructure now exist that future work should reuse: (1) the `email-template` skill and module library at `resources/views/emails/modules/` — every new email must use these per the skill rules; (2) the `App\Models\News\NewsSubscriber` namespace pattern — any future news-domain model should land at `App\Models\News\X` not `App\Models\X` (Group A reviewer caught this drift; mirrors peer `NewsArticle`).

### Files written this session (local, gitignored)

- `April/April28Updates/news-subscribe-fix-plan.md` (26-task implementation plan)
- `April/April28Updates/deployNewsletter.md` (12-section deploy guide)
- `tech-debt-report.md` (9 findings, 0 critical) — at repo root, gitignored

### Decision register additions (locked this session)

13. **Newsletter is double opt-in.** Confirmation email click required before email lands on the active list. GDPR posture.
14. **Already-registered Fynla user → "Sign in" inline link, no list-row created.** Soft user-enumeration oracle accepted as UX trade-off.
15. **`marketing@fynla.org` is the from-address for all newsletter mail.** Distinct from `noreply@fynla.org` (transactional / lifecycle) and `support@fynla.org` (contact form).
16. **Newsletter broadcast deferred** until list is built. List-only first; broadcast in a follow-up PR.
17. **In-banner "Prefer RSS?" link hidden** until newsletter broadcast lands. Bottom CTA's "Or subscribe via RSS" link kept (PR-237 original, for tech users).

---

## Session 71 (27 April 2026) — RSS news hub + landing-page restoration

**Branch:** `rss-feed` (15 commits ahead of `origin/main`, in sync with `origin/rss-feed`).
**PR:** [#237](https://github.com/Stoff73/fynla/pull/237) `rss-feed → dev` open, awaiting review.
**Note on this session's branch:** all of session 70's work was on `feature/fyn-persona-split`; that branch was NOT touched today. This session worked entirely on `rss-feed` (a separate workstream — news/landing fixes for the public marketing site).

### Completed this session

#### Homepage + campaign-page restoration (commit `4de75357`)
- [x] Restored fixes from `email-onboarding-video` branch that never reached main:
  - Homepage stats: "1000's of financial plans created" replacing "1 / The only UK platform" filler; line-break tweak on "UK adults don't get<br/>financial advice"
  - Latest insights: gate DB-driven block on `insightsFeatured`; add static fallback (3 hardcoded articles via `STATIC_INSIGHTS` + `getInsightImage()`) for environments where the CMS feature flag is off
  - Homepage + campaign-page video: swap to `Homepage-Fynla-ProductVideov2.mp4` (14.3 MB asset restored from `email-onboarding-video`) with click-to-play overlay; drop fake browser-chrome card and autoplay/loop/muted
- [x] Meta Pixel gated behind `app()->environment('production')` so dev/local don't fire it (`resources/views/app.blade.php:80`)

#### News hub + RSS feed scaffolding (commit `11a85c7a`)
- [x] `news_articles` migration + `NewsArticle` model + factory + `NewsArticleSeeder`
- [x] Public API: `Api/Public/NewsController` with `/api/news` (list) + `/api/news/{slug}` (show)
- [x] `FeedController` serving `/feed/news.xml` (RSS 2.0)
- [x] Frontend: `NewsHubPage` + `NewsArticlePage` views; `/news` and `/news/:slug` routes; `newsService.js` API wrapper
- [x] Footer link in `PublicLayout.vue`: "Accreditations" → "News"

#### News redesign — match brand patterns (commit `25daf6bb`)
- [x] `NewsHubPage`: full-width gradient hero card (raspberry blur-blob accents, "Latest" badge) for the featured article + 3-col grid of recent articles + light-pink RSS subscribe panel at top
- [x] `NewsArticlePage`: hero stripped to title-only `py-10` (matches bespoke insights pages); body restructured with back-link, byline, italic summary intro, then v-html'd body; canonical pink-100 CTA section after the body
- [x] Article body typography refactored to Tailwind `@apply` directives matching the insights pages — also satisfies CLAUDE.md rule 12
- [x] Lead paragraph (`<p class="lead">` or `:first-child`) styled to match h2 subtitle formatting: `text-xl sm:text-2xl font-bold text-horizon-500`
- [x] News article body: "Today we're launching..." → "We're launching..."; "Investment" bullet → "Planning"; co-founder names linked to `/about#chris-slater-jones` and `/about#brett-isenberg`
- [x] `AboutPage.vue`: anchor IDs added to founder cards with `scroll-mt-24` for clean deep-link landing

#### RSS link polish (commit `b55cd9c0`)
- [x] Top pink subscribe panel: trailing right-arrow swapped for the open-in-new-window icon (no slide transform; hover colour-swap to raspberry)
- [x] Bottom-of-page "Or subscribe via RSS" link: added `target="_blank"` + external-link icon next to the word "RSS"

#### Other
- [x] Dev build complete: `./deploy/csjones-fynla/build.sh` → `public/build/` (8.3M)
- [x] Local dev server: Vite running on `:5174` (5173 was held by an orphaned node process), `public/hot` regenerated
- [x] Pre-existing `dev.ps1` bugs flagged but NOT touched (scope discipline): `$pid` is a reserved PS automatic variable; `mysql` CLI not in PATH for the connection check
- [x] Mockup file at `public/mockups/news-redesign.html` (gitignored) — Variant A approved and shipped to `NewsHubPage.vue`

### NOT Done — Outstanding for next session

#### Top priority — dev deploy of PR #237
- [ ] **Branch rename decision** — `rss-feed` doesn't match the mandatory `feature/<owner>/<task>` convention. Per CLAUDE.md "any other prefix is wrong and the PR will be closed." Options: rename to `feature/phailanx/rss-feed` (since gh user is Phailanx) and re-target the PR, or push through and accept the codeowner request to rename
- [ ] **Upload to dev** (`~/www/csjones.co/fynla-app/`) — files listed below
- [ ] **SSH after upload**: `php artisan migrate --force` (creates `news_articles` table) → `php artisan db:seed --class=NewsArticleSeeder --force` (seeds the launch announcement) → cache clears + optimize
- [ ] **Smoke test** on `https://csjones.co/fynla`:
  - `/news` renders the redesigned hub; pink RSS panel opens `/feed/news.xml` in a new tab
  - `/news/launching-fynla` renders with subtitle-formatted lead paragraph; co-founder links land on the right About sections
  - `/feed/news.xml` returns valid RSS 2.0 (Apache may need MIME type for `.xml` if served as text/html)
  - Homepage stats reads "1000's / of financial plans created"
  - Latest insights static fallback renders (3 cards) since CMS flag is off on dev
  - Homepage + campaign videos load `Homepage-Fynla-ProductVideov2.mp4` with click-to-play
  - Meta Pixel does NOT appear in page source (dev `APP_ENV=staging`)
- [ ] **Production deploy** (only after dev sign-off): build with `./deploy/fynla-org/build.sh`, repeat upload + SSH steps on `~/www/fynla.org/public_html/`. Verify Meta Pixel DOES fire on production.

#### Pending migrations (from main, NOT auto-run this session)
Local DB still has 7 pending migrations dated 2026-04-14/15:
- `2026_04_14_122231_create_lifecycle_email_log_table`
- `2026_04_14_122345_create_feedback_responses_table`
- `2026_04_14_122424_add_user_id_and_metadata_to_discount_codes`
- `2026_04_14_122508_add_is_lifecycle_test_user_to_users`
- `2026_04_14_122545_add_lifecycle_columns_to_notification_preferences`
- `2026_04_14_122656_add_subscriptions_indexes`
- `2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum`
- `2026_04_15_153100_add_awin_tracking_to_payments_table`
These come from upstream main and should run cleanly: `php artisan migrate --force`. Confirm before running.

### Files to upload to dev (rss-feed → dev, beyond `public/build/`)

**PHP / Laravel:**
- `resources/views/app.blade.php` (Meta Pixel gate)
- `app/Http/Controllers/Api/Public/NewsController.php` *(new)*
- `app/Http/Controllers/FeedController.php` *(new)*
- `app/Http/Resources/News/NewsArticleListResource.php` *(new)*
- `app/Http/Resources/News/NewsArticleResource.php` *(new)*
- `app/Models/News/NewsArticle.php` *(new)*
- `database/factories/NewsArticleFactory.php` *(new)*
- `database/migrations/2026_04_27_120000_create_news_articles_table.php` *(new)*
- `database/seeders/NewsArticleSeeder.php` *(new)*
- `database/seeders/DatabaseSeeder.php` (registers NewsArticleSeeder)
- `routes/api.php`
- `routes/web.php`
- `resources/js/views/Public/AboutPage.vue` (anchor IDs)
- `resources/js/layouts/PublicLayout.vue` (footer "News" link)

**Asset:**
- `public/images/Homepage-Fynla-ProductVideov2.mp4` (14.3 MB)

### Context for next session

Pick up at the dev deploy of PR #237. The dev build artefacts are already in `public/build/` (8.3M, built this session). If the user has uploaded since this session ended, skip the build; otherwise re-run `./deploy/csjones-fynla/build.sh` first because Vite output paths are deterministic but timestamps are not, and SiteGround's preserve-old-chunks pattern only works if both old and new artefacts are present locally.

The branch-rename question is worth resolving up-front so the PR doesn't sit in limbo. CLAUDE.md treats the convention as strict.

---

## Session 70 (24 April PM → evening) — Fyn v2 spec directory + test strategy

**No code changes.** Working tree clean. All deliverables in `.gitignored` `/April/April24Updates/` (mirrored to `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/` throughout session). Session built on session 69's audit correction pass.

### Completed

#### Audit doc corrections + three-pass review
- [x] `code-vs-review-report.md` (105 lines) — first-pass compare of `feature/fyn-persona-split` code vs morning audit claims; surfaced the invoker-gap-fill FICTION that the audit carried.
- [x] `docs-three-pass-review.md` (464 lines) — Pass 1 VERIFIED/STALE/FICTION/UNCLEAR per claim; Pass 2 eight mental-model contradictions (error paths / concurrency / data ownership); Pass 3 forward traceability for every Sprint 0 task.
- [x] `audit-evidence.md` v2 — canonical §0 at top; §3.2 retracted ("orchestrator has no gap-fill" is FICTION — invoker has extractor wired at lines 48/175/200/251-300); §4 tool counts corrected (37 Anthropic / 33 xAI, direction inverted from audit claim); stale line anchors refreshed throughout; §14 processor framing refined; §18-23 new addenda (audit-truthfulness, handoff-contract failure mode, persona_state / onboarding_fyn_* reconciliation, visible-handoff leak, missing billing tools, memory model 3+1); **inline code citations on every implementation claim**.
- [x] `audit-synthesis.md` v2 — canonical §0 at top; §2 #4 FICTION retracted; §2 #5 tool counts corrected + direction inverted; §5.7 six canonical gaps enumerated with file:line anchors; §8.2 rewritten with full two-Fyn behavioural contract + corrected LOC scope (1,238 prod + ~1,000 test delete; ~1,000-1,200 prod + ~400-500 test new; net ~500-800 LOC reduction); §9.9 25-item ambiguity-resolution list; **inline code citations**.
- [x] `fyn-rubrics.md` v2 — canonical §0; D4 current-level nuance (0-1, scoring choice explained); D8 CoordinatingAgent LOC corrected to ~3,500; handoff-invisibility sub-criteria rolled into D5; memory-coherence sub-criteria into D9; scenario catalogue 65 → 75 with new `09-canonical-behaviour` (10 scenarios); **inline citations on D1-D10 evidence**.
- [x] Memory model correction per CSJ: 3 stores + 1 index (not 4). `MemoryRetrieverService` retrieval order DB → parked facts → current conversation → index. Conversation index = new JSON columns on `ai_conversations` + `ConversationSummariserJob` + `search_conversation_index` tool.

#### Spec directory `April/April24Updates/spec/` — 10 files, 4,644 lines total

- [x] `README.md` (132 lines) — navigation + branch mandate (`feature/fyn-persona-split` in every file) + decision register (16 CSJ decisions) + verification summary.
- [x] `00-canonical.md` (48 lines) — two-Fyn canonical verbatim. Source of truth.
- [x] `01-invariants.md` (500 lines) — 13 invariant groups, ~35 falsifiable invariants, each with Property / Falsifiability test / Acceptance criterion. §verification section lists per-sprint Browser matrix requirements (20 → 24 → 38 → 44 → 39 scenarios across sprints 0-4).
- [x] `02-current-system.md` (285 lines) — code-grounded description of branch today, anchored to file:line.
- [x] `03-test-strategy.md` (647 lines) — **dual-layer test strategy**: Pest (unit / feature / architecture) + Playwright BS-NN browser scenarios. Click-through discipline ("no URL make up crap" — only `http://localhost:8000` typed; everything else clicked). 24 fully-specified scenarios with seed + script + assertions + pass criterion. Per-invariant → test mapping table. Non-negotiable "report-finished" gate.
- [x] `10-sprint-0-plan.md` (1,665 lines) — 16 TDD tasks including Browser harness + 20 Playwright scenarios.
- [x] `11-sprint-1-plan.md` (691 lines) — 9 TDD tasks: eval harness + memory model + advice_response SSE + 4 new Playwright scenarios (24 total).
- [x] `12-sprint-2-plan.md` (345 lines) — 19 tasks: 14 batch-shaped capture tools + BS-17 parameterised over 14 variants (38 runs).
- [x] `13-sprint-3-plan.md` (159 lines) — 5 tasks: full local matrix + dev deploy to `csjones.co/fynla` + canonical subset on dev.
- [x] `14-sprint-4-plan.md` (172 lines) — external calendar (legal / DPIA / DPA / privacy-policy) + 6 code tasks + production matrix (39 runs on `fynla.org`).

### Project-wide non-negotiables (carried forward into every subsequent session)

- **Every doc in this workstream starts with canonical §0 verbatim.** Spec, plan, PRD, task list.
- **Branch: `feature/fyn-persona-split`.** Everything builds here. DO NOT start from `main` or `dev`.
- **Two test layers per invariant** — Pest + Playwright BS-NN. Sprint not done without both green + screenshot evidence in `docs/sprint-<n>-verification/BS-NN/`.
- **No fabricated URLs in Playwright scenarios.** Start at `http://localhost:8000`; click through the UI for everything else.

### NOT Done — Outstanding for Session 71

#### Top priority (user requested, not yet written)

- [ ] **Plan directory `April/April24Updates/plan/`** — user invoked `/planning-with-files` skill at end of session with plan-slice template (Objective / Spec reference / Files affected / Acceptance test / Out of scope). Invocation arrived at the same moment as `/session-end`. Resume by re-invoking `/planning-with-files` with the original args. Target structure: one file per invariant group (§2.1 through §2.13) under `plan/slices/` plus a `plan/README.md` + `plan/template.md`.

#### Sprint 0 execution (when ready to start coding)

- [ ] **Check out `feature/fyn-persona-split`** — currently on `main`. Switch before ANY code work: `git checkout feature/fyn-persona-split`.
- [ ] **Sprint 0 Task 0.1** — rebase onto `origin/main` (179-commit drift). Expect conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `routes/api.php`, `HasAiChat.php`, `Prompts/*`, `AiToolDefinitions.php`, `StructuredResponseValidator.php`, `aiChat.js`, `AiChatPanel.vue`.
- [ ] **Sprint 0 Task 0.16** — build Browser test harness (`tests/Browser/TestCase.php` + Login helper + SSE capture helper + 20 scenario files).
- [ ] Sprint 0 Tasks 0.2 through 0.15 — per `spec/10-sprint-0-plan.md`.

#### Execution mode decision (pending user choice)

Two options offered at session end; not chosen before `/session-end`:

1. **Subagent-driven** (recommended) — `superpowers:subagent-driven-development` with fresh subagent per task + two-stage review. Best isolation; keeps session context fresh; good for 16-task sprint.
2. **Inline execution** — `superpowers:executing-plans` with batch commits + checkpoints. Faster but session context fills with 16 tasks × 7-16 steps each.

### Context for Session 71

- **Start by reading `April/April24Updates/spec/README.md`** (132 lines) — entire workstream navigation.
- **Then `00-canonical.md` + `01-invariants.md`** — source of truth.
- **For Sprint 0 execution**: read `03-test-strategy.md` + `10-sprint-0-plan.md`.
- **For the plan-slice deliverable**: re-invoke `/planning-with-files`, build `April/April24Updates/plan/`.
- **Branch reality check**: `git log -1 feature/fyn-persona-split` — confirm tip; `git rev-list --count origin/feature/fyn-persona-split..origin/main` should still be 179.
- **Vault parity**: `diff -r April/April24Updates/ /Users/CSJ/Desktop/fynlaBrain/April/April24Updates/` — expect zero diff.

### Deploy Status

Nothing deployed this session. Nothing to deploy (no code changed). Sprint 0 is the next deploy-adjacent work; Sprint 3 is when dev-deploy gates open.

### Decision register snapshot (all locked)

1. Two Fyns, no Orchestrator class. Delete orchestrator/invoker/registry/data_capture prompt builder.
2. All 17 fill_form handlers → direct-write (Q1=a).
3. Provider parity. 40 tools post-Sprint-0 (+14 batch = 54 post-Sprint-2).
4. FCA: guidance-only. Signposting: *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."*
5. Out-of-remit: *"I'm able to help you with your finances. {context} is out of scope."*
6. Advice response: new `advice_response` SSE event + `AdviceResponsePanel.vue`.
7. SSE abort: keep partial writes; instrument + monitor.
8. Document extraction: UI-only CTA (not an Advice Fyn tool).
9. Entry-source → journey mapping: config-driven + extensible (4 initial, `path_choice` fallback).
10. Memory: 3 stores + 1 index; retrieval order DB → parked → current → index.
11. Eval floors: 95% baseline recall/precision; 100% hard-fail on validity/value/consistency/fabrication; mortgage → 100%/100% + protection + savings → 98%/98% by Sprint 2.
12. Local-first deploy gate.

---

## Session 69 (24 April full day) — Fyn AI audit + adversarial review + rubrics

**No code changes this session.** Full working tree clean. Two passes:

- **Morning:** produced 4 planning docs for the Fyn AI rework (fyn-system-map.md, verdictFyn.md (superseded), enterprise-verdict.md, fyn-integrated-plan.md).
- **Afternoon:** audited those 4 docs with 5 parallel reviewers (web-researcher, best-practices-researcher, reliability-reviewer, cli-agent-readiness-reviewer, adversarial-document-reviewer) + independent code reconnaissance on `main` and `feature/fyn-persona-split`. Produced 3 correction artefacts. CSJ answered the 7 decision-gate questions.

### Completed

#### Four audit documents produced in `April/April24Updates/` (mirrored to fynlaBrain vault)

- [x] **`fyn-system-map.md`** (126KB, 2038 lines) — exhaustive map of the Fyn AI system. §1-§21 cover AI chat (routes, 10-layer prompt verbatim, 29 tools, data model, frontend web + mobile, admin surfaces, observability). §22 cross-doc enterprise addendum. §23 documents the Document Extraction AI surface (`AIExtractionService`, 965 LOC, Anthropic Vision + xAI Vision paths, stale `claude-3-5-haiku-20241022` model). §24 documents the Python Agent SDK Sidecar (`scripts/fynla_agent/` + `AgentInternalController`). §25 consolidated touchpoint inventory across 3 AI systems. §26 architecture correction — intended vs built (two Fyns, not three).
- [x] **`verdictFyn.md`** (69KB) — v1 verdict against Anthropic's *Building Effective Agents* + xAI docs. Graded B+ (72/100). **Superseded** by enterprise-verdict. Kept for accountability.
- [x] **`enterprise-verdict.md`** (141KB, 2021 lines) — v3 verdict, **7 passes** (Parts C/D framework + E adversarial + J cross-doc + K exhaustive Loop 3 + L CSJ resolutions + M scope correction + N architecture correction). Grade **D+ (45/100)** for the Fyn AI system specifically. **13 Fyn-AI Critical gaps**, **16 Fyn-AI High risks**. Key findings: C1 xAI undisclosed, C2 no FCA analysis, C3 `update_record` over-exposure, C5 no runtime consent check, C6 Article 9 health data LLM flow, C7 audit logs not tamper-evident, C8 no DPIA, C10 read tools not audited, C11 `AIExtractionService` gaps, C14 "no health data to third parties" policy contradiction.
- [x] **`fyn-integrated-plan.md`** (119KB, 1678 lines) — integrated 6-sprint roadmap. 25-touchpoint dependency index (T1–T25) to prevent compound-change bugs. §12 architecture correction with Sprint 0.19 "collapse three-persona → two-persona" task. Reconciles current Fyn + verdict + in-flight persona-split work.

#### Key architectural finding

**`feature/fyn-persona-split` built the wrong architecture.** It introduced a three-persona model (onboarding + advice + `data_capture`) duplicating capture machinery. **CSJ's intended architecture is two Fyns**: Onboarding Fyn handles ALL data capture (during onboarding AND post-onboarding inline captures); Advice Fyn handles post-onboarding non-capture. Handoff via `delegate_to_capture` / `capture_complete` routes the capture state to the **same Onboarding Fyn stack**, not to a separate persona.

#### Scope corrections made during the audit

- LPA creation rate KPI — dropped (inherited from PRD without scrutiny)
- Model currency (grok-4-1-fast-reasoning) — withdrawn (CSJ: deliberate unit-economics choice, not a gap)
- App-wide findings (Meta Pixel, AWIN, FCM, Google DPA, Plausible general) — removed from Fyn AI scope; would belong in a separate app-wide compliance audit if CSJ wants one
- Three-persona architecture — corrected to two-persona

#### Discoveries from exhaustive sweep (Part K)

- **Three AI systems** not one: Chat, Document Extraction, Python Agent Sidecar
- **Python Agent Sidecar appears to be dead code** — zero PHP invocations, no cron/Procfile/systemd references, last modified Mar 16. Recommendation: remove entirely (1 hour)
- **Stale OpenAI config block** in `config/services.php` — leftover from abandoned March OpenAI migration. Remove (5 min)
- **`update_record` over-exposure** — 2-field blocklist (user_id, id). LLM can change `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`
- **Plausible tracks `chat_opened`/`chat_message_sent`** events (narrow Fyn-AI-specific concern)

### NOT Done — Outstanding for next session

The four docs are decision input; the next session should execute Sprint 0 per the integrated plan. Priority order per CSJ's stated "get it working" direction:

#### Sprint 0 (1–2 days) — unblock persona-split shipping

- [ ] **0.1** Rebase `feature/fyn-persona-split` onto current `main` (72 commits drift; expect conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `router/index.js`)
- [ ] **0.2** Full Pest run post-rebase (should stay 2,448 passing + 1 flake)
- [ ] **0.3** Close PR #214 (`onboardingFyn`) as superseded by persona-split
- [ ] **0.5** Tighten `update_record` per-entity field whitelist — replace 2-field blocklist with per-entity allowlist (1 day)
- [ ] **0.6** Add `delete_record` confirmation pattern (4 hrs)
- [ ] **0.7** Add `ConsentService::hasConsent` runtime check in `AiChatController::sendMessage` (2 hrs)
- [ ] **0.8** Sanitise user-controlled prompt fields (`first_name`, `surname`, `employer`, `occupation`, family member names, goal names) — strip to `[A-Za-z0-9\s'.-]` (4 hrs)
- [ ] **0.16** Delete Python Agent SDK sidecar — `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `AgentInternalController`, `AgentTokenAuth`, `/api/internal/agent/*` routes, `AGENT_INTERNAL_TOKEN` env+config. **Unless** CSJ confirms an external caller (none found in repo) (1 hr)
- [ ] **0.17** Remove stale OpenAI config block from `config/services.php` + `.env.example` (5 min)
- [ ] **0.18** Begin AI DB audit migration — create `ai_tool_executions` table, migrate `[AI-AUDIT]` file log writes in `CoordinatingAgent::executeTool` to DB inserts with `operation: read|write` column (1 day)
- [ ] **0.19** **Collapse three-persona architecture to two-persona** — delete `DataCapturePromptBuilder` + test, update `config/fyn_personas.php` so `data_capture` registry entry routes to `OnboardingChatDirector::handleInlineCaptureTurn` (new method wrapping existing capture machinery), update `FynPersonaOrchestrator::runCaptureTurn` to invoke director instead of a separate persona (1–2 days incl tests)

#### Verifications needed (quick SSH/console checks — not audit work)

- [ ] Python agent external caller — CSJ direct confirmation: is there any external Python worker/cron running `run_agent.py`?
- [ ] Plausible chat-event tracking on production — SSH + `grep ANALYTICS_ENABLED .env` — only if the `chat_opened`/`chat_message_sent` signal matters
- [ ] Full health-data trace through `orchestrateAnalysis` — 1-day code audit to walk every numerical field in layer 5 back to source; decide per-field: strip or disclose (Sprint 4)

#### Sprint 1+ deferred until Sprint 0 completes

See `April/April24Updates/fyn-integrated-plan.md` §8 for full sprint breakdown. Sprint 1 = verdict quick wins (temperature → 0.3, Anthropic cache metrics, reasoning tokens tracking, sanitise-order fix, eval harness MVP). Sprint 2 = B-X bug fixes + 11 missing Feature tests + 12 remaining browser matrix rows. Sprint 3 = ship to dev. Sprint 4 = production hardening (Privacy Policy update, DPIA, tamper-evident audit, provider failover, Sentry).

### Afternoon — 5-reviewer audit of the morning's 4 docs

Five review agents dispatched in parallel, each seeded with an evidence bundle I built from direct code reads on `main` and `feature/fyn-persona-split`. Reviewers:

1. `ce-web-researcher` — prior-art scan (UK fintech, OpenAI Agents SDK, LangGraph supervisor, SEC 17a-4, AuditableLLM)
2. `ce-best-practices-researcher` — Anthropic / xAI / FCA / ICO / OWASP / NIST best-practice comparison
3. `ce-reliability-reviewer` — SSE abort, token-budget race, provider cache coherence, audit durability, gap-fill retry
4. `ce-cli-agent-readiness-reviewer` — tool catalogue divergence, tool-result schema, parity gaps
5. `ce-adversarial-document-reviewer` — premise challenge, contradiction hunt, scope creep, grade-rubric defensibility

### Correction artefacts produced (afternoon — ALL in vault, not git)

- [x] **`April/April24Updates/audit-evidence.md`** — code-grounded ground truth with file:line anchors, §1-17. Separates claims the four docs get RIGHT from what they get WRONG. Addenda 14-17 add the Privacy-Policy contradiction, stale-extraction-model, stale OpenAI config block, and `ai_advice_logs.user_data_snapshot` GDPR concern.
- [x] **`April/April24Updates/audit-synthesis.md`** — consolidated verdict across all 5 reviewers + my own code reads. 10 sections: Headline, Correctly Planned, Invalidated by Code, Assumptions Stated as Fact, Scope Creep, Real Gaps Missed, Sprint 0 Honest Re-estimate, Multi-Entity Deep Dive, CSJ Decisions, Recommendations. §8 now contains CSJ's answers to all 7 decision questions.
- [x] **`April/April24Updates/fyn-rubrics.md`** — two rubrics replacing the undisclosed D+(45/100). Rubric A: Enterprise Assessment, 10 dims × 5 levels = /40 score, Fyn currently **4/40 — 🔴 Pre-launch**, projected Sprint 0+1 → **~17/40 — 🟠 Limited beta**. Rubric B: Eval Harness, 65 golden conversations, Mode 1 (CI-gated, mocked) + Mode 2 (weekly, real providers), per-tool scorecard with tunable thresholds.

### Load-bearing findings from the afternoon audit (overturns / extends the morning docs)

- **`main` has NONE of `OnboardingChatDirector`, `DataCapturePromptBuilder`, `FynPersonaOrchestrator/Invoker/Registry`, `HandoffContract`, `AssetCaptureEntityExtractor`, `CaptureContext`** — all live ONLY on `feature/fyn-persona-split`. The morning system-map §1-26 conflates the two branches.
- **Persona-split is 178 commits behind main, not 72.** CSJTODO morning entry and integrated-plan §0 both had 72. Every rebase-effort estimate understated by ~2.5×.
- **Anthropic cache metrics ARE persisted** at `HasAiChat.php:467-469` (`cached_tokens` + `cache_hit_rate` into `ai_messages.metadata`). Morning's system-map §21 Q3 + integrated-plan Sprint 1.2 fix is a no-op — delete the task.
- **Admin UI for AiAuditController EXISTS** (`resources/js/components/Admin/AiAudit.vue`, mounted in AdminPanel). Morning's §21 Q2 + verdict G20 + Sprint 5.3 "missing" is wrong.
- **Tool catalogue is 23 on Anthropic vs 29 on xAI.** `list_records`, `create_holding`, `set_expenditure` exist only on xAI. Morning's "29 tools" count is correct on only ONE provider.
- **All 13 `create_*` tools are FORM PRE-FILLERS, not DB writers.** Every `handleCreate*` in `CoordinatingAgent.php` returns `['action' => 'fill_form', ...]`; the frontend POSTs to the standard module API. Tool descriptions lie to the model; `[AI-AUDIT]` logs "Tool executed" for things that didn't execute. Narrows verdict C3 exposure but breaks the model's own truth story.
- **Multi-entity STILL BROKEN on `feature/fyn-persona-split` post-onboarding.** `AssetCaptureEntityExtractor` is wired into `OnboardingChatDirector` only (lines 1708/1714/1715). `FynPersonaOrchestrator::runCaptureTurn` invokes the standard LLM loop without the extractor. Integrated-plan §5.1 "persona-split fixes multi-entity" is FALSE for the path persona-split exists to serve. 4 of 18 entity types covered even in the onboarding path.
- **`OnboardingChatDirector::handleInlineCaptureTurn` does NOT exist on persona-split** — it's proposed NEW code in integrated-plan §12.2, not a refactor target. Sprint 0.19 "1-2 day collapse" under-scopes: it's deletion + 300-500 LOC new + extractor rewiring + tests = **2-3 days**.
- **FCA PS25/22 "targeted support" went LIVE 6 April 2026** — new regulated category between guidance and full advice, explicitly for AI-assisted consumer guidance. Not mentioned anywhere in the morning docs. CSJ's decision: guidance-only posture (see §8.1 below) — no targeted-support authorisation pursued.
- **Privacy Policy §5/§7 factually contradict the code.** §5 line 111: *"We do not share health data with any third party."* §7 line 132: *"**We do not use third-party analytics or tracking services.**"* Both falsified by Meta Pixel (unconditional `app.blade.php:81-91`), AWIN (full integration), Plausible (conditional), and health-data flow to LLMs. **5 third-party processors**, not 3 as verdict K3 claims.
- **No SSE abort detection anywhere** — no `connection_aborted()`, no `ignore_user_abort(true)`, no idempotency keys. Users billed for turns they never received. Biggest reliability gap; nowhere in the 4 docs.
- **Token-budget race** via `Cache::remember($key, 300, …)` — two concurrent SSE requests both read stale budget, both pass, both run. Pro user can overshoot £2M/day cap by ~50%.
- **Provider cache coherence race** — `Cache::forever('ai_provider', …)` admin toggle can flip mid-conversation, mixing Anthropic `cache_control: ephemeral` markers with xAI request shape.
- **Python sidecar is dead code.** Uses regular `anthropic` Messages SDK, NOT `claude-agent-sdk`. Zero PHP callers in any path (grep across `app/`, `routes/`, `config/`, `database/`, `resources/`, `Kernel.php`, no Procfile/systemd/supervisor). Three patterns worth harvesting (Pydantic output validation, task-type-specific prompts, externalised PreToolUse hook) — none require keeping the Python code. CSJ confirmed deletion (§8.4 below).

### CSJ decisions — all 7 §8 questions answered

1. **FCA posture: GUIDANCE ONLY.** No targeted-support authorisation. External legal opinion needed for the guidance posture (Sprint 4). `CoreIdentity.php` "you think like a qualified financial planner" rewritten in Sprint 1 (not Sprint 4). Every advice-type response signposts to regulated advice.
2. **Two Fyns (Onboarding + Advice), NO orchestrator class.** Routing collapses into `AiChatController`. DELETE on persona-split: `FynPersonaOrchestrator`, `FynPersonaInvoker`, `FynPersonaRegistry`, `DataCapturePromptBuilder`. KEEP: `HandoffContract` (constants), `CaptureContext` VO, `OnboardingChatDirector` (promoted to Onboarding Fyn; new `handleInlineCapture` method). NEW: `AdviceFyn` class wrapping advice-side chat loop + prompt. Net ~800 LOC deletion, ~300-400 LOC new.
3. **Multi-entity thresholds: 95% baseline recall + precision per focus, tunable up.** Non-tunable 100% hard-fail floors on entity validity (FormRequest passes), monetary value accuracy (no £ drift), cross-entity consistency (no field-bleed), 0% fabrication. Per-tool scorecard published every eval run. Sprint 2 ratchet: mortgage → 100/100, protection + savings → 98/98, add 12 remaining entity types at 90 baseline.
4. **Python sidecar: DELETE.** Sprint 0.16 unblocked (1 hr).
5. **Local-first UNAMBIGUOUS.** Nothing deploys anywhere until 100% verified on `localhost:8000`. Per-sprint local verification is the dev-deploy gate.
6. **Terminology irrelevant.** Spec will use "routing workflow → orchestrator-workers pattern" for literature refs; "Fyn / Onboarding Fyn / Advice Fyn" internally.
7. **Rubric: BUILD BOTH.** Rubric A (enterprise) + Rubric B (eval) — see `fyn-rubrics.md`.

### NOT Done — Outstanding for next session

The four original planning docs need a **correction pass** before they seed a spec. Three artefacts already produced are inputs to that pass:

#### Correction pass on the four original planning docs (Sprint 0 precursor, ~1 day)

- [ ] **Canonical-facts pass.** Apply `audit-evidence.md` §2-§5 corrections to `fyn-system-map.md`, `fyn-integrated-plan.md`, `enterprise-verdict.md`. Every contradicting sentence retracted.
- [ ] **Scope pass.** Prune T18/T24/T25 from touch-point index. Prune Sprint 4.22 Privacy Policy if app-wide. Pick one Critical count (Part M's 13) and enforce.
- [ ] **Effort honesty pass.** Rewrite Sprint 0 envelope from "1-2 days" to **3-4 weeks**. Move 0.5 (allowlist), 0.8 (sanitise + structural separation), 0.18 (DB audit + hash chain), 0.19 (two-Fyn collapse) into Sprint 1 if smaller sprints preferred, or size Sprint 0 honestly.
- [ ] **Add new Sprint 0 tasks from reviewers:** 0.20 SSE abort detection + idempotency key, 0.21 atomic token-budget check-and-increment, 0.22 provider-swap write lock, 0.23 gap-fill dedup key, 0.24 `generateTitle` sanitation, 0.25 rebase-conflict strategy doc.
- [ ] **Grade rubric pass.** Replace "D+ (45/100)" in verdict + INDEX with the Rubric-A 4/40 🔴 Pre-launch score (reproducible from `fyn-rubrics.md`).

#### Sprint 0 (corrected scope, ~3-4 weeks engineering) — unblock persona-split shipping

- [ ] **0.1** Rebase `feature/fyn-persona-split` onto `main` (**178 commits** drift, not 72 — 0.5-1 day minimum). Expect conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `routes/api.php`, `HasAiChat.php`, `Prompts/*`, `AiToolDefinitions.php`.
- [ ] **0.2** Full Pest run post-rebase (probable test failures from rebase — +0.5 day for triage).
- [ ] **0.3** Close PR #214 (`onboardingFyn`) as superseded.
- [ ] **0.5** `update_record` per-entity allowlist + `additionalProperties: false` in schema (**2 days**, 15+ entities × ~10 fields).
- [ ] **0.6** `delete_record` confirmation pattern + cover `update_record` when fields touch tax/legal state (Trust.settlor, FamilyMember.relationship, Mortgage.start_date) — 4 hrs.
- [ ] **0.7** `ConsentService::hasConsent` runtime check in `AiChatController::sendMessage` + "consent-withdrawn mid-conversation" UX (0.5 day — check is 2 hrs but UX design matters).
- [ ] **0.8** Sanitise user-controlled prompt fields + wrap user content in `<user_provided>...</user_provided>` structural markers per OWASP Cheat Sheet (1 day).
- [ ] **0.16** Delete Python sidecar — `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `AgentInternalController`, `AgentTokenAuth`, `/api/internal/agent/*` routes, `AGENT_INTERNAL_TOKEN` env+config (1 hr — CSJ confirmed delete).
- [ ] **0.17** Remove stale OpenAI config block from `config/services.php:34-38` + `.env.example` (5 min).
- [ ] **0.18** AI DB audit migration — **5-7 days** (not 1): hash-chain append-only `ai_audit_events` table + HMAC signing + retention policy (7yr advice / 2yr general) + erasure-compatible pseudonymisation + weekly integrity-verification job. Per SEC 17a-4 / AuditableLLM precedent.
- [ ] **0.19** Two-Fyn architecture rewrite (**2-3 days**): DELETE `FynPersonaOrchestrator` + `FynPersonaInvoker` + `FynPersonaRegistry` + `DataCapturePromptBuilder`. CREATE `AdviceFyn` class + `OnboardingChatDirector::handleInlineCapture`. WIRE routing into `AiChatController`. **CRITICAL:** rewire `AssetCaptureEntityExtractor` into the new inline-capture path — otherwise post-onboarding multi-entity stays broken.
- [ ] **0.20** SSE abort detection + idempotency key on `POST /conversations/{id}/messages` (2-3 days).
- [ ] **0.21** Atomic token-budget check-and-increment — replace `Cache::remember($key, 300, …)` with DB atomic INSERT + row-level `FOR UPDATE` on `ai_daily_usage` (1-2 days).
- [ ] **0.22** Provider-swap write lock — version counter on `ai_provider` cache key, per-request snapshot + abort on mid-loop drift (1 day).
- [ ] **0.23** Gap-fill dedup key against existing records — `(user_id, entity_fingerprint, 24h window)` — closes retry double-insert vector (0.5 day).
- [ ] **0.24** `generateTitle` sanitation — `strip_tags` + length-clamp before persist (2 hrs).

#### Sprint 1 (after Sprint 0 — eval harness first, then quick wins)

- [ ] **Eval harness MVP** (`fyn-rubrics.md` Rubric B) — `tests/Feature/Fyn/Eval/` with `EvalRunner`, `MockedProviderClient`, first **10 scenarios** (6 query types + 4 multi-entity). CI gate: Mode 1 must be 100%.
- [ ] Expand to **30 scenarios** (all 22 query types + 6 handoff/cancel + 2 injection).
- [ ] Rewrite `CoreIdentity.php` — drop "you think like a qualified financial planner" language; align with guidance-only posture.
- [ ] `config/fyn_eval.php` with tunable thresholds per tool (`recall_floor`, `precision_floor`, `reason`, `reviewed_by`, `next_review`).
- [ ] Structural separation: Layers 4-6 wrap user-controlled content in `<user_provided>...</user_provided>` markers.
- [ ] Canary instruction + output drift-detection test.
- [ ] First per-tool scorecard run — CSJ reviews → raises thresholds where needed.

#### Sprint 2 (after Sprint 1 eval harness is in place)

- [ ] Expand eval harness to **65 scenarios**, enable weekly Mode 2 real-provider cron.
- [ ] Add the 12 missing entity types to eval at 90% baseline (goal, family, life-event, property+mortgage, trust, will, POA, business, chattel, liability, gift, holding).
- [ ] **Batch-shaped extractor tools** (Alternative A per best-practices reviewer): `capture_protection_policies(policies: [...])`, `capture_savings_accounts`, `capture_pensions`, `capture_investment_accounts` with strict JSON schema. Retire regex `AssetCaptureEntityExtractor` when fire rate < 2%.
- [ ] Split tool budget: 5 reads + 10 writes when classifier type = `data_entry`.
- [ ] Move multi-entity instruction from `ComplianceRules.php` into each `create_*` tool's `description` field (per-decision salience).
- [ ] Close remaining parity gaps: `upload_document` tool (expose `AIExtractionService`), `link_spouse`, `configure_assumption`, `run_projection`, `submit_risk_questionnaire`, `delete_record` covers `investment_holding` enum, `create_will` / `create_power_of_attorney` registered in both tool-definition classes.

#### Sprint 3 — ship to dev (`csjones.co/fynla`), local-first gate enforced

Every task above must be 100% verified on `localhost:8000` first. Dev deploy is only after local verification passes.

#### Sprint 4 — production hardening + external work (parallel calendar tracks)

- [ ] External legal opinion on the guidance-only posture (commissioned by CSJ; 4-8 week calendar).
- [ ] DPIA drafting (external DPO or retained counsel; 2-4 weeks).
- [ ] Privacy Policy rewrite to honestly disclose Anthropic + xAI + (if retained) Meta Pixel + AWIN + Plausible — OR remove those trackers to match the current policy text. **Commercial decision pending.**
- [ ] Article 28 DPA verification with Anthropic + xAI (commercial/legal).
- [ ] UK IDTA + Transfer Risk Assessment for both Anthropic + xAI (US processors).
- [ ] Provider failover (Anthropic ↔ xAI) with state preservation.
- [ ] Sentry / structured error reporting.

#### Verifications still needed (quick SSH/console checks)

- [ ] Full health-data trace through `orchestrateAnalysis` — 1-day code audit to walk every numerical field in Layer 5 back to source; decide per-field: strip or capture specific consent (Sprint 4).
- [ ] Plausible chat-event tracking on production — SSH + `grep ANALYTICS_ENABLED .env` — only if retained as in-scope tracker.

### Context for Next Session

**Start with:** read `April/April24Updates/audit-synthesis.md` (the consolidated verdict — reviewer synthesis + CSJ decisions), then `audit-evidence.md` (ground-truth anchors), then `fyn-rubrics.md` (grading + eval-harness shape). Do NOT read the morning's 4 docs without reading the audit first — they contain load-bearing errors the afternoon audit overturns.

**Before starting Sprint 0:** run the **correction pass** on the morning's 4 docs (8 items above) so the spec isn't drafted on inherited errors. This is ~1 day of editing.

**Critical context:**
- CSJ decisions locked: guidance-only FCA posture, two-Fyn architecture with no orchestrator class, local-first deploy gate, both rubrics to be built, Python sidecar deletion confirmed.
- The afternoon audit overturned several morning claims — read `audit-synthesis.md` §2 (Invalidated by Code) before trusting anything in the morning docs.
- Multi-entity is the user's top-priority pain point and is **NOT** fixed by persona-split as the morning docs imply. Sprint 1's batch-tools pattern is the structural fix.
- 178-commit rebase drift (not 72) means Sprint 0.1 alone is 0.5-1 day, not 2-4 hrs.

**Branch state:** `main` unchanged. `feature/fyn-persona-split` 68 commits ahead / **178 behind** `origin/main`. PR #214 (`onboardingFyn`) still open, to be closed in Sprint 0.3 as superseded.

**Working tree:** clean. CSJTODO.md updated (this file). The 3 afternoon correction artefacts + the 4 morning docs are in `.gitignore`d `April/April24Updates/` — vault is the source of truth (mirrored via `/vault-sync`).

**Current Enterprise Rubric score:** **4/40 — 🔴 Pre-launch.** Projected after Sprint 0+1: ~17/40 🟠 Limited beta.

---

## Session 68 (23 April late night) — `dev → main` release + investment 500 fix + lifecycle hotfix

Three PRs shipped to **production** (`fynla.org`). Git dev ↔ main now fully in sync at tip `21ecf67` (lifecycle hotfix) with back-merge `bcf9509` on dev. All 7 production smoke tests PASSED.

### Completed

#### PR #227 — Investment `/api/analyze` 500 fix + session 67 tech-debt bundle (→ dev)

- [x] **`/api/investment/analyze` 500 → 200.** `Holding::$casts[cost_basis, current_value]` are `decimal:2` which Laravel returns as strings; PHP 8's strict `round()` rejected them in `TaxEfficiencyCalculator.php:107` via the `opportunities[]` payload from `CGTHarvestingCalculator`. Fixed at the source with `(float)` casts on lines 154-155 so every downstream consumer gets floats. Commit `0236006`.
- [x] **Vue `_uid` warning flood silenced.** `AssetAllocationDonut.vue:145` used `this._uid` (Vue 2 internal) — replaced with `this.$.uid` (Vue 3 options-API equivalent). Became visible once session 67's joint-donut layout started rendering two instances per page. Confirmed live: gradient ID resolves to `nw-alloc-grad-423-0` (not `-undefined-0`).
- [x] **Session 67 tech-debt report remediation** — `AssetBreakdownBar` tooltip hex (`#E83E6D`, `#1F2A44`, `#5854E6`, `#20B486`) replaced with `PRIMARY_COLORS[500]`, `TEXT_COLORS.primary`, `WARNING_COLORS[500]`, `SUCCESS_COLORS[500]` imports from `designSystem.js`. Spouse-name fallback chain collapsed from 8-18 lines to one-line getter reads across `NetWorthWealthSummary.vue`, `PortfolioOverview.vue`, `LetterToSpouse.vue` (the `userProfile/spouse` getter's `withName` helper already normalises every return path). Net −32 LOC.
- [x] **PR #227 opened + admin-merged to `dev`** as merge commit `2f9c308`. Deploy guide at `April/April23Updates/fixDeployInvest.md` (mirrored to vault).

#### PR #228 — First `dev → main` release since session 64 (99 commits / 188 files / +6,677/−1,545)

- [x] **Git verification pass.** Counted commits/files; confirmed `origin/main..origin/dev` was 97 commits ahead + my new 2 commits = 99. Confirmed `onboardingFyn` (PR #214) and `feature/fyn-persona-split` branches stayed unmerged.
- [x] **Local production build.** `./deploy/fynla-org/build.sh` → bundle `app-B31kpBbU.js` (1,195,754 bytes). Verified the built `CheckoutPage-CbzaPZdL.js` has live pk `pk_sY0uq1Q2d2lo0EO` + `merchant.revolut` URL (0 sandbox refs).
- [x] **PR #228 opened + admin-merged to `main`** as `27bb188`. Back-merge PR #229 (`34b77a3`) brought the merge commit to dev.
- [x] **Production upload.** rsync'd 113 PHP/config/database/routes/views files to `~/www/fynla.org/public_html/` in a single pass using the production SSH key (loaded into agent). User uploaded `public/build/` separately. Verified the active manifest on prod now points at `app-B31kpBbU.js`.
- [x] **Production SSH finalisation.** `composer install --no-dev --optimize-autoloader` ran (downgraded `intervention/image` 4.0.1 → 3.11.7 per PR #224; prod is PHP 8.3.30 so either works but the 3.11 API port in `InsightImageService` requires 3.x). `php artisan migrate --force` — 7 April 14 migrations ran (lifecycle_email_log, feedback_responses, discount_codes user_id+metadata+type enum, users is_lifecycle_test_user, notification_preferences lifecycle columns, subscriptions indexes). `cache:clear` + `config:clear` + `view:clear` + `route:clear` + `optimize` + `config:cache`.
- [x] **Production deploy guide.** `April/April23Updates/devMainDeploy.md` — scope, pre-flight (Revolut pk verification commands), 113-file upload buckets, preserve-old-chunks tar-pipe, SSH finalisation, 7 smoke tests, rollback procedure. Mirrored to vault.

#### Lifecycle engine SMTP rate-limit bug (found during smoke tests, hotfixed as PR #230 + #231)

- [x] **Bug surfaced.** Smoke-test trigger `php artisan lifecycle:run-daily` fired against real prod users; SiteGround SMTP capped at ~10 msg/sec, deferring 11 of 22 engaged_trialer sends with `451-gukm1022.siteground.biz received more than 10.7 messages for 1s`. 10 empty_trialer + 2 engaged_trialer delivered successfully. **The daily cron is scheduled for 08:30 UTC** and would have hit the same wall every day regardless — not a smoke-test artifact, a real production bug in PR #212.
- [x] **Engine disabled on prod** immediately — `LIFECYCLE_ENGINE_ENABLED=false` appended to prod `.env` + `config:cache`. Verified `config("lifecycle.enabled") === FALSE` via Tinker. `.env.backup-2026-04-23-lifecycle-disable` preserved.
- [x] **PR #230 hotfix** — added `throttle_ms` config key to `config/lifecycle.php` (default 150 ms = ~6.6 sends/sec, well below SG's cap; env override `LIFECYCLE_THROTTLE_MS`, `0` disables for tests and self-hosted SMTP). `LifecycleEngine::run()` now calls `usleep()` between iterations on both success and error paths. 3 new unit tests cover default config, pacing active (3 sends at 50 ms → elapsed ≥ 150 ms), pacing disabled (5 sends at throttle=0 → elapsed < 1 s, all 5 logged). **47/47 lifecycle tests pass**. Admin-merged to dev as `c8b0f05`.
- [x] **PR #231 (dev → main)** admin-merged as `21ecf67`. PR #232 back-merge main → dev as `bcf9509`. Three files rsync'd to prod.
- [x] **Engine re-enabled on prod** — `LIFECYCLE_ENGINE_ENABLED=false` line removed from `.env`, `config:cache` regenerated, verified `config.enabled === TRUE | throttle: 150ms`.
- [x] **Re-ran `lifecycle:run-daily` against the 11 deferred users.** All 11 engaged_trialer delivered, 0 errored, total runtime 2.245s (1.65s throttle overhead + ~0.6s send/query overhead — exactly on-spec for 150ms pacing across 11 sends). `lifecycle_email_log` went from 12 → 23 rows. `empty_trialer: 0 sent` confirms the 10 already-sent users are correctly dedup'd via log lookup.

#### Orphan PSR-4 cleanup on prod

- [x] **`app/Http/UserResource.php` removed from prod.** Byte-identical duplicate sitting at the PSR-4-violating path since 20 March (never tracked in git). Composer dump-autoload warned on every `composer install`. Removed via SSH, `composer dump-autoload -o` regenerated 7,325 classes with zero PSR-4 warnings. `composer install --dry-run` confirms no regression. The correct file at `app/Http/Resources/UserResource.php` still resolves cleanly. Dev server (csjones.co) was already clean.

#### All 7 production smoke tests PASSED

- [x] **A. Homepage + auth** — fynla.org landing + sign-in as `chris@fynla.org` / `Password1!` + email 2FA code `971539` → landed on `/dashboard` as Chris Jones. 0 console errors.
- [x] **B. `/api/investment/analyze` × 3 → 200**, Vanguard account detail renders with £788,539 Account Projection at 10yr/80% probability (validates PR #225's `getAccountProjections` restore + PR #227's `(float)` cast).
- [x] **C. Net Worth `_uid` fix live.** `document.querySelector('svg defs linearGradient[id^="nw-alloc-grad-"]')` returned id `nw-alloc-grad-423-0` with `hasUndefined: false`. Zero `_uid` warnings across full console dump.
- [x] **D. Pension projection chart renders non-zero** — £200K–£1M percentile bands over 2026–2056 timeline (validates session 66's content-addressed Monte Carlo cache fix).
- [x] **E. Revolut live pk baked into active chunk.** Prod's active `CheckoutPage-BT54db5H.js` has `pk_sY0uq1Q2d2lo0EO` + `merchant.revolut` + 0 sandbox refs. `/pricing` loads clean, 0 errors / 0 warnings.
- [x] **F. Lifecycle engine dry-run clean under pacing.** 11 deferred users delivered, 0 errored, 150 ms pacing verified on-spec.
- [x] **G. Admin insights image pipeline (intervention/image 3.11.7).** Via Tinker on prod: `ImageManager::gd()->read($logoPath)->cover(1200,630)->toWebp(quality: 85)` → 10,848 bytes valid WebP. Same pipeline for thumb → 3,384 bytes. Exact API used by `InsightImageService::upload()`.

### Outstanding from session 68

- [ ] **Prod hygiene sweep ~24h post-deploy** (i.e. 24 April night-ish): `rm -rf ~/www/fynla.org/public_html/public/build.old` + `rm ~/www/fynla.org/public_html/.env.backup-2026-04-23-*` (two backup files from the lifecycle disable/re-enable). Also purge the **19 historical sandbox-pk CheckoutPage chunks** that have accumulated in `public/build/assets/` from past preserve-old-chunks merges — one of the past csjones-configured builds was uploaded to fynla.org in error. Unreachable via the current manifest (customers only load what the manifest points to) but shouldn't live on a production server. One-liner:
  ```bash
  for f in $(ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org "cd ~/www/fynla.org/public_html && grep -l pk_D2JdE2srRipv0jd public/build/assets/CheckoutPage-*.js"); do ssh … "rm $f"; done
  ```
- [ ] **Consider architectural follow-up for lifecycle engine.** 150 ms pacing is a pragmatic fix; for larger send batches (>100 users) we should consider `ShouldQueue` on the Mailables + a rate-limited queue worker. Not urgent — current daily batches are ~20 users and the cron has plenty of runway.
- [ ] **The 11 failed engaged_trialer sends from the buggy first run are now logged + delivered.** If any of them did NOT reach their inbox (SiteGround's 451 is typically a deferral, so they should eventually arrive even from the failed first attempt), check `lifecycle_email_log` + user support queue.
- [ ] **Exercise the edit-mode auto-expand** on an existing pension or investment account that already has hidden-field values populated. Logic reviewed in diff only; not browser-tested end-to-end. Carried from session 67.
- [ ] **Exercise collapsed-form submit → DB null verification** for both pension + investment forms. Carried from session 67.
- [ ] **Exercise the onboarding path** for the field-collapse toggle. Carried from session 67.

### Context for Next Session

**fynla.org is live on main tip `21ecf67`** (dev tip `bcf9509`) with lifecycle engine paced at 150 ms/send. All 7 smoke tests passed, so prod is stable. Only outstanding item strictly needed before the next session is the 24h cleanup sweep above. The big open next-session task is the ongoing **Fyn AI onboarding** work on `feature/fyn-persona-split` (also coupled with PR #214 / `onboardingFyn`) — see `memory/project_pr214_with_persona_split.md`.

### Outstanding from session 67 (resolved)

- [x] **Cut `dev → main` PR when ready.** PR #228 admin-merged as `27bb188`.
- [ ] Exercise edit-mode auto-expand — still carried (see above).
- [ ] Exercise collapsed-form submit → DB — still carried.
- [ ] Exercise onboarding path for field-collapse — still carried.

### Outstanding from session 66 (resolved)

- [x] **Cut `dev → main` PR when ready** — done as PR #228.
- [ ] **Optional SQL purge on production after dev→main cut** to age out legacy MC cache keys immediately. Still available; not yet run. Safe to defer 24h or skip entirely (cache keys age out naturally):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```

### Outstanding from session 65b (resolved)

- [x] **Verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk.** Verified via bundle grep: `CheckoutPage-CbzaPZdL.js` (local build) has `pk_sY0uq1Q2d2lo0EO` + `merchant.revolut` — matches prod's active `CheckoutPage-BT54db5H.js`.

---

## Session 67 (23 April night) — UI fixes bundle

PR [#226](https://github.com/Stoff73/fynla/pull/226) merged to `dev` as merge commit `416e770`, deployed + browser-tested on `csjones.co/fynla` (per CSJ).

### Completed

#### Six independent UI fixes, one branch (`genUIFixes`)

- [x] **Logout redirects straight to `/login`** — the success modal used to hold the user on the dashboard until they dismissed it. `AppNavbar.vue` now mirrors what `SideMenu.vue` already did: dispatch `auth/logout`, then `router.push('/login')`. Orphan `LogoutSuccessModal.vue` deleted. Commit `acc6086`.
- [x] **Dashboard progress hero now renders for every user**, not only journey users. Skip-to-dashboard and Fyn-onboarded users previously saw a blank top of page. The Scenario Completeness column is hidden when there's no active journey; its column width is split evenly into narrow left + right margins so Profile Completeness and Recommended Actions keep their original `w-1/3` positions. Ring restored to full 140px; labels like "Cash Management" fit on one line without overflowing into the percentage column. Collapsed bar shows overall profile % + "Profile complete" when no journey. Mobile carousel skips the Scenario slide and re-counts pagination dots. Commit `d3756ae`.
- [x] **Pension + Investment Add/Edit forms** — advanced fields now collapse behind a single "Additional information" toggle per form. Auto-expands in edit mode when any hidden field has a user-provided value. Collapsed-on-save nulls the hidden fields in the outgoing payload. Commit `c515aa3`.
  - Pension form (DCPensionForm for Money Purchase types): Lump Sum Contribution, Expected Return %, Platform Fee, Advisor Fee, Beneficiary section, Holdings editor. DB / State branches unchanged.
  - Investment form (AccountForm + StandardInvestmentFields for ISA / GIA / Bonds / VCT / NS&I / Other): Country, Platform/Product Name, Planned Lump Sum (amount + date, both non-ISA and ISA variants), Platform Fee, Holdings editor. Private Investment and Employee Share Scheme sub-forms explicitly left untouched.
  - `expected_return_percent` default changed from `5.0` to `null` so users who never expand the section don't persist a synthetic return assumption.
- [x] **Joint Net Worth Wealth Summary redesigned** — married users previously saw three donuts stacked in the left column (user, spouse, combined) and a right-hand bar chart showing only the current user's figures. Joint users now see two per-person donuts inline, then a full-width Assets-vs-Liabilities bar chart underneath. Hovering a bar opens a custom tooltip: "Category: £TOTAL" with the per-person split below it ("David Mitchell: £755,500 / Sarah Mitchell: £637,500"). Single users keep the original layout untouched. Commit `eaf4552`.
- [x] **Root-cause fix for the recurring "Partner" / "Spouse" regression** — the `userProfile/spouse` getter returned inconsistent shapes across its code paths. `spouseInFamily` paths returned FamilyMember records (which carry a `name` column from the DB), but the `currentUser.spouse` fallback paths built synthetic objects with only `first_name` / `last_name`. Every consumer reading `spouse.name` through those fallback paths silently rendered empty and was masked by `|| 'Partner'` / `|| 'Spouse'` fallbacks in callers. Getter now normalises every return path through a `withName` helper so `name` is always resolved. `NetWorthWealthSummary.spouseUserName`, `PortfolioOverview.getSpouseName`, and `LetterToSpouse.spouseNameForLetter` all updated to read from `userProfile/spouse` first, falling back to the auth inline spouse object, and only then to the string literal. Admin / Estate IHT / Protection analysis / Preview persona spouse-name reads are fed by different data sources (admin users list API, IHT calc response, preview persona JSON) and intentionally not touched. Commits `2a0d7b2` + `7e1739d`.
- [x] **csjones build script output updated** — the post-build echoed instructions pointed at the legacy `public_html/fynla/` layout and omitted the sibling-dir reality (Laravel app at `~/www/csjones.co/fynla-app/`, `public_html/fynla` is a symlink). Script now echoes the correct upload target, the preserve-old-chunks `mv`+`cp -rn` pattern, the full SSH command, and the full cache-clear sequence. No logic change — only the trailing echo. Commit `677f146`.

#### Deploy + docs

- [x] **PR #226 opened, 7 commits, admin-merged to `dev`** as merge commit `416e770`.
- [x] **`April/April23Updates/deployUIFix.md`** — full deploy guide with sibling-dir upload path, preserve-old-chunks pattern, smoke-test steps per fix, rollback, and the promote-to-main handoff. Mirrored to vault.
- [x] **Deployed to csjones.co/fynla dev + browser-tested.** Per CSJ: all six fixes working on the live dev site.
- [x] **Local browser-tested during the session:** pension Add form (collapse/expand, SIPP variant), investment Add form (collapse/expand, GIA + ISA variants), joint net-worth layout (David & Sarah Mitchell preview persona — tooltip split, spouse name on donut + wealth summary + bar chart props), logout redirect.

### Outstanding from session 67

- [ ] **Cut `dev → main` PR when ready.** This deploy passes dev smoke tests. When the next production cut happens, #226 rides along. Production build uses `./deploy/fynla-org/build.sh` (NOT the csjones script — base paths differ).
- [ ] **Exercise the edit-mode auto-expand** on an existing pension or investment account that already has hidden-field values populated. Logic is reviewed in diff only; not browser-tested end-to-end.
- [ ] **Exercise collapsed-form submit → DB verification** for both forms — confirm the null-on-save code path actually writes nulls on a real save.
- [ ] **Exercise the onboarding path** for both forms. Both accept `isOnboarding` prop but only the standalone modal path was browser-tested this session.

### Outstanding from session 66 (carried forward)

- [ ] **Cut `dev → main` PR when ready.** Pension projection fix + nav refresh (PR #225) still pending production cut.
- [ ] **Optional SQL purge on production after dev→main cut** to age out legacy MC cache keys immediately (otherwise 24h wait):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 66 (23 April evening) — pension projection + unified add pension + nav refresh

PR [#225](https://github.com/Stoff73/fynla/pull/225) merged to `dev` as commit `6b7306d`, deployed + browser-tested on `csjones.co/fynla`, old builds cleaned up.

### Completed

#### The long-standing pension projection regression, fixed at the root
- [x] **Reproduced the "pension added but projection shows £0" bug** live on `sarah@example.com` — the pension's fund value rendered correctly on the dashboard but `pension_pot_projection.percentile_20_at_retirement` and the year-by-year Monte Carlo array were all zeros. No console errors. The API returned structurally-valid data that happened to all be zero.
- [x] **Traced the root cause to the Monte Carlo DB cache.** Cache key for `projectPensionPot` was `user_{id}_pension_pot_{years}y_e{eventHash}` — user, years-to-retirement, and life-event hash, but **not** the actual simulation inputs (start value, monthly contribution, return, volatility). When a brand-new user loaded the dashboard with zero pensions, `simulate(0, 0, …)` produced all zeros and cached them under that key. When the user added a pension, `simulate(50000, 500, …)` hit the same key and got the stale zeros back.
- [x] **Fix: content-addressed cache key.** Hashed the four numeric inputs into the key (`md5("{startValue}:{monthly}:{return}:{vol}")`). Input changes → new key → fresh simulation. No observer wiring, no write-path coupling — which is why the previous attempts to fix this at the write side (observers, central `CacheInvalidationService`) kept regressing. Commit `a6cfa5a`. Same fix applied to `projectIndividualDCPension`.

#### Unified Add Pension form (no more three-tile picker)
- [x] **Replaced the tile picker** that had Money Purchase / Final Salary / State Pension with a single "Add Pension" form. Pension type dropdown now carries Occupational, SIPP, Personal, Stakeholder, **Final Salary (Defined Benefit)**, **State Pension** — all six in one place.
- [x] **Conditional field groups** inside `DCPensionForm`: picking Final Salary swaps body to DB fields (scheme status, annual income, service years, accrual rate, revaluation rate, PCLS). Picking State Pension swaps to State fields (forecast weekly, qualifying years, NI gaps). Backend payload shapes mirror the legacy `DBPensionForm` / `StatePensionForm` outputs exactly — verified `db_pensions` and `state_pensions` records are identical whether captured via this unified form or edited via the legacy forms. Commit `5a7ecec`.
- [x] **Onboarding scoped** — when `isOnboarding=true`, the two new dropdown options are hidden via `v-if="!isOnboarding"` so the onboarding DC pension step keeps its original 4-option dropdown and its `dc_pension` AI-fill wiring.
- [x] **Edit flows untouched** — existing DB and State pension edits still render the legacy `DBPensionForm` / `StatePensionForm` via `initialPensionType` routing.

#### SubNavBar hidden globally, CTAs moved inline
- [x] **SubNavBar suppressed** (`v-if="false"` in `AppLayout.vue`). Component + `subNavConfig.js` kept intact — one-char revert to re-enable. Commit `88af49a`.
- [x] **Retirement CTAs inline** under the pension list, right-aligned next to the projection chart (same raspberry / bordered styling as the old SubNavBar). Commit `618e0ba`.
- [x] **Investments CTAs inline** at the bottom of the accounts column (same convention as retirement).
- [x] **Property-type pages CTAs** top-right of the list on Property, Liabilities, Personal Valuables, Business, Trusts, Goals.
- [x] **Duplicate CTAs resolved** — Cash and Protection already had inline buttons (hiding the SubNavBar removes the duplicates). `GoalsOverview` had its own quick-add row that would have doubled with the new tab-header Add Goal — removed.
- [x] **Life Events** uses `EventsTab`'s own internal Add button — not duplicated in the tab header.

#### Sticky top nav
- [x] **AppNavbar wrapper** is now `sticky top-0 z-30 bg-eggshell-500` in `AppLayout.vue`. Dashboards scroll under it; nav always visible. Offsets to `top-[44px]` when the AdvisorBanner is active during advisor impersonation. Docked-chat `headerOffset` calculation continues to work — as a bonus, the chat no longer jumps upward as the user scrolls since the header bottom edge stops moving. Commit `2901b30`.

#### Investment account detail projection fix (same session, different shape)
- [x] **Found and fixed a matching-but-different projection bug** — clicking into an investment account card showed "Failed to load projection data" with `TypeError: investmentService.getAccountProjections is not a function` in console. Not a cache bug — the frontend service method itself was missing (likely removed by commit `d635d36`'s dead-code sweep and never restored by the `b0ad5ad` revert). Backend route + controller were fine. Added the method back with optional `risk_level` param for the what-if feature the backend already supports. Commit `f2ba360`.

#### Small UX polish
- [x] **Browser tab always reads "Fynla"** — `Login.vue` was setting `document.title = 'Sign In — Fynla'` on mount and nothing reset it post-login, so the tab label stuck as "Sign In — Fynla" across the whole authenticated session. Login.vue now sets `'Fynla'`, and a `router.afterEach` hook keeps the tab title as `'Fynla'` on every SPA navigation. Blade template's long marketing title untouched for SEO crawlers. Commit `e653180`.

#### Deploy + docs
- [x] **PR #225 opened, pushed through 8 commits, admin-merged to `dev`** as merge commit `6b7306d`.
- [x] **`April/April23Updates/deployPensionFix.md`** — upload checklist, SSH command sequence, 7-part smoke-test plan, rollback, optional SQL purge for legacy MC cache rows. Mirrored to vault.
- [x] **`April/April23Updates/patchPensionInvest.md`** — end-user patch notes (plain English, no tech jargon). Mirrored to vault.
- [x] **Dev server deployed + browser-tested by CSJ.** All 7 smoke-test sections passed. Old `public/build.old` and `public/build.old2` directories removed from `~/www/csjones.co/fynla-app/public/` — freed ~23MB.

### Outstanding from session 66

- [ ] **Cut `dev → main` PR when ready.** This deploy passes all smoke tests on dev. Production cut-over guidance is in `deployPensionFix.md` §Production cut-over. Must include PR #224 (intervention/image v3 downgrade) carried through — verified by running `composer show intervention/image` on dev reporting `3.11.7`.
- [ ] **Optional SQL purge on production after the dev→main cut** to age out legacy MC cache keys immediately (otherwise 24h wait):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```

### Outstanding from 65b (carried forward)

- [x] **Complete the in-flight checkout test** — ticked at session 66 start after CSJ confirmed it was done.
- [x] **Clean up `public/build.old/` and `public/build.old2/`** on the dev server — done at end of session 66.
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 65b (23 April late-afternoon) — CSP / Revolut / .env cascade

### Completed

- [x] **Removed HSTS + CSP + Permissions-Policy `Header set` from both `.htaccess` templates** (`deploy/csjones-fynla/.htaccess`, `deploy/fynla-org/.htaccess`). Apache's `Header set` was overwriting `SecurityHeaders` middleware's richer CSP and blocking Revolut widget on dev. Commit `f0770bb`.
- [x] **Uploaded new csjones `.htaccess` to dev server**, cleared Laravel caches.
- [x] **Fixed dotenv syntax on server `.env` line 62** — `ADMIN_EMAILS` now quoted (was unquoted comma-separated value with whitespace, invalid dotenv syntax that was hidden by config cache until `config:clear` exposed it). Backup at `.env.backup-2026-04-23-csp-fix`.
- [x] **Pinned `VITE_REVOLUT_SANDBOX=true` + `VITE_REVOLUT_PUBLIC_KEY=pk_D2JdE2srRipv0jdHerivLw1hMoWSrjqDa4lEozJxTwchuG04`** into `deploy/csjones-fynla/build.sh`. Builds now reproducible regardless of builder's local `.env`. Commits `921bb3d` + follow-up.
- [x] **Rebuilt + uploaded** new `public/build/`. New `CheckoutPage-CAePoYgl.js` has correct sandbox SDK URL + correct merchant pk, Revolut widget 403s are gone.
- [x] **Preserved old build chunks** alongside new ones (`cp -rn public/build.old/. public/build/`) so CSJ's in-flight incognito session survived the rebuild without a forced refresh — every route except `/checkout` continued to work mid-session.
- [x] **Incident log written** at `April/April23Updates/revolutCSPIncident.md` + mirrored to vault. Documents timeline, root causes, fixes, and 5 rules for next session (chief rule: warn CSJ before rebuilding during active browser testing).

### Outstanding from 65b

- [x] **Complete the in-flight checkout test** — CSJ's original session has the pre-fix `CheckoutPage-Dq2ZEZzV.js` in memory with the wrong pk. Needs a fresh incognito window to exercise the correct `CheckoutPage-CAePoYgl.js` chunk and confirm the full sandbox checkout flow works end-to-end.
- [x] **Clean up `public/build.old/` and `public/build.old2/`** on the dev server once ~24h have passed and no one is on a pre-rebuild session. `rm -rf` both. *Done end of session 66 — freed ~23MB.*
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 65 (23 April afternoon) — PR triage + dev deploy + intervention/image v3 downgrade

### Completed This Session

#### Repository + branch protection
- [x] **Re-enabled branch protection on `dev`** — 1 required PR review, code-owner review required (CODEOWNERS pins `@Stoff73`), dismiss stale reviews, required conversation resolution, no force pushes, no deletions. `enforce_admins: false` retained so CSJ can admin-bypass when needed.
- [x] **Re-enabled branch protection on `main`** — identical settings to dev. Previously unprotected, which contradicted CLAUDE.md's documented workflow.
- [x] **Saved new durable rule** in memory (`feedback_main_via_dev_only.md`): nothing merges to main without first being committed to dev, deployed to csjones.co/fynla, and browser-tested. Only CSJ overrides with explicit words in the current turn. MEMORY.md index updated.

#### PR triage (5 PRs processed)
- [x] **PR #213 closed** — stale session 52 CSJTODO doc, superseded by later handovers.
- [x] **PR #212 re-targeted** from `main` → `dev` (violated the new rule by targeting main directly).
- [x] **PR #221 rebased** onto the refreshed `dev` — CSJTODO conflict resolved by taking dev's newer version; force-pushed; admin-merged via `gh pr merge 221 --merge --admin`. Campaign pages + ReviewCarousel + StaticFynChat + 404 page now on dev.
- [x] **PR #223 opened + admin-merged** (`main → dev` back-merge) — brought session 64's subscription hotfix + session 63/64 handover docs onto dev. Dev was missing 3 commits (`ad73bd0`, `5cd5d62`, `bd9042e`) that had been admin-merged directly to main. Clean merge — only `AppLayout.vue` overlapped and auto-merged.
- [x] **PR #212 rebased** onto new `dev` through 40+ commits, 6 conflict points resolved manually (CSJTODO, CLAUDE.md, trial-expiration-reminder.blade.php, routes/web.php twice, AppLayout.vue three times, router/index.js, Settings.vue deletion). Force-pushed and admin-merged. Full lifecycle email engine (5 campaigns + engine + E2E test commands + magic-link routes + NotificationPreferences page + 14 toggles) now on dev.
- [x] **PR #224 opened + admin-merged** — downgraded `intervention/image ^4.0 → ^3.0` to keep PHP 8.2 compatibility, ported `InsightImageService` to the 3.11 API (`ImageManager::gd()`, `->read()`, `->toWebp(quality:)`). 9/9 existing tests still pass.

#### Dev server redeploy (csjones.co/fynla) — 167 files uploaded, 7 deleted, 12 migrations ran
- [x] **Server state probed via SSH** — confirmed server was at approximately `origin/onboardingFyn` state (last migration `2026_04_15_153100`), not main. Real delta was 173 files not the 153 my original guide assumed.
- [x] **`filesUploaded.md` comprehensive checklist** generated and mirrored to repo + vault. 215 line items across §A upload / §B delete / §C exclusions / §D server commands / §E smoke tests / §F rollback.
- [x] **167 files uploaded** via tar-pipe in 0.3s; hash-verified byte-for-byte match against `origin/dev`.
- [x] **7 superseded files deleted** on server (OnboardingChatDirector, OnboardingPromptBuilder, OnboardingStateMachine, OnboardingValueInterpreter, SpouseLinkingService, EmptyDataGuard, config/onboarding.php). 2 items in delete list were already absent.
- [x] **composer install** — resolved to `intervention/image 3.11.7` + `intervention/gif 4.2.4`, both PHP 8.2 compatible. Platform-check re-enabled and passing.
- [x] **Appended `.env` vars**: `LIFECYCLE_ENGINE_ENABLED=true` + `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`. Deduped after a session confusion created doubles. `.env.backup-2026-04-23-post-lifecycle` preserved.
- [x] **12 pending migrations ran** — 7 lifecycle + 5 insights, all `DONE`.
- [x] **Cache clears + optimize** — config + routes cached.
- [x] **Insights seeder** — 8 bespoke articles seeded.
- [x] **Full `php artisan db:seed --force`** — 22 seeders all green, including **OccupationCode (406 codes)**, Preview users (6 personas), ChrisUser, AdvisorClient, etc.
- [x] **Lifecycle engine smoke test** — `php artisan lifecycle:run-daily` ran all 5 campaigns cleanly (0 eligible users, as expected).
- [x] **Endpoint smoke tests** — `/fynla/`, `/fynla/pricing`, `/fynla/quickstart`, `/fynla/insights`, `/fynla/how-it-works`, `/fynla/features`, bad-URL SPA fallthrough → all HTTP 200.

#### Landing page CTA
- [x] **Unhid "Quick start with Fyn" CTA** on the landing page hero — commit `97edb5d` admin-pushed to dev. The HTML comment markers were removed; the `<router-link to="/register?from=fyn">` now renders live on both localhost:8000 and csjones.co/fynla. Known caveat: new-user Fyn flow has bugs (per `April/April9Updates/fynQuickStartBugs.md`) — CTA-to-flow fixes deferred to a future session.

#### Supporting docs (all mirrored to repo + vault)
- [x] `April/April23Updates/devUpdateDeploy.md` — initial deploy guide (subsequently superseded by filesUploaded.md when server state turned out to be further behind than main).
- [x] `April/April23Updates/filesUploaded.md` — authoritative 215-item upload + server-command checklist; all §A/§B/§D items (except optional §B4 renames + cron verification) ticked.
- [x] MEMORY.md index updated with new project memory for PR #214 coupling with `feature/fyn-persona-split`, and new feedback rule for main-via-dev-only workflow.

### NOT Done — Outstanding from Session 65

- [ ] **Browser smoke-test PR #221 features** end-to-end on csjones.co/fynla dev — 14 items listed in `filesUploaded.md` §E. This is the next-session opening task. Tech stack to exercise: `/quickstart`, QuickStart CTA (newly unhidden), ReviewCarousel on pricing/features/how-it-works, NotFoundPage fall-through, `/profile/notifications` toggles, lifecycle magic-link → discount prefill, admin insights image upload (tests intervention/image 3.11.7 port).
- [ ] **Fix Fyn quickstart bugs** — see `April/April9Updates/fynQuickStartBugs.md`. CTA is now live on dev but clicks route to `/register?from=fyn` which hits the known-buggy new-user Fyn flow. User explicitly deferred this to a later session.
- [ ] **Verify SG Site Tools crontab** — `crontab -l` via SSH returns empty, yet existing daily jobs (`trials:send-reminders`, `trials:expire`, etc.) clearly run on dev. SiteGround manages cron via their Site Tools web UI. Check that `* * * * * php artisan schedule:run` is configured for csjones.co; if not, the 08:30 UTC daily lifecycle job will silently never fire.
- [ ] **Test lifecycle engine end-to-end** with real emails — `php artisan lifecycle:e2e-test` seeds 5 test users and runs all campaigns against them, sending to `chris@fynla.org` (the LIFECYCLE_TEST_RECIPIENT override). Then `php artisan lifecycle:e2e-cleanup` removes them. Verifies magic-link routes, WebP hero rendering, discount code generation, restart-trial handler, feedback capture.
- [ ] **Optional §B4 cleanup** on server — delete the 7 stale Vue source files on the server (`Navbar.vue`, `Footer.vue`, `Holdings.vue`, `Performance.vue`, `Recommendations.vue`, dead `Goals.vue`, dead `UserProfile/Settings.vue`). Purely cosmetic — build output doesn't reference them.

### Context for Next Session

Dev branch is fully in sync with csjones.co/fynla server. Working tree is clean. Local dev server was running at end of session on Laravel :8000 + Vite :5173 — may still be up or may have been shut down. The big next-session task is browser-testing all the deployed PR #221/#212 features on the dev server, specifically the ones newly visible via the unhidden QuickStart CTA. After dev is stable and browser-tested, the next PR pipeline is `dev → main` for production rollout — but that must include #224's intervention/image downgrade or production will 500 on first composer install.

---

## Outstanding — Tech Debt Deferred (from earlier sessions)

- [ ] **Session 63 tech-debt branch** — already merged to dev (via PR #220) but still needs browser-test matrix before `dev → main`. 8 flows in `April/April18Updates/handover-tech-debt.md §4a`: Estate/IHT dashboard, Investment (holdings/fees/tax/rebalance), Protection, Expenditure form penny-level totals, Estate CRUD, Net worth, Savings, Investment detail.
- [ ] **28 Vue god components** (>800 lines) — prioritise `Admin/TaxSettings.vue` (3,068 lines) and `UserProfile/ExpenditureForm.vue` (2,574 lines). Multi-week effort.
- [ ] **13 backend god files** — `SavingsActionDefinitionService.php` (3,686 lines), `RetirementActionDefinitionService.php` (2,701), `ProtectionActionDefinitionService.php` (2,349), `RetirementIncomeService.php` (2,292), `IHTCalculationService.php` (1,641).
- [ ] **54 controllers using inline `$request->validate()`** — convert to Form Request classes (~60-80h total).
- [ ] **npm `--force` fix** — schedule a 2-4h window for vite 8 + `@capacitor/cli` 8 major upgrades with full PWA + iOS + web regression. 6 high-severity vulnerabilities remain until done. Carried from session 63.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deployed in session 58 but not browser-tested. Carried from session 58.
- [ ] **Add `Current State/Insights.md`** to the vault — carried from session 62.
- [ ] **`AutoRiskCalculatorTest` pre-existing failure** — `risk_level` enum truncation. Pre-existing since 16 April.

## Follow-ups from news-subscribe-fix (2026-04-28)

- [ ] **Newsletter broadcast** — when a `NewsArticle` flips to `status='published'`, fan out an email to all confirmed `NewsSubscriber` rows (`->confirmed()` scope). Should be queueable, paced (avoid SMTP 451 — see Session 67 lifecycle hotfix), and skip subscribers who unsubscribe between queueing and sending. Out of scope for the news-subscribe-fix branch which only built list-build infrastructure.
- [ ] **PR-237 Finding #16 — News/RSS/lifecycle test coverage** — news-subscribe-fix added 20 tests for the new code, but the original PR-237 news/RSS/lifecycle code (~1,000 lines) still has no tests. Add a separate PR with unit/feature tests for `NewsController`, `FeedController`, `NewsArticle::published()` scope, RSS XML schema, and Lifecycle Mailable construction.

## Known Issues

- **CLAUDE.md stale tax-year claim** — says `active: 2025/26` but the seeded `TaxConfiguration` table correctly has `2026/27` active (which is right — 2026/27 started 6 April 2026). `TaxConfigService` reads from DB so behaviour is correct; the line in CLAUDE.md just wants a one-character update.
- **Build script deploy-path echo** is outdated — `./deploy/csjones-fynla/build.sh` prints `~/www/csjones.co/public_html/fynla/public/build/` but the actual sibling-dir path is `~/www/csjones.co/fynla-app/public/build/`. Cosmetic.
- **Dev server user crontab empty** — see "Outstanding — verify SG Site Tools crontab" above.

## Deploy Status

- **fynla.org (production)** — unchanged from session 64. `ad73bd0` subscription hotfix live. Test user `bugrepro_expired_2026_04_23@fynla.org` still in grace-period state.
- **csjones.co/fynla (dev)** — fully in sync with dev branch tip `97edb5d`. All four merged PRs (#212, #220, #221, #223) plus session 65's CTA unhide deployed. composer, .env, migrations, seeds, caches all current.
- **Pending production deploy** — `dev → main` PR not opened. Must include PR #224 (intervention/image v3) or production will 500 on first composer install due to PHP 8.3 requirement. Don't open the `dev → main` PR until session 65's browser testing is complete and any uncovered issues are fixed.
- **Open PRs remaining:** #214 (`onboardingFyn` → `dev`) — still CONFLICTING, coupled with `feature/fyn-persona-split` per memory. Do NOT rebase/merge in isolation.

## Active Work Not Carried by PR

- **Local dev server:** running at `http://localhost:8000/` + Vite `:5173` as of end of session. Check with `lsof -i :8000` before relying on it next session.
- **SSH key:** `~/.ssh/fynlaDev` was loaded into the agent this session (`ssh-add`). It'll remain loaded until the agent cache expires or the machine is rebooted.
