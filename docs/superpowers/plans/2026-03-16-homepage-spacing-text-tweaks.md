# Homepage Spacing & Text Tweaks Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce nav bar height, increase top padding on the "Map your path" section, and update the Solutions section heading text.

**Architecture:** Three isolated Tailwind class / text changes across two files. No logic changes, no new components.

**Tech Stack:** Vue 3, Tailwind CSS (Fynla design tokens)

---

## File Map

| File | Change |
|------|--------|
| `resources/js/layouts/PublicLayout.vue` | Reduce nav bar height (`h-20` → `h-16`) |
| `resources/js/views/Public/LandingPage.vue` | Increase top padding on "Map your path" section; update Solutions heading text |

---

## Task 1: Reduce Nav Bar Height

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:6`

Current state (line 6):
```html
<div class="flex justify-between h-20">
```

- [ ] **Step 1: Reduce height from `h-20` (80px) to `h-16` (64px)**

```html
<div class="flex justify-between h-16">
```

- [ ] **Step 2: Verify**

Open http://localhost:8000 — nav bar should be visibly shorter. Logo (`h-14`) fits within `h-16` with minimal vertical breathing room, which is intentional. If the logo feels cramped, adjust logo to `h-10` instead.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/PublicLayout.vue
git commit -m "style: reduce nav bar height from h-20 to h-16"
```

---

## Task 2: Increase Top Padding on "Map Your Path" Section

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:41`

Current state (line 41):
```html
<div class="bg-white py-6">
```

`py-6` applies equal top and bottom padding (24px each). Increase top padding independently to `pt-12 pb-6` to add more breathing room between the hero gradient and this section.

- [ ] **Step 1: Change `py-6` to `pt-12 pb-6`**

```html
<div class="bg-white pt-12 pb-6">
```

- [ ] **Step 2: Verify**

Scroll to the "Map your path to financial freedom" section — there should be noticeably more whitespace above the heading compared to before. Bottom spacing remains unchanged.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: increase top padding on financial freedom section"
```

---

## Task 3: Update Solutions Section Heading

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:223`

Current state (line 223):
```html
<h2 class="text-center mb-12">Solutions built just for you</h2>
```

- [ ] **Step 1: Replace heading text**

```html
<h2 class="text-center mb-12">Templates to help you start quickly</h2>
```

- [ ] **Step 2: Verify**

Scroll to the Solutions cards section — heading should now read "Templates to help you start quickly".

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "copy: update solutions section heading"
```
