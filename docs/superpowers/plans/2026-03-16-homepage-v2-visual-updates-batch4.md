# Homepage v2 Visual Updates — Batch 4 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Four spacing and typography tweaks to tighten the landing page layout and improve the solutions and stats sections.

**Architecture:** All changes are in `LandingPage.vue` — Tailwind class edits only. No logic, no new files.

**Tech Stack:** Vue 3, Tailwind CSS (custom palette), Laravel 10

---

## Files to Modify

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 96 | "How Fyn can help you" padding |
| `resources/js/views/Public/LandingPage.vue` | 201 | "Your Fynla dashboard" padding |
| `resources/js/views/Public/LandingPage.vue` | 221 | Solutions section top padding |
| `resources/js/views/Public/LandingPage.vue` | 231–232, 241–242, 251–252, 261–262, 271–272 | Solutions card title sizes |
| `resources/js/views/Public/LandingPage.vue` | 290, 295, 300 | Stats bar number font weight |

---

## Task 1 — Reduce padding: "How Fyn can help you" section

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:96`

**Current:** `py-16 lg:py-20` (4rem mobile / 5rem desktop)
**Target:** `py-10 lg:py-12` (2.5rem mobile / 3rem desktop) — a moderate reduction

- [ ] **Step 1: Update section padding**

```diff
- <div id="features" class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-16 lg:py-20">
+ <div id="features" class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-10 lg:py-12">
```

- [ ] **Step 2: Verify in browser**

  Open http://localhost:8000. Scroll to "How Fyn can help you". The section should be visibly shorter top and bottom — less dark navy breathing room around the feature cards.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: reduce padding on How Fyn can help you section"
```

---

## Task 2 — Reduce padding: "Your Fynla dashboard" section

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:201`

**Current:** `py-16 lg:py-20` (4rem mobile / 5rem desktop)
**Target:** `py-10 lg:py-12` — matches Task 1 for visual rhythm

- [ ] **Step 1: Update section padding**

```diff
- <div class="bg-[#FAD6E0] py-16 lg:py-20">
+ <div class="bg-[#FAD6E0] py-10 lg:py-12">
```

- [ ] **Step 2: Verify in browser**

  Scroll to "Your Fynla dashboard". The pink section should have less vertical breathing room, matching the tighter rhythm of the "How Fyn can help you" section above it.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: reduce padding on Your Fynla dashboard section"
```

---

## Task 3 — Reduce top padding: "Solutions built just for you"

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:221`

**Current:** `pt-16 lg:pt-20 pb-24 lg:pb-28`
- Top: 4rem mobile / 5rem desktop
- Bottom: 6rem mobile / 7rem desktop (must stay large — the stats bar uses `-mt-14 -mb-12` to straddle the solutions/footer boundary)

**Target:** Reduce top only → `pt-10 lg:pt-12 pb-24 lg:pb-28`

- [ ] **Step 1: Update top padding only**

```diff
- <div id="solutions" class="bg-eggshell-500 pt-16 lg:pt-20 pb-24 lg:pb-28">
+ <div id="solutions" class="bg-eggshell-500 pt-10 lg:pt-12 pb-24 lg:pb-28">
```

- [ ] **Step 2: Verify in browser**

  Scroll to "Solutions built just for you". The gap between the pink dashboard section and the solutions heading should be tighter. The bottom padding (where the stats bar overlaps) should be unchanged.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: reduce top padding on Solutions section"
```

---

