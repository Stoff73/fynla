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

You are producing a standards-compliant, accessible, SEO-optimised HTML page for Fynla. Follow every
section of this skill in order. Do not skip sections. Each rule exists because violations cause real
problems: W3C errors break parsers, accessibility failures exclude users, CLS hurts search ranking,
inline-only JS breaks assistive technology.

---

## Phase 1 — Research before writing

Before writing a single line of HTML:

1. **Read the source Vue file in full.** Note every component imported — read those files too.
2. **Identify every interactive feature**: nav toggles, modals, accordions, dropdowns, carousels,
   forms, CTAs, lazy-loaded sections. You must map each one to a vanilla HTML+JS equivalent.
3. **Read the Fynla design palette** from `tailwind.config.js` (already indexed below) and note
   any custom CSS variables used in the source.
4. **List any data that Vue fetches via API** (axios/fetch in `mounted()` / `created()`). For each:
   - If it can be inlined as static content: inline it.
   - If it must remain dynamic: write a vanilla JS fetch with a visible loading state and a
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
- **Images**: every `<img>` needs `alt`. Decorative images: `alt=""`. Informative images: meaningful alt text.
- **Colour contrast**: all text must meet 4.5:1 (normal) or 3:1 (large/bold) against its background.
  Fynla palette already passes — never use grey text below `#767676` on white.
- **Keyboard navigation**: every interactive element must be reachable by Tab and operable by Enter/Space.
  If you use `<div>` as a button, add `role="button" tabindex="0"` and a keydown handler — but prefer
  a real `<button>` or `<a>`.
- **Focus indicators**: never use `outline: none` without an equally visible replacement.
- **Form labels**: every `<input>` must have an associated `<label>` (via `for`/`id` or wrapping).

### 2.3 No Inline CSS
- All styles go in a `<style>` block in `<head>` or an external stylesheet.
- `style="..."` attribute is allowed ONLY when the value is computed at runtime by JS and cannot be
  expressed as a CSS class (e.g. a dynamically calculated pixel width). Comment when you do this.
- Never use `!important` unless overriding a third-party stylesheet.

### 2.4 Graceful JS Degradation
- The page must render useful, navigable content with JavaScript disabled.
- JS may enhance (smooth scroll, accordions open/close, lazy loading), but must not gate content.
- Wrap every JS block in a null-check: `if (document.querySelector('.accordion')) { ... }`.
- Use `<noscript>` tags for critical fallbacks (e.g. if a menu is JS-only, provide a `<noscript>`
  version or ensure the `<nav>` links are always visible).
- Never block the parser: use `defer` on all `<script>` tags. Never use `document.write()`.
- If a Vue `mounted()` hook fetches data, replicate this with a vanilla `fetch()` wrapped in a
  try/catch that falls back to inline static content.

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
```

- One `<h1>` per page — use the primary page headline.
- `<h2>`–`<h6>` must follow a logical hierarchy (no skipping levels).
- All `<a>` tags must have descriptive text — never "click here" or "read more" alone.
- Add JSON-LD structured data for WebPage (and Organisation on the homepage):
  ```html
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Page Title",
    "url": "https://fynla.org/PAGE-SLUG",
    "description": "...",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>
  ```
- `hreflang`: if the page exists in multiple languages, add:
  ```html
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/PAGE-SLUG">
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/PAGE-SLUG">
  ```
  For English-only pages, add `hreflang="en-GB"` and `x-default` pointing to the same URL.

### 2.6 Performance & CLS
- **Images**: always include `width` and `height` attributes matching the natural/rendered size.
  Use `loading="lazy"` for below-the-fold images. Use `loading="eager"` for the hero/LCP image.
- **Web fonts**: if using Google Fonts or similar, add `<link rel="preconnect">` and use
  `font-display: swap` in the `@font-face` rule.
- **Reserve space for async content**: if JS will inject content (e.g. a news ticker), set
  a `min-height` on the container in CSS so the layout does not shift.
- **No render-blocking scripts**: all `<script>` must have `defer` (or `type="module"`).
- **Preload LCP image**: add `<link rel="preload" as="image" href="..." fetchpriority="high">` for
  the largest above-the-fold image.
- **CSS**: no unused rules. Group media queries at the bottom. Use shorthand properties.

---

## Phase 3 — Fynla Design Palette (CSS variables to define in `:root`)

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
  --horizon-400: #94A3B8;
  --horizon-500: #1F2A44;
  --horizon-600: #0F172A;

  /* Spring — success, positive CTAs */
  --spring-400: #34D399;
  --spring-500: #20B486;
  --spring-600: #059669;

  /* Savannah — hover, subtle backgrounds */
  --savannah-100: #FDFAF7;
  --savannah-200: #FAF5F0;
  --savannah-300: #F5EDE5;

  /* Eggshell — page background */
  --eggshell-500: #F7F6F4;

  /* Neutrals */
  --neutral-400: #9CA3AF;
  --neutral-500: #6B7280;
  --neutral-600: #4B5563;

  /* Utility */
  --light-gray: #EEEEEE;
  --light-pink-100: #FEE2E2; /* approximate — verify against tailwind.config.js */
  --white: #FFFFFF;

  /* Typography */
  --font-primary: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;

  /* Spacing / radius */
  --radius-sm: 0.375rem;   /* rounded-md */
  --radius-lg: 0.75rem;    /* rounded-xl */
  --radius-2xl: 1rem;      /* rounded-2xl */
  --radius-button: 0.5rem; /* rounded-button */
}
```

