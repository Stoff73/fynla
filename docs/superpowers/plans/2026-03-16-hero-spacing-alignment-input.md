# Hero Section Spacing, Alignment & Input Height Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce the hero section's bottom padding by 50%, vertically centre the hero content, and increase the Ask Fyn input/button height.

**Architecture:** Three Tailwind class changes confined entirely to `LandingPage.vue`. No new components, no logic changes.

**Tech Stack:** Vue 3, Tailwind CSS (Fynla design tokens)

---

## File Map

| File | Line(s) | Change |
|------|---------|--------|
| `resources/js/views/Public/LandingPage.vue` | 5 | Hero inner container: `pb-12` → `pb-6` |
| `resources/js/views/Public/LandingPage.vue` | 6, 8, 13 | Vertical centring: `lg:items-start` → `lg:items-center`, remove `lg:mt-[39px]` from image, remove `lg:self-center` from text div |
| `resources/js/views/Public/LandingPage.vue` | 28, 31 | Input and button: add `!py-3` override |

---

## Task 1: Reduce Hero Top Padding by 50%

The hero inner container is at line 5:
```html
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-12">
```

`pb-12` (48 px) provides the bottom spacing of the hero section, which is what creates visible top-of-page height. 50% reduction → `pb-6` (24 px).

- [ ] **Step 1: Change `pb-12` to `pb-6`**

```html
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-6">
```

- [ ] **Step 2: Verify**

Refresh http://localhost:8000 — hero section should be noticeably shorter / less tall.

---

## Task 2: Vertically Centre Hero Content

Currently the row container uses `lg:items-start` which top-aligns everything. The Fyn image compensates with a manual `lg:mt-[39px]` offset. Switching to `lg:items-center` centres both columns naturally and the manual offset can be removed.

**Line 6** — row container:
```html
<!-- BEFORE -->
<div class="flex flex-col lg:flex-row lg:items-start lg:gap-10">

<!-- AFTER -->
<div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">
```

**Line 8–9** — Fyn image wrapper & image (remove `items-start` and `lg:mt-[39px]`):
```html
<!-- BEFORE -->
<div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-start justify-center">
  <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[427px] w-auto lg:mt-[39px] lg:-mb-24" />

<!-- AFTER -->
<div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-center">
  <img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[427px] w-auto lg:-mb-24" />
```

**Line 13** — text column (remove redundant `lg:self-center` since parent now centres):
```html
<!-- BEFORE -->
<div class="flex-1 lg:self-center">

<!-- AFTER -->
<div class="flex-1">
```

- [ ] **Step 1: Apply all three sub-changes above**

- [ ] **Step 2: Verify**

Refresh http://localhost:8000 at desktop width — Fyn character and text block should sit at the same vertical midpoint within the hero section.

---

## Task 3: Increase Ask Fyn Input & Button Height

The input uses the global `.input-field` class which applies `py-2`. To increase height only in the hero (without touching the global style), use Tailwind's important modifier `!py-3` to override just this instance.

The button currently has `py-2` directly on the element — change to `py-3`.

**Line 28** — input:
```html
<!-- BEFORE -->
class="input-field flex-1"

<!-- AFTER -->
class="input-field flex-1 !py-3"
```

**Line 31** — button:
```html
<!-- BEFORE -->
class="px-8 py-2 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap"

<!-- AFTER -->
class="px-8 py-3 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap"
```

- [ ] **Step 1: Apply both changes**

- [ ] **Step 2: Verify**

Refresh http://localhost:8000 — the Ask Fyn input and button should be visibly taller than before, and matched in height to each other.

- [ ] **Step 3: Commit all three tasks together**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: reduce hero padding, centre content, increase input height"
```
