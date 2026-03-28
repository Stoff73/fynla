# Calculator Page Redesign — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the tab-button calculator selector with a grouped card list organised by life stage, add 7 gated planning tool preview cards (5 free-account, 2 paid-plan).

**Architecture:** Create a reusable `CalculatorCard` component. Restructure the CalculatorsPage hero (new gradient) and replace the button grid with stage-grouped card sections. Existing calculator panels remain unchanged below the card list.

**Tech Stack:** Vue 3 Options API, Tailwind CSS, Vue Router, Vuex (for auth check)

**Spec:** `docs/superpowers/specs/2026-03-24-calculator-page-redesign.md`

---

### Task 1: Create CalculatorCard component

**Files:**
- Create: `resources/js/components/Public/CalculatorCard.vue`

- [ ] **Step 1: Create the component file**

```vue
<template>
  <!-- Free calculator: button that emits select -->
  <button
    v-if="type === 'free'"
    class="w-full flex items-center gap-4 bg-white rounded-lg border border-light-gray p-4 text-left transition-all duration-200 hover:shadow-sm hover:border-raspberry-400 group"
    :class="{ 'border-raspberry-500 shadow-sm': active }"
    @click="$emit('select', calculatorId)"
  >
    <div class="w-10 h-10 rounded-lg bg-savannah-50 flex items-center justify-center text-lg flex-shrink-0">
      {{ icon }}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ name }}</p>
      <p class="text-xs text-neutral-500 mt-0.5">{{ description }}</p>
    </div>
    <svg class="w-4 h-4 text-neutral-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
    </svg>
  </button>

  <!-- Gated free-account: link to /register -->
  <router-link
    v-else-if="type === 'gated-free'"
    to="/register"
    class="w-full flex items-center gap-4 bg-white rounded-lg border border-light-gray p-4 text-left transition-all duration-200 hover:shadow-sm hover:border-light-blue-500 group"
  >
    <div class="w-10 h-10 rounded-lg bg-savannah-50 flex items-center justify-center text-lg flex-shrink-0">
      {{ icon }}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-horizon-500 group-hover:text-light-blue-500 transition-colors">{{ name }}</p>
      <p class="text-xs text-neutral-500 mt-0.5">{{ description }}</p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-light-blue-100 text-light-blue-600 text-xs font-medium">Free account</span>
      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
    </div>
  </router-link>

  <!-- Gated paid-plan: link to /pricing -->
  <router-link
    v-else-if="type === 'gated-paid'"
    to="/pricing"
    class="w-full flex items-center gap-4 bg-white/80 rounded-lg border border-light-gray p-4 text-left transition-all duration-200 hover:shadow-sm hover:border-violet-400 group"
  >
    <div class="w-10 h-10 rounded-lg bg-neutral-100 flex items-center justify-center text-lg flex-shrink-0 opacity-60">
      {{ icon }}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-neutral-400 group-hover:text-violet-500 transition-colors">{{ name }}</p>
      <p class="text-xs text-neutral-400 mt-0.5">{{ description }}</p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-violet-100 text-violet-600 text-xs font-medium">Paid plan</span>
      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
    </div>
  </router-link>
</template>

<script>
export default {
  name: 'CalculatorCard',
  props: {
    name: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: String, required: true },
    type: { type: String, default: 'free', validator: v => ['free', 'gated-free', 'gated-paid'].includes(v) },
    calculatorId: { type: String, default: null },
    active: { type: Boolean, default: false },
  },
  emits: ['select'],
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Public/CalculatorCard.vue
git commit -m "feat: create CalculatorCard component for grouped calculator list"
```

---

### Task 2: Define calculator and stage data

**Files:**
- Modify: `resources/js/views/Public/CalculatorsPage.vue` — data section

- [ ] **Step 1: Add `calculatorStages` data array**

Add this to the `data()` return object (alongside existing `calculators` array — the old `calculators` array stays for now as the calculator panels still reference it):

