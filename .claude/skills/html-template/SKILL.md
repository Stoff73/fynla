---
name: html-template
description: >
  Standards-enforcing skill for creating or converting unauthenticated/public HTML pages in Fynla.
  Use this skill whenever: (1) creating a new public-facing HTML page from scratch, (2) converting
  a Vue page that is unauthenticated/public (PublicLayout-wrapped) into a standalone HTML file,
  (3) producing any HTML output for marketing, campaign, or landing pages. Also invoke when the user
  says "convert to HTML", "make an HTML version", "static page", "standalone page", or "public HTML page".
  This skill enforces W3C compliance, WCAG 2.1 AA accessibility, SEO, CLS performance, and graceful
  JS degradation on every page produced. Always invoke at the start of any HTML page task — never skip it.
---

# HTML Template Skill — Fynla Public Pages

You are producing a standards-compliant, accessible, SEO-optimised PHP page for Fynla. Follow every
section of this skill in order. Do not skip sections. Each rule exists because violations cause real
problems: W3C errors break parsers, accessibility failures exclude users, CLS hurts search ranking,
inline-only JS breaks assistive technology.

---

## Phase 1 — Research before writing

Before writing a single line of HTML:

1. **Read the source Vue file in full.** Note every component imported — read those files too.
2. **Identify every interactive feature**: nav toggles, modals, accordions, dropdowns, carousels,
   forms, CTAs, lazy-loaded sections. You must map each one to a vanilla HTML+JS equivalent.
3. **Read `public/pages/css/global.css`** — it already contains all design tokens, the reset, and
   nav/footer styles. Do NOT duplicate these in the page-specific CSS file.
4. **List any data that Vue fetches via API** (axios/fetch in `mounted()` / `created()`). For each:
   - If it can be inlined as static content: inline it.
   - If it must remain dynamic: write a vanilla JS `fetch()` with a visible loading state and a
     graceful fallback (the page must show meaningful content even if the fetch fails).

5. **Check the Module Catalogue (Phase 14) before writing any new markup.** For each section on
   the page, look it up in the catalogue. If a module exists for that section type (FAQ, carousel,
   CTA band, hero, etc.), use the PHP partial — do NOT write new markup from scratch. If no module
   exists and the section is likely to appear on other pages, create a new module and add it to
   the catalogue before continuing.

---

## Phase 2 — Mandatory rules (enforce all, every time)

### 2.1 W3C Compliance
- Always open with `<!DOCTYPE html>`.
- `<html lang="en">` (or the correct ISO 639-1 language code).
- Every element must be correctly nested and closed.
- No deprecated elements (`<font>`, `<center>`, `<b>` for styling, `<i>` for styling, `<marquee>`).
- Use semantic elements: `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<aside>`, `<footer>`.
- `<title>` is required and must be unique and descriptive.
- Before finishing, mentally walk the DOM tree and flag any invalid nesting.

### 2.2 WCAG 2.1 Level AA Accessibility
- **Skip navigation**: first element inside `<body>` must be:
  ```html
  <a href="#main-content" class="skip-nav">Skip to main content</a>
  ```
  Style it visually hidden by default, visible on `:focus`.
