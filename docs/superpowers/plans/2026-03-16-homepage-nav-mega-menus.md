# Homepage Nav Mega Menus Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace flat nav links with mega menus for Solutions and Features, a dropdown for Learning Centre, and swap the financial freedom image for the Fyn Brain PNG.

**Architecture:** All changes are confined to two files — `PublicLayout.vue` (nav mega menus + dropdowns) and `LandingPage.vue` (image swap). No new components; everything is self-contained Vue template + scoped CSS within those files.

**Tech Stack:** Vue 3 Options API, Tailwind CSS (Fynla design tokens), `<style scoped>`

---

## File Map

| File | Change |
|------|--------|
| `resources/js/layouts/PublicLayout.vue` | Add mega menus for Solutions & Features, dropdown for Learning Centre (desktop + mobile expansions) |
| `resources/js/views/Public/LandingPage.vue` | Swap `financial-freedom-wheel.png` → `/images/Fyn/202603-FynlaBrain.png` |

---

## Task 1: Swap Financial Freedom Image

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:84-90`

- [ ] **Step 1: Replace the image src and alt**

In `LandingPage.vue`, find the financial freedom image block (around line 84–90) and replace:

```html
<!-- BEFORE -->
<div class="mt-10 lg:mt-0 lg:w-[28rem] flex items-center justify-center">
  <img
    src="/images/financial-freedom-wheel.png"
    alt="Financial Freedom - Optimise your Tax, Invest Wisely, Plan for Retirement, Establish Passive Income, Reduce Debt, Build Savings"
    class="w-full max-w-md opacity-60"
  />
</div>

<!-- AFTER -->
<div class="mt-10 lg:mt-0 lg:w-[28rem] flex items-center justify-center">
  <img
    src="/images/Fyn/202603-FynlaBrain.png"
    alt="Fynla Brain — your financial planning intelligence"
    class="w-full max-w-md"
  />
</div>
```

Note: Remove `opacity-60` — the brain graphic is designed to render at full opacity.

- [ ] **Step 2: Verify visually**

Open http://localhost:8000 → scroll to "Map your path to financial freedom" section. Brain PNG should appear instead of the wheel, full opacity, same width.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: replace financial freedom wheel with Fyn Brain image"
```

---

## Task 2: Solutions Mega Menu (Desktop)

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:21-32` (Solutions nav link → mega menu trigger)

The 5 solutions from `LandingPage.vue` are:
| Name | Colour | Description |
|------|--------|-------------|
| Fynla Investor | horizon-500 | Portfolio analysis, risk profiling, and investment strategy tools |
| Fynla Life | raspberry-500 | Protection, critical illness, and income cover analysis for your family |
| Fynla Manager | spring-500 | Net worth tracking, savings goals, and financial oversight tools |
| Fynla Planner | violet-500 | Retirement projections, pension tracking, and estate planning |
| Fynla Saver | savannah-500 | Emergency funds, ISA allowances, and savings goal tracking |

- [ ] **Step 1: Add `solutionsOpen` data property**

In the `data()` return object of `PublicLayout.vue`, add:
```js
solutionsOpen: false,
featuresOpen: false,
learningOpen: false,
```

- [ ] **Step 2: Replace the Solutions `<a>` with a mega menu trigger + panel**

Replace the flat `<a href="/#solutions">Solutions</a>` link in the desktop nav with:

```html
<!-- Solutions Mega Menu -->
<div class="relative" @mouseenter="solutionsOpen = true" @mouseleave="solutionsOpen = false">
  <button
    type="button"
    class="inline-flex items-center gap-1 px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
    :class="{ 'text-raspberry-500': solutionsOpen }"
  >
    Solutions
    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': solutionsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>

  <!-- Mega Menu Panel -->
  <div
    v-if="solutionsOpen"
    class="absolute left-1/2 -translate-x-1/2 top-full mt-2 w-[600px] bg-white rounded-xl shadow-lg border border-light-gray z-50 p-6"
  >
    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-4">Choose your plan</p>
    <div class="grid grid-cols-1 gap-3">

      <!-- Investor -->
      <a href="/#solutions" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-lg bg-horizon-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Fynla Investor</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Portfolio analysis, risk profiling, and investment strategy tools.</p>
        </div>
      </a>

      <!-- Life -->
      <a href="/#solutions" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-lg bg-raspberry-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Fynla Life</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Protection, critical illness, and income cover analysis for your family.</p>
        </div>
      </a>

      <!-- Manager -->
      <a href="/#solutions" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-lg bg-spring-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Fynla Manager</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Net worth tracking, savings goals, and financial oversight tools.</p>
        </div>
      </a>

      <!-- Planner -->
      <a href="/#solutions" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-lg bg-violet-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Fynla Planner</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Retirement projections, pension tracking, and estate planning.</p>
        </div>
      </a>

      <!-- Saver -->
      <a href="/#solutions" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-lg bg-savannah-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Fynla Saver</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Emergency funds, ISA allowances, and savings goal tracking.</p>
        </div>
      </a>

    </div>
  </div>
