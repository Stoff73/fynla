# Verification: visibility, speed, visual, sitemap, smoke tests (Phases 8, 9, 12, 13, 15)

## Contents
- Phase 8 — HTML Visibility Verification (MANDATORY after writing)
- Phase 9 — Speed Test Check (run after every new or updated page)
- Phase 12 — Visual Consistency Testing (mandatory for every styling change)
- Phase 13 — Sitemap.xml Check (mandatory when URLs change)
- Phase 15 — Post-Conversion Smoke Tests (mandatory after every new or converted page)

## Phase 8 — HTML Visibility Verification (MANDATORY after writing)

After writing the page, verify that the raw HTML is actually visible to crawlers — not an empty SPA shell.

**The critical check:** `curl -s http://localhost:8000/PAGE-SLUG | grep -c "<h1\|<p\|<section"` must return > 0.
If it returns 0, the route is serving the Vue SPA shell (`<div id="app"></div>`) instead of the PHP page.

**How to verify:**
```bash
# 1. Check the homepage route returns PHP content (not the SPA shell)
curl -s http://localhost:8000/ | grep -c "div id=\"app\""
# → Must return 0 (zero matches = PHP page, not SPA shell)

# 2. Check meaningful HTML exists
curl -s http://localhost:8000/ | grep -E "<h1|<section|<main" | head -5
# → Must show real HTML tags

# 3. Check the page title is correct
curl -s http://localhost:8000/ | grep "<title>"
# → Must show the page title, not a generic fallback
```

**Why this matters:** If `routes/web.php` is missing the explicit route for the page path, Laravel's
SPA catch-all (`/{any}`) serves `view('app')` — a `<div id="app"></div>` shell. Google sees nothing.
All the SEO meta tags, schema markup, and content are invisible to crawlers.

**When it fails:** Add an explicit route BEFORE the catch-all in `routes/web.php`:
```php
// MUST come before the catch-all Route::get('/{any}', ...)
Route::get('/your-page', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/your-page.php');
    $html = ob_get_clean();
    return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});
```

Add this check to the compliance report under a new **HTML Visibility** line.

## Phase 9 — Speed Test Check (run after every new or updated page)

After writing and deploying (or locally serving) the page, run a speed test to verify performance
improvements. Use DebugBear or PageSpeed Insights against the live URL.