```javascript
calculatorStages: [
  {
    name: 'Starting Out',
    colour: '#1D9E75',
    items: [
      { id: 'student-loan', name: 'Student Loan Repayment', description: 'Estimate repayments, write-off date, and total cost by plan type', icon: '🎓', type: 'free' },
      { id: 'savings-goal', name: 'Savings Goal', description: 'See how long it takes to reach your savings target with compound interest', icon: '✨', type: 'free' },
      { id: 'emergency-fund', name: 'Emergency Fund', description: 'Calculate how many months of expenses you have covered', icon: '🛡', type: 'free' },
    ],
  },
  {
    name: 'Building Foundations',
    colour: '#5DCAA5',
    items: [
      { id: 'mortgage', name: 'Mortgage Repayment', description: 'Monthly payments, total interest, and amortisation breakdown', icon: '🏠', type: 'free' },
      { id: 'mortgage-afford', name: 'Mortgage Affordability', description: 'How much could you borrow based on your income?', icon: '🔑', type: 'free' },
      { id: 'stamp-duty', name: 'Stamp Duty', description: 'SDLT, LBTT, or LTT breakdown for England, Scotland, or Wales', icon: '📜', type: 'free' },
      { id: 'personal-loan', name: 'Personal Loan', description: 'Monthly repayments and total cost of borrowing', icon: '💷', type: 'free' },
      { id: 'compound-interest', name: 'Compound Interest', description: 'See how your money grows over time with compound returns', icon: '📈', type: 'free' },
    ],
  },
  {
    name: 'Protecting and Growing',
    colour: '#378ADD',
    items: [
      { id: 'life-insurance', name: 'Life Insurance Needs', description: 'How much cover does my family actually need?', icon: '🛡️', type: 'gated-free' },
      { id: 'income-protection', name: 'Income Protection', description: 'What would I need if I couldn\'t work?', icon: '☂️', type: 'gated-free' },
    ],
  },
  {
    name: 'Planning Your Future',
    colour: '#7F77DD',
    items: [
      { id: 'income-tax', name: 'Income Tax', description: 'Calculate your UK income tax and National Insurance for 2025/26', icon: '🧮', type: 'free' },
      { id: 'pension', name: 'Pension Growth', description: 'Project your pension pot value at retirement', icon: '📊', type: 'free' },
      { id: 'pension-relief', name: 'Pension Tax Relief', description: 'See how much tax relief you get on pension contributions', icon: '🧾', type: 'free' },
      { id: 'salary-sacrifice', name: 'Salary Sacrifice', description: 'Take-home pay vs pension boost — what\'s the real trade-off?', icon: '⚖️', type: 'gated-free' },
      { id: 'retirement-budget', name: 'Retirement Budget Planner', description: 'Detailed income vs spending plan for retirement', icon: '📋', type: 'gated-paid' },
    ],
  },
  {
    name: 'Enjoying Your Wealth',
    colour: '#EF9F27',
    items: [
      { id: 'pension-withdrawal', name: 'Pension Withdrawal Tax', description: 'See how much tax you\'ll pay when withdrawing from your pension', icon: '💰', type: 'free' },
      { id: 'iht-checker', name: 'Inheritance Tax Exposure Checker', description: 'Estimated inheritance tax liability with full breakdown', icon: '🏛️', type: 'gated-free' },
      { id: 'drawdown-runway', name: 'Pension Drawdown / Runway', description: 'How long will my pension pot last?', icon: '⏳', type: 'gated-free' },
      { id: 'annuity-vs-drawdown', name: 'Annuity vs Drawdown Comparison', description: 'Which gives you more over your lifetime?', icon: '⚖️', type: 'gated-paid' },
    ],
  },
],
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Public/CalculatorsPage.vue
git commit -m "feat: add calculatorStages data for grouped card layout"
```

---

### Task 3: Replace hero and tab buttons with grouped card list

**Files:**
- Modify: `resources/js/views/Public/CalculatorsPage.vue` — template section (lines 1–95 approx)

- [ ] **Step 1: Replace the hero section and tab buttons**

Replace everything from `<!-- Hero Section -->` through the closing `</div>` of the hero (the dark gradient section containing the tab buttons) with:

