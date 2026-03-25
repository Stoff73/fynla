# Homepage Colour, Text & Layout Tweaks Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Six isolated changes — three background colour swaps, one text removal from hero, one text addition to Features section, and a bottom-padding reduction to let the Fyn character hang over the section boundary.

**Architecture:** All changes are in `LandingPage.vue`. No new components, no logic changes.

**Tech Stack:** Vue 3, Tailwind CSS (Fynla design tokens)

---

## File Map

| Line | Section | Change |
|------|---------|--------|
| 63 | Meet Fyn section | `bg-white` → `bg-light-pink-100`; `py-10` → `pt-10 pb-0` |
| 69 | Fyn character image | add `lg:-mb-16` so image hangs below section |
| 104 | How Fyn Can Help You | add subtitle paragraph after `<h2>` |
| 15 | Hero paragraph | truncate text — remove second clause |
| 207 | Fynla Dashboard section | `bg-[#FAD6E0]` → `bg-eggshell-500` |
| 227 | Solutions section | `bg-eggshell-500` → `bg-light-pink-100` |

---

## Task 1: Meet Fyn — Light Pink Background & Fyn Hangs Over Bottom

Two sub-changes on the same section.

**1a — Background colour + bottom padding**

```html
<!-- BEFORE -->
<div class="bg-white py-10">

<!-- AFTER -->
<div class="bg-light-pink-100 pt-10 pb-0">
```

**1b — Fyn character image: add negative bottom margin**

The image currently ends flush at the section boundary. Adding `lg:-mb-16` pulls it 64 px below so it overlaps the dark "How Fyn Can Help You" section beneath.

```html
<!-- BEFORE -->
<img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto" />

<!-- AFTER -->
<img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto lg:-mb-16" />
```

- [ ] **Step 1: Apply both sub-changes**
- [ ] **Step 2: Verify** — Meet Fyn section has a light pink background, and the Fyn character visually breaks into the dark "How Fyn Can Help You" section below. The text/input column should still have `pb-10` breathing room (no change needed there — the `pt-10` on the outer div handles top padding; the image drives the bottom overlap).

---

## Task 2: Shorten Hero Paragraph

Current text (line 15):
```
We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain®, which will leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
```

Remove everything from ", which will leverage..." to the end. Keep only the first clause:

```html
<!-- BEFORE -->
<p class="text-white/80 mb-6 max-w-lg leading-relaxed">
  We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;, which will leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
</p>

<!-- AFTER -->
<p class="text-white/80 mb-6 max-w-lg leading-relaxed">
  We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;.
</p>
```

- [ ] **Step 1: Apply the text change**
- [ ] **Step 2: Verify** — Hero paragraph ends after "Fynla Brain®." with no trailing clause.

---

## Task 3: Add Subtitle to "How Fyn Can Help You"

Current (line 104):
```html
<h2 class="text-center mb-12 text-white">How Fyn can help you</h2>
```

Add a subtitle paragraph directly after the `<h2>`, before `mb-12` creates too much space. Reduce the h2's `mb-12` to `mb-4` to accommodate the subtitle, then add `mb-10` after the subtitle.

```html
<!-- AFTER -->
<h2 class="text-center mb-4 text-white">How Fyn can help you</h2>
<p class="text-center text-white/70 text-sm max-w-2xl mx-auto mb-10 leading-relaxed">
  We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
</p>
```

- [ ] **Step 1: Apply the change**
- [ ] **Step 2: Verify** — "How Fyn can help you" heading is followed by the subtitle paragraph in lighter white text, then the 6 feature cards.

---

## Task 4: Fynla Dashboard — Eggshell Background

Current (line 207):
```html
<div class="bg-[#FAD6E0] py-10 lg:py-12">
```

Replace hardcoded hex with the design system token:
```html
<div class="bg-eggshell-500 py-10 lg:py-12">
```

- [ ] **Step 1: Apply the change**
- [ ] **Step 2: Verify** — Dashboard section background is the eggshell cream tone, not the pink hex.

---

## Task 5: Solutions — Light Pink Background

Current (line 227):
```html
<div id="solutions" class="bg-eggshell-500 pt-10 lg:pt-12 pb-24 lg:pb-28">
```

```html
<div id="solutions" class="bg-light-pink-100 pt-10 lg:pt-12 pb-24 lg:pb-28">
```

- [ ] **Step 1: Apply the change**
- [ ] **Step 2: Verify** — Solutions section has a light pink background matching the Meet Fyn section.

- [ ] **Step 3: Commit all changes**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: light pink sections, eggshell dashboard, Fyn character overflow, hero/features text updates"
```
