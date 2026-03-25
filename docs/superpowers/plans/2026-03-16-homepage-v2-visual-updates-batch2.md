# Homepage v2 Visual Updates — Batch 2 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Match five visual sections of the Fynla landing page to the Fynla-Homepagev2.png reference design.

**Architecture:** Two files change — `LandingPage.vue` (subtitle size + solutions cards) and `PublicLayout.vue` (footer background, logo, text). No logic changes; pure Tailwind class and template edits.

**Tech Stack:** Vue 3, Tailwind CSS (custom palette: horizon, raspberry, spring, violet, savannah, eggshell), Laravel 10

**Reference image:** `public/images/References/Fynla-Homepagev2.png`

---

## Files to Modify

| File | Change |
|------|--------|
| `resources/js/views/Public/LandingPage.vue` | Subtitle size (line 15); Solutions section cards (lines 221–274) |
| `resources/js/layouts/PublicLayout.vue` | Footer background, logo src + size, all text/link colours (lines 129–188, 199) |

---

## Task 1 — Subtitle: "Your financial companion for life"

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:15`

### What to change

The subtitle is currently sized `text-xl md:text-2xl`. In the reference it reads as a large, prominent sub-heading beneath "Meet Fyn" — roughly proportional to `text-2xl md:text-3xl lg:text-4xl`.

- [ ] **Step 1: Apply the new size classes**

```diff
- <p class="text-xl md:text-2xl font-semibold text-white/90 mb-3">
+ <p class="text-2xl md:text-3xl lg:text-4xl font-semibold text-white/90 mb-3">
```

- [ ] **Step 2: Verify in browser**

  Open http://localhost:8000 — the subtitle should now be visibly larger, balanced against the `text-6xl lg:text-8xl` "Meet Fyn" heading. It should not overflow the hero on mobile.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: increase subtitle size to match homepage v2 reference"
```

---

## Task 2 — Solutions section: redesign to cards with icon placeholder

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:221-274` (solutions section)

### Context

**Current design:** Each solution is a small centered icon + label column — `max-w-[140px]` items in a flex-wrap row.

**Reference design:** Each solution is a proper rectangular card. The card has:
- A large icon-placeholder area at the top (a coloured rounded square, ~48×48 or larger, e.g., `w-16 h-16`)
- "FYNLA" label in small caps
- Solution name bold
- Short description paragraph
- Cards sit in a grid (reference shows 5 across on desktop, 2-col on tablet, 1-col on mobile)

The background remains `bg-eggshell-500`. Cards should use `bg-white` with a subtle shadow (`card` class). Layout changes from `flex-wrap` to a responsive `grid`.

- [ ] **Step 1: Replace the solutions grid markup**

Replace the entire `<div class="flex flex-wrap justify-center gap-8 lg:gap-10 mb-10">` block (lines ~225–266) with the card grid below.

**New markup** (replaces existing flex-wrap block — keep the `<div class="text-center">` button block beneath it unchanged):

```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
  <!-- Fynla Investor -->
  <div class="card p-6 flex flex-col items-start cursor-pointer hover:shadow-md transition-shadow" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-horizon-500 flex items-center justify-center mb-4">
      <!-- Icon placeholder -->
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-horizon-400 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-horizon-500 mb-2">INVESTOR</p>
    <p class="text-xs text-neutral-500 leading-relaxed">Portfolio analysis, risk profiling, and investment strategy tools.</p>
  </div>

  <!-- Fynla Life -->
  <div class="card p-6 flex flex-col items-start cursor-pointer hover:shadow-md transition-shadow" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-raspberry-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-raspberry-400 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-horizon-500 mb-2">LIFE</p>
    <p class="text-xs text-neutral-500 leading-relaxed">Protection, critical illness, and income cover analysis for your family.</p>
  </div>

  <!-- Fynla Manager -->
  <div class="card p-6 flex flex-col items-start cursor-pointer hover:shadow-md transition-shadow" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-spring-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-spring-600 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-horizon-500 mb-2">MANAGER</p>
    <p class="text-xs text-neutral-500 leading-relaxed">Net worth tracking, savings goals, and financial oversight tools.</p>
  </div>

  <!-- Fynla Planner -->
  <div class="card p-6 flex flex-col items-start cursor-pointer hover:shadow-md transition-shadow" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-violet-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-violet-400 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-horizon-500 mb-2">PLANNER</p>
    <p class="text-xs text-neutral-500 leading-relaxed">Retirement projections, pension tracking, and estate planning.</p>
  </div>

  <!-- Fynla Saver -->
  <div class="card p-6 flex flex-col items-start cursor-pointer hover:shadow-md transition-shadow" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-savannah-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-savannah-600 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-horizon-500 mb-2">SAVER</p>
    <p class="text-xs text-neutral-500 leading-relaxed">Emergency funds, ISA allowances, and savings goal tracking.</p>
  </div>
</div>
```

- [ ] **Step 2: Verify in browser**

  Scroll to "Solutions built just for you" section at http://localhost:8000. Should show 5 cards across on desktop, 2 on tablet, 1 on mobile. Each card should have a coloured icon-placeholder block at top-left, bold name, and description.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: redesign solutions section to card layout with icon placeholders"
```

