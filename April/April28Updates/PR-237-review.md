# PR #237 Review — News hub + RSS feeds + lifecycle emails + landing fixes

**PR:** https://github.com/Stoff73/fynla/pull/237
**Author:** Phailanx (external contributor)
**Reviewer:** CSJ (with Claude Opus 4.7)
**Date:** 2026-04-28
**Original branch:** `rss-feed` → renamed/squashed to `feature/phailanx/news-rss-lifecycle-emails`
**Replacement commit:** `24cc76d` on `feature/phailanx/news-rss-lifecycle-emails` (pushed to origin)
**Stats:** 69 files changed, +3,936 / −34, 1 squash commit (was 9 commits on the original PR)

---

## Decisions

| # | Item | Decision |
|---|------|----------|
| 1 | Test-command bug + `9 → 11` docstring drift | **Approved & fixed** |
| 2 | Migration safety guard + factory `fake()` helper | **Approved & fixed** |
| 3 | Icons on public landing surfaces (RSS, open-in-new-window, play, right-arrow) | **Approved — these icons are fine** |
| 4 | Branch naming violation (`rss-feed`) | **Rebase onto `feature/phailanx/news-rss-lifecycle-emails` and squash** — done |

---

## Overview of the PR

Three bundled streams:

1. **News hub & RSS feed** (new). DB-backed `news_articles` table with model + scope, public JSON API at `/api/news` + `/api/news/{slug}`, public RSS 2.0 feeds at `/feed/news.xml` + `/feed/insights.xml`, redesigned `NewsHubPage` (gradient hero featured card + 3-col recent grid + light-pink RSS subscribe panel + pink "Want to stay updated?" CTA) and `NewsArticlePage` (subtitle-formatted lead paragraph + bespoke insights typography + pink-100 CTA). Footer link "Accreditations" replaced with "News".
2. **Landing / campaign-page fixes** (forward-port from `email-onboarding-video` that never reached `main`). Stats-bar copy restored ("1000's of financial plans created"), latest-insights section gated behind `insightsFeatured` with a three-article static fallback, homepage + campaign-page videos swapped to `Homepage-Fynla-ProductVideov2.mp4` with click-to-play overlay, Meta Pixel gated behind `app()->environment('production')`, About-page anchor IDs added so the launch article's co-founder links land cleanly below the navbar.
3. **Lifecycle email system** (foundation). Master Blade layout on white-outer/eggshell-600px-container, 19 reusable module partials, `LifecycleMail` abstract base with UTM helper, 11 concrete Mailables + 11 Blade templates (welcome → we-haven't-seen-you), `mail:send-lifecycle-test` artisan command for visual QA. Three follow-up fixes also bundled: footer logo filename, `great-job` top-tips inline (avoiding nested `<tr>` inside `<td>`), Unsubscribe href (so Gmail's default link styling doesn't override inline styles).

---

## Findings (severity / confidence tagged)

### Blockers — needed CSJ decision before merge

**B1. Branch name violates external-contributor convention** · severity HIGH · confidence HIGH
- Branch was `rss-feed`. CLAUDE.md mandates `feature/phailanx/<task>` for external contributors. Author flagged this in the PR body.
- **Decision:** Rebase onto `feature/phailanx/news-rss-lifecycle-emails` and squash. Done locally; pushed to origin as commit `24cc76d`.

**B2. Icons on public landing/marketing surfaces** · severity HIGH · confidence MEDIUM
- Rule #14: side-nav is the only allowed surface; chat / dashboard cards / detail views are explicitly banned; "Other surfaces" require asking CSJ.
- Icons added on public surfaces:
  - RSS antenna SVG + open-in-new-window glyph in the subscribe panel — `NewsHubPage.vue:23`, `:31`, `:142`
  - Right-arrow glyphs on the featured hero card and recent-article tiles — `NewsHubPage.vue:73`, `:94`
  - Play-triangle SVG inside the video overlay — `LandingPage.vue` and `CampaignPage.vue`
- **Decision:** These icons are fine on public landing surfaces. No change required.

### Real bugs — fixed in the squash commit

**1. `array_keys()` on the already-emptied sequence** · severity LOW · confidence HIGH
- `app/Console/Commands/SendLifecycleTestCommand.php:96-99`
- `array_intersect_key` empties `$sequence`, then `array_keys($sequence)` returns `[]` — the error reads "Known slugs: " with nothing.
- **Fix applied:** Capture `$knownSlugs = array_keys($sequence)` **before** the intersect; reference `$knownSlugs` in the error.

**2. Stale "9" → "11" in command signature & description** · severity LOW · confidence HIGH
- The sequence array now has 11 entries (welcome, get-started, dont-miss-out, insights, great-job, well-done, subscribe-in-progress, subscribe-max, countdown, end-of-trial, we-havent-seen-you). Signature and description still said "9".
- **Fix applied:** Updated both to "11".

### Convention drift — fixed in the squash commit

**4. Migration missing `Schema::hasTable()` guard** · severity LOW · confidence HIGH
- `database/CLAUDE.md` mandates the guard. Other 2026 migrations follow it.
- **Fix applied:** Added `if (Schema::hasTable('news_articles')) { return; }` at the top of `up()`.

**5. Factory uses `$this->faker` instead of `fake()` helper** · severity LOW · confidence HIGH
- `database/CLAUDE.md` says: "Use `fake()` (not `$this->faker`)". Five call sites in this factory used the old form.
- **Fix applied:** All five swapped to `fake()`.

### Quality issues — recorded for follow-up (not fixed in this PR)

**3. `subscribe-max` slug vs `SubscribeMaxDiscountMail` name mismatch** · severity LOW · confidence MEDIUM
- Other slugs are derived directly from the email name (e.g. `great-job` ↔ `GreatJobMail`). If a future sweep auto-derives slugs from class names, `subscribe-max` will silently change. Worth tightening to `subscribe-max-discount` for parity. Not fixed here — flagging for a future small PR.

**6. Composite index would serve the published-list query better** · severity LOW · confidence MEDIUM
- Current migration has separate `index('status')` and `index('published_at')`. The `published()` scope filters `status='published' AND published_at <= now()` ordered by `published_at DESC`. MySQL won't combine the two single-column indexes effectively. A composite `(status, published_at)` is the canonical shape. Free win on a one-line change but probably fine while the table is small.

**7. `enum('status', ...)` column type** · severity LOW · confidence LOW
- Adding a fourth status later requires a schema migration. Most other Fynla tables use `string` + `Rule::in()` validation. Not a hard rule, just noting.

**8. `v-html="article.body"` renders trusted HTML — document the trust boundary** · severity MEDIUM · confidence HIGH
- `resources/js/views/Public/NewsArticlePage.vue:45`
- Body is rendered raw via `v-html`. Currently safe because the only writer is the seeder. When an admin UI lands, body must be sanitised on input (HTMLPurifier) or rendered through a markdown-to-HTML pipeline. Recommend adding a trust-boundary comment near the `v-html` call and on the seeder.

**9. `:deep(p:first-child)` selector is brittle for future articles** · severity LOW · confidence MEDIUM
- `NewsArticlePage.vue:150`. If a future article opens with `<h2>` instead of `<p>`, the lead-paragraph styling silently disappears unless the author explicitly sets `<p class="lead">`. Safer: drop the `:first-child` fallback and only style explicit `.lead`.

**10. `notFound = true` swallows network errors** · severity LOW · confidence HIGH
- `NewsArticlePage.vue:107-109` shows "Article not found" for any failure (404, 500, offline). Distinguish on `err.response?.status === 404`.

**11. `image_url` exposed in list resource but never rendered on the hub** · severity LOW · confidence HIGH
- `NewsArticleListResource` exposes `image_url`; `NewsHubPage` recent-articles tiles render no image. Either render it (graceful fallback when null) or drop from the resource until needed.

**12. Unicode `✓` / `✗` in CLI output** · severity LOW · confidence HIGH
- `SendLifecycleTestCommand` lines 118, 121. Mojibake on hosts without UTF-8 locale. Cosmetic; `[OK]` / `[FAIL]` is more portable.

**13. Stats-bar hard line-break (`<br/>`)** · severity LOW · confidence MEDIUM
- `LandingPage.vue:289` — `UK adults don't get<br/>financial advice`. Forces a wrap that may look odd at certain viewport widths. Prefer letting CSS wrap naturally.

**14. `{!! $step['description'] !!}` raw output in welcome.blade** · severity LOW · confidence HIGH
- `welcome.blade.php:76`. Current strings have no HTML — this is `{{ }}`-safe. Using `{!!` is a dormant XSS sink if the data ever becomes dynamic.

**15. Static insights fallback duplicates DB content** · severity LOW · confidence HIGH
- `LandingPage.vue:498-503`. Acceptable as a CMS-off escape hatch but will drift from `ExistingInsightsMetadataSeeder.php`. Add a comment marking this as the intentional fallback shown only when `VITE_INSIGHTS_CMS_ENABLED !== 'true'`.

### No-tests gap

**16. Zero new tests for ~1,000 lines of new backend code** · severity MEDIUM · confidence HIGH
- News module: no unit/feature tests for `NewsController`, `FeedController`, model `published()` scope, or the seeder.
- Lifecycle emails: no smoke tests that each Mailable can construct + render.
- RSS XML: no schema validation test.
- Existing public Insights APIs (`tests/Feature/Api/Public/InsightControllerTest.php`) are a good template — News should mirror it. Open a follow-up to add coverage in a separate PR.

### Notes / non-issues (recorded for completeness)

- **NewsController response shape** matches existing `InsightController` (raw `AnonymousResourceCollection`, no `{success, message, data}` envelope). Public API pattern is consistent — not a violation.
- **`/feed/insights.xml` try/catch on missing table** is sensible; comment in code explains rationale.
- **RSS XML escaping** uses Laravel's `e()` helper plus CDATA for description — correct.
- **Routes ordering** — feeds declared before SPA catch-all in `routes/web.php` — correct (commented).
- **Meta Pixel gate** (`@if(app()->environment('production'))`) is the right shape.
- **About page anchor IDs** with `scroll-mt-24` — clean addition.
- **Footer link change** ("Accreditations" → "News") — the previous link pointed back to `/about` (placeholder), so swapping is fine; FCA link still present.
- **14 MB MP4** committed to `public/images/` — not great practice but consistent with existing `fynla-dashboard-walkthrough.mp4`. Future Git LFS migration if repo size becomes an issue.

---

## What changed in the squash branch (vs the original PR)

**Same as PR #237** for the bulk of the work: 69 files, ~3,931 line additions, ~34 deletions of placeholder content.

**Plus four fixes from this review:**

```diff
# app/Console/Commands/SendLifecycleTestCommand.php
- protected $signature = '... default is all 9}';
+ protected $signature = '... default is all 11}';
- protected $description = 'Fire the 9 lifecycle emails ...';
+ protected $description = 'Fire the 11 lifecycle emails ...';

  $only = $this->option('only');
  if ($only !== null && $only !== '') {
+     $knownSlugs = array_keys($sequence);
      $selected = array_map('trim', explode(',', $only));
      $sequence = array_intersect_key($sequence, array_flip($selected));
      if (empty($sequence)) {
-         $this->error("No matching slugs. Known slugs: " . implode(', ', array_keys($sequence)));
+         $this->error("No matching slugs. Known slugs: " . implode(', ', $knownSlugs));

# database/migrations/2026_04_27_120000_create_news_articles_table.php
  public function up(): void
  {
+     if (Schema::hasTable('news_articles')) {
+         return;
+     }
+
      Schema::create('news_articles', function (Blueprint $table) {

# database/factories/NewsArticleFactory.php
- $title = $this->faker->sentence(6);
+ $title = fake()->sentence(6);
- 'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
+ 'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
- 'summary' => $this->faker->sentence(20),
+ 'summary' => fake()->sentence(20),
- 'body' => $this->faker->paragraphs(6, true),
+ 'body' => fake()->paragraphs(6, true),
```

All four PHP files pass `php -l` syntax check.

---

## Test plan when CSJ pulls and tests on local

1. `git fetch origin && git checkout feature/phailanx/news-rss-lifecycle-emails`
2. `php artisan migrate --force` succeeds and creates `news_articles` (idempotent — re-running is a no-op).
3. `php artisan db:seed --class=NewsArticleSeeder --force` creates the launching-fynla announcement (idempotent — `updateOrCreate`).
4. `/news` renders in browser via Playwright: gradient hero featured card, 3-col recent grid, pink RSS subscribe panel opens `/feed/news.xml` in a new tab.
5. `/news/launching-fynla` renders: subtitle-formatted lead paragraph, co-founder names link to `/about#chris-slater-jones` and `/about#brett-isenberg`, pink-100 CTA after body.
6. `curl -i http://localhost:8000/feed/news.xml` returns valid RSS 2.0 with `Content-Type: application/rss+xml; charset=UTF-8` and at least one `<item>`.
7. Homepage stats bar reads "91% / UK adults don't get financial advice" + "1000's / of financial plans created for people like you".
8. Latest insights — when `VITE_INSIGHTS_CMS_ENABLED` is `false`, three static articles render. Toggle and confirm.
9. Homepage + campaign-page videos play `Homepage-Fynla-ProductVideov2.mp4` with click-to-play overlay (no autoplay).
10. Meta Pixel: view source on dev landing page (running with `APP_ENV=staging`) — pixel script must NOT be present. Confirm presence on production after main release.
11. `php artisan mail:send-lifecycle-test you@example.com --only=welcome` sends one test email correctly.
12. `php artisan mail:send-lifecycle-test you@example.com --only=does-not-exist` shows the corrected error: `Known slugs: welcome, get-started, dont-miss-out, ...` (proves Bug 1 fix).
13. Footer: "News" link routes to `/news`. FCA link still present.

---

## Deployment to dev (csjones.co/fynla)

After local browser testing passes:

1. **Open replacement PR** `feature/phailanx/news-rss-lifecycle-emails` → `dev` on GitHub. Close PR #237 with a reference to the new PR.
2. Self-review and merge.
3. `git checkout dev && git pull`
4. `./deploy/csjones-fynla/build.sh` (sets `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`, `VITE_REVOLUT_SANDBOX=true`).
5. Upload `public/build/` + changed PHP files to `~/www/csjones.co/public_html/fynla/` via SiteGround File Manager or rsync.
6. SSH in and finalise:
   ```bash
   ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
   cd ~/www/csjones.co/public_html/fynla
   php artisan migrate --force
   php artisan db:seed --class=NewsArticleSeeder --force
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```
7. Smoke test https://csjones.co/fynla/news and https://csjones.co/fynla/feed/news.xml.

After dev is green, periodic `dev → main` PR will roll the news hub + lifecycle email foundation out to production.

---

## Recommendation

**Approve and merge** `feature/phailanx/news-rss-lifecycle-emails` → `dev` after local browser testing.

PR #237 should be closed with a note pointing at the replacement PR for traceability — original `Phailanx:rss-feed` ref is preserved in the squash commit's co-author attribution.
