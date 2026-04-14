<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla
          <span class="text-raspberry-300">Insights</span>
        </h1>
        <p class="text-lg text-white/70">
          Timely commentary on what's changing — and what it means for you.
        </p>
      </div>
    </div>

    <!-- Intro + Articles — full width -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-500 mb-8 leading-relaxed">
          Focused, practical pieces about tax changes, pension rules, budget updates, and their impact on your financial plan.
          Our insights draw on information from key financial institutions and regulatory bodies, advice from certified advisors,
          and updates on the Fynla platform including upcoming releases.
        </p>

        <!-- Category filter -->
        <div class="flex flex-wrap gap-2 mb-8">
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all"
            :class="activeCategory === cat ? 'bg-horizon-500 text-white' : 'bg-white border border-light-gray text-neutral-500 hover:border-raspberry-300 hover:text-horizon-500'"
            @click="activeCategory = cat"
          >
            {{ cat }}
          </button>
        </div>

        <!-- Article Cards -->
        <div class="space-y-4">
          <router-link
            v-for="article in filteredArticles"
            :key="article.slug"
            :to="article.slug"
            class="flex flex-col sm:flex-row bg-white rounded-2xl overflow-hidden hover:bg-light-pink-100 hover:shadow-md transition-all group"
          >
            <!-- Image -->
            <div class="sm:w-[280px] flex-shrink-0 bg-gradient-to-br from-horizon-500 to-raspberry-500 flex items-center justify-center overflow-hidden" :class="getImage(article.image) ? '' : 'p-8 sm:p-6'">
              <img v-if="getImage(article.image)" :src="getImage(article.image)" :alt="article.title" class="w-full h-full object-cover" />
              <div v-else class="w-full aspect-video sm:aspect-auto sm:h-full rounded-lg bg-white/10 flex items-center justify-center">
                <svg class="w-12 h-12 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="article.icon" />
                </svg>
              </div>
            </div>
            <!-- Content -->
            <div class="flex-1 p-5 sm:p-6 flex flex-col justify-center">
              <p class="text-xs text-neutral-400 mb-1">{{ article.date }}</p>
              <h2 class="text-lg lg:text-xl xl:text-2xl font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-2 leading-tight">
                {{ article.title }}
              </h2>
              <p class="text-sm text-neutral-500 mb-3 leading-relaxed">{{ article.summary }}</p>
              <div class="flex flex-wrap gap-1 mb-3">
                <span
                  v-for="tag in article.tags"
                  :key="tag"
                  class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide"
                  :class="tagClass(tag)"
                >
                  {{ tag }}
                </span>
              </div>
              <span class="text-base font-semibold text-raspberry-500">Read &rarr;</span>
            </div>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Stay Updated — light pink bg -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Want to stay updated?</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          More insights are on the way. Register for a free Fynla account and we'll let you know when new articles are published.
        </p>
        <router-link
          to="/register"
          class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Register for free
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

// Auto-import all insight images — add new images to resources/js/assets/insights/
// and reference the filename in the article's `image` field (e.g. 'my-article.jpg')
const insightImages = import.meta.glob('@/assets/insights/*.{jpg,png,webp}', { eager: true, import: 'default' });

