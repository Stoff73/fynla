# Homepage v2 Visual Updates — Batch 3 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply six visual polish changes to the public landing page and layout to match the Fynla Homepage v2 reference design.

**Architecture:** Two files change — `PublicLayout.vue` (sign-in button, footer padding) and `LandingPage.vue` (solutions panel cards, view-demo button, sign-up button). No logic changes; pure Tailwind class edits. Solutions panels drop the global `.card` class (which hardcodes `bg-white`) in favour of explicit classes so the dark gradient can be applied without `!important` hacks.

**Tech Stack:** Vue 3, Tailwind CSS (custom palette tokens defined in `tailwind.config.js`), Laravel 10

**Reference image:** `public/images/References/Fynla-Homepagev2.png`

**Palette quick-reference** (from `tailwind.config.js`):
| Token | Hex |
|-------|-----|
| `light-pink-100` | `#FAD6E0` |
| `light-pink-400` | `#EF7598` |
| `light-blue-500` | `#6C83BC` |
| `eggshell-500`   | `#F7F6F4` |
| `horizon-600`    | `#0F172A` |
| `horizon-700`    | `#020617` |
| `spring-500`     | `#20B486` |
| `spring-600`     | `#059669` |

---

## Files to Modify

| File | Changes |
|------|---------|
| `resources/js/layouts/PublicLayout.vue` | Sign-in button colour (line 51–54); footer top padding (line 129) |
| `resources/js/views/Public/LandingPage.vue` | Solutions section panels (lines 225–282); "View demo" button (line 269); "Sign up" button (line 76) |

---

## ⚠️ Note: Solutions background is already correct

The "Solutions built just for you" section wrapper currently has `class="bg-eggshell-500 ..."` and `eggshell-500 = #F7F6F4` in `tailwind.config.js`. **No change needed** — the background is already the requested colour.

---

## Task 1 — Sign-in button: green → light pink

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:51–54`

The nav sign-in button is currently `bg-spring-500` (green). Change it to light pink (`light-pink-100`) with dark text for legibility, darkening on hover to `light-pink-400` with white text.

- [ ] **Step 1: Update the desktop sign-in button**

```diff
- class="min-w-[120px] px-5 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors text-center"
+ class="min-w-[120px] px-5 py-2.5 bg-light-pink-100 text-horizon-500 text-sm font-semibold rounded-lg hover:bg-light-pink-400 hover:text-white transition-colors text-center"
```

- [ ] **Step 2: Verify in browser**

  Open http://localhost:8000. The "Sign in" button in the nav bar should now be a pale pink (`#FAD6E0`) with dark navy text, turning deeper pink on hover.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/PublicLayout.vue
git commit -m "feat: change sign-in button to light pink to match homepage v2"
```

---

## Task 2 — Footer: increase top padding

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:129`

The footer currently has `pt-16` (4rem). The stats bar straddles the footer with `-mb-12` (3rem), leaving only 1rem of visible footer top before content. The reference shows more breathing room above the footer columns. Increase to `pt-28` (7rem), which gives 4rem of clear space below the stats bar.

- [ ] **Step 1: Update footer padding**

```diff
- <footer class="bg-gradient-to-r from-horizon-600 to-horizon-700 pt-16">
+ <footer class="bg-gradient-to-r from-horizon-600 to-horizon-700 pt-28">
```

- [ ] **Step 2: Verify in browser**

  Scroll to the bottom of http://localhost:8000. The footer columns (logo, links) should sit further below the stats bar, matching the reference spacing.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/PublicLayout.vue
git commit -m "feat: increase footer top padding to match homepage v2 reference spacing"
```

---

## Task 3 — Solutions panels: white cards → dark navy gradient

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:225–282`

**Current state:** Each card uses the global `.card` class, which applies `bg-white rounded-card border border-light-gray shadow-sm p-6`. The white background cannot be overridden with the dark gradient without Tailwind `!important` hacks because `.card` is declared in `@layer components`.

**Approach:** Replace the `.card` class on these five cards with explicit utility classes that include the same visual properties (`rounded-card`, `border`, `shadow-sm`, `p-6`, `transition-all`, `duration-200`) plus the dark gradient background. This keeps the global `.card` class unchanged everywhere else.

