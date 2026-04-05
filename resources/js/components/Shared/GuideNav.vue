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
import { PRIMARY_COLORS, SUCCESS_COLORS, WARNING_COLORS, CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'GuideNav',

  data() {
    return {
      activeCategory: 'all',

      categories: [
        { key: 'all', label: 'All', color: null },
        { key: 'explainers', label: 'Key Terms', color: PRIMARY_COLORS[500] },
        { key: 'decisions', label: 'Decision Support', color: SUCCESS_COLORS[500] },
        { key: 'stages', label: 'Personal Journey Guides', color: WARNING_COLORS[500] },
        { key: 'tax', label: 'Tax & Allowances', color: CHART_COLORS[4] },
      ],

      allGuides: [
        { title: 'What is an ISA?', to: '/learn/what-is-an-isa', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'What is Drawdown?', to: '/learn/what-is-drawdown', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'What is Salary Sacrifice?', to: '/learn/what-is-salary-sacrifice', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'What is a Lasting Power of Attorney?', to: '/learn/what-is-an-lpa', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'What is a Self-Invested Personal Pension?', to: '/learn/what-is-a-sipp', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'What is Inheritance Tax?', to: '/learn/what-is-inheritance-tax', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'What is Financial Planning?', to: '/learn', category: 'explainers', dotColor: PRIMARY_COLORS[500] },
        { title: 'Should I overpay my mortgage?', to: '/learn/should-i-overpay-my-mortgage', category: 'decisions', dotColor: SUCCESS_COLORS[500] },
        { title: 'Should I consolidate my pensions?', to: '/learn/should-i-consolidate-pensions', category: 'decisions', dotColor: SUCCESS_COLORS[500] },
        { title: 'When should I make a will?', to: '/learn/when-should-i-make-a-will', category: 'decisions', dotColor: SUCCESS_COLORS[500] },
        { title: 'Lifetime ISA or ISA?', to: '/learn/should-i-use-a-lisa-or-isa', category: 'decisions', dotColor: SUCCESS_COLORS[500] },
        { title: 'When can I afford to retire?', to: '/learn/when-can-i-afford-to-retire', category: 'decisions', dotColor: SUCCESS_COLORS[500] },
        { title: 'Starting Out: money basics', to: '/learn/guide/starting-out', category: 'stages', dotColor: WARNING_COLORS[500] },
        { title: 'Building Foundations: first home', to: '/learn/guide/building-foundations', category: 'stages', dotColor: WARNING_COLORS[500] },
        { title: 'Protecting and Growing: family finances', to: '/learn/guide/protecting-and-growing', category: 'stages', dotColor: WARNING_COLORS[500] },
        { title: 'Planning Your Future: retirement', to: '/learn/guide/planning-your-future', category: 'stages', dotColor: WARNING_COLORS[500] },
        { title: 'Enjoying Your Wealth: estate planning', to: '/learn/guide/enjoying-your-wealth', category: 'stages', dotColor: WARNING_COLORS[500] },
        { title: 'ISA allowance guide', to: '/learn/tax/isa-allowance', category: 'tax', dotColor: CHART_COLORS[4] },
        { title: 'Pension annual allowance', to: '/learn/tax/pension-annual-allowance', category: 'tax', dotColor: CHART_COLORS[4] },
        { title: 'Inheritance Tax thresholds', to: '/learn/tax/iht-thresholds', category: 'tax', dotColor: CHART_COLORS[4] },
        { title: 'Capital gains tax rates', to: '/learn/tax/capital-gains-tax', category: 'tax', dotColor: CHART_COLORS[4] },
        { title: 'Tax year checklist', to: '/learn/tax/tax-year-checklist', category: 'tax', dotColor: CHART_COLORS[4] },
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

  mounted() {
    this.injectBreadcrumbSchema();
  },

  beforeUnmount() {
    const el = document.getElementById('guide-breadcrumb-schema');
    if (el) el.remove();
  },

  methods: {
    injectBreadcrumbSchema() {
      const existing = document.getElementById('guide-breadcrumb-schema');
      if (existing) existing.remove();

      const guide = this.allGuides.find(g => g.to === this.currentPath);
      if (!guide) return;

      const cat = this.categories.find(c => c.key === guide.category);
      const schema = {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        'itemListElement': [
          { '@type': 'ListItem', 'position': 1, 'name': 'Home', 'item': 'https://fynla.org/' },
          { '@type': 'ListItem', 'position': 2, 'name': 'Guides & Explainers', 'item': 'https://fynla.org/learn' },
          { '@type': 'ListItem', 'position': 3, 'name': cat ? cat.label : 'Guide', 'item': 'https://fynla.org/learn' },
          { '@type': 'ListItem', 'position': 4, 'name': guide.title },
        ],
      };

      const script = document.createElement('script');
      script.id = 'guide-breadcrumb-schema';
      script.type = 'application/ld+json';
      script.textContent = JSON.stringify(schema);
      document.head.appendChild(script);
    },
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
  @apply border-raspberry-500;
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