</div>
```

- [ ] **Step 3: Verify desktop hover**

Open http://localhost:8000 (desktop width). Hover over "Solutions" — mega panel drops down with 5 items. Mouse leave closes it.

---

## Task 3: Features Mega Menu (Desktop)

The 6 feature areas from the "How Fyn can help you" section:

| Name | Colour | Description |
|------|--------|-------------|
| Protection | raspberry-500 | Analyse life insurance, critical illness, and income protection coverage gaps |
| Savings | spring-500 | Track emergency funds, ISA allowances, and savings goals across all your accounts |
| Investment | violet-500 | Portfolio analysis, risk profiling, and Monte Carlo projections |
| Retirement | light-blue-500 | Defined Contribution, Defined Benefit, and State Pension tracking |
| Estate | savannah-500 | Inheritance Tax calculations, gifting strategies, and estate value projections |
| Net Worth | horizon-400 | Complete balance sheet with properties, assets, and liabilities tracking |

- [ ] **Step 1: Replace the Features `<a>` with a mega menu trigger + panel**

Replace the flat `<a href="/#features">Features</a>` link with:

```html
<!-- Features Mega Menu -->
<div class="relative" @mouseenter="featuresOpen = true" @mouseleave="featuresOpen = false">
  <button
    type="button"
    class="inline-flex items-center gap-1 px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
    :class="{ 'text-raspberry-500': featuresOpen }"
  >
    Features
    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': featuresOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>

  <!-- Mega Menu Panel -->
  <div
    v-if="featuresOpen"
    class="absolute left-1/2 -translate-x-1/2 top-full mt-2 w-[680px] bg-white rounded-xl shadow-lg border border-light-gray z-50 p-6"
  >
    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-4">How Fyn can help you</p>
    <div class="grid grid-cols-2 gap-3">

      <!-- Protection -->
      <a href="/#features" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-full bg-raspberry-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Protection</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Analyse life insurance, critical illness, and income protection coverage gaps.</p>
        </div>
      </a>

      <!-- Savings -->
      <a href="/#features" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-full bg-spring-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Savings</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Track emergency funds, ISA allowances, and savings goals across all your accounts.</p>
        </div>
      </a>

      <!-- Investment -->
      <a href="/#features" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-full bg-violet-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Investment</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your strategy.</p>
        </div>
      </a>

      <!-- Retirement -->
      <a href="/#features" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-full bg-light-blue-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Retirement</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Defined Contribution, Defined Benefit, and State Pension tracking with income projections.</p>
        </div>
      </a>

      <!-- Estate -->
      <a href="/#features" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-full bg-savannah-500 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Estate</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Inheritance Tax calculations, gifting strategies, and estate value projections.</p>
        </div>
      </a>

      <!-- Net Worth -->
      <a href="/#features" class="flex items-start gap-4 p-3 rounded-lg hover:bg-savannah-100 transition-colors group">
        <div class="w-9 h-9 rounded-full bg-horizon-400 flex-shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div>
          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Net Worth</p>
          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Complete balance sheet with properties, assets, and liabilities for a clear financial picture.</p>
        </div>
      </a>

    </div>
  </div>
