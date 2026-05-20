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
- **Async CSS loading**: external stylesheets load with `media="print"` trick so they never block render.
  The critical CSS inline block (see Phase 5) covers above-the-fold rendering:
  ```html
  <link rel="stylesheet" href="/pages/css/global.css?v=N" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="/pages/css/slug.css?v=N" media="print" onload="this.media='all'" />
  <noscript>
    <link rel="stylesheet" href="/pages/css/global.css?v=N" />
    <link rel="stylesheet" href="/pages/css/slug.css?v=N" />
  </noscript>
  ```
- **Cache-busting**: all external assets (`css/*.css`, `js/*.js`) use `?v=N` query strings. Increment N
  on every change.
- **Reserve space for async content**: if JS will inject content, set a `min-height` on the container.
- **No render-blocking scripts**: all `<script>` must have `defer`.

---

## Phase 3 — Fynla Design Palette (CSS variables — source of truth is `global.css`)

**Read `public/pages/css/global.css` before every page.** The complete `:root` block is there. Key tokens:

```css
:root {
  /* Raspberry — CTAs, errors, highlights */
  --raspberry-300: #F472B6;
  --raspberry-400: #EC4899;
  --raspberry-500: #E83E6D;
  --raspberry-600: #DB2777;

  /* Horizon — text, nav, headings */
  --horizon-100: #F1F5F9;
  --horizon-200: #E2E8F0;
  --horizon-300: #CBD5E1;
  --horizon-400: #94A3B8;
  --horizon-500: #1F2A44;
  --horizon-600: #0F172A;
  --horizon-700: #020617;

  /* Spring — success, positive CTAs */
  --spring-400: #34D399;
  --spring-500: #20B486;
  --spring-600: #059669;
  --spring-700: #047857;

  /* Violet — warnings, focus */
  --violet-500: #5854E6;

  /* Savannah — hover, subtle backgrounds */
  --savannah-100: #FDFAF7;
  --savannah-200: #FAF5F0;
  --savannah-300: #F5EDE5;
  --savannah-400: #EFDCD1;
  --savannah-500: #E6C9A8;

  /* Eggshell — page background */
  --eggshell-500: #F7F6F4;

  /* Neutrals */
  --neutral-400: #9CA3AF;
  --neutral-500: #717171;
  --neutral-600: #4B5563;

  /* Light Blue */
  --light-blue-100: #DDE2EF;
  --light-blue-500: #6C83BC;

  /* Light Pink */
  --light-pink-50:  #FDF0F4;
  --light-pink-100: #FAD6E0;
  --light-pink-200: #F5B3C5;

  /* Utility */
  --light-gray: #EEEEEE;
  --white:      #FFFFFF;

  /* Alpha variants — define in :root, never inline rgba() */
  --white-80:       rgba(255, 255, 255, 0.80);
  --white-70:       rgba(255, 255, 255, 0.70);
  --white-40:       rgba(255, 255, 255, 0.40);
  --white-30:       rgba(255, 255, 255, 0.30);
  --horizon-500-30: rgba(31,  42,  68,  0.30);
  --black-05:       rgba(0,   0,   0,   0.05);
  --black-10:       rgba(0,   0,   0,   0.10);

  /* Typography */
  --font-primary: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;

  /* Radius */
  --radius-sm: 0.375rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 0.75rem;
  --radius-2xl: 1rem;
  --radius-button: 0.5rem;
  --radius-full: 9999px;

  /* Shadows */
  --shadow-sm: 0 1px 2px 0 var(--black-05);
  --shadow-lg: 0 10px 15px -3px var(--black-10), 0 4px 6px -4px var(--black-10);
}
```

Do NOT redefine `:root` in the page CSS file — `global.css` already contains it. Only add tokens to
the page CSS file if a new alpha variant is needed that isn't in `global.css`.

---

## Phase 4 — Tailwind-to-CSS conversion reference

| Tailwind | CSS |
|---|---|
| `font-black` | `font-weight: 900` |
| `font-bold` | `font-weight: 700` |
| `font-semibold` | `font-weight: 600` |
| `font-medium` | `font-weight: 500` |
| `tracking-widest` | `letter-spacing: 0.1em` |
| `leading-tight` | `line-height: 1.25` |
| `leading-relaxed` | `line-height: 1.625` |
| `leading-none` | `line-height: 1` |
| `truncate` | `overflow: hidden; text-overflow: ellipsis; white-space: nowrap` |
| `line-clamp-2` | `-webkit-line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden` |
| `transition-colors` | `transition: color 0.15s ease, background-color 0.15s ease, border-color 0.15s ease` |
| `shadow-sm` | `box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)` |
| `shadow-lg` | `box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)` |
| `rounded-xl` | `border-radius: 0.75rem` |
| `rounded-2xl` | `border-radius: 1rem` |
| `object-cover` | `object-fit: cover` |
| `aspect-[16/9]` | `aspect-ratio: 16/9` |
| `group-hover:` | CSS `:hover` on the parent with a descendant selector |
| `sm:` | `@media (min-width: 640px)` |
| `md:` | `@media (min-width: 768px)` |
| `lg:` | `@media (min-width: 1024px)` |
| `xl:` | `@media (min-width: 1280px)` |

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

  <!-- Async external CSS — media="print" prevents render-blocking;
       onload swaps to 'all' once downloaded. noscript fallback for no-JS. -->
  <link rel="stylesheet" href="/pages/css/global.css?v=N" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="/pages/css/<slug>.css?v=N" media="print" onload="this.media='all'" />
  <noscript>
    <link rel="stylesheet" href="/pages/css/global.css?v=N" />
    <link rel="stylesheet" href="/pages/css/<slug>.css?v=N" />
  </noscript>
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

