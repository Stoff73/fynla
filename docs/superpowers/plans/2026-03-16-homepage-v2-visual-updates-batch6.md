# Homepage v2 Visual Updates — Batch 6 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Two fine-tuning tweaks to the hero section — reduce top padding and nudge the Fyn character image down by 5px.

**Architecture:** Single file, two line edits in `LandingPage.vue`. No logic changes.

**Tech Stack:** Vue 3, Tailwind CSS, Laravel 10

---

## Files to Modify

| File | Line | Change |
|------|------|--------|
| `resources/js/views/Public/LandingPage.vue` | 5 | Hero container top padding |
| `resources/js/views/Public/LandingPage.vue` | 9 | Fyn image top margin |

---

## Task 1 — Reduce hero top padding

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:5`

**Current:** `pt-8 pb-12` — top padding 2rem (32px)
**Target:** `pt-4 pb-12` — top padding 1rem (16px), halving the top space

- [ ] **Step 1: Update hero container top padding**

```diff
- <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12">
+ <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-12">
```

- [ ] **Step 2: Verify in browser**

  Open http://localhost:8000. The "Meet Fyn" heading and Fyn character should sit closer to the navigation bar at the top of the hero.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: reduce hero top padding from pt-8 to pt-4"
```

---

## Task 2 — Increase Fyn image top margin by 5px

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:9`

**Current:** `lg:mt-6` = 24px top margin
**Target:** `lg:mt-[29px]` = 29px (24 + 5)

Tailwind's spacing scale has no token for 29px exactly, so an arbitrary value `mt-[29px]` is used. The `lg:` prefix scopes it to desktop only, matching the existing pattern. Bottom margin `lg:-mb-24` stays unchanged.

- [ ] **Step 1: Update Fyn image top margin**

```diff
- <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[473px] w-auto lg:mt-6 lg:-mb-24" />
+ <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[473px] w-auto lg:mt-[29px] lg:-mb-24" />
```

- [ ] **Step 2: Verify in browser**

  The Fyn character horns should sit 5px lower than before on desktop. A subtle change — the gap between the nav and the top of the image increases slightly.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: increase Fyn image top margin by 5px (mt-6 → mt-[29px])"
```

---

## Final Verification Checklist

- [ ] Hero section has less space above "Meet Fyn" and the Fyn image (`pt-4`)
- [ ] Fyn character horns sit 5px lower than before on desktop (`mt-[29px]`)
- [ ] Bottom overlap into "Map your path" section unchanged (`-mb-24`)
- [ ] No other sections affected
