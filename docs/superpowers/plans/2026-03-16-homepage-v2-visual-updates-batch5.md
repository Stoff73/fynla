# Homepage v2 Visual Updates — Batch 5 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Two small visual tweaks — slightly larger Fyn hero image and restyled "our demo" link in the "Map your path" section.

**Architecture:** Single file, two line edits in `LandingPage.vue`. No logic changes.

**Tech Stack:** Vue 3, Tailwind CSS (custom palette), Laravel 10

**Palette reference:**
| Token | Hex | Use |
|-------|-----|-----|
| `raspberry-500` | `#E83E6D` | Demo link regular state |
| `light-pink-400` | `#EF7598` | Demo link hover state |

---

## Files to Modify

| File | Line | Change |
|------|------|--------|
| `resources/js/views/Public/LandingPage.vue` | 9 | Fyn image height |
| `resources/js/views/Public/LandingPage.vue` | 79 | "our demo" link styles |

---

## Task 1 — Increase Fyn image size by ~5%

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:9`

**Current:** `h-[410px]`
**Target:** `h-[430px]` (410 × 1.05 = 430.5 → rounded to 430px)

The image uses `w-auto` so width scales proportionally. The bottom overlap margin `lg:-mb-24` and top margin `lg:mt-6` stay unchanged.

- [ ] **Step 1: Update image height**

```diff
- <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[410px] w-auto lg:mt-6 lg:-mb-24" />
+ <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[430px] w-auto lg:mt-6 lg:-mb-24" />
```

- [ ] **Step 2: Verify in browser**

  On desktop (≥1024px), the Fyn character should be visibly but subtly larger — approximately 5% bigger. The image should still sit within the hero area without clipping the nav above or pushing the "Map your path" section below awkwardly.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: increase Fyn hero image size by 5% (410px → 430px)"
```

---

## Task 2 — Restyle "our demo" link in "Map your path" section

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:79`

**Current:** `class="text-horizon-500 underline hover:text-raspberry-500 transition-colors"`
- Colour: dark navy (`#1F2A44`) — doesn't stand out against body text
- Underline: present
- Hover: raspberry

**Target:**
- Regular state: `raspberry-500` (`#E83E6D`) — matches the design system CTA colour
- Hover state: `light-pink-400` (`#EF7598`) — lighter pink on hover (inverse of the usual pattern for a subtle "softening" effect)
- Remove underline: use `no-underline` Tailwind utility

- [ ] **Step 1: Update the link classes**

```diff
- <a href="/?demo=true" class="text-horizon-500 underline hover:text-raspberry-500 transition-colors" @click.prevent="enterPreviewMode">our demo</a>
+ <a href="/?demo=true" class="text-raspberry-500 no-underline hover:text-light-pink-400 transition-colors" @click.prevent="enterPreviewMode">our demo</a>
```

- [ ] **Step 2: Verify in browser**

  In the "Map your path to financial freedom" section, the "our demo" link in the line "Not convinced yet, check out our demo" should:
  - Appear raspberry pink (`#E83E6D`) with no underline in its resting state
  - Lighten to `#EF7598` on hover
  - No underline at any state

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: restyle our demo link — raspberry colour, no underline, light-pink hover"
```

---

## Final Verification Checklist

- [ ] Fyn character image is ~5% larger (`h-[430px]`) on desktop
- [ ] "our demo" link is raspberry pink with no underline
- [ ] "our demo" link lightens to `#EF7598` on hover
- [ ] No other elements affected
