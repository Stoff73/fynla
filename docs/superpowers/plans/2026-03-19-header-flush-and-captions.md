# Header Flush Image & Caption Styling — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the three-panel header image sit flush against the bottom of the hero gradient, and restyle the captions to appear as distinct white-background card headers above each panel (matching Fynla-HomepageHeaderv4.png reference).

**Architecture:** Two CSS/layout changes in `LandingPage.vue` hero section only. No new files, no backend changes, no new components.

**Tech Stack:** Vue 3 template + Tailwind CSS

**Reference:** `public/images/References/Fynla-HomepageHeaderv4.png`

---

## Chunk 1: Header Image & Captions

### Task 1: Make header image flush to bottom of gradient

The current hero has `pt-10 pb-0` on the container and `-mb-8` on the image wrapper, leaving a gap. The v4 reference shows the image sitting flush — no gap between the gradient ending and the panels starting.

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:4-67` (hero section)

- [ ] **Step 1: Remove negative margin on image wrapper, add overflow-hidden to hero**

In `resources/js/views/Public/LandingPage.vue`, change the hero container and image wrapper:

Line 4 — the hero outer div stays the same:
```html
<div class="bg-gradient-to-r from-horizon-500 to-raspberry-500">
```

Line 5 — remove `pb-0` (redundant) from the inner container, keep `pt-10`:
```html
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
```

Line 41 — change the desktop image wrapper from `-mb-8` to `-mb-0` (flush):
```html
<div class="relative hidden lg:block">
```
(Simply remove the `-mb-8` class entirely.)

- [ ] **Step 2: Verify in browser — image should be flush with bottom of gradient**

Navigate to `http://localhost:8000` and confirm:
- No gap between gradient and the section below
- The three-panel image touches the bottom edge of the pink gradient
- Mobile layout still looks correct (single card)

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "fix: make header image flush to bottom of hero gradient"
```

### Task 2: Restyle captions as white card headers above each panel

Currently the captions ("One financial view", "One financial brain", "One financial voice") are absolutely positioned overlays on top of the image. The v4 reference shows them sitting **above** each panel as distinct card-style headers with white backgrounds and visible text.

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:42-57` (caption overlay block)

- [ ] **Step 1: Replace the absolute-positioned caption overlay with a flex/grid row above the image**

Replace lines 42-57 (the `<!-- Caption overlay -->` block) with a non-overlaid grid row that sits above the image:

Old (lines 42-57):
```html
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
```

New:
```html
          <!-- Caption cards — row above the image panels -->
          <div class="grid grid-cols-3 gap-6 px-4 mb-0">
            <div class="bg-white/95 rounded-t-lg px-4 py-3 shadow-sm">
              <p class="text-sm font-bold text-horizon-500 mb-1">One financial view</p>
              <p class="text-xs text-neutral-500 leading-relaxed">Use Fynla to securely centralise and view all your financial data</p>
            </div>
            <div class="bg-white/95 rounded-t-lg px-4 py-3 shadow-sm text-center">
              <p class="text-sm font-bold text-horizon-500 mb-1">One financial brain.</p>
              <p class="text-xs text-neutral-500 leading-relaxed">Our proprietary brain does the calculations so you don't have to</p>
            </div>
            <div class="bg-white/95 rounded-t-lg px-4 py-3 shadow-sm text-right">
              <p class="text-sm font-bold text-horizon-500 mb-1">One financial voice</p>
              <p class="text-xs text-neutral-500 leading-relaxed">We will give you clear and simple advice for financial freedom</p>
            </div>
          </div>
```

Key changes:
- Removed `absolute inset-x-0 top-0` positioning — captions now flow normally above the image
- Added `bg-white/95 rounded-t-lg px-4 py-3 shadow-sm` to each caption card
- Changed gap from `gap-8` to `gap-6` and padding from `px-8` to `px-4` to align with panel edges
- Removed `z-10` (no longer overlaid)
- Updated "One financial brain" to include period (matches v4 reference)
- Updated "One financial voice" description text to match v4: "We will give you clear and simple advice for financial freedom"

- [ ] **Step 2: Verify in browser — captions should appear as white card headers above panels**

Navigate to `http://localhost:8000` and confirm:
- Three white-background caption cards sit directly above the three image panels
- Each card has rounded top corners, blending visually into the panel below
- Text is legible (horizon-500 headings, neutral-500 body)
- Cards align horizontally with the image panels beneath them
- Mobile layout unaffected (desktop-only block)

- [ ] **Step 3: Fine-tune spacing if needed**

Compare against `public/images/References/Fynla-HomepageHeaderv4.png` and adjust:
- Gap between caption cards and image (`mb-0` may need `-mb-px` to remove hairline gap)
- Padding within cards
- Card width alignment with underlying panels

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: restyle header captions as white card headers above panels"
```

### Task 3: Final visual check

- [ ] **Step 1: Compare full header against v4 reference**

Open `public/images/References/Fynla-HomepageHeaderv4.png` and compare:
- Gradient → caption cards → image panels → Meet Fyn section should flow seamlessly
- No visible gaps between gradient bottom and image/captions
- Caption cards match the white-header style in the reference

- [ ] **Step 2: Check responsive behaviour**

Resize browser to tablet and mobile widths:
- Caption cards and desktop image are hidden below `lg` breakpoint
- Mobile single-card image still displays correctly
- No layout shifts or overflow issues
