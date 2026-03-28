# Mobile Fyn Character in Meet Fyn Section — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show a smaller version of the Fyn character image on mobile in the Meet Fyn section, positioned to the right of the "Meet Fyn" heading as shown in the reference image.

**Architecture:** The Fyn character is currently hidden below the `lg` breakpoint (`hidden lg:flex`). We add a separate, smaller mobile-only Fyn image inline with the "Meet Fyn" heading row, visible only below `lg`. The existing desktop Fyn image remains unchanged.

**Tech Stack:** Vue.js 3, Tailwind CSS

**Reference image:** `public/images/References/Homepage-MeetFynimage-Mobile.png` — shows a green box to the right of the "Meet Fyn" heading indicating where the mobile Fyn image should appear.

---

## File Structure

- Modify: `resources/js/views/Public/LandingPage.vue` — Add mobile Fyn image to Meet Fyn section

No new files needed. Uses existing `public/images/Fyn/Design Character 001a.webp`.

---

### Task 1: Add mobile Fyn character to Meet Fyn section

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:82-118`

**Current state (lines 86-89):**
```html
<!-- Left: Meet Fyn text + Ask Fyn input -->
<div class="flex-1 self-center">
  <h2 class="text-6xl lg:text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-[24px]">Meet Fyn</h2>
  <p class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">
```

**Current state (lines 111-114) — desktop Fyn (hidden on mobile):**
```html
<!-- Right: Fyn character -->
<div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-end">
  <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" class="h-[427px] w-auto lg:-mb-[3em]" />
</div>
```

- [ ] **Step 1: Add a mobile-only row with "Meet Fyn" heading and small Fyn image**

Replace the current `<h2>` heading (line 88) with a flex row that places a small Fyn image to its right, visible only on mobile (below `lg`):

```html
<!-- Mobile: Meet Fyn heading + small Fyn character side by side -->
<div class="flex items-end justify-between lg:hidden mb-1">
  <h2 class="text-6xl font-bold text-horizon-500 leading-none">Meet Fyn</h2>
  <img
    src="/images/Fyn/Design Character 001a.webp"
    alt="Fyn — your AI financial companion"
    loading="lazy"
    width="324"
    height="427"
    class="h-28 w-auto -mb-2"
  />
</div>
<!-- Desktop: Meet Fyn heading only (Fyn character shown separately on right) -->
<h2 class="hidden lg:block text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-[24px]">Meet Fyn</h2>
```

This creates:
- **Mobile (below `lg`):** A flex row with the heading on the left and a ~112px tall Fyn image on the right, matching the reference image placement
- **Desktop (`lg`+):** The original heading style, with the full-size Fyn character still shown in its own column on the right

- [ ] **Step 2: Verify in browser at mobile width**

Run dev server: `./dev.sh`

Check at 375px (mobile) and 768px (tablet):
- Fyn character appears to the right of "Meet Fyn" heading
- Image is small (~112px / `h-28`) and doesn't overflow
- Text below ("Your financial companion for life") flows normally underneath

Check at 1024px+ (desktop):
- Mobile Fyn image is hidden
- Desktop layout unchanged — full-size Fyn character still appears in right column

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: add smaller Fyn character to Meet Fyn section on mobile"
```
