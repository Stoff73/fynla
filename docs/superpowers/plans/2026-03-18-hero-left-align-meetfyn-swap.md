# Hero Left Align & Meet Fyn Column Swap Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Left-align hero text and CTA while keeping the Brain image centred; swap the Meet Fyn section so the text/input is on the left and the Fyn character is on the right.

**Architecture:** Template-only changes in `LandingPage.vue`. Two isolated edits — one in the hero, one in the Meet Fyn section.

**Tech Stack:** Vue 3, Tailwind CSS

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 5–29 | Remove `text-center` from hero container; left-align heading, paragraph, and CTA; keep Brain image centred |
| `resources/js/views/Public/LandingPage.vue` | 46–76 | Swap column order: text/input left, Fyn character right |

---

## Task 1: Left-Align Hero Text & CTA

Three sub-changes:

**1a — Remove `text-center` from the outer container (line 5)**

```html
<!-- BEFORE -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-12 text-center">

<!-- AFTER -->
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-12">
```

**1b — Remove `mx-auto` from the paragraph (line 14) so it left-aligns**

```html
<!-- BEFORE -->
<p class="text-white/80 mb-4 max-w-2xl mx-auto leading-relaxed">

<!-- AFTER -->
<p class="text-white/80 mb-4 max-w-2xl leading-relaxed">
```

**1c — Change CTA `items-center` to `items-start` (line 20)**

```html
<!-- BEFORE -->
<div class="flex flex-col items-center gap-3 mb-8">

<!-- AFTER -->
<div class="flex flex-col items-start gap-3 mb-8">
```

The Brain image div already uses `flex justify-center` (line 32) — no change needed; it stays centred.

- [ ] **Step 1: Apply all three sub-changes**

- [ ] **Step 2: Verify**

Refresh http://localhost:8000 — heading, paragraph, and Sign up / link row are left-aligned. Brain image remains centred beneath.

---

## Task 2: Swap Meet Fyn Column Order

Currently: **Left** = Fyn character image | **Right** = text + input
After:     **Left** = text + input | **Right** = Fyn character image

Move the Fyn character div after the text/input div. Also update the character wrapper from `hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-center` to use `justify-end` so it sits to the right naturally.

```html
<!-- BEFORE -->
<div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">

  <!-- Left: Fyn character -->
  <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-center">
    <img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto lg:-mb-[3em]" />
  </div>

  <!-- Right: Meet Fyn text + Ask Fyn input -->
  <div class="flex-1">
    ...text and input...
  </div>

</div>

<!-- AFTER -->
<div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">

  <!-- Left: Meet Fyn text + Ask Fyn input -->
  <div class="flex-1">
    ...text and input...
  </div>

  <!-- Right: Fyn character -->
  <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-end">
    <img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto lg:-mb-[3em]" />
  </div>

</div>
```

- [ ] **Step 1: Move the Fyn character div to after the text/input div; change `justify-center` to `justify-end`**

- [ ] **Step 2: Verify**

Refresh http://localhost:8000 — Meet Fyn text and Ask Fyn input are on the left, Fyn character is on the right.

---

## Task 3: Update Button Colours

**3a — Sign up button: light blue → green**

```html
<!-- BEFORE -->
<router-link to="/register" class="px-8 py-2.5 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-all">Sign up</router-link>

<!-- AFTER -->
<router-link to="/register" class="px-8 py-2.5 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-all">Sign up</router-link>
```

**3b — Ask Fyn button in Meet Fyn section: green → light blue**

```html
<!-- BEFORE -->
<button type="button" @click="handleAskFyn" class="px-8 py-3 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap">

<!-- AFTER -->
<button type="button" @click="handleAskFyn" class="px-8 py-3 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-colors whitespace-nowrap">
```

- [ ] **Step 1: Apply both button colour changes**

---

## Task 4: "Financial Freedom" Text Colour in Hero

Currently `text-spring-400`. Change to `text-light-pink-400` — a light pink that contrasts clearly against the horizon-to-raspberry gradient without blending into the raspberry end.

```html
<!-- BEFORE -->
<span class="text-spring-400">financial freedom</span>

<!-- AFTER -->
<span class="text-light-pink-400">financial freedom</span>
```

- [ ] **Step 1: Apply the colour change**

---

## Task 5: Increase Hero Heading Text Size to Match "Meet Fyn"

"Meet Fyn" uses `text-6xl lg:text-8xl`. Update the hero h2 to match.

```html
<!-- BEFORE -->
<h2 class="text-5xl leading-tight mb-3 text-white">

<!-- AFTER -->
<h2 class="text-6xl lg:text-8xl leading-tight mb-3 text-white">
```

- [ ] **Step 1: Apply the text size change**

- [ ] **Step 2: Verify** — "Map your path to financial freedom" renders at the same size as "Meet Fyn" below it.

- [ ] **Step 3: Commit all tasks**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: left-align hero, swap Meet Fyn columns, button colours, financial freedom text pink"
```