**Text colour updates required** (dark bg → white text):
- `FYNLA` label: from module-specific colour (e.g. `text-horizon-400`) → `text-white/60`
- Solution name: from `text-horizon-500` → `text-white`
- Description: from `text-neutral-500` → `text-white/70`
- Border on the card: `border-white/10` (replaces `border-light-gray`)
- Hover border: remove (or use `hover:border-white/30`)

- [ ] **Step 1: Replace all five solution cards**

Replace the entire `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">` block with:

```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
  <!-- Fynla Investor -->
  <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-horizon-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-white mb-2">INVESTOR</p>
    <p class="text-xs text-white/70 leading-relaxed">Portfolio analysis, risk profiling, and investment strategy tools.</p>
  </div>

  <!-- Fynla Life -->
  <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-raspberry-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-white mb-2">LIFE</p>
    <p class="text-xs text-white/70 leading-relaxed">Protection, critical illness, and income cover analysis for your family.</p>
  </div>

  <!-- Fynla Manager -->
  <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-spring-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-white mb-2">MANAGER</p>
    <p class="text-xs text-white/70 leading-relaxed">Net worth tracking, savings goals, and financial oversight tools.</p>
  </div>

  <!-- Fynla Planner -->
  <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-violet-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-white mb-2">PLANNER</p>
    <p class="text-xs text-white/70 leading-relaxed">Retirement projections, pension tracking, and estate planning.</p>
  </div>

  <!-- Fynla Saver -->
  <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
    <div class="w-16 h-16 rounded-lg bg-savannah-500 flex items-center justify-center mb-4">
      <span class="w-8 h-8 rounded bg-white/20 block"></span>
    </div>
    <p class="text-xs font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
    <p class="text-sm font-bold text-white mb-2">SAVER</p>
    <p class="text-xs text-white/70 leading-relaxed">Emergency funds, ISA allowances, and savings goal tracking.</p>
  </div>
</div>
```

- [ ] **Step 2: Verify in browser**

  Scroll to "Solutions built just for you" at http://localhost:8000. The five cards should now show a dark navy gradient background (matching the footer), white text, and coloured icon-placeholder squares. The section background remains the light `#F7F6F4` eggshell creating contrast.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: update solutions panels to dark navy gradient with white text"
```

---

## Task 4 — "View demo" button: raspberry → green

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:269`

The "View demo" button uses `btn-primary` which applies raspberry (`#E83E6D`) background. Change to spring green.

- [ ] **Step 1: Replace `btn-primary` with explicit green classes**

```diff
- <button type="button" @click="enterPreviewMode" :disabled="enteringPreview" class="btn-primary px-8">
+ <button type="button" @click="enterPreviewMode" :disabled="enteringPreview" class="px-8 py-2 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 active:bg-spring-700 transition-all duration-150 shadow-sm hover:shadow-md">
```

- [ ] **Step 2: Verify in browser**

  The "View demo" button in the Solutions section should be spring green (`#20B486`), not raspberry.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: change solutions view demo button to green"
```

---

## Task 5 — "Sign up" button: horizon navy → light blue (#6C83BC)

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:76`

The "Sign up" button on the "Map your path to financial freedom" section is currently `bg-horizon-500` (dark navy `#1F2A44`). Change to `light-blue-500` (`#6C83BC`). There is no `light-blue-600` token, so use `hover:opacity-90` for the hover state.

- [ ] **Step 1: Update the sign-up button classes**

```diff
- <router-link to="/register" class="px-8 py-2.5 bg-horizon-500 text-white rounded-button font-medium hover:bg-horizon-600 transition-colors">Sign up</router-link>
+ <router-link to="/register" class="px-8 py-2.5 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-all">Sign up</router-link>
```

