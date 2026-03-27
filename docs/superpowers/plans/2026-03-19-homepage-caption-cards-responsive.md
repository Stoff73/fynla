# Homepage Caption Cards Responsive Text Fix

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the three homepage caption cards ("One financial view", "One financial brain", "One financial voice") overlapping each other when the browser is resized to medium-large widths (~1024–1280px), while preserving the current full-screen desktop layout.

**Architecture:** Replace fixed rem offsets and `max-w-xs` (320px) with percentage-based positioning and responsive max-widths. Use `lg:` vs `xl:` Tailwind breakpoints to scale text size and spacing — smaller at `lg` (1024px), current size at `xl` (1280px+). Remove hard-coded `<br/>` tags in description text so text reflows naturally at all widths.

**Tech Stack:** Vue.js 3, Tailwind CSS

---

## Problem Analysis

The desktop caption cards (lines 59–75 of `LandingPage.vue`) use absolute positioning over the hero image. At the `lg` breakpoint (1024px):

| Card | Position | Width | Pixel range |
|------|----------|-------|-------------|
| Left | `left: 4.5rem` (72px) | `max-w-xs` (320px) | 72–392px |
| Centre | `left: 50%` centred | `max-w-xs` (320px) | 393–713px |
| Right | `right: 4.5rem` (72px) | `max-w-xs` (320px) | 714–1034px |

Container is 108% of parent = ~1106px at `lg`. The three cards total 960px + gaps, leaving only ~146px total clearance. With padding (px-4 = 32px per card = 96px total) the cards collide.

**Reference image:** `public/images/References/Homepage-Cards-Text.png` — shows the overlap at ~1100px browser width.

## Fix Strategy

1. **Percentage-based positioning** — `left-[4%]` / `right-[4%]` instead of `left-[4.5rem]` / `right-[4.5rem]` so cards scale with container
2. **Responsive max-width** — `max-w-[28%]` at `lg`, `max-w-xs` at `xl` so cards never exceed 1/3 of available space
3. **Responsive text size** — `text-lg lg:text-lg xl:text-2xl` for headings so text wraps less at smaller widths
4. **Remove `<br/>` tags** — hard breaks force awkward line splits at narrow widths; let text flow naturally
5. **Responsive top offset** — scale `pt-24` down at `lg` breakpoint since image is shorter

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `resources/js/views/Public/LandingPage.vue:59-75` | Modify | Update caption card positioning and text sizing |

---

### Task 1: Update left caption card with responsive positioning and text

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:59-63`

- [ ] **Step 1: Replace left caption card**

Change lines 59-63 from:
```html
          <!-- Caption card: left -->
          <div class="absolute top-[4.5rem] left-[4.5rem] px-4 pt-24 py-3 max-w-xs">
            <p class="text-2xl font-bold text-horizon-500 mb-1">One financial view.</p>
            <p class="text-sm text-neutral-500 leading-tight">Use Fynla to securely centralise and view<br/>all your financial data.</p>
          </div>
```

To:
```html
          <!-- Caption card: left -->
          <div class="absolute top-[4.5rem] left-[4%] px-4 lg:pt-16 xl:pt-24 py-3 max-w-[28%] xl:max-w-xs">
            <p class="lg:text-lg xl:text-2xl font-bold text-horizon-500 mb-1">One financial view.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">Use Fynla to securely centralise and view all your financial data.</p>
          </div>
```

**What changed:**
- `left-[4.5rem]` → `left-[4%]` (scales with container width)
- `max-w-xs` → `max-w-[28%] xl:max-w-xs` (cards can't exceed ~28% of container at lg)
- `pt-24` → `lg:pt-16 xl:pt-24` (less top padding when image is shorter)
- `text-2xl` → `lg:text-lg xl:text-2xl` (smaller heading at lg)
- `text-sm` → `text-xs xl:text-sm` (smaller body text at lg)
- Removed `<br/>` so text reflows naturally

---

### Task 2: Update centre caption card with responsive text

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:65-69`

- [ ] **Step 1: Replace centre caption card**

Change lines 65-69 from:
```html
          <!-- Caption card: centre -->
          <div class="absolute top-14 left-1/2 -translate-x-1/2 px-4 py-3 max-w-xs text-center">
            <p class="text-2xl font-bold text-horizon-500 mb-1">One financial brain.</p>
            <p class="text-sm text-neutral-500 leading-tight">Our proprietary brain does the calculations<br/>so you don't have to.</p>
          </div>
```

To:
```html
          <!-- Caption card: centre -->
          <div class="absolute top-14 left-1/2 -translate-x-1/2 px-4 py-3 max-w-[28%] xl:max-w-xs text-center">
            <p class="lg:text-lg xl:text-2xl font-bold text-horizon-500 mb-1">One financial brain.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">Our proprietary brain does the calculations so you don't have to.</p>
          </div>
```

**What changed:**
- `max-w-xs` → `max-w-[28%] xl:max-w-xs`
- `text-2xl` → `lg:text-lg xl:text-2xl`
- `text-sm` → `text-xs xl:text-sm`
- Removed `<br/>`

---

### Task 3: Update right caption card with responsive positioning and text

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:71-75`

- [ ] **Step 1: Replace right caption card**

Change lines 71-75 from:
```html
          <!-- Caption card: right -->
          <div class="absolute top-[4.5rem] right-[4.5rem] px-4 pt-24 py-3 max-w-xs text-right">
            <p class="text-2xl font-bold text-horizon-500 mb-1">One financial voice.</p>
            <p class="text-sm text-neutral-500 leading-tight">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
```

To:
```html
          <!-- Caption card: right -->
          <div class="absolute top-[4.5rem] right-[4%] px-4 lg:pt-16 xl:pt-24 py-3 max-w-[28%] xl:max-w-xs text-right">
            <p class="lg:text-lg xl:text-2xl font-bold text-horizon-500 mb-1">One financial voice.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
```

**What changed:**
- `right-[4.5rem]` → `right-[4%]` (scales with container width)
- `max-w-xs` → `max-w-[28%] xl:max-w-xs`
- `pt-24` → `lg:pt-16 xl:pt-24`
- `text-2xl` → `lg:text-lg xl:text-2xl`
- `text-sm` → `text-xs xl:text-sm`

---

### Task 4: Browser test at multiple widths

- [ ] **Step 1: Test at 1024px width (lg breakpoint)**

Open `http://localhost:8000` and resize browser to 1024px wide. Verify:
- All three caption cards are visible with no overlap
- Text is smaller but fully readable
- Cards sit within their respective thirds of the image

- [ ] **Step 2: Test at 1280px width (xl breakpoint)**

Resize to 1280px. Verify:
- Cards return to larger text size (`text-2xl` headings)
- Layout matches the original full-screen design
- No visual regression from current production appearance

- [ ] **Step 3: Test at 1440px+ (full desktop)**

Resize to 1440px and wider. Verify:
- Cards are positioned identically to current production
- `max-w-xs` caps card width so they don't stretch too wide

- [ ] **Step 4: Test at <1024px (mobile breakpoint)**

Resize below 1024px. Verify:
- Desktop cards are hidden (`hidden lg:block`)
- Mobile stacked cards appear instead
- No layout shift or flash between layouts

---

### Task 5: Commit

- [ ] **Step 1: Stage and commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "fix: responsive caption card positioning to prevent text overlap at medium widths"
```
