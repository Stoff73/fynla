# Hero & White Section Content Swap Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Swap content between the hero (gradient) and the white section below. Hero gets the "Map your path to financial freedom" content + Fyn Brain image. White section gets the Fyn character image + "Meet Fyn" text + Ask Fyn input.

**Architecture:** Two template blocks in `LandingPage.vue` are rewritten in place. No new components. Colours adjusted where needed — hero text goes white-on-gradient, white section text stays dark-on-white.

**Tech Stack:** Vue 3, Tailwind CSS (Fynla design tokens)

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 4–38 | Hero: replace with Map Your Path content + Fyn Brain image |
| `resources/js/views/Public/LandingPage.vue` | 41–93 | White section: replace with Fyn character + Meet Fyn text + Ask Fyn input |

---

## Before → After Layout

```
BEFORE                              AFTER
──────────────────────────────      ──────────────────────────────
[GRADIENT]                          [GRADIENT]
  Fyn character (left)                Map your path to... (left)
  Meet Fyn text + input (right)       Fyn Brain image (right)
──────────────────────────────      ──────────────────────────────
[WHITE]                             [WHITE]
  Map your path text (left)           Fyn character (left)
  Fyn Brain image (right)             Meet Fyn text + input (right)
──────────────────────────────      ──────────────────────────────
```

---

## Task 1: Rewrite Hero Section (Gradient)

Replace lines 4–38 with the financial freedom content, colours adjusted for dark background:
- Heading: `text-white`
- "financial freedom" highlight: `text-spring-400` (raspberry-on-raspberry is invisible)
- Body paragraph: `text-white/80`
- Feature indicator labels: `text-white/70`
- Net Worth dot: `bg-horizon-300` (horizon-500 would vanish on horizon gradient)
- Demo link: `text-white/90`, hover `text-spring-400`

- [ ] **Step 1: Replace the hero section block**

Replace:
```html
<!-- Hero Section -->
<div class="bg-gradient-to-r from-horizon-500 to-raspberry-500">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-12">
    <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">
      <!-- Left: Fyn character placeholder -->
      <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-center">
        <img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto lg:mt-5 lg:-mb-24" />
      </div>

      <!-- Right: Content -->
      <div class="flex-1">
        <h1 class="text-6xl lg:text-8xl font-bold text-white leading-none mb-2 mt-5">Meet Fyn</h1>
        <p class="text-2xl md:text-3xl lg:text-4xl font-semibold text-white/90 mb-3">
          Your financial companion for life
        </p>
        <p class="text-sm text-white/70 mb-6 max-w-xl leading-relaxed">
          Fyn will help you meet your financial goals by giving you clarity of your finances from planning to saving and investments, through to your net worth and real estate
        </p>

        <!-- Ask Fyn input -->
        <div class="flex flex-col sm:flex-row gap-3 max-w-xl">
          <input
            v-model="chatInput"
            type="text"
            placeholder="Enter your text here"
            class="input-field flex-1 !py-3"
            @keyup.enter="handleAskFyn"
          />
          <button type="button" @click="handleAskFyn" class="px-8 py-3 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap">
            Ask Fyn
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
```

With:
```html
<!-- Hero Section -->
<div class="bg-gradient-to-r from-horizon-500 to-raspberry-500">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-12">
    <div class="flex flex-col lg:flex-row lg:items-center lg:gap-16">

      <!-- Left: Financial Freedom content -->
      <div class="lg:flex-1">
        <h2 class="text-5xl leading-tight mb-6 text-white">
          Map your path to<br />
          <span class="text-spring-400">financial freedom</span>
        </h2>
        <p class="text-white/80 mb-6 max-w-lg leading-relaxed">
          We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;, which will leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
        </p>

        <!-- Feature indicators -->
        <div class="flex flex-wrap gap-3 mb-8">
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
            <span class="w-2.5 h-2.5 rounded-full bg-raspberry-500"></span> Protection
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
            <span class="w-2.5 h-2.5 rounded-full bg-spring-500"></span> Savings
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
            <span class="w-2.5 h-2.5 rounded-full bg-violet-500"></span> Investment
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
            <span class="w-2.5 h-2.5 rounded-full bg-light-blue-500"></span> Retirement
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
            <span class="w-2.5 h-2.5 rounded-full bg-savannah-500"></span> Estate
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white/70">
            <span class="w-2.5 h-2.5 rounded-full bg-horizon-300"></span> Net Worth
          </span>
        </div>

        <div class="flex flex-col items-start gap-4">
          <router-link to="/register" class="px-8 py-2.5 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-all">Sign up</router-link>
          <p class="text-sm text-white/70">
            Not convinced yet, check out
            <a href="/?demo=true" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="enterPreviewMode">our demo</a>
          </p>
        </div>
      </div>

      <!-- Right: Fyn Brain image -->
      <div class="mt-10 lg:mt-0 lg:w-[28rem] flex items-center justify-center">
        <img
          src="/images/Fyn/202603-FynlaBrain.png"
          alt="Fynla Brain — your financial planning intelligence"
          class="w-full max-w-md"
        />
      </div>

    </div>
  </div>
</div>
```