- [ ] **Step 2: Verify in browser**

  The "Sign up" button in the "Map your path" section should now be a medium blue (`#6C83BC`), not dark navy.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: change sign-up button to light-blue-500 (#6C83BC) to match homepage v2"
```

---

## Task 6 — "View demos" link: invisible navy → white

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:193`

The "View demos >" link sits inside the dark navy gradient "How Fyn can help you" section. Its current colour `text-horizon-500` (`#1F2A44`) is near-invisible against the dark background. Change to white with a subtle dimmed-white hover.

- [ ] **Step 1: Update the link text colour**

```diff
- <a href="/?demo=true" class="text-horizon-500 font-medium hover:text-raspberry-500 transition-colors" @click.prevent="enterPreviewMode">
+ <a href="/?demo=true" class="text-white font-medium hover:text-white/70 transition-colors" @click.prevent="enterPreviewMode">
```

- [ ] **Step 2: Verify in browser**

  In the "How Fyn can help you" section, scroll to the bottom and confirm "View demos >" is clearly visible in white.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "fix: make View demos link white on dark section background"
```

---

## Task 7 — Reduce top padding above "Map your path to freedom"

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:41`

**Current state:** `class="bg-white py-6 lg:pt-20"` — `py-6` (1.5rem) on mobile, but `lg:pt-20` (5rem) overrides the top on desktop, creating a large white gap between the hero section and the "Map your path" text.

**Target:** Remove the large desktop-only top padding so the section flows directly below the hero. The Fyn character image has `lg:-mb-24` which already creates overlap into this section. Change `lg:pt-20` to `lg:pt-6` so the desktop top matches mobile.

- [ ] **Step 1: Reduce desktop top padding**

```diff
- <div class="bg-white py-6 lg:pt-20">
+ <div class="bg-white py-6">
```

- [ ] **Step 2: Verify in browser**

  On desktop (≥1024px), the white gap between the hero gradient and "Map your path to financial freedom" heading should be substantially reduced. The Fyn character image should overlap the top of this section naturally.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: reduce top padding on Map your path section to tighten hero layout"
```

---

## Task 8 — Slightly decrease top padding above "Meet Fyn" hero text

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:5`

**Current state:** The hero container uses `py-12` (3rem top and bottom). The user wants slightly less breathing room at the top. Change to `pt-8 pb-12` (2rem top, 3rem bottom).

- [ ] **Step 1: Reduce hero top padding**

```diff
- <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
+ <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12">
```

- [ ] **Step 2: Verify in browser**

  The "Meet Fyn" heading should sit slightly closer to the top of the hero gradient section. The bottom padding (space above the next section) should remain unchanged.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: slightly reduce hero top padding to match homepage v2"
```

---

## Task 9 — Increase desktop navigation link text size

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:17, 23, 29, 35, 41`

**Current state:** All five desktop nav links use `text-sm font-medium` (0.875rem). The reference shows noticeably larger nav text — `text-base` (1rem).

**Note:** The mobile menu links already use `text-base` — this change only affects the hidden desktop nav (`hidden md:flex` block). Do NOT change the mobile menu block.

- [ ] **Step 1: Update all five desktop nav link classes**

Replace every desktop nav link class. There are 5 links — Home (router-link), Features (a), Solutions (a), Pricing (router-link), Learning centre (router-link). All share the same pattern; only `text-horizon-500` vs `text-neutral-500` differs between the active and inactive links.

```diff
- class="inline-flex items-center px-1 pt-1 text-sm font-medium text-horizon-500 hover:text-raspberry-500 transition-colors"
+ class="inline-flex items-center px-1 pt-1 text-base font-medium text-horizon-500 hover:text-raspberry-500 transition-colors"
```

```diff
- class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
+ class="inline-flex items-center px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
```

(Apply to all four `text-neutral-500` nav links: Features, Solutions, Pricing, Learning centre.)

- [ ] **Step 2: Verify in browser**

  Desktop nav links (Home, Features, Solutions, Pricing, Learning centre) should be visibly larger — `text-base` (16px) vs previous `text-sm` (14px). The nav bar height is `h-20` so there is room. Mobile menu unchanged.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/PublicLayout.vue
git commit -m "feat: increase desktop nav text from text-sm to text-base to match homepage v2"
```

---

## Final Verification Checklist

After all tasks complete, check http://localhost:8000:

- [ ] Nav "Sign in" button is pale pink (`#FAD6E0`) with dark text; hover turns it `#EF7598` with white text
- [ ] Desktop nav links are visibly larger (`text-base`); mobile menu unchanged
- [ ] Solutions background is light `#F7F6F4` (eggshell — unchanged, already correct)
- [ ] Solutions panels are dark navy gradient (same as footer and "How Fyn can help you"), with white text and coloured icon squares
- [ ] Footer columns have noticeably more top padding (more space between stats bar and footer content)
- [ ] "View demo" button in solutions is spring green
- [ ] "Sign up" button on "Map your path" section is medium blue (`#6C83BC`)
- [ ] "View demos >" link in "How Fyn can help you" section is white and visible
- [ ] White gap between hero and "Map your path" section is significantly reduced on desktop
- [ ] "Meet Fyn" heading sits slightly closer to the top of the hero on all screen sizes
- [ ] All other sections unaffected; header logo and auth pages unchanged