## Phase 7 — Critical CSS patterns and gotchas

### The `[hidden]` reset — mandatory in every page's critical CSS

```css
[hidden] { display: none !important; }
```

Without this, `display: flex` or `display: grid` on a parent overrides the browser's default for
`[hidden]` and makes hidden panels visible. This breaks every accordion, mega menu, and modal.

### Async CSS loading — the `onload` swap trick

```html
<link rel="stylesheet" href="/pages/css/global.css?v=N" media="print" onload="this.media='all'" />
```

The browser downloads it at low priority (print-only), then the `onload` handler makes it apply to
all media. The inline critical CSS block covers the user's above-fold view during this window. Always
add a `<noscript>` fallback with normal `<link>` tags.

### Mega menu positioning

```css
/* nav-dropdown: position static so the mega panel anchors to nav-primary__inner,
   not the narrow trigger button. nav-primary__inner must be position:relative. */
.nav-dropdown    { position: static; }
.nav-primary__inner { position: relative; }

.mega-menu {
  position: absolute;
  top: 100%;
  left: 50%; transform: translateX(-50%);
  width: min(60rem, calc(100vw - 2rem));
}
```

### Equalising grid item heights — use JS, not `grid-auto-rows: 1fr`

`grid-auto-rows: 1fr` does NOT work on auto-height containers — `1fr` resolves to `auto`.
Use the `equalizeGridItems()` JS pattern called each time the panel opens:

```js
function equalizeGridItems(panel) {
  var grid  = panel.querySelector('.mega-menu__grid--full');
  if (!grid) return;
  var items = grid.querySelectorAll('.mega-menu__item');
  items.forEach(function (item) { item.style.minHeight = ''; });          // reset
  var maxH  = 0;
  items.forEach(function (item) { maxH = Math.max(maxH, item.offsetHeight); });
  if (maxH > 0) items.forEach(function (item) { item.style.minHeight = maxH + 'px'; });
}
```

### Bottom-anchoring a character/image when content above it grows

Flexbox `align-self: flex-end` does not work when the container height is `auto` — the row
doesn't grow with the content. Use CSS Grid instead:

```css
.section__inner {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: start;        /* both columns start at top by default */
}
.section__content   { align-self: start; }
.section__character { align-self: end; }   /* sticks to the bottom of whichever row is taller */
```

### Conditional state-based padding with `:has()`

Avoid always-on padding that wastes space in the collapsed state:

```css
/* Only add bottom padding when an accordion panel is open */
.section:has(.accordion__panel.is-open) { padding-bottom: 2.5rem; }
```

### Instant mega menu switching + mouseleave delay

```js
var openWrapper = null;   // tracks which menu is currently visible

function open() {
  // Close the previous menu instantly — no delay — before opening the new one
  if (openWrapper && openWrapper !== wrapper) {
    clearTimeout(openWrapper._closeTimer);
    openWrapper._closeNow();
  }
  panel.hidden = false;
  openWrapper  = wrapper;
  equalizeGridItems(panel);
}

function scheduleClose() {
  // 150ms delay so the cursor can cross the gap between trigger and panel
  closeTimer = setTimeout(close, 150);
  wrapper._closeTimer = closeTimer;
}

wrapper._closeNow   = close;
wrapper._closeTimer = null;
wrapper.addEventListener('mouseenter', open);
wrapper.addEventListener('mouseleave', scheduleClose);
```

---

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

---

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
| Async CSS (`media="print"` pattern) | FCP ↓ (removes render-blocking CSS) |
| Stripping inline `<style>` to critical-only | FCP ↓ |

**Local Lighthouse check** (when production deploy isn't available):
```bash
# Requires: npm install -g lighthouse
lighthouse http://localhost:8000/ --output=json --output-path=/tmp/lh.json --chrome-flags="--headless"
node -e "const r=require('/tmp/lh.json').categories; Object.keys(r).forEach(k=>console.log(k,r[k].score*100))"
```

Add the speed test results to the compliance report under a **Speed Test** line with
before/after numbers where available.

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
- Never load external CSS with a plain `<link>` — use the `media="print"` async pattern with
  a `<noscript>` fallback.
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

---

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

---

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
