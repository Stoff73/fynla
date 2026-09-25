# Reusable module system (Phase 14)

## Contents
- Phase 14 — Reusable Module System

## Phase 14 — Reusable Module System

### Architecture overview

Every common page section is built once as a three-layer unit:

1. **PHP partial** (`public/pages/partials/modules/<module>.php`) — the markup, written once.
   The caller sets a `$module` array then `include`s the partial. The partial reads from `$module`
   and calls `htmlspecialchars()` on every user-supplied value before rendering.

2. **Base CSS** (`public/pages/css/global.css` — MODULE STYLES section) — the default appearance.
   All colours use CSS custom properties from `:root`. No hardcoded hex anywhere.

3. **JS initialiser** (`public/pages/js/site.js` — MODULE INITIALISERS section) — the interactive
   behaviour. Detects its module via `data-*` attributes. Auto-runs on DOMContentLoaded.

Page CSS may add **BEM modifier classes** (`.faq--dark`) or **page-scoped descendant selectors**
(`.pricing-page .faq`) to customise appearance without forking the partial.

### Module Catalogue

| Module | Partial | CSS root class | JS trigger | BEM modifiers |
|--------|---------|---------------|------------|---------------|
| FAQ Accordion | `partials/modules/faq.php` | `.faq` | `[data-faq-item]` | `.faq--dark` |
| Review Carousel | `partials/modules/review-carousel.php` | `.review-carousel` | `[data-module="review-carousel"]` | — |
| CTA Band | `partials/modules/cta-band.php` | `.cta-band` | — | `.cta-band--raspberry` |
| Section Hero | `partials/modules/section-hero.php` | `.section-hero` | — | `.section-hero--light` |

### PHP caller pattern

Set `$module` immediately before the `include`. Never pass the array any other way.

```php
<?php
$module = [
  'id'      => 'pricing-faq',        // unique section id (required)
  'heading' => 'Common questions',   // section heading (required)
  'items'   => [
    ['q' => 'How much does it cost?', 'a' => 'See our pricing page for details.'],
  ],
];
include __DIR__ . '/partials/modules/faq.php';
?>
```

**FAQ partial fields:**

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `id` | string | yes | `'faq'` | Unique `id` on the `<section>` |
| `heading` | string | yes | — | Section heading text |
| `heading_tag` | string | no | `'h2'` | HTML tag for the heading (`h2`–`h4`) |
| `modifier` | string | no | `''` | BEM modifier class e.g. `'faq--dark'` |
| `items` | array | yes | — | Each item: `['q' => '...', 'a' => '...']` |

**Review carousel partial fields:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `id` | string | yes | Unique `id` on the `<section>` |
| `heading` | string | yes | Section heading text |
| `modifier` | string | no | BEM modifier class |
| `reviews` | array | yes | Each item: `['name' => '...', 'text' => '...']` |

**CTA Band partial fields:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `id` | string | yes | Unique `id` on the `<section>` |
| `heading` | string | yes | Section heading text |
| `heading_tag` | string | no | Defaults to `h2` |
| `modifier` | string | no | e.g. `'cta-band--raspberry'` |
| `subtext` | string | no | Paragraph below the heading |
| `actions` | array | yes | Each item: `['text' => '...', 'href' => '...', 'primary' => true/false]` |

**Section Hero partial fields:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `id` | string | yes | Unique `id` on the `<section>` |
| `heading` | string | yes | Main heading text |
| `heading_tag` | string | no | Defaults to `h1` |
| `modifier` | string | no | e.g. `'section-hero--light'` |
| `badge` | string | no | Small pill label above the heading |
| `subtext` | string | no | Paragraph below the heading |
| `cta_primary` | array | no | `['text' => '...', 'href' => '...']` |
| `cta_secondary` | array | no | `['text' => '...', 'href' => '...']` |

### Override strategy

**Prefer BEM modifiers** for colour/theme variants that may be needed on multiple pages:

```css
/* global.css — MODULE STYLES */
.faq--dark { background: var(--horizon-600); }
.faq--dark .faq__heading { color: var(--white); }
```

```php
$module = ['id' => 'help-faq', 'modifier' => 'faq--dark', 'heading' => '...', 'items' => [...]];
include __DIR__ . '/partials/modules/faq.php';
```

**Use page-scoped descendant selectors** for one-off positional tweaks that only make sense on
a single page (e.g. removing the top padding because this FAQ follows directly after a hero):

```css
/* pricing.css — page-specific only */
.pricing-page .faq { padding-top: 2rem; }
```

Never fork the module partial for a one-page visual change. If the same override is needed on
three or more pages, promote it to a BEM modifier in `global.css`.

### Adding a new module

When a new section type appears that will be used on more than one page:

1. Create the partial in `public/pages/partials/modules/<module-name>.php`.
   - Accept config via a `$module` array.
   - Call `htmlspecialchars()` on every output value.
   - Use `[hidden]` (not `display:none`) for any element that starts hidden.
   - No inline `style=""` except runtime-computed values (add a comment explaining why).
   - No `on*=""` event attributes.
2. Add base CSS to `global.css` under the MODULE STYLES section.
   - All colours via CSS custom properties — no hex literals.
   - Include at least one BEM modifier if the module has theme variants.
3. Add JS init to `site.js` under MODULE INITIALISERS (only if interactive).
   - Guard with a `data-*` presence check so it is a no-op on pages without the module.
4. Add a row to the Module Catalogue table above.
5. Add a "Never write X from scratch" rule to Phase 11.

    - /register returns 200: [PASS / FAIL — Vite manifest missing, fix applied: ...]
    - Demo CTAs return token: [PASS / FAIL / N/A — no demo CTAs on this page]
    - /dashboard returns 200: [PASS / FAIL / N/A — no demo CTAs on this page]
```

---

### Never duplicate module markup

If a section matches a catalogue module, using anything other than the partial is a violation.
Writing bespoke FAQ accordion HTML on a new page when `faq.php` exists is the same class of
problem as duplicating CSS — one change to the module now requires a change on every page that
copied the markup.
