# Hero Section — Centred Layout & Scroll CTA Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the gradient hero from a two-column layout to a single centred column — heading + paragraph on top, Fyn Brain image beneath, then a centred CTA with Sign up + "Not convinced? Check out our demo or ask Fyn" where "Ask Fyn" smooth-scrolls to the Meet Fyn section.

**Architecture:** Template-only changes in `LandingPage.vue`. The hero inner layout switches from `lg:flex-row` to a stacked single-column layout. An `id` is added to the Meet Fyn section for scroll targeting. No new components, no JS changes needed (native anchor scroll).

**Tech Stack:** Vue 3, Tailwind CSS

---

## New Hero Layout (single column, centred)

```
┌─────────────────────────────────────────────┐
│  GRADIENT BACKGROUND                        │
│                                             │
│        Map your path to                     │
│        financial freedom          ← centred │
│                                             │
│   Body paragraph (centred, max-w-2xl)       │
│                                             │
│   ● Protection  ● Savings  ● Investment     │
│   ● Retirement  ● Estate   ● Net Worth      │
│                  ↑ centred                  │
│                                             │
│        [Fyn Brain PNG — centred]            │
│                                             │
│              [Sign up]                      │
│   Not convinced? Check out our demo         │
│   or ask Fyn ← scrolls to #meet-fyn        │
└─────────────────────────────────────────────┘
```

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 4–60 | Rewrite hero as single centred column |
| `resources/js/views/Public/LandingPage.vue` | ~62 | Add `id="meet-fyn"` to Meet Fyn section |

---

## Task 1: Add `id` to Meet Fyn Section

Required before rewriting hero so the anchor link has a target.

- [ ] **Step 1: Add `id="meet-fyn"` to the Meet Fyn section div**

```html
<!-- BEFORE -->
<div class="bg-light-pink-100 pt-10 pb-0">

<!-- AFTER -->
<div id="meet-fyn" class="bg-light-pink-100 pt-10 pb-0">
```

---

## Task 2: Rewrite Hero as Single Centred Column

Replace the entire hero section (lines 4–60) with:

```html
<!-- Hero Section -->
<div class="bg-gradient-to-r from-horizon-500 to-raspberry-500">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-12 text-center">

    <!-- Heading -->
    <h2 class="text-5xl leading-tight mb-6 text-white">
      Map your path to<br />
      <span class="text-spring-400">financial freedom</span>
    </h2>

    <!-- Body paragraph -->
    <p class="text-white/80 mb-6 max-w-2xl mx-auto leading-relaxed">
      We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;.
    </p>

    <!-- Fyn Brain image -->
    <div class="flex justify-center mb-8">
      <img
        src="/images/Fyn/202603-FynlaBrain.png"
        alt="Fynla Brain — your financial planning intelligence"
        class="w-full max-w-sm"
      />
    </div>

    <!-- CTA -->
    <div class="flex flex-col items-center gap-3">
      <router-link to="/register" class="px-8 py-2.5 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-all">Sign up</router-link>
      <p class="text-sm text-white/70">
        Not convinced? Check out
        <a href="/?demo=true" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="enterPreviewMode">our demo</a>
        or
        <a href="#meet-fyn" class="text-white/90 no-underline hover:text-spring-400 transition-colors">ask Fyn</a>
      </p>
    </div>

  </div>
</div>
```

- [ ] **Step 1: Apply the rewrite**

- [ ] **Step 2: Verify**

Refresh http://localhost:8000:
- Hero is a single centred column (no side-by-side columns)
- Heading + paragraph centred
- Feature dots row centred
- Fyn Brain image centred below the dots
- Sign up button centred below the image
- "Not convinced?" line centred below, "ask Fyn" link scrolls smoothly to the Meet Fyn section

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: hero to single centred column, brain image below text, ask Fyn scroll anchor"
```