export default {
  name: 'InsightsHubPage',
  components: { PublicLayout },

  data() {
    return {
      activeCategory: 'All',
      categories: ['All', 'Tax changes', 'Pensions', 'Savings & ISA', 'Estate planning', 'Platform updates'],
      articles: [
        {
          slug: '/insights/how-much-to-retire-uk',
          title: 'How Much Do I Need to Retire in the UK? A Realistic Guide',
          date: '14 April 2026',
          summary: 'Calculate your UK retirement number using 2026 PLSA living standards. Pension pot sizes needed and how to bridge the State Pension gap.',
          tags: ['Pensions'],
          image: 'how-much-to-retire-uk.jpg',
          icon: 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941',
        },
        {
          slug: '/insights/stocks-shares-isa-uk',
          title: 'What Is a Stocks and Shares ISA? How It Works, Benefits & Risks',
          date: '13 April 2026',
          summary: 'A complete guide to Stocks and Shares ISAs — how they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.',
          tags: ['Savings & ISA'],
          image: 'stocks-shares-isa.jpg',
          icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        },
        {
          slug: '/insights/isa-guide-uk',
          title: 'The Ultimate Guide to ISAs in the UK: Types, Rules & Best Options',
          date: '8 April 2026',
          summary: 'Everything you need to know about ISAs in 2026 — types, allowances, rules, and how to choose the right one for your goals.',
          tags: ['Savings & ISA'],
          image: 'isa-guide-uk.jpg',
          icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
        },
        {
          slug: '/insights/retirement-planning-uk',
          title: 'The Complete Guide to Retirement Planning in the UK',
          date: '8 April 2026',
          summary: 'Plan a retirement that lasts — pensions, State Pension, ISAs, drawdown strategies, tax and how to estimate what you will need.',
          tags: ['Pensions'],
          image: 'retirement-planning-uk.jpg',
          icon: 'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h9a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75',
        },
        {
          slug: '/insights/inheritance-tax-uk',
          title: 'Inheritance Tax Explained: Thresholds, Rules & How to Calculate IHT',
          date: 'April 2026',
          summary: 'Understand UK inheritance tax with our 2026 guide. Learn IHT thresholds, nil rate bands, calculation methods and strategies to reduce your estate\'s tax bill.',
          tags: ['Estate planning'],
          image: 'inheritance-tax-uk.jpg',
        },
        {
          slug: '/insights/pension-contribution-limits-uk',
          title: 'Pension Contribution Limits UK 2026/27: How Much Can You Pay In?',
          date: 'April 2026',
          summary: 'Find out the 2026/27 pension contribution limits, annual allowance, tax relief rates and carry forward rules. Updated guide for UK savers.',
          tags: ['Pensions'],
          image: 'pension-contribution-limits.jpg',
        },
        {
          slug: '/insights/pension-iht-changes-2027',
          title: 'Pension Inheritance Tax Changes: April 2027',
          date: 'March 2026',
          summary: 'From April 2027, unused pension pots will be included in your estate for Inheritance Tax. Here\'s what\'s changing and what you can do.',
          tags: ['Pensions', 'Estate planning'],
          image: 'pension-iht-changes.jpg',
        },
        {
          slug: '/insights/isa-allowance-2025-26',
          title: `ISA Allowance ${getCurrentTaxYear()}: Make the Most of Your \u00A320,000`,
          date: 'April 2025',
          summary: 'The ISA allowance remains at \u00A320,000. Types, deadlines, and strategies for maximising your tax-free savings.',
          tags: ['Savings & ISA'],
          image: 'isa-allowance.jpg',
        },
      ],
    };
  },

  computed: {
    filteredArticles() {
      if (this.activeCategory === 'All') return this.articles;
      return this.articles.filter(a => a.tags.includes(this.activeCategory));
    },
  },

  methods: {
    getImage(filename) {
      if (!filename) return null;
      const key = Object.keys(insightImages).find(k => k.endsWith('/' + filename));
      return key ? insightImages[key] : null;
    },

    tagClass(tag) {
      const classes = {
        'Tax changes': 'bg-raspberry-50 text-raspberry-700',
        'Pensions': 'bg-violet-50 text-violet-700',
        'Savings & ISA': 'bg-spring-50 text-spring-700',
        'Estate planning': 'bg-violet-50 text-violet-700',
        'Platform updates': 'bg-light-blue-100 text-light-blue-700',
      };
      return classes[tag] || 'bg-neutral-100 text-neutral-600';
    },
  },

  mounted() {
    document.title = 'Insights \u2014 UK Financial Planning News & Commentary | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'UK financial planning insights covering tax changes, pension rules, budget updates, and platform news from Fynla.');
  },
};
</script>
