<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Frequently asked <span class="text-raspberry-300">questions</span>
        </h1>
        <p class="text-lg text-white/70">
          Everything you need to know about Fynla — from what it does, to how it works, to whether it's right for you.
        </p>
      </div>
    </div>

    <!-- Intro + Filter -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-500 mb-8 leading-relaxed">
          Answers to the most common questions about Fynla — what it does, how it works, pricing, security, and whether it's the right tool for your financial planning needs.
        </p>

        <!-- Category filter -->
        <div class="flex flex-wrap gap-2 mb-8">
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="activeCategory = cat.id"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all"
            :class="activeCategory === cat.id
              ? 'bg-horizon-500 text-white'
              : 'bg-white border border-light-gray text-neutral-500 hover:border-raspberry-300 hover:text-horizon-500'"
          >
            {{ cat.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- FAQ Content -->
    <section class="pb-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div v-for="cat in filteredCategories" :key="cat.id" class="mb-10">
          <h2 class="text-lg font-bold text-raspberry-500 bg-light-pink-100 rounded-lg px-4 py-1.5 mb-4">{{ cat.label }}</h2>
          <div class="space-y-2">
            <div
              v-for="(item, idx) in cat.items"
              :key="idx"
              class="bg-white rounded-lg border border-light-gray overflow-hidden"
            >
              <button
                @click="toggle(cat.id, idx)"
                class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-savannah-50 transition-colors"
              >
                <span class="text-sm font-medium text-horizon-500 pr-4">{{ item.q }}</span>
                <svg
                  class="w-4 h-4 text-neutral-400 flex-shrink-0 transition-transform duration-200"
                  :class="{ 'rotate-180': isOpen(cat.id, idx) }"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="isOpen(cat.id, idx)"
                class="px-4 pb-3 border-t border-light-gray"
              >
                <p class="text-sm text-neutral-500 pt-3 leading-relaxed">{{ item.a }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Still Have Questions -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-2">Still have questions?</h2>
        <p class="text-sm text-neutral-500 mb-6">Can't find what you're looking for? Get in touch and we'll help.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <router-link
            to="/contact"
            class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
          >
            Contact Us
          </router-link>
          <a
            href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
            class="px-6 py-2.5 bg-white text-horizon-500 text-sm font-semibold rounded-lg border border-light-gray hover:bg-savannah-50 transition-colors"
          >
            Try the Demo
          </a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getAllFaqCategories } from '@/constants/faqData';

export default {
  name: 'FaqPage',

  components: {
    PublicLayout,
  },

  data() {
    const faqCategories = getAllFaqCategories();
    return {
      activeCategory: 'all',
      openItems: {},
      categories: [
        { id: 'all', label: 'All' },
        ...faqCategories.map(c => ({ id: c.id, label: c.label })),
      ],
      faqData: faqCategories,
    };
  },

  computed: {
    filteredCategories() {
      if (this.activeCategory === 'all') {
        return this.faqData;
      }
      return this.faqData.filter(cat => cat.id === this.activeCategory);
    },
  },

  mounted() {
    document.title = 'Frequently asked questions | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Find answers to common questions about Fynla, UK financial planning, pensions, investments, savings, and how to get the most from the platform.');
  },

  methods: {
    toggle(catId, idx) {
      const key = `${catId}-${idx}`;
      this.openItems = { ...this.openItems, [key]: !this.openItems[key] };
    },
    isOpen(catId, idx) {
      return !!this.openItems[`${catId}-${idx}`];
    },
  },
};
</script>