---

## Task 2: Rewrite White Section (Meet Fyn content)

Replace lines 41–93 with the Fyn character image and Meet Fyn text/input. Colours remain dark-on-white as before.

- [ ] **Step 1: Replace the white section block**

Replace:
```html
<!-- Map Your Path to Financial Freedom -->
<div class="bg-white pt-12 pb-6">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:gap-16">
      <div class="lg:flex-1">
        <h2 class="text-5xl leading-tight mb-6">
          Map your path to<br />
          <span class="text-raspberry-500">financial freedom</span>
        </h2>
        <p class="text-neutral-500 mb-6 max-w-lg leading-relaxed">
          We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;, which will leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
        </p>

        <!-- Feature indicators -->
        <div class="flex flex-wrap gap-3 mb-8">
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
            <span class="w-2.5 h-2.5 rounded-full bg-raspberry-500"></span> Protection
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
            <span class="w-2.5 h-2.5 rounded-full bg-spring-500"></span> Savings
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
            <span class="w-2.5 h-2.5 rounded-full bg-violet-500"></span> Investment
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
            <span class="w-2.5 h-2.5 rounded-full bg-light-blue-500"></span> Retirement
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
            <span class="w-2.5 h-2.5 rounded-full bg-savannah-500"></span> Estate
          </span>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
            <span class="w-2.5 h-2.5 rounded-full bg-horizon-500"></span> Net Worth
          </span>
        </div>

        <div class="flex flex-col items-start gap-4">
          <router-link to="/register" class="px-8 py-2.5 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-all">Sign up</router-link>
          <p class="text-sm text-neutral-500">
            Not convinced yet, check out
            <a href="/?demo=true" class="text-raspberry-500 no-underline hover:text-light-pink-400 transition-colors" @click.prevent="enterPreviewMode">our demo</a>
          </p>
        </div>
      </div>

      <div class="mt-10 lg:mt-0 lg:w-[28rem] flex items-center justify-center">
        <img
          src="/images/Fyn/202603-FynlaBrain.png"
          alt="Fynla Brain — your financial planning intelligence"
          class="w-full max-w-md"
        />
      </div>
    </div>
  </div>
</div>
```

With:
```html
<!-- Meet Fyn Section -->
<div class="bg-white py-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">

      <!-- Left: Fyn character -->
      <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-center">
        <img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto" />
      </div>

      <!-- Right: Meet Fyn text + Ask Fyn input -->
      <div class="flex-1">
        <h1 class="text-6xl lg:text-8xl font-bold text-horizon-500 leading-none mb-2">Meet Fyn</h1>
        <p class="text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-3">
          Your financial companion for life
        </p>
        <p class="text-sm text-neutral-500 mb-6 max-w-xl leading-relaxed">
          Fyn will help you meet your financial goals by giving you clarity of your finances from planning to saving and investments, through to your net worth and real estate
        </p>

        <!-- Ask Fyn input -->
        <div class="flex flex-col sm:flex-row gap-3 max-w-xl">
          <input
            v-model="chatInput"
            type="text"
            placeholder="Enter your text here"
            class="input-field flex-1 !py-3"
            @keyup.enter="handleAskFyn"
          />
          <button type="button" @click="handleAskFyn" class="px-8 py-3 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap">
            Ask Fyn
          </button>
        </div>
      </div>

    </div>
  </div>
</div>
```

- [ ] **Step 2: Verify**

Refresh http://localhost:8000:
- Hero gradient: "Map your path to financial freedom" heading in white, "financial freedom" in spring-400, Fyn Brain on the right, feature dots visible, Sign up + demo link present
- White section below: Fyn character on the left, "Meet Fyn" heading in horizon-500, tagline + description in neutral-500, Ask Fyn input + button

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "feat: swap hero and white section content — financial freedom in gradient, Meet Fyn in white"
```
