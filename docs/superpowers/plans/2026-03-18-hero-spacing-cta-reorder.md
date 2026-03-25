# Hero Spacing, CTA Text & Reorder Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tighten vertical spacing in the hero heading and paragraph, move the Sign up CTA above the Brain image, and update the secondary link row to "View the video | See our demo | Ask Fyn".

**Architecture:** Template-only changes within the hero section of `LandingPage.vue`. No logic changes.

**Tech Stack:** Vue 3, Tailwind CSS

---

## New Hero Order

```
1. Heading          (reduced mb-6 → mb-3)
2. Paragraph        (reduced mb-6 → mb-4)
3. Sign up button   ← moved up from below image
4. Link row: View the video | See our demo | Ask Fyn
5. Fyn Brain image  ← moved to bottom
```

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 8 | `mb-6` → `mb-3` on h2 |
| `resources/js/views/Public/LandingPage.vue` | 14 | `mb-6` → `mb-4` on paragraph |
| `resources/js/views/Public/LandingPage.vue` | 18–36 | Reorder: CTA block before Brain image; update link text |

---

## Task 1: Reduce Heading Spacing

```html
<!-- BEFORE -->
<h2 class="text-5xl leading-tight mb-6 text-white">

<!-- AFTER -->
<h2 class="text-5xl leading-tight mb-3 text-white">
```

- [ ] **Step 1: Change `mb-6` to `mb-3` on the h2**

---

## Task 2: Reduce Paragraph Spacing

```html
<!-- BEFORE -->
<p class="text-white/80 mb-6 max-w-2xl mx-auto leading-relaxed">

<!-- AFTER -->
<p class="text-white/80 mb-4 max-w-2xl mx-auto leading-relaxed">
```

- [ ] **Step 1: Change `mb-6` to `mb-4` on the paragraph**

---

## Task 3: Reorder CTA Above Image & Update Link Text

Replace the Brain image block + CTA block (lines 18–36) with the CTA first, image second. Also update the secondary link row to three pipe-separated links.

For "View the video" — no video exists yet so use `href="#"` as a placeholder. "See our demo" triggers the preview modal. "Ask Fyn" scrolls to `#meet-fyn`.

```html
<!-- CTA — now ABOVE the image -->
<div class="flex flex-col items-center gap-3 mb-8">
  <router-link to="/register" class="px-8 py-2.5 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-all">Sign up</router-link>
  <p class="text-sm text-white/70 flex items-center gap-2">
    <a href="#" class="text-white/90 no-underline hover:text-spring-400 transition-colors">View the video</a>
    <span class="text-white/40">|</span>
    <a href="/?demo=true" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="enterPreviewMode">See our demo</a>
    <span class="text-white/40">|</span>
    <a href="#meet-fyn" class="text-white/90 no-underline hover:text-spring-400 transition-colors">Ask Fyn</a>
  </p>
</div>

<!-- Fyn Brain image — now BELOW the CTA -->
<div class="flex justify-center">
  <img
    src="/images/Fyn/202603-FynlaBrain.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="w-full max-w-sm"
  />
</div>
```

- [ ] **Step 1: Replace the Brain image + CTA blocks with the reordered version above**

- [ ] **Step 2: Verify**

Refresh http://localhost:8000:
- Heading and paragraph sit closer together
- Sign up button and link row appear before the Brain image
- Link row reads: "View the video | See our demo | Ask Fyn"
- "Ask Fyn" scrolls to Meet Fyn section
- "See our demo" opens the persona modal
- Brain image sits at the bottom of the hero

---

## Task 4: Smooth Scroll for Ask Fyn

A plain `href="#meet-fyn"` jumps instantly. Replace it with a `@click.prevent` handler that uses `scrollIntoView({ behavior: 'smooth' })`.

**Template change** — update the Ask Fyn anchor:

```html
<!-- BEFORE -->
<a href="#meet-fyn" class="text-white/90 no-underline hover:text-spring-400 transition-colors">Ask Fyn</a>

<!-- AFTER -->
<a href="#meet-fyn" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="scrollToMeetFyn">Ask Fyn</a>
```

**Script change** — add method to `LandingPage.vue` `methods: {}`:

```js
scrollToMeetFyn() {
  document.getElementById('meet-fyn').scrollIntoView({ behavior: 'smooth' });
},
```

- [ ] **Step 1: Update the Ask Fyn anchor with `@click.prevent="scrollToMeetFyn"`**

- [ ] **Step 2: Add `scrollToMeetFyn()` to the component's `methods`**

- [ ] **Step 3: Verify** — clicking "Ask Fyn" smoothly animates the scroll down to the Meet Fyn section instead of jumping.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: tighten hero spacing, move CTA above brain image, update link row, smooth scroll ask Fyn"
```
