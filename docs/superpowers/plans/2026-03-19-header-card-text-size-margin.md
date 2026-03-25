# Header Card Text Size & Margin Reduction

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce title and body text size in the left and right caption cards and move them 10px higher at the `lg` breakpoint, matching the reference image `public/images/References/Homepage-Cards-Text.png`.

**Architecture:** Adjust Tailwind responsive classes on the left and right caption cards only (centre card unchanged). Reduce `lg:` title from `text-lg` to `text-base`, body from `text-xs` to a tighter line-height, and reduce `top-[4.5rem]` (72px) by 10px to `top-[62px]` on left and right cards.

**Tech Stack:** Vue.js 3, Tailwind CSS

---

## Current State (lines 59–75 of LandingPage.vue)

| Card | Title size (lg) | Body size | Top position | Top padding (lg) |
|------|----------------|-----------|-------------|-----------------|
| Left | `lg:text-lg` | `text-xs` | `top-[4.5rem]` (72px) | `lg:pt-16` (64px) |
| Centre | `lg:text-lg` | `text-xs` | `top-14` (56px) | none |
| Right | `lg:text-lg` | `text-xs` | `top-[4.5rem]` (72px) | `lg:pt-16` (64px) |

## Changes (left & right only — centre unchanged)

| Property | Before | After | Why |
|----------|--------|-------|-----|
| Title size (lg) | `lg:text-lg` (18px) | `lg:text-base` (16px) | Smaller text box at lg widths |
| Top position | `top-[4.5rem]` (72px) | `top-[62px]` | 10px higher to match reference |
| Top padding (lg) | `lg:pt-16` (64px) | `lg:pt-[54px]` | 10px less internal padding to match |

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `resources/js/views/Public/LandingPage.vue:60-62,72-74` | Modify | Reduce text size and top offset on left & right cards |

---

### Task 1: Update left caption card

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:60-62`

- [ ] **Step 1: Replace left caption card classes**

Change lines 60-62 from:
```html
          <div class="absolute top-[4.5rem] left-[4%] px-4 lg:pt-16 xl:pt-24 py-3 max-w-[28%] xl:max-w-xs">
            <p class="lg:text-lg xl:text-2xl font-bold text-horizon-500 mb-1">One financial view.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">Use Fynla to securely centralise and view all your financial data.</p>
```

To:
```html
          <div class="absolute top-[62px] xl:top-[4.5rem] left-[4%] px-4 lg:pt-[54px] xl:pt-24 py-3 max-w-[28%] xl:max-w-xs">
            <p class="lg:text-base xl:text-2xl font-bold text-horizon-500 mb-1">One financial view.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">Use Fynla to securely centralise and view all your financial data.</p>
```

**What changed:**
- `top-[4.5rem]` → `top-[62px] xl:top-[4.5rem]` (10px higher at lg, restored at xl)
- `lg:pt-16` → `lg:pt-[54px]` (10px less internal padding at lg)
- `lg:text-lg` → `lg:text-base` (16px instead of 18px at lg)

---

### Task 2: Update right caption card

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:72-74`

- [ ] **Step 1: Replace right caption card classes**

Change lines 72-74 from:
```html
          <div class="absolute top-[4.5rem] right-[4%] px-4 lg:pt-16 xl:pt-24 py-3 max-w-[28%] xl:max-w-xs text-right">
            <p class="lg:text-lg xl:text-2xl font-bold text-horizon-500 mb-1">One financial voice.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">We will give you clear, simple and tailored advice to help your financial freedom.</p>
```

To:
```html
          <div class="absolute top-[62px] xl:top-[4.5rem] right-[4%] px-4 lg:pt-[54px] xl:pt-24 py-3 max-w-[28%] xl:max-w-xs text-right">
            <p class="lg:text-base xl:text-2xl font-bold text-horizon-500 mb-1">One financial voice.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">We will give you clear, simple and tailored advice to help your financial freedom.</p>
```

**What changed:** Same as left card — `top-[62px] xl:top-[4.5rem]`, `lg:pt-[54px]`, `lg:text-base`.

---

### Task 3: Browser test

- [ ] **Step 1: Test at ~1100px (reference image width)**

Resize browser to 1100px wide. Verify:
- Left and right card titles are smaller than centre card title
- Cards sit ~10px higher than before
- No overlap between cards

- [ ] **Step 2: Test at 1280px+ (xl breakpoint)**

Resize to 1280px+. Verify:
- Left and right cards restore to original size (`text-2xl`, `top-[4.5rem]`, `pt-24`)
- No visual regression from current production

---

### Task 4: Commit

- [ ] **Step 1: Stage and commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "fix: reduce left/right caption card text size and top margin at lg breakpoint"
```
