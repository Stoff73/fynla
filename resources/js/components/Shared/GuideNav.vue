<template>
  <div>
    <!-- Intro paragraph -->
    <section class="py-6 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-600 leading-relaxed">
          Whether you're trying to understand what a pension drawdown is, deciding if you should overpay your mortgage, or checking this year's tax allowances &mdash; we've written a guide for it. Everything here is free, jargon-free, and written for real people, not finance professionals.
        </p>
      </div>
    </section>

    <!-- Category tabs + links -->
    <section class="bg-eggshell-500 border-b border-light-gray">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Category tabs -->
      <div class="flex gap-1 border-b-2 border-light-gray pt-3 overflow-x-auto scrollbar-hide">
        <button
          v-for="cat in categories"
          :key="cat.key"
          class="category-tab whitespace-nowrap"
          :class="{ active: activeCategory === cat.key }"
          @click="activeCategory = activeCategory === cat.key && cat.key !== currentCategory ? 'all' : cat.key"
        >
          <span v-if="cat.color" class="inline-block w-2 h-2 rounded-full mr-1.5 align-middle" :style="{ background: cat.color }"></span>
          {{ cat.label }}
        </button>
      </div>

      <!-- Guide links -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 py-4">
        <router-link
          v-for="link in filteredGuides"
          :key="link.to"
          :to="link.to"
          class="guide-nav-link"
          :class="{ 'active-link': currentPath === link.to }"
        >
          <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="{ background: currentPath === link.to ? 'white' : link.dotColor }"></span>
          {{ link.title }}
        </router-link>
      </div>
    </div>
  </section>
  </div>
</template>

<script>
export default {
  name: 'GuideNav',

  data() {
    return {
      activeCategory: 'all',

      categories: [
        { key: 'all', label: 'All', color: null },
        { key: 'explainers', label: 'Key Terms', color: '#E8326E' },
        { key: 'decisions', label: 'Decision Support', color: '#20B486' },
        { key: 'stages', label: 'Personal Journey Guides', color: '#5854E6' },
        { key: 'tax', label: 'Tax & Allowances', color: '#E6C9A8' },
      ],

      allGuides: [
        { title: 'What is an ISA?', to: '/learn/what-is-an-isa', category: 'explainers', dotColor: '#E8326E' },
        { title: 'What is Drawdown?', to: '/learn/what-is-drawdown', category: 'explainers', dotColor: '#E8326E' },
        { title: 'What is Salary Sacrifice?', to: '/learn/what-is-salary-sacrifice', category: 'explainers', dotColor: '#E8326E' },
        { title: 'What is a Lasting Power of Attorney?', to: '/learn/what-is-an-lpa', category: 'explainers', dotColor: '#E8326E' },
        { title: 'What is a Self-Invested Personal Pension?', to: '/learn/what-is-a-sipp', category: 'explainers', dotColor: '#E8326E' },
        { title: 'What is Inheritance Tax?', to: '/learn/what-is-inheritance-tax', category: 'explainers', dotColor: '#E8326E' },
        { title: 'What is Financial Planning?', to: '/learning-centre', category: 'explainers', dotColor: '#E8326E' },
        { title: 'Should I overpay my mortgage?', to: '/learn/should-i-overpay-my-mortgage', category: 'decisions', dotColor: '#20B486' },
        { title: 'Should I consolidate my pensions?', to: '/learn/should-i-consolidate-pensions', category: 'decisions', dotColor: '#20B486' },
        { title: 'When should I make a will?', to: '/learn/when-should-i-make-a-will', category: 'decisions', dotColor: '#20B486' },
        { title: 'Lifetime ISA or ISA?', to: '/learn/should-i-use-a-lisa-or-isa', category: 'decisions', dotColor: '#20B486' },
        { title: 'When can I afford to retire?', to: '/learn/when-can-i-afford-to-retire', category: 'decisions', dotColor: '#20B486' },
        { title: 'Starting Out: money basics', to: '/learn/guide/starting-out', category: 'stages', dotColor: '#5854E6' },
        { title: 'Building Foundations: first home', to: '/learn/guide/building-foundations', category: 'stages', dotColor: '#5854E6' },
        { title: 'Protecting and Growing: family finances', to: '/learn/guide/protecting-and-growing', category: 'stages', dotColor: '#5854E6' },
        { title: 'Planning Your Future: retirement', to: '/learn/guide/planning-your-future', category: 'stages', dotColor: '#5854E6' },
        { title: 'Enjoying Your Wealth: estate planning', to: '/learn/guide/enjoying-your-wealth', category: 'stages', dotColor: '#5854E6' },
        { title: 'ISA allowance guide', to: '/learn/tax/isa-allowance', category: 'tax', dotColor: '#E6C9A8' },
        { title: 'Pension annual allowance', to: '/learn/tax/pension-annual-allowance', category: 'tax', dotColor: '#E6C9A8' },
        { title: 'Inheritance Tax thresholds', to: '/learn/tax/iht-thresholds', category: 'tax', dotColor: '#E6C9A8' },
        { title: 'Capital gains tax rates', to: '/learn/tax/capital-gains-tax', category: 'tax', dotColor: '#E6C9A8' },
        { title: 'Tax year checklist', to: '/learn/tax/tax-year-checklist', category: 'tax', dotColor: '#E6C9A8' },
      ],
    };
  },

  computed: {
    currentPath() {
      return this.$route.path;
    },

    currentCategory() {
      const guide = this.allGuides.find(g => g.to === this.currentPath);
      return guide ? guide.category : 'all';
    },

    filteredGuides() {
      if (this.activeCategory === 'all') return this.allGuides;
      return this.allGuides.filter(g => g.category === this.activeCategory);
    },
  },

  created() {
    // Default to the current article's category
    this.activeCategory = this.currentCategory || 'all';
  },
};
</script>

<style scoped>
.category-tab {
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  background: transparent;
  @apply text-neutral-400;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
}

.category-tab:hover {
  @apply text-horizon-500;
}

.category-tab.active {
  @apply text-raspberry-500;
  border-bottom-color: #E8326E;
}

.guide-nav-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  @apply bg-white text-horizon-500;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.2s;
  font-size: 13px;
}

.guide-nav-link:hover {
  @apply bg-light-pink-100 text-raspberry-500;
}

.guide-nav-link.active-link {
  @apply bg-raspberry-500 text-white;
}
</style>
