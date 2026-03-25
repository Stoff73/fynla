# Hero Header Image Swap Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Fynla Brain PNG in the hero section with a desktop-specific wide composite image and a separate mobile-specific single-card image, and add three descriptive text columns above the desktop panels, matching the v4 reference design.

**Architecture:** Template-only change in `LandingPage.vue`. The single `<img>` in the hero image div is replaced with two `<img>` tags — one visible only on mobile (`block lg:hidden`), one visible only on desktop (`hidden lg:block`). No JS, no new components.

**Tech Stack:** Vue 3, Tailwind CSS

---

## Reference

| File | Path |
|------|------|
| Desktop image | `public/images/Website/Homepage-Header-Desktop.png` |
| Mobile image | `public/images/Website/Homepage-Header-Mobile.png` |
| v4 reference | `public/images/References/Fynla-Homepagev4.png` |

The v4 reference shows the desktop image spanning the full hero content width with no max-width constraint. The mobile image is a single centred card, similar in width to the current brain image.

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 31–38 | Replace single brain `<img>` with two responsive images |
| `resources/js/views/Public/LandingPage.vue` | 31–38 | Add desktop-only three-column text row above the image |

---

## Current Code (for reference)

```html
<!-- Fyn Brain image -->
<div class="flex justify-center">
  <img
    src="/images/Fyn/202603-FynlaBrain.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="w-full max-w-sm"
  />
</div>
```

---

## Task 1: Swap Hero Image for Responsive Desktop + Mobile Versions

Replace the single brain image with two images — desktop shown on `lg+`, mobile shown below `lg`.

The desktop image is a wide three-panel composite, so it should be `w-full` with no max-width cap (it will be naturally constrained by the `max-w-7xl` hero container). The mobile image is a single card, `max-w-sm` centred, matching the current layout.

```html
<!-- Hero images — desktop and mobile variants -->
<div class="flex justify-center">

  <!-- Mobile: single card (shown below lg) -->
  <img
    src="/images/Website/Homepage-Header-Mobile.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="block lg:hidden w-full max-w-sm"
  />

  <!-- Desktop: three-panel composite (shown at lg and above) -->
  <img
    src="/images/Website/Homepage-Header-Desktop.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="hidden lg:block w-full"
  />

</div>
```

- [ ] **Step 1: Replace the current brain image div with the two-image responsive version above**

- [ ] **Step 2: Verify on desktop (≥ 1024px)**

  Refresh http://localhost:8000 at full desktop width — the wide three-panel composite (Net Worth + Fyn Brain + Protection panels) fills the hero content area. The brain PNG is gone.

- [ ] **Step 3: Verify on mobile (< 1024px)**

  Use browser DevTools to set viewport to 375px — the single centred card appears, sized similarly to the previous brain image.

---

## Task 2: Add Three-Column Panel Text Above Desktop Image

The v4 reference shows three short text blocks positioned above the three panels — one per column. These sit only on desktop (hidden on mobile, since the mobile image is a single card with no multi-column layout).

Add a `hidden lg:grid grid-cols-3 gap-8 mb-4` row immediately before the image div. Each column has a bold heading and a short descriptive sentence, left-aligned within its column. Text is white to sit on the gradient background.

```html
<!-- Panel captions — desktop only, aligns with three image panels -->
<div class="hidden lg:grid grid-cols-3 gap-8 mb-4">
  <div>
    <p class="text-sm font-bold text-white mb-1">One financial view</p>
    <p class="text-xs text-white/70 leading-relaxed">Use Fynla to securely centralise and view your financial data</p>
  </div>
  <div class="text-center">
    <p class="text-sm font-bold text-white mb-1">One financial brain</p>
    <p class="text-xs text-white/70 leading-relaxed">Our proprietary brain does the calculations so you don't have to</p>
  </div>
  <div class="text-right">
    <p class="text-sm font-bold text-white mb-1">One financial voice</p>
    <p class="text-xs text-white/70 leading-relaxed">Fyn will give you clear and simple advice for financial freedom</p>
  </div>
</div>
```

- [ ] **Step 1: Add the three-column caption row immediately before the image `<div class="flex justify-center">`**

- [ ] **Step 2: Verify on desktop** — three text blocks appear above the panels, each aligned over its respective panel (left, centre, right). Text is white/muted on the gradient.

- [ ] **Step 3: Verify on mobile** — the caption row is not visible (hidden below lg breakpoint).

---

## Task 3: Commit

- [ ] **Step 1: Commit all changes**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: swap hero brain image for responsive desktop/mobile header images with panel captions"
```