```html
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">
          Financial Calculators &amp; Planning Tools
        </h1>
        <p class="text-base text-white/80 max-w-2xl mx-auto">
          Free tools to help you understand your finances. Planning tools require a free Fynla account.
        </p>
      </div>
    </div>

    <!-- Grouped Calculator Cards -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div v-for="stage in calculatorStages" :key="stage.name" class="mb-8 last:mb-0">
        <!-- Stage header -->
        <div class="flex items-center gap-2 mb-3">
          <div class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: stage.colour }"></div>
          <h2 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">{{ stage.name }}</h2>
        </div>
        <!-- Cards -->
        <div class="space-y-2">
          <CalculatorCard
            v-for="item in stage.items"
            :key="item.id"
            :name="item.name"
            :description="item.description"
            :icon="item.icon"
            :type="item.type"
            :calculator-id="item.id"
            :active="activeCalculator === item.id"
            @select="selectCalculator"
          />
        </div>
      </div>
    </div>
```

- [ ] **Step 2: Add `selectCalculator` method**

Add to the `methods` object:

```javascript
selectCalculator(id) {
  this.activeCalculator = id;
  this.$nextTick(() => {
    const el = document.getElementById('calculator-panel');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
},
```

- [ ] **Step 3: Wrap existing calculator panels with an anchor div**

Find `<!-- Calculator Content -->` (or the `<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">` that wraps all the `v-if="activeCalculator === '...'"` panels) and add `id="calculator-panel"` to it:

```html
<div id="calculator-panel" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
```

- [ ] **Step 4: Import CalculatorCard component**

In the `<script>` section, add the import and register it:

```javascript
import CalculatorCard from '@/components/Public/CalculatorCard.vue';
```

Add to `components`:
```javascript
components: {
  PublicLayout,
  CalculatorCard,
},
```

- [ ] **Step 5: Remove old SVG icon block**

Delete the entire block of `v-if="calc.id === 'income-tax'"` through all the `v-else-if` SVG icons (approximately lines 49-92). These are no longer needed since the cards use emoji icons.

- [ ] **Step 6: Commit**

```bash
git add resources/js/views/Public/CalculatorsPage.vue
git commit -m "feat: replace calculator tab buttons with grouped card list"
```

---

### Task 4: Clean up — remove old calculators array references

**Files:**
- Modify: `resources/js/views/Public/CalculatorsPage.vue`

- [ ] **Step 1: Remove the old `calculators` array from data**

Delete the `calculators: [...]` array from `data()`. The `calculatorStages` array now drives the UI. The `activeCalculator` property stays.

- [ ] **Step 2: Verify no template code references the old `calculators` array**

Search for `v-for="calc in calculators"` — if still present from the old tab buttons, remove that entire block (should already be gone from Task 3).

- [ ] **Step 3: Test in browser**

Run: Open http://localhost:8000/calculators
Expected:
- Hero shows navy-to-raspberry gradient with headline
- Below: 5 stage groups with coloured dot headers
- Free calculators: white cards with emoji, name, description, arrow
- Gated free-account cards: same style + "Free account" badge + lock icon, links to /register
- Gated paid cards: slightly muted, "Paid plan" badge, links to /pricing
- Clicking a free calculator card scrolls to and displays the calculator panel below

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Public/CalculatorsPage.vue
git commit -m "refactor: remove old calculator tab data, clean up references"
```

---

### Task 5: Final review and commit

- [ ] **Step 1: Verify all 13 free calculators still work**

Click each free calculator card and confirm it loads the correct calculator panel.

- [ ] **Step 2: Verify all 5 gated-free cards link to /register**

Click each and confirm navigation to /register.

- [ ] **Step 3: Verify both gated-paid cards link to /pricing**

Click each and confirm navigation to /pricing.

- [ ] **Step 4: Test mobile menu**

Resize browser to mobile width. Confirm the card list stacks correctly and is tappable.

- [ ] **Step 5: Final commit if any cleanup needed**

```bash
git add -A
git commit -m "feat: calculator page redesign — grouped cards with gated planning tools"
```