---

## Phase 4 — Tailwind-to-CSS conversion reference

When converting Tailwind utility classes to plain CSS, use these mappings. For classes not listed,
derive the value from the Tailwind default scale (spacing: 0.25rem per unit; font sizes: text-sm=0.875rem,
text-base=1rem, text-lg=1.125rem, text-xl=1.25rem, text-2xl=1.5rem, etc.).

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
| `transition-all` | `transition: all 0.15s ease` |
| `transition-shadow` | `transition: box-shadow 0.15s ease` |
| `shadow-sm` | `box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)` |
| `shadow-lg` | `box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)` |
| `rounded-2xl` | `border-radius: 1rem` |
| `rounded-xl` | `border-radius: 0.75rem` |
| `rounded-lg` | `border-radius: 0.5rem` |
| `object-cover` | `object-fit: cover` |
| `object-contain` | `object-fit: contain` |
| `aspect-[16/9]` | `aspect-ratio: 16/9` |
| `group-hover:` | Use CSS `:hover` on the parent and a CSS child selector |
| `sm:` | `@media (min-width: 640px)` |
| `md:` | `@media (min-width: 768px)` |
| `lg:` | `@media (min-width: 1024px)` |
| `xl:` | `@media (min-width: 1280px)` |

---

## Phase 5 — Output format

- Single `.html` file, self-contained (CSS in `<style>`, JS in `<script defer>`).
- Save to `public/pages/<slug>.html` unless the user specifies otherwise.
- Relative paths for assets that exist in `public/` (e.g. `/images/Website/foo.png`).
- After writing, produce a short **Compliance Report**:

```
## Compliance report
- W3C: [PASS / issues found: ...]
- Accessibility AA: [PASS / issues found: ...]
- SEO: [PASS / issues found: ...]
- CLS risks: [none / ...]
- JS degradation: [PASS / ...]
- Functionality preserved: [list any Vue feature that needed a vanilla JS polyfill]
```

---

## Phase 6 — Reusable partials architecture

### PHP server-side includes are mandatory — JS fetch is not acceptable for SEO

Every multi-page project MUST use **PHP server-side includes** for nav and footer. JS-based
fetch/injection (`fetch('/partials/nav.html')` → `innerHTML`) is explicitly forbidden for
structural partials because:

- Search engine crawlers (Bing, DuckDuckGo, social preview bots) do not execute JavaScript.
  They receive a page with empty placeholder `<div>`s and index nothing useful.
- Even Google, which does execute JS, does so with a crawl-budget delay — server-rendered HTML
  is indexed faster and more reliably.
- PHP `include` is zero-cost at the server: the complete HTML arrives in the first HTTP response
  byte. There is no round-trip, no render-blocking, no CLS risk.

