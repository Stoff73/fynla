# Hero Panel Text Overlay & Section Overlap Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the three panel captions from above the desktop hero image to an overlay at the top of the image, and remove the hero bottom padding so the image slightly overlaps the pink Meet Fyn section below.

**Architecture:** Template-only changes in `LandingPage.vue`. The desktop image wrapper becomes `relative` to act as a positioning context; the caption grid becomes `absolute` within it. Bottom overlap uses the same negative-margin pattern already used for the Fyn character image (`pb-0` on the container + `-mb-8` on the image wrapper).

**Tech Stack:** Vue 3, Tailwind CSS

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 5 | `pb-12` → `pb-0` on hero inner container |
| `resources/js/views/Public/LandingPage.vue` | 31–64 | Restructure image/caption block — captions become absolute overlay inside `relative` image wrapper; image wrapper gets `-mb-8` |

---

## Current Code (for reference — lines 31–64)

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

---

## Task 1: Remove Hero Bottom Padding

The hero inner container (`max-w-7xl`) currently has `pb-12`. Setting it to `pb-0` lets the image sit flush at the bottom of the gradient, ready for the overlap effect in Task 2.

```html
<!-- BEFORE -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-12">

<!-- AFTER -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-0">
```

- [ ] **Step 1: Change `pb-12` to `pb-0` on the hero inner container (line 5)**

---

## Task 2: Overlay Captions Inside Image & Add Section Overlap

Two sub-changes in one block:

**2a — Restructure the image/caption block**

The standalone caption grid is removed. The desktop image wrapper becomes `relative` and gains `-mb-8` (overlap). The captions become an `absolute` overlay at the top of that wrapper. Text colours switch from white (was on gradient) to dark (now on white panel image).

The mobile image stays in its own simple `flex justify-center` wrapper — no overlay, no overlap on mobile.

```html
<!-- Mobile: single card (shown below lg, no overlap on mobile) -->
<div class="flex justify-center lg:hidden">
  <img
    src="/images/Website/Homepage-Header-Mobile.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="w-full max-w-sm"
  />
</div>

<!-- Desktop: three-panel composite with caption overlay and section overlap -->
<div class="relative hidden lg:block -mb-8">

  <!-- Caption overlay — sits at top of image panels -->
  <div class="absolute inset-x-0 top-0 grid grid-cols-3 gap-8 px-8 pt-5 z-10">
    <div>
      <p class="text-sm font-bold text-horizon-500 mb-1">One financial view</p>
      <p class="text-xs text-neutral-500 leading-relaxed">Use Fynla to securely centralise and view your financial data</p>
    </div>
    <div class="text-center">
      <p class="text-sm font-bold text-horizon-500 mb-1">One financial brain</p>
      <p class="text-xs text-neutral-500 leading-relaxed">Our proprietary brain does the calculations so you don't have to</p>
    </div>
    <div class="text-right">
      <p class="text-sm font-bold text-horizon-500 mb-1">One financial voice</p>
      <p class="text-xs text-neutral-500 leading-relaxed">Fyn will give you clear and simple advice for financial freedom</p>
    </div>
  </div>

  <img
    src="/images/Website/Homepage-Header-Desktop.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="w-full"
  />

</div>
```

- [ ] **Step 1: Replace the current caption grid + image block with the restructured version above**

- [ ] **Step 2: Verify captions on desktop** — Three text blocks appear at the top of the image panels with dark text (`horizon-500` headings, `neutral-500` body). They are readable against the white panel backgrounds. No captions appear on mobile.

- [ ] **Step 3: Verify overlap on desktop** — The hero image's bottom edge slightly overlaps the light-pink Meet Fyn section below it (8 units / 32px). No hard line between hero gradient and pink section at the image bottom.

- [ ] **Step 4: Verify mobile** — Single card image centred, no overlap, no captions.

---

## Task 3: Commit

- [ ] **Step 1: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: overlay panel captions on hero image, hero image overlaps meet fyn section"
```