## Task 4 — Increase solutions card title sizes

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:231–232, 241–242, 251–252, 261–262, 271–272`

Each solution card has two text elements forming its title:
1. The "FYNLA" label: `text-xs font-bold text-white/60 tracking-wider` — increase to `text-sm`
2. The product name (INVESTOR, LIFE, etc.): `text-sm font-bold text-white` — increase to `text-base`

There are 5 cards, each with both lines. Use replace-all since the pattern is identical across all five.

- [ ] **Step 1: Increase "FYNLA" label from `text-xs` to `text-sm`**

```diff
- <p class="text-xs font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
+ <p class="text-sm font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
```

Apply to all 5 cards (replace-all is safe — this class string is unique to the solutions section).

- [ ] **Step 2: Increase product name from `text-sm` to `text-base`**

```diff
- <p class="text-sm font-bold text-white mb-2">INVESTOR</p>
- <p class="text-sm font-bold text-white mb-2">LIFE</p>
- <p class="text-sm font-bold text-white mb-2">MANAGER</p>
- <p class="text-sm font-bold text-white mb-2">PLANNER</p>
- <p class="text-sm font-bold text-white mb-2">SAVER</p>
```

The class string `text-sm font-bold text-white mb-2` also appears on the product name `<p>` in each card. Use replace-all: change `class="text-sm font-bold text-white mb-2"` → `class="text-base font-bold text-white mb-2"`.

**⚠️ Caution:** Confirm no other element in `LandingPage.vue` uses `class="text-sm font-bold text-white mb-2"` before using replace-all. A targeted search confirms this pattern only exists on these five product-name `<p>` tags.

- [ ] **Step 3: Verify in browser**

  The five solution cards should now show larger "FYNLA" labels and bolder/larger product names (INVESTOR, LIFE, MANAGER, PLANNER, SAVER). Cards may be slightly taller — confirm they still align in the 5-column grid on desktop.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: increase solutions card title sizes"
```

---

## Task 5 — Stats bar: number font weight from `font-black` to `font-bold`

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:290, 295, 300`

The three stats numbers (123, 16, 12) use `text-4xl font-black` (`font-weight: 900`). Change to `font-bold` (`font-weight: 700`) for a slightly less heavy appearance.

- [ ] **Step 1: Update all three number elements**

All three share the exact same class string. Use replace-all:

```diff
- <div class="text-4xl font-black text-horizon-500">
+ <div class="text-4xl font-bold text-horizon-500">
```

- [ ] **Step 2: Verify in browser**

  Scroll to the stats bar (the white card that straddles the solutions/footer boundary). The numbers 123, 16, and 12 should appear slightly less heavy — bold rather than ultra-black weight.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: change stats bar numbers from font-black to font-bold"
```

---

## Task 6 — Add space above Fyn character image (horns) in hero

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:9`

**Current state:** The Fyn character image has `lg:-mt-[36px]` — a negative 36px top margin that pulls the image (and its horns) upward, closer to the navigation bar. In the reference design the horns sit lower, with clear breathing room between the nav and the top of the image.

**Fix:** Replace `lg:-mt-[36px]` with `lg:mt-6` (positive 24px top margin). This shifts the image down by 60px total relative to current (from −36px to +24px), giving the horns clear space below the nav.

- [ ] **Step 1: Update the Fyn image top margin**

```diff
- <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[410px] w-auto lg:-mt-[36px] lg:-mb-24" />
+ <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[410px] w-auto lg:mt-6 lg:-mb-24" />
```

The bottom margin `lg:-mb-24` stays — it controls how far the image overlaps into the "Map your path" section below and must remain.

- [ ] **Step 2: Verify in browser**

  On desktop (≥1024px), the Fyn character's horns should now sit noticeably lower in the hero section, with visible space between the navigation bar and the top of the image. The hero content (Meet Fyn, subtitle, input) alignment should remain unchanged as it is in a separate flex column.

- [ ] **Step 3: If too much or too little space, tune the value**

  The exact offset is visual. If `lg:mt-6` (24px) is too little, try `lg:mt-8` (32px). If too much, try `lg:mt-4` (16px). Keep `lg:-mb-24` unchanged.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: add space above Fyn character horns in hero to match homepage v2"
```

---

## Final Verification Checklist

- [ ] "How Fyn can help you" section is visibly shorter (less top/bottom padding)
- [ ] "Your Fynla dashboard" section is visibly shorter (less top/bottom padding)
- [ ] "Solutions built just for you" has less space above the heading
- [ ] Solutions card titles (FYNLA label + product name) are larger than before
- [ ] Stats bar numbers (123, 16, 12) use bold weight, not ultra-black
- [ ] Stats bar overlap with footer unchanged (bottom padding intact)
- [ ] Fyn character horns have clear space below the navigation bar
- [ ] No other sections affected
