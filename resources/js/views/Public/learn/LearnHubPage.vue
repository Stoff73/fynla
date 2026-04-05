<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Guides &amp; Explainers</h1>
        <p class="text-lg text-white/70">
          Understand your finances. Make better decisions.
        </p>
      </div>
    </div>

    <!-- Intro (full width, left aligned) -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-600 leading-relaxed">
          Whether you're trying to understand what a pension drawdown is, deciding if you should overpay your mortgage, or checking this year's tax allowances &mdash; we've written a guide for it. Everything here is free, jargon-free, and written for real people, not finance professionals.
        </p>
      </div>
    </section>

    <!-- Category Filter Tabs -->
    <section class="bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex gap-1 border-b-2 border-light-gray pt-4 overflow-x-auto scrollbar-hide">
          <button
            v-for="cat in categories"
            :key="cat.key"
            class="category-tab whitespace-nowrap"
            :class="{ active: activeCategory === cat.key }"
            @click="activeCategory = cat.key"
          >
            <span v-if="cat.color" class="inline-block w-2 h-2 rounded-full mr-1.5 align-middle" :style="{ background: cat.color }"></span>
            {{ cat.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- Guide Links -->
    <section class="bg-eggshell-500 py-6 pb-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
          <router-link
            v-for="link in filteredGuides"
            :key="link.to"
            :to="link.to"
            class="guide-link"
          >
            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="{ background: link.dotColor }"></span>
            {{ link.title }}
          </router-link>
        </div>
      </div>
    </section>

    <!-- Glossary / Insights / FAQ -->
    <section class="bg-eggshell-500 pb-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <router-link
            to="/learn/glossary"
            class="resource-card group"
          >
            <h3 class="text-lg font-bold text-white group-hover:text-white/90 transition-colors mb-1">Glossary A&ndash;Z</h3>
            <p class="text-xs text-white/70">37 financial terms explained in plain English</p>
          </router-link>
          <router-link
            to="/insights"
            class="resource-card group"
          >
            <h3 class="text-lg font-bold text-white group-hover:text-white/90 transition-colors mb-1">Latest Insights</h3>
            <p class="text-xs text-white/70">Timely commentary on tax changes and financial news</p>
          </router-link>
          <router-link
            to="/faq"
            class="resource-card group"
          >
            <h3 class="text-lg font-bold text-white group-hover:text-white/90 transition-colors mb-1">FAQ</h3>
            <p class="text-xs text-white/70">Common questions about Fynla answered</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Ready to put knowledge into action?</h2>
          <p class="text-lg text-white/70 mb-4">Try Fynla free and see your full financial picture in minutes.</p>
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            Try the demo
          </a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'LearnHubPage',
  components: { PublicLayout },

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
        { title: 'What is an ISA?', to: '/learn/what-is-an-isa', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'What is Drawdown?', to: '/learn/what-is-drawdown', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'What is Salary Sacrifice?', to: '/learn/what-is-salary-sacrifice', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'What is a Lasting Power of Attorney?', to: '/learn/what-is-an-lpa', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'What is a Self-Invested Personal Pension?', to: '/learn/what-is-a-sipp', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'What is Inheritance Tax?', to: '/learn/what-is-inheritance-tax', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'What is Financial Planning?', to: '/learn', category: 'explainers', dotColor: '#E8326E', categoryLabel: 'Key Term' },
        { title: 'Should I overpay my mortgage?', to: '/learn/should-i-overpay-my-mortgage', category: 'decisions', dotColor: '#20B486', categoryLabel: 'Decision Support' },
        { title: 'Should I consolidate my pensions?', to: '/learn/should-i-consolidate-pensions', category: 'decisions', dotColor: '#20B486', categoryLabel: 'Decision Support' },
        { title: 'When should I make a will?', to: '/learn/when-should-i-make-a-will', category: 'decisions', dotColor: '#20B486', categoryLabel: 'Decision Support' },
        { title: 'Lifetime ISA or ISA?', to: '/learn/should-i-use-a-lisa-or-isa', category: 'decisions', dotColor: '#20B486', categoryLabel: 'Decision Support' },
        { title: 'When can I afford to retire?', to: '/learn/when-can-i-afford-to-retire', category: 'decisions', dotColor: '#20B486', categoryLabel: 'Decision Support' },
        { title: 'Starting Out: money basics', to: '/learn/guide/starting-out', category: 'stages', dotColor: '#5854E6', categoryLabel: 'Personal Journey Guide' },
        { title: 'Building Foundations: first home', to: '/learn/guide/building-foundations', category: 'stages', dotColor: '#5854E6', categoryLabel: 'Personal Journey Guide' },
        { title: 'Protecting and Growing: family finances', to: '/learn/guide/protecting-and-growing', category: 'stages', dotColor: '#5854E6', categoryLabel: 'Personal Journey Guide' },
        { title: 'Planning Your Future: retirement', to: '/learn/guide/planning-your-future', category: 'stages', dotColor: '#5854E6', categoryLabel: 'Personal Journey Guide' },
        { title: 'Enjoying Your Wealth: estate planning', to: '/learn/guide/enjoying-your-wealth', category: 'stages', dotColor: '#5854E6', categoryLabel: 'Personal Journey Guide' },
        { title: 'ISA allowance guide', to: '/learn/tax/isa-allowance', category: 'tax', dotColor: '#E6C9A8', categoryLabel: 'Tax & Allowances' },
        { title: 'Pension annual allowance', to: '/learn/tax/pension-annual-allowance', category: 'tax', dotColor: '#E6C9A8', categoryLabel: 'Tax & Allowances' },
        { title: 'Inheritance Tax thresholds', to: '/learn/tax/iht-thresholds', category: 'tax', dotColor: '#E6C9A8', categoryLabel: 'Tax & Allowances' },
        { title: 'Capital gains tax rates', to: '/learn/tax/capital-gains-tax', category: 'tax', dotColor: '#E6C9A8', categoryLabel: 'Tax & Allowances' },
        { title: 'Tax year checklist', to: '/learn/tax/tax-year-checklist', category: 'tax', dotColor: '#E6C9A8', categoryLabel: 'Tax & Allowances' },
      ],
    };
  },

  computed: {
    filteredGuides() {
      if (this.activeCategory === 'all') return this.allGuides;
      return this.allGuides.filter(g => g.category === this.activeCategory);
    },

  },

  mounted() {
    document.title = 'Guides & Explainers — Free UK Financial Planning Guides | Fynla';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'Free jargon-free guides to UK financial planning. Learn about ISAs, pensions, drawdown, inheritance tax, salary sacrifice, mortgages and more. Written for real people.');

    // ItemList schema for GEO (AI search engines)
    const schema = {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      'name': 'Fynla Guides & Explainers',
      'description': 'Free UK financial planning guides covering savings, investments, pensions, tax, and estate planning.',
      'url': 'https://fynla.org/learn',
      'numberOfItems': this.allGuides.length,
      'itemListElement': this.allGuides.map((guide, i) => ({
        '@type': 'ListItem',
        'position': i + 1,
        'name': guide.title,
        'url': 'https://fynla.org' + guide.to,
      })),
    };
    const script = document.createElement('script');
    script.id = 'learn-hub-schema';
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify(schema);
    document.head.appendChild(script);
  },

  beforeUnmount() {
    const el = document.getElementById('learn-hub-schema');
    if (el) el.remove();
  },
};
</script>

<style scoped>
.category-tab {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  background: transparent;
  @apply text-neutral-500;
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

.guide-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  @apply bg-white;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.2s;
  font-size: 14px;
  @apply text-horizon-500;
  cursor: pointer;
  border: none;
  text-align: left;
  width: 100%;
}

.guide-link:hover {
  @apply bg-light-pink-100 text-raspberry-500;
}

.resource-card {
  @apply bg-horizon-500 rounded-lg;
  padding: 20px;
  text-decoration: none;
  display: block;
  transition: all 0.2s;
}

.resource-card:hover {
  @apply bg-horizon-600;
}
</style>
