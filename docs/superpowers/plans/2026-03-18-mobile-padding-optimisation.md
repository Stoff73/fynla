# Mobile Padding Optimisation Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix awkward spacing, oversized text, and missing breathing room in the homepage on mobile (< 640px) without changing the desktop layout.

**Architecture:** Template-only changes in `LandingPage.vue`. All fixes use Tailwind responsive prefixes (`sm:`, `lg:`) so desktop styles are untouched. No logic changes.

**Tech Stack:** Vue 3, Tailwind CSS

---

## File Map

| File | Section | Change |
|------|---------|--------|
| `resources/js/views/Public/LandingPage.vue` | Hero heading | `text-6xl lg:text-8xl` → `text-4xl sm:text-5xl lg:text-8xl` |
| `resources/js/views/Public/LandingPage.vue` | Hero CTA link row | add `flex-wrap` so links wrap on narrow screens |
| `resources/js/views/Public/LandingPage.vue` | Meet Fyn section | replace `-mt-2 pt-0` with `pt-6 lg:-mt-2 lg:pt-0` |
| `resources/js/views/Public/LandingPage.vue` | Meet Fyn section | add `pb-8 lg:pb-0` for bottom breathing room on mobile |
| `resources/js/views/Public/LandingPage.vue` | Meet Fyn h1 | move `-mt-4` to `lg:-mt-4` so it only applies on desktop |
| `resources/js/views/Public/LandingPage.vue` | Meet Fyn subtitle | `text-2xl md:text-3xl lg:text-4xl` → `text-xl sm:text-2xl md:text-3xl lg:text-4xl` |
| `resources/js/views/Public/LandingPage.vue` | Dashboard section | add `id="dashboard"` |
| `resources/js/views/Public/LandingPage.vue` | Hero "View the video" link | add `@click.prevent="scrollToDashboard"` handler + method |

---

## Task 1: Reduce Hero Heading Size on Mobile

The `text-6xl` (3.75rem / 60px) heading is too large for a 375px screen — lines wrap awkwardly.

```html
<!-- BEFORE -->
<h2 class="text-6xl lg:text-8xl leading-tight mb-3 text-white">

<!-- AFTER -->
<h2 class="text-4xl sm:text-5xl lg:text-8xl leading-tight mb-3 text-white">
```

- [ ] **Step 1: Apply the heading size change**

- [ ] **Step 2: Verify** — On a 375px viewport the heading fits on two clean lines without overflow.

---

## Task 2: Allow CTA Link Row to Wrap on Narrow Screens

The `View the video | See our demo | Ask Fyn` row uses `flex items-center gap-2` with no wrapping. On narrow screens all three links plus two pipe separators squeeze into one line.

```html
<!-- BEFORE -->
<p class="text-sm text-white/70 flex items-center gap-2">

<!-- AFTER -->
<p class="text-sm text-white/70 flex flex-wrap items-center gap-2">
```

- [ ] **Step 1: Add `flex-wrap` to the CTA link paragraph**

- [ ] **Step 2: Verify** — On a 375px viewport the links wrap to a second line rather than overflowing.

---

## Task 3: Add Top Padding to Meet Fyn Section on Mobile

The section currently has `-mt-2 pt-0`. On desktop the Fyn character image creates natural height, but on mobile (where the character is hidden) the section has zero top spacing, causing the "Meet Fyn" heading to sit flush against the hero section.

Replace the negative-margin/zero-padding approach with mobile-first padding that collapses back to the desktop values at `lg:`.

```html
<!-- BEFORE -->
<div id="meet-fyn" class="bg-light-pink-100 -mt-2 pt-0 pb-0">

<!-- AFTER -->
<div id="meet-fyn" class="bg-light-pink-100 pt-6 pb-8 lg:-mt-2 lg:pt-0 lg:pb-0">
```

- [ ] **Step 1: Apply the section padding change**

- [ ] **Step 2: Verify** — On mobile there is breathing room above and below the Meet Fyn content. On desktop the section still sits close to the hero with no visible gap.

---

## Task 4: Restrict Meet Fyn H1 Negative Margin to Desktop

The h1 has `-mt-4` which was added to fine-tune desktop alignment against the Fyn character image. On mobile (no image) this pulls the heading upward into the section padding added in Task 3.

```html
<!-- BEFORE -->
<h1 class="text-6xl lg:text-8xl font-bold text-horizon-500 leading-none mb-1 -mt-4">Meet Fyn</h1>

<!-- AFTER -->
<h1 class="text-6xl lg:text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-4">Meet Fyn</h1>
```

- [ ] **Step 1: Change `-mt-4` to `lg:-mt-4`**

- [ ] **Step 2: Verify** — On mobile the "Meet Fyn" heading sits naturally within the section padding. On desktop it still nudges up by 4 units as before.

---

## Task 5: Reduce Meet Fyn Subtitle Size on Mobile

`text-2xl` (1.5rem / 24px) is slightly heavy for mobile on a long subtitle. Dropping to `text-xl` on the smallest breakpoint keeps it readable without crowding.

```html
<!-- BEFORE -->
<p class="text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">

<!-- AFTER -->
<p class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">
```

- [ ] **Step 1: Apply the subtitle size change**

- [ ] **Step 2: Verify** — Subtitle is slightly smaller on a 375px screen but still prominent.

---

## Task 6: "View the Video" Scrolls to Fynla Dashboard Section

Two sub-changes: add an `id` to the dashboard section so there's a scroll target, then wire up the link with a smooth-scroll handler.

**6a — Add `id="dashboard"` to the Your Fynla Dashboard section**

```html
<!-- BEFORE -->
<div class="bg-eggshell-500 py-10 lg:py-12">

<!-- AFTER -->
<div id="dashboard" class="bg-eggshell-500 py-10 lg:py-12">
```

**6b — Update the "View the video" anchor with a click handler**

```html
<!-- BEFORE -->
<a href="#" class="text-white/90 no-underline hover:text-spring-400 transition-colors">View the video</a>

<!-- AFTER -->
<a href="#dashboard" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="scrollToDashboard">View the video</a>
```

**6c — Add `scrollToDashboard()` method to the component's `methods` block**

```js
scrollToDashboard() {
  document.getElementById('dashboard').scrollIntoView({ behavior: 'smooth' });
},
```

- [ ] **Step 1: Add `id="dashboard"` to the dashboard section div**

- [ ] **Step 2: Update the "View the video" anchor with `href="#dashboard"` and `@click.prevent="scrollToDashboard"`**

- [ ] **Step 3: Add `scrollToDashboard()` to the component's `methods`**

- [ ] **Step 4: Verify** — Clicking "View the video" smoothly scrolls to the Fynla dashboard section.

---

## Task 7: Commit

- [ ] **Step 1: Commit all changes**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: optimise mobile padding, text sizes, and wire View the video scroll"
```