</div>
```

- [ ] **Step 2: Verify desktop hover**

Open http://localhost:8000. Hover "Features" — 6-item 2-column mega panel appears. Mouse leave closes it.

---

## Task 4: Learning Centre Dropdown (Desktop)

- [ ] **Step 1: Replace the Learning Centre `<router-link>` with a dropdown trigger + panel**

Replace the flat `<router-link to="/learning-centre">Learning centre</router-link>` with:

```html
<!-- Learning Centre Dropdown -->
<div class="relative" @mouseenter="learningOpen = true" @mouseleave="learningOpen = false">
  <button
    type="button"
    class="inline-flex items-center gap-1 px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
    :class="{ 'text-raspberry-500': learningOpen }"
  >
    Learning centre
    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': learningOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>

  <!-- Dropdown Panel -->
  <div
    v-if="learningOpen"
    class="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-light-gray z-50 py-2"
  >
    <router-link
      to="/calculators"
      class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-500 hover:bg-savannah-100 hover:text-raspberry-500 transition-colors"
      @click="learningOpen = false"
    >
      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 7h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8a1 1 0 011-1z" />
      </svg>
      Calculators
    </router-link>
    <router-link
      to="/learning-centre"
      class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-500 hover:bg-savannah-100 hover:text-raspberry-500 transition-colors"
      @click="learningOpen = false"
    >
      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
      </svg>
      Resources
    </router-link>
  </div>
</div>
```

- [ ] **Step 2: Verify desktop hover**

Hover "Learning centre" — compact 2-item dropdown (Calculators + Resources). Mouse leave closes it.

---

## Task 5: Mobile Menu Expansions

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:74-119` (mobile menu section)

The mobile menu currently has flat links. Replace the three affected links with accordion-style toggles.

- [ ] **Step 1: Replace mobile Features link with accordion**

```html
<!-- Mobile: Features accordion -->
<div>
  <button
    type="button"
    class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
    @click="featuresOpen = !featuresOpen"
  >
    Features
    <svg class="w-4 h-4" :class="{ 'rotate-180': featuresOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>
  <div v-if="featuresOpen" class="pl-6 pb-1 space-y-0.5">
    <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Protection</a>
    <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Savings</a>
    <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Investment</a>
    <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Retirement</a>
    <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Estate</a>
    <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Net Worth</a>
  </div>
</div>
```

- [ ] **Step 2: Replace mobile Solutions link with accordion**

```html
<!-- Mobile: Solutions accordion -->
<div>
  <button
    type="button"
    class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
    @click="solutionsOpen = !solutionsOpen"
  >
    Solutions
    <svg class="w-4 h-4" :class="{ 'rotate-180': solutionsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>
  <div v-if="solutionsOpen" class="pl-6 pb-1 space-y-0.5">
    <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Fynla Investor</a>
    <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Fynla Life</a>
    <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Fynla Manager</a>
    <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Fynla Planner</a>
    <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Fynla Saver</a>
  </div>
</div>
```

- [ ] **Step 3: Replace mobile Learning Centre link with accordion**

```html
<!-- Mobile: Learning Centre accordion -->
<div>
  <button
    type="button"
    class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
    @click="learningOpen = !learningOpen"
  >
    Learning centre
    <svg class="w-4 h-4" :class="{ 'rotate-180': learningOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </button>
  <div v-if="learningOpen" class="pl-6 pb-1 space-y-0.5">
    <router-link to="/calculators" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learningOpen = false">Calculators</router-link>
    <router-link to="/learning-centre" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learningOpen = false">Resources</router-link>
  </div>
</div>
```

- [ ] **Step 4: Verify mobile at narrow viewport**

Resize browser to mobile width. Open hamburger menu — Features, Solutions, and Learning centre each expand on tap to show their sub-items. Tapping a sub-item closes the whole menu.

- [ ] **Step 5: Commit all nav changes**

```bash
git add resources/js/layouts/PublicLayout.vue
git commit -m "feat: add mega menus for Solutions/Features, dropdown for Learning Centre"
```

---

## Final Verification

- [ ] Desktop: All three menus open on hover, close on mouse-leave, close when a link is clicked (page scroll occurs)
- [ ] Mobile: All three accordions toggle on tap, sub-item tap closes menu
- [ ] No amber/orange colours used anywhere — only palette tokens
- [ ] `rotate-180` chevron animates correctly on open/close
- [ ] No `text-raspberry-500` active class on plain `<a>` anchor tags (only on buttons/router-links)