Rule: **use `.php` files, not `.html` files.** The web server (Apache/nginx + PHP-FPM) in a
Laravel `public/` deployment executes `.php` files natively.

### File layout

```
public/pages/
├── index.php           ← page files (one per route, PHP extension)
├── savetax.php
├── partials/
│   ├── nav.php         ← full <header>/<nav> markup only (no DOCTYPE, no <html>)
│   └── footer.php      ← full <footer> markup only
└── js/
    └── site.js         ← interactive wiring ONLY (menus, carousel, accordion)
                           NOT responsible for injecting nav/footer HTML
```

### Page skeleton (every .php file uses this pattern)

```php
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- meta, SEO, styles -->
</head>
<body>
  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content">
    <!-- page-specific content -->
  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js" defer></script>
  <script defer>
    /* page-specific JS */
  </script>
</body>
</html>
```

### site.js contract

`site.js` wires **interactive behaviour only** — it must never inject structural HTML:
1. Mark the current page's nav link active by matching `window.location.pathname`
2. Wire up the mobile hamburger toggle
3. Wire up desktop mega-menu hover/keyboard behaviour
4. Any other shared interactive patterns (smooth scroll, etc.)

Because nav and footer are already in the DOM (rendered by PHP), `site.js` runs on
`DOMContentLoaded` with no async dependency.

### nav.php and footer.php rules

- Contain only the inner markup — NO `<!DOCTYPE>`, `<html>`, `<head>`, or `<body>` tags.
- All styles for nav/footer live in the page's `<style>` block (or a shared `site.css`).
- Use `data-nav-link` attributes on `<a>` tags so `site.js` can set the active state.
- These files may contain PHP expressions if needed (e.g. `<?= date('Y') ?>` in the footer
  copyright line).

### Module sections

Every distinct content section on a page MUST have:
1. A semantic HTML5 landmark element (`<section>`, `<article>`, `<aside>`)
2. A unique `id` matching the section's route anchor (e.g. `id="features"`, `id="reviews"`)
3. A descriptive BEM-style CSS class as the module root (e.g. `class="hero"`, `class="review-carousel"`, `class="journey-stages"`)

This makes each section independently targetable for deep-linking, scroll-spy, and reuse.

Example:
```html
<section id="features" class="feature-grid" aria-labelledby="features-heading">
  <div class="feature-grid__inner"> ... </div>
</section>
```

---

## Phase 7 — No hardcoded colours anywhere

**All colour values — including those inside `<style>` blocks — MUST use CSS custom properties.**

The `:root` block defined in Phase 3 is the single source of truth. After defining `:root`, no hex
value (`#...`), `rgb(...)`, or `hsl(...)` literal may appear anywhere else in the document.

Bad:
```css
.hero { background: linear-gradient(to right, #1F2A44, #E83E6D); }
.btn  { background-color: #20B486; }
```

Good:
```css
.hero { background: linear-gradient(to right, var(--horizon-500), var(--raspberry-500)); }
.btn  { background-color: var(--spring-500); }
```

The only exception is `rgba()` / `color-mix()` where you need an alpha channel on a palette colour
and CSS doesn't yet support `var(--colour) / 0.5` shorthand in all browsers. In that case, define a
named variable for that alpha variant in `:root` too:

```css
:root {
  --horizon-500-30: rgba(31, 42, 68, 0.3); /* horizon-500 at 30% opacity */
}
```

---

## Phase 8 — What NOT to do

- Never use Vue, React, Alpine.js, or any JS framework in the output.
- Never use inline `style=""` for anything expressible as a CSS class.
- Never omit `alt`, `width`, or `height` from `<img>` tags.
- Never use `<script>` without `defer`.
- Never skip the compliance report.
- Never assume a Vue component is simple — always read it before deciding it needs no JS equivalent.
- Never hardcode a hex, rgb, or hsl colour value outside the `:root` block.
- Never put nav or footer markup directly in a page file — always use the partial pattern.
- Never use JS `fetch()` to inject nav or footer HTML — use PHP `include` so crawlers see complete HTML.
- Never create `.html` page files when `.php` is available — PHP includes are the default.