- **Semantic landmarks**: `<header>`, `<nav aria-label="Primary navigation">`, `<main id="main-content">`, `<footer>`.
- **ARIA roles**: add only where native semantics are insufficient. Never duplicate role on an element
  that already has it (e.g. don't put `role="button"` on a `<button>`).
- **Images — alt text rules**:
  - Every `<img>` must have an `alt` attribute (including `alt=""`).
  - Use `alt=""` (empty, not omitted) for **decorative** images and for images whose meaning is already
    conveyed by adjacent text (e.g. a card thumbnail where the card's heading says the same thing).
    Screen readers skip empty-alt images; they would read non-empty alt text *in addition to* the heading,
    making the user hear the same information twice.
  - Use descriptive alt text only when the image conveys unique information not present elsewhere on the page.
  - For images injected by JS (e.g. API-fetched article thumbnails whose title appears in a nearby `<h4>`),
    set `alt=""` in the JS — never use the title as alt text.
- **Buttons must have an accessible name**: every `<button>` (and any element with `role="button"`) must
  have either visible text content, an `aria-label`, or an `aria-labelledby` pointing to a visible label.
  An icon-only button without any of these is invisible to screen readers.
  ```html
  <!-- Bad: screen reader announces "button" with no name -->
  <button><svg aria-hidden="true">...</svg></button>

  <!-- Good: aria-label names the button -->
  <button aria-label="Previous review"><svg aria-hidden="true">...</svg></button>
  ```
- **Touch targets — minimum 44×48px**: every interactive element (button, link, input) must have a
  tap target area of at least 44×48px. For visually small elements (dots, small icons) that can't be
  made larger without breaking the design, expand the tap area with a pseudo-element rather than changing
  the visible size:
  ```css
  .small-dot {
    position: relative;   /* required for the pseudo-element to anchor */
    width: 0.625rem;
    height: 0.625rem;
  }
  .small-dot::before {
    content: '';
    position: absolute;
    inset: -1.2rem;   /* expands tap area to ~48px without changing visual appearance */
  }
  ```
  For buttons whose visual size is close to 44px, simply increase `width` and `height` to `3rem` (48px).
- **Colour contrast**: all text must meet 4.5:1 (normal) or 3:1 (large/bold) against its background.
  Fynla palette already passes — never use grey text below `#767676` on white.
- **Keyboard navigation**: every interactive element must be reachable by Tab and operable by Enter/Space.
  If you use `<div>` as a button, add `role="button" tabindex="0"` and a keydown handler — but prefer
  a real `<button>` or `<a>`.
- **Focus indicators**: never use `outline: none` without an equally visible replacement.
- **Form labels**: every `<input>` must have an associated `<label>` (via `for`/`id` or wrapping).

### 2.3 CSS location rules
- **Global stylesheet first**: any style that could apply to more than one page belongs in
  `global.css`, not the page CSS file and not inline. Before adding a rule to `<slug>.css`,
  ask whether it is truly page-specific. If not, put it in `global.css`.
- **Page CSS file second**: page-specific rules go in `css/<slug>.css` loaded asynchronously
  (see Phase 5). Never put page-specific rules in `global.css`.
- **Inline `<style>` last resort**: only use an inline `<style>` block in `<head>` for the
  minimum above-fold critical CSS needed before the external files load (tokens, reset, skip-nav,
  site-header skeleton, hero). Everything else — including all below-fold sections — must live in
  the external files. Do not put rules in `<head>` that already exist verbatim in `global.css` or
  `<slug>.css`; duplication causes the external file to silently override the inline version after
  it loads, risking CLS if the values differ.
- **`style="..."` attribute**: allowed ONLY when the value is computed at runtime by JS and cannot
  be expressed as a CSS class (e.g. a dynamically calculated pixel width or `min-height` set by
  `equalizeGridItems`). Always add an inline comment explaining why.
- Never use `!important` unless overriding a third-party stylesheet.

### 2.4 JavaScript location rules
- **External `.js` files always**: all JavaScript must live in `js/<slug>.js` (page-specific) or
  `js/site.js` (shared). Never write `<script>` blocks containing logic directly in the PHP/HTML
  file. The only exception is a small `<script>` that is genuinely required for the page to function
  at all before the external file loads (e.g. a feature-detection polyfill) — in that case add an
  inline comment explaining why it cannot be deferred to the `.js` file.
- **No inline event handlers**: never use `onclick=""`, `onchange=""`, or any `on*=""` attributes
  in HTML. All event wiring belongs in the `.js` file.
- **Graceful degradation**: the page must render useful, navigable content with JavaScript disabled.
  JS may enhance (smooth scroll, accordions, lazy loading) but must not gate content.
- Wrap every JS block in a null-check: `if (document.querySelector('.accordion')) { ... }`.
- Use `<noscript>` tags for critical fallbacks where needed.
- Never block the parser: use `defer` on all external `<script>` tags. Never use `document.write()`.
- If a Vue `mounted()` hook fetches data, replicate this with a vanilla `fetch()` in the `.js` file,
  wrapped in a try/catch that falls back to inline static content.

### 2.5 SEO
Every page must include in `<head>`:

```html
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Page Title — Fynla</title>
<meta name="description" content="150–160 char description">
<link rel="canonical" href="https://fynla.org/PAGE-SLUG">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="Page Title — Fynla">
<meta property="og:description" content="150–160 char description">
<meta property="og:image" content="https://fynla.org/images/og/PAGE-SLUG.jpg">
<meta property="og:url" content="https://fynla.org/PAGE-SLUG">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Page Title — Fynla">
<meta name="twitter:description" content="150–160 char description">
<meta name="twitter:image" content="https://fynla.org/images/og/PAGE-SLUG.jpg">

<!-- hreflang (English-only pages) -->
<link rel="alternate" hreflang="en-GB" href="https://fynla.org/PAGE-SLUG">
<link rel="alternate" hreflang="x-default" href="https://fynla.org/PAGE-SLUG">
```

- One `<h1>` per page — use the primary page headline.
- `<h2>`–`<h6>` must follow a logical hierarchy (no skipping levels).
- All `<a>` tags must have descriptive text — never "click here" or "read more" alone.
- Add JSON-LD structured data for WebPage (and Organisation on the homepage).

### 2.6 Performance & CLS
- **Images**: always include `width` and `height` attributes. Use `loading="lazy"` for below-the-fold
  images. Use `loading="eager"` for the hero/LCP image.
- **Preload LCP image**: add `<link rel="preload" as="image" href="..." fetchpriority="high">` for the
  largest above-the-fold image.
- **Blocking CSS — no FOUC**: load external stylesheets with standard `<link rel="stylesheet">` so
  the browser never paints an unstyled frame. The `media="print"` async trick causes a visible flash
  (FOUC) when the inline critical CSS block is incomplete — the flash IS a CLS event, so blocking CSS
  is the correct default for these PHP pages. CSS files are served from the same server, so the
  round-trip penalty is negligible:
  ```html
  <link rel="stylesheet" href="/pages/css/global.css?v=N" />
  <link rel="stylesheet" href="/pages/css/<slug>.css?v=N" />
  ```
- **Cache-busting**: all external assets (`css/*.css`, `js/*.js`) use `?v=N` query strings. Increment N
  on every change.
- **Reserve space for async content**: if JS will inject content, set a `min-height` on the container.
- **No render-blocking scripts**: all `<script>` must have `defer`.

---

## Phase 5 — Output format (multi-file PHP architecture)

Pages are **not** self-contained HTML files. They are multi-file PHP pages served via Laravel:

### Files to create for each new page

```
public/pages/
├── <slug>.php               ← the page (PHP, not HTML)
├── css/
│   ├── global.css           ← already exists — shared tokens, reset, nav, footer
│   └── <slug>.css           ← page-specific styles only (no :root, no nav/footer rules)
└── js/
    ├── site.js              ← already exists — shared nav/menu wiring
    └── <slug>.js            ← page-specific interactions
```

Only create `<slug>.css` and `<slug>.js`. Do not modify `global.css` or `site.js` unless the change
is genuinely shared across all pages.

### Page `<head>` skeleton

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Page Title — Fynla</title>
  <meta name="description" content="150–160 char description" />
  <link rel="canonical" href="https://fynla.org/PAGE-SLUG" />
  <!-- og, twitter, hreflang, json-ld here -->

  <!-- Preload LCP image (desktop only if below mobile fold) -->
  <link rel="preload" as="image" href="/images/..." fetchpriority="high" media="(min-width: 1024px)" />

  <!-- Critical CSS — inline only the above-fold rules needed before external CSS loads.
       Copy the :root tokens + reset + skip-nav + site-header + hero from global.css.
       Everything below the fold goes in the external files. -->
  <style>
    /* :root tokens (minified) */
    /* reset */
    /* skip-nav */
    /* site-header skeleton */
    /* hero / first section */
    /* @media (min-width: 1024px) { nav padding, hero padding } */
  </style>

  <!-- Blocking CSS — prevents FOUC. Files are same-server so render penalty is negligible. -->
  <link rel="stylesheet" href="/pages/css/global.css?v=N" />
  <link rel="stylesheet" href="/pages/css/<slug>.css?v=N" />
</head>
```

### Laravel route (add to `routes/web.php` before the SPA catch-all)

```php
Route::get('/PAGE-SLUG', function () {
    ob_start();
    include public_path('pages/PAGE-SLUG.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8']);
});
```

The homepage (`/`) also checks `auth()->check()` and serves the Vue SPA for authenticated users.
Other public pages do not need this check unless they have an authenticated variant.

### After writing, produce a short **Compliance Report**:

```
## Compliance report
- W3C: [PASS / issues found: ...]
- Accessibility AA: [PASS / issues found: ...]
- SEO: [PASS / issues found: ...]
- CLS risks: [none / ...]
- JS degradation: [PASS / ...]
- Functionality preserved: [list any Vue feature that needed a vanilla JS polyfill]
- HTML Visibility: [PASS — curl returns 0 matches for "div id=\"app\"" / FAIL — route missing from web.php]
- Smoke tests:
    - Page HTML visible (not SPA shell): [PASS / FAIL]
    - /login returns 200: [PASS / FAIL]
    - /register returns 200: [PASS / FAIL]
    - Demo CTAs return token: [PASS / FAIL / N/A]
    - /dashboard returns 200: [PASS / FAIL / N/A]
- Speed Test: [TTFB: Xms, FCP: Xs, LCP: Xs, CLS: X.XX — via DebugBear/PageSpeed / pending deployment]
```

---

## Phase 6 — Reusable partials architecture

### PHP server-side includes are mandatory — JS fetch is not acceptable for SEO

Rule: **use PHP `include` for nav and footer, never `fetch()` + `innerHTML`.** Crawlers that don't
execute JS receive empty placeholder divs and index nothing. PHP `include` costs nothing — the
complete HTML arrives in the first byte with no extra round-trips.

### Page body skeleton

```php
<body>
  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content">
    <!-- page-specific content -->
  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=N" defer></script>
  <script src="/pages/js/<slug>.js?v=N" defer></script>
</body>
```

### nav.php and footer.php rules

- Contain only inner markup — NO `<!DOCTYPE>`, `<html>`, `<head>`, or `<body>` tags.
- All styles for nav/footer live in `global.css` (already written — do not duplicate).
- Use `data-nav-link` attributes on `<a>` tags so `site.js` can set the active state.
- May contain PHP expressions: `<?= date('Y') ?>` in the footer copyright line.

### Module sections

Every distinct content section on a page MUST have:
1. A semantic HTML5 landmark element (`<section>`, `<article>`, `<aside>`)
2. A unique `id` (e.g. `id="features"`, `id="reviews"`)
3. A BEM-style root class (e.g. `class="hero"`, `class="review-carousel"`)

```html
<section id="features" class="feature-grid" aria-labelledby="features-heading">
  <div class="feature-grid__inner"> ... </div>
</section>
```

### Semantic element selection guide

Pick the correct element for the content type — never default to `<div>` when a semantic element exists.

| Content type | Correct element | Notes |
|---|---|---|
| News / insight / blog post | `<article>` | Self-contained, independently redistributable content |
| News / insight card (in a list) | `<article>` inside `<section>` | Each card is its own article; the list is a section |
| Site-wide primary nav | `<nav aria-label="Primary navigation">` | One per page |
| Supplementary nav (footer links, breadcrumbs) | `<nav aria-label="Footer navigation">` | Give each a distinct label |
| Page hero / marketing band | `<section>` with `aria-labelledby` pointing to its `<h2>` | Not `<div>` |
| Sidebar / related content | `<aside>` | Content tangentially related to the main content |
| Author bio, product card | `<article>` | Self-contained item |
| Testimonial / review | `<article>` or `<blockquote>` with `<cite>` | |
| FAQ / accordion items | `<dl>` + `<dt>` / `<dd>`, or `<details>` / `<summary>` | Never bare `<div>` |
| Pricing tiers | `<section>` containing `<article>` per tier | |
| Step-by-step / how-it-works | `<ol>` with `<li>` | Ordered — sequence matters |
| Feature list | `<ul>` with `<li>` | Unordered — sequence doesn't matter |

**Specific rule for insights/news content:** every article card, article preview, and full article page
MUST use `<article>`. The container listing multiple articles uses `<section id="insights">`. Never
render news/insight content inside a plain `<div>`.

---

## Phase 10 — No hardcoded colours anywhere

**All colour values MUST use CSS custom properties from `global.css`.** Never write a hex, `rgb()`,
or `hsl()` value outside of `:root`. For alpha variants, define a named token in `:root`:

```css
:root { --horizon-500-30: rgba(31, 42, 68, 0.30); }
/* then: */ background: var(--horizon-500-30);
```

---

## Phase 11 — What NOT to do

- Never use Vue, React, Alpine.js, or any JS framework.
- Never use inline `style=""` for anything expressible as a CSS class.
- Never omit `alt`, `width`, or `height` from `<img>` tags.
- Never use non-empty `alt` text that duplicates adjacent visible text — use `alt=""` for those images.
- Never ship a `<button>` with no accessible name (no text content, no `aria-label`, no `aria-labelledby`).
- Never ship touch targets smaller than 44×48px without a `::before` pseudo-element to expand the tap area.
- Never add `defer` to an inline `<script>` (no `src` attribute) — `defer` only applies to external scripts.
  Inline scripts at the end of `<body>` execute after the DOM is parsed without needing `defer`.
- Never use `<script>` without `defer` (external scripts only).
- Never skip the compliance report.
- Never assume a Vue component is simple — always read it first.
- Never hardcode hex/rgb/hsl outside `:root`.
- Never put nav or footer markup directly in a page file — use partials.
- Never use JS `fetch()` to inject nav or footer HTML.
- Never create `.html` files — always `.php`.
- Never omit `[hidden] { display: none !important; }` from the critical CSS reset.
- Never use `grid-auto-rows: 1fr` to equalise item heights — use `equalizeGridItems()` JS.
- Never use `media="print" onload="this.media='all'"` for CSS loading — it causes FOUC. Use plain
  blocking `<link rel="stylesheet">` instead.
- Never forget to increment the `?v=N` cache-buster when changing any CSS or JS file.
- Never redefine `:root` or nav/footer rules in the page CSS file — `global.css` owns those.
- Never put below-fold module styles in the inline `<style>` block — they belong in the external
  `<slug>.css` file. The inline block is for critical above-fold content only (tokens, reset, nav, hero).
- Never inline styles that already exist verbatim in `global.css` or `<slug>.css` — duplication causes
  the external file to silently override the inline version after it loads, and risks CLS if the values differ.
- Never skip the HTML visibility check (Phase 8) — a missing `routes/web.php` entry silently serves the
  SPA shell, making all SEO content invisible to crawlers. `curl | grep "div id=\"app\""` must return 0.
- Never skip the speed test check (Phase 9) — always verify TTFB/FCP/LCP before marking done.
- Never leave large animated GIFs (> 500 KB) with `loading="eager"` — they block LCP. Use `loading="lazy"`
  for any GIF not in the hero's LCP critical path.
- Never serve dynamic API images without an `onerror` handler — broken images leave empty grey boxes.
  Use `onerror="this.parentElement.style.display='none'"` on img tags whose src comes from an API.
- Never skip the visual consistency test (Phase 12) — a screenshot before/after is mandatory for every
  styling change. The page must look right, not just validate.
- Never skip the sitemap check (Phase 13) — if a page slug or file name changes, `sitemap.xml` must
  be updated in the same change set.
- Never skip the smoke tests (Phase 15) — verify `/login`, `/register`, demo CTAs, and `/dashboard`
  all return 200 before marking any page task done. A missing `public/hot` file or absent preview
  user seed will cause 500s that are invisible until a user clicks a demo button.
- Never write FAQ accordion markup from scratch — use `partials/modules/faq.php`.
- Never write review carousel markup from scratch — use `partials/modules/review-carousel.php`.
- Never write a page CTA band from scratch — use `partials/modules/cta-band.php`.
- Never write a page hero from scratch — use `partials/modules/section-hero.php`.
- Never add carousel or FAQ accordion JS to a page-specific `.js` file — the initialisers live in `site.js`.

---

## Reference files (read the one the step needs)

- **Palette tokens and Tailwind-to-CSS mapping (Phases 3–4):** [reference/palette-and-tailwind.md](reference/palette-and-tailwind.md). Read before writing any CSS.
- **Critical CSS patterns and gotchas (Phase 7):** [reference/css-patterns.md](reference/css-patterns.md). Read before writing critical CSS, menus or grids.
- **Reusable module system and catalogue (Phase 14):** [reference/modules.md](reference/modules.md). Read before writing any page section; never duplicate module markup.
- **Verification (Phases 8, 9, 12, 13, 15):** [reference/verification.md](reference/verification.md). Run every applicable check after writing, and add the results to the compliance report.
