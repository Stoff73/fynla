# Homepage Margin & Text Tweaks (Batch 2) Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add top margin to the Fyn hero image, adjust top padding on the "Map your path" section, and revert the Solutions section heading to use "Solutions".

**Architecture:** Three Tailwind class / text changes in one file. No logic changes.

**Tech Stack:** Vue 3, Tailwind CSS

---

## File Map

| File | Line | Change |
|------|------|--------|
| `resources/js/views/Public/LandingPage.vue` | ~9 | Add `lg:mt-5` to Fyn image |
| `resources/js/views/Public/LandingPage.vue` | ~41 | `pt-12` → `pt-6` on Map your path section |
| `resources/js/views/Public/LandingPage.vue` | ~223 | Heading text → "Solutions" |

---

## Task 1: Add Top Margin to Fyn Image

Current state (image class after previous edits):
```html
<img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[427px] w-auto lg:-mb-24" />
```

- [ ] **Step 1: Add `lg:mt-5` to the image classes**

```html
<img src="/images/Fyn/Design Character 002.png?v=2" alt="Fyn" class="h-[427px] w-auto lg:mt-5 lg:-mb-24" />
```

- [ ] **Step 2: Verify**

Refresh http://localhost:8000 — Fyn character should sit 20px lower within the hero at desktop width.

---

## Task 2: Set Map Your Path Top Padding to 6

Current state:
```html
<div class="bg-white pt-12 pb-6">
```

- [ ] **Step 1: Change `pt-12` to `pt-6`**

```html
<div class="bg-white pt-6 pb-6">
```

Which can be simplified to `py-6`:
```html
<div class="bg-white py-6">
```

- [ ] **Step 2: Verify**

Scroll to "Map your path to financial freedom" — spacing above the heading should be tighter than before.

---

## Task 3: Update Solutions Section Heading

Current state:
```html
<h2 class="text-center mb-12">Templates to help you start quickly</h2>
```

- [ ] **Step 1: Change heading text to "Solutions"**

```html
<h2 class="text-center mb-12">Solutions</h2>
```

- [ ] **Step 2: Verify**

Scroll to the solutions cards section — heading should read "Solutions".

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: fyn image top margin, map your path padding, solutions heading"
```