**DebugBear** (preferred — matches what the user monitors):
- URL: https://www.debugbear.com/test/website-speed
- Test the production URL or the deployed dev URL
- Target metrics (from Fynla's baseline):
  - **TTFB**: < 500ms (was 796ms–1.2s)
  - **FCP**: < 2s (was 1.7s–4.3s)
  - **LCP**: < 2.5s (was 4.3s–5.4s)
  - **CLS**: 0.00 (maintain)

**PageSpeed Insights** (quick alternative):
```
https://pagespeed.web.dev/report?url=https://fynla.org/
```

**What to look for after each change:**

| Change type | Expected metric improvement |
|---|---|
| Lazy-loading large GIFs/images | LCP ↓ significantly |
| Moving preload link to top of `<head>` | FCP ↓ |
| `Cache-Control: public, max-age=300` on route | TTFB ↓ for cached responses |
| Blocking CSS (`<link rel="stylesheet">`) | No FOUC, no CLS from style flash |
| Stripping inline `<style>` to critical-only | FCP ↓ |

**Local Lighthouse check** (when production deploy isn't available):
```bash
# Requires: npm install -g lighthouse
lighthouse http://localhost:8000/ --output=json --output-path=/tmp/lh.json --chrome-flags="--headless"
node -e "const r=require('/tmp/lh.json').categories; Object.keys(r).forEach(k=>console.log(k,r[k].score*100))"
```

Add the speed test results to the compliance report under a **Speed Test** line with
before/after numbers where available.

## Phase 12 — Visual Consistency Testing (mandatory for every styling change)

After any change that touches CSS, layout, or content structure, take a screenshot before AND after
and compare them visually. This catches invisible regressions — spacing drift, font changes, element
resizing — that W3C validators and Lighthouse cannot detect.

### How to run the visual check

1. **Before making changes** — take a screenshot of the affected page section:
   - Use Playwright MCP (`browser_take_screenshot`) against `http://localhost:8000/<slug>`
   - Or use the browser's DevTools device toolbar at 1280px (desktop) and 390px (mobile)
   - Save/label it "before"

2. **After making changes** — take a second screenshot with the same viewport and label it "after"

3. **Compare the two images** and verify ALL of the following:

| Check | What to look for |
|---|---|
| **Margin / padding** | No unexpected gaps or collapsed spacing between elements |
| **Font size** | Headings and body text match the before state (check `rem` / `em` cascades) |
| **Font type** | Correct family in use — Segoe UI / Inter, not a browser fallback serif |
| **Font format** | Weight correct — 900 for display/h1, 700 for h2–h5, 400/500 for body |
| **Element sizing** | Cards, images, buttons, containers — same width/height as before |
| **Main content area** | Content starts at the correct offset (no nav overlap, no extra top gap) |
| **Alignment** | Nothing has shifted left/right due to a missing `margin: auto` or changed `max-width` |
| **Responsive breakpoints** | Check at 390px (mobile) AND 1280px (desktop) — a change that looks fine at one size often breaks the other |

### When to run this check

- **Always**: when editing any CSS rule in `global.css`, `<slug>.css`, or an inline `<style>` block.
- **Always**: when adding, removing, or restructuring HTML elements in a page section.
- **Always**: when updating any external asset URL, image `width`/`height`, or `aspect-ratio`.
- **Not needed**: for purely backend changes (controller, route, API logic) with no HTML/CSS output change.

### What to do if the before/after differ unexpectedly

1. Identify the differing element using browser DevTools (computed styles panel).
2. Trace the change — did the new CSS override an existing rule? Did a `global.css` rule cascade differently?
3. Fix the root cause — do not add `!important` to paper over it.
4. Take a third screenshot and verify the fix matches the "before" state.

Add a **Visual Consistency** line to the compliance report:
```
- Visual Consistency: [PASS — before/after screenshots match / FAIL — <describe diff, fix applied>]
```

## Phase 13 — Sitemap.xml Check (mandatory when URLs change)

Whenever a page is added, renamed, or removed, `public/sitemap.xml` must be updated in the same
change set. Crawlers cache sitemap entries — a stale entry pointing at a 404 burns crawl budget and
delays deindexing.

### When this check is required

- A new public page is created (new `<slug>.php` + `routes/web.php` entry)
- An existing page slug changes (e.g. `/insights` → `/news`)
- A page is removed or redirected
- A page's canonical URL changes (e.g. trailing slash added/removed)

### What to check

1. Read `public/sitemap.xml` (or `public/sitemap_index.xml` if the site uses a sitemap index).
2. For a **new page**: add a `<url>` entry with `<loc>`, `<lastmod>` (today's date, `YYYY-MM-DD`),
   `<changefreq>`, and `<priority>`.
3. For a **renamed page**: update the `<loc>` in the existing entry AND update `<lastmod>`.
4. For a **removed page**: delete the `<url>` entry entirely.
5. Verify every `<loc>` URL matches the canonical URL in the page's `<link rel="canonical">` tag exactly
   (same scheme, same domain, same path, no trailing slash mismatch).

### Sitemap entry template

```xml
<url>
  <loc>https://fynla.org/PAGE-SLUG</loc>
  <lastmod>YYYY-MM-DD</lastmod>
  <changefreq>monthly</changefreq>
  <priority>0.8</priority>
</url>
```

Priority guidance: `1.0` = homepage, `0.9` = primary marketing pages (pricing, about),
`0.8` = content pages (insights, features), `0.6` = secondary pages (legal, contact).

Add a **Sitemap** line to the compliance report:
```
- Sitemap: [PASS — entry added/updated/removed / N/A — no URL change]
```

## Phase 15 — Post-Conversion Smoke Tests (mandatory after every new or converted page)

After the page is wired up (PHP file written, route added, dev server running), run these four
checks before marking the task done. Each one catches a class of failure that has caused real
production incidents. Do not skip any of them, even if the page has no demo CTAs.

### 15.1 — Page renders HTML (not SPA shell)

```bash
curl -s http://localhost:8000/PAGE-SLUG | grep -c "div id=\"app\""
# Must return 0. Any non-zero result means the route is missing or the auth guard is firing.
```

If it returns non-zero:
- Check the route exists in `routes/web.php` before the SPA catch-all.
- Check whether `auth()->check()` is the culprit. For pages that are pure content (learn articles,
  guides, glossary, tax explainers) — remove the auth guard so authenticated users also see the PHP.
  For pages that have an in-app equivalent (pricing, features, homepage) — the auth guard is correct.

### 15.2 — Sign in and register return 200

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/login
# Must return 200

curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/register
# Must return 200
```

Both pages render `view('app')` via the SPA catch-all, which requires the Vite manifest or the
`public/hot` dev file to exist. A 500 here means one of:

1. **`public/hot` is missing** — Vite is running but wrote its hot file to the main repo's
   `public/` directory, not the worktree's. Fix:
   ```bash
   cp /path/to/main-repo/public/hot public/hot
   # or create it manually:
   echo -n "http://127.0.0.1:5173" > public/hot
   ```
2. **`public/build/manifest.json` is missing and Vite is not running** — start Vite from the
   correct directory.

If `/login` or `/register` returns 500, **all demo CTAs will also fail** because the post-login
redirect to `/dashboard` hits the same `view('app')` code path.

### 15.3 — Demo CTAs return a token (only if the page has `data-demo-persona` attributes)

Check the page's HTML for any `data-demo-persona` attribute. If found, test every distinct persona:

```bash
curl -s -X POST http://localhost:8000/api/preview/login/PERSONA_ID \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  | grep -c '"token"'
# Must return 1 (token present in response)
```

Common persona IDs: `student`, `young_saver`, `young_family`, `peak_earners`, `retired_couple`,
`entrepreneur`.

A 500 here means the preview user seeder hasn't been run. Fix:
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

A 200 with no token field means `PreviewController::login()` encountered a logic error — check
`storage/logs/laravel.log`.

### 15.4 — Dashboard loads after demo login (only if page has demo CTAs)

After confirming the API returns a token, verify the destination page (`/dashboard`) won't 500:

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/dashboard
# Must return 200 (SPA shell loads without Vite manifest error)
```

A 500 here is the same Vite manifest issue as 15.2. Fix is identical.

### Adding results to the compliance report

```
- Smoke tests:
    - Page HTML visible (not SPA shell): [PASS / FAIL — route missing / auth guard firing]
    - /login returns 200: [PASS / FAIL — Vite manifest missing, fix applied: ...]