---

## Task 3 — Footer: background, logo, text colours

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:129-188, 199`

### Context

**Current state:**
- `<footer class="bg-savannah-100 pt-16">` — light tan background
- Logo: `LogoHiResFynlaDark.png`, `h-16 w-auto` in footer
- Header logo: `LogoHiResFynlaDark.png`, `h-14 w-auto`
- All text: `text-neutral-500`, headings: `text-horizon-500`, links: `text-neutral-500 hover:text-raspberry-500`
- Copyright: `text-neutral-500`
- Border: `border-light-gray`

**Target state (matching reference and "How Fyn can help you" gradient):**
- Background: `bg-gradient-to-r from-horizon-600 to-horizon-700`
- Logo: `LogoHiResFynlaLight.png`, `h-14 w-auto` (matches header size)
- Logo `data` property: change `logoUrl` to point to Light version (but only in footer — header keeps Dark)
- Body text: `text-white/70`
- Section headings (`h3`): `text-white`
- Links: `text-white/70 hover:text-white`
- Description paragraph: `text-white/70`
- Copyright text: `text-white/70`
- Border: `border-white/20` (replaces `border-light-gray`)

**Important:** The header uses `logoUrl` from `data()` which currently points to `LogoHiResFynlaDark.png`. The footer currently also uses `logoUrl`. Since they need different logos, the footer should use a separate `footerLogoUrl` data property.

### Steps

- [ ] **Step 1: Add `footerLogoUrl` to `data()`**

```diff
  data() {
    return {
      mobileMenuOpen: false,
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
+     footerLogoUrl: '/images/logos/LogoHiResFynlaLight.png',
    };
  },
```

- [ ] **Step 2: Update footer `<footer>` opening tag — background**

```diff
- <footer class="bg-savannah-100 pt-16">
+ <footer class="bg-gradient-to-r from-horizon-600 to-horizon-700 pt-16">
```

- [ ] **Step 3: Update footer logo — src + size**

```diff
- <img :src="logoUrl" alt="Fynla" class="h-16 w-auto" />
+ <img :src="footerLogoUrl" alt="Fynla" class="h-14 w-auto" />
```

- [ ] **Step 4: Update footer description paragraph**

```diff
- <p class="text-sm text-neutral-500 leading-relaxed">
+ <p class="text-sm text-white/70 leading-relaxed">
```

- [ ] **Step 5: Update all four column headings (`h3`) — four occurrences**

```diff
- <h3 class="text-sm font-bold text-horizon-500 mb-4">About Fynla</h3>
+ <h3 class="text-sm font-bold text-white mb-4">About Fynla</h3>

- <h3 class="text-sm font-bold text-horizon-500 mb-4">Help centre</h3>
+ <h3 class="text-sm font-bold text-white mb-4">Help centre</h3>

- <h3 class="text-sm font-bold text-horizon-500 mb-4">Terms</h3>
+ <h3 class="text-sm font-bold text-white mb-4">Terms</h3>

- <h3 class="text-sm font-bold text-horizon-500 mb-4">Tools</h3>
+ <h3 class="text-sm font-bold text-white mb-4">Tools</h3>
```

- [ ] **Step 6: Update all footer link classes — all `<li><router-link>` and `<li><a>` items**

Replace every instance of:
```
class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors"
```
with:
```
class="text-sm text-white/70 hover:text-white transition-colors"
```

There are 10 link items across four columns. Use find-replace (all occurrences).

- [ ] **Step 7: Update the bottom border and copyright**

```diff
- <div class="border-t border-light-gray mt-8 pt-8">
-   <p class="text-sm text-neutral-500">
+ <div class="border-t border-white/20 mt-8 pt-8">
+   <p class="text-sm text-white/70">
```

- [ ] **Step 8: Verify in browser**

  Check http://localhost:8000 footer. Should show:
  - Dark navy gradient background (matching "How Fyn can help you" section)
  - Light Fynla logo (white/light variant), same height as header logo
  - All text white or white/70
  - Column headings fully white
  - Links white/70, hover to full white
  - Copyright white/70

- [ ] **Step 9: Commit**

```bash
git add resources/js/layouts/PublicLayout.vue
git commit -m "feat: update footer to dark gradient, light logo, and white text"
```

---

## Verification Checklist

After all tasks are done, visually confirm:

- [ ] Subtitle "Your financial companion for life" is noticeably larger than before, proportional to "Meet Fyn" heading
- [ ] "Solutions built just for you" shows 5 cards in a grid (not icon-columns), each with a coloured square icon-placeholder, bold name, and description
- [ ] Footer has the same dark navy gradient as the "How Fyn can help you" section
- [ ] Footer logo is the light/white version (`LogoHiResFynlaLight.png`), same height as header logo (`h-14`)
- [ ] All footer text (description, headings, links, copyright) is white or white/70
- [ ] Header logo is unchanged (still dark version, `h-14`)
- [ ] No other sections affected
