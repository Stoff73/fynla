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

    <!-- Latest articles — light pink bento -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-7">
          <div>
            <p class="text-[0.7rem] tracking-[0.2em] font-semibold text-raspberry-700 mb-1 uppercase">Just published</p>
            <h2 class="text-2xl md:text-4xl font-bold text-horizon-500" style="letter-spacing:-0.02em;">Latest articles</h2>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <!-- Hero feature -->
          <router-link
            v-if="latestArticles[0]"
            :to="'/insights/' + latestArticles[0].slug"
            class="lg:col-span-2 group relative block rounded-3xl overflow-hidden bg-horizon-500 min-h-[420px] lg:min-h-[520px]"
          >
            <img
              v-if="latestArticles[0].image"
              :src="latestArticles[0].image"
              :alt="latestArticles[0].title"
              class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:opacity-90 group-hover:scale-105 transition-all duration-700"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-horizon-700 via-horizon-500/50 to-transparent"></div>
            <div class="relative h-full flex flex-col justify-end p-7 md:p-10">
              <div class="flex items-center gap-2 mb-3">
                <span class="text-[0.65rem] font-bold px-2 py-1 rounded-md uppercase tracking-wider bg-raspberry-500 text-white">Featured</span>
                <span
                  v-for="tag in latestArticles[0].tags"
                  :key="tag"
                  class="text-[0.65rem] font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-white/20 backdrop-blur text-white"
                >
                  {{ tag }}
                </span>
              </div>
              <p class="text-xs text-white/70 mb-2">{{ latestArticles[0].date }}</p>
              <h3 class="text-2xl md:text-4xl font-bold text-white mb-3 leading-tight group-hover:text-raspberry-300 transition-colors" style="letter-spacing:-0.02em;">
                {{ latestArticles[0].title }}
              </h3>
              <p class="text-sm md:text-base text-white/80 mb-4 leading-relaxed max-w-2xl">
                {{ latestArticles[0].summary }}
              </p>
              <span class="inline-flex items-center gap-1 text-sm font-semibold text-white">
                Read the full guide
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
              </span>
            </div>
          </router-link>

          <!-- Stacked side cards -->
          <div class="grid grid-rows-2 gap-5">
            <router-link
              v-for="article in latestArticles.slice(1, 3)"
              :key="article.slug"
              :to="'/insights/' + article.slug"
              class="group relative block rounded-3xl overflow-hidden bg-white hover:shadow-xl transition-all"
            >
              <div class="flex h-full min-h-[200px]">
                <div class="w-2/5 relative overflow-hidden bg-horizon-100">
                  <img
                    v-if="article.image"
                    :src="article.image"
                    :alt="article.title"
                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                  />
                </div>
                <div class="flex-1 p-5 flex flex-col justify-center">
                  <p class="text-[0.65rem] text-neutral-400 mb-1 uppercase tracking-wide">{{ article.date }}</p>
                  <h4 class="text-base md:text-lg font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-2 leading-tight">
                    {{ article.title }}
                  </h4>
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="tag in article.tags"
                      :key="tag"
                      class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide self-start"
                      :class="tagClass(tag)"
                    >
                      {{ tag }}
                    </span>
                  </div>
                </div>
              </div>
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Browse all insights -->
    <section class="pt-12 pb-14 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mb-8">
          <h3 class="text-xl md:text-2xl font-bold text-horizon-500 mb-3" style="letter-spacing:-0.02em;">Browse all insights</h3>
          <p class="text-sm text-neutral-500 leading-relaxed">
            Focused, practical pieces about tax changes, pension rules, budget updates, and their impact on your financial plan.
            Our insights draw on information from key financial institutions and regulatory bodies, advice from certified advisors,
            and updates on the Fynla platform including upcoming releases.
          </p>
        </div>

        <!-- Category filter -->
        <div class="flex flex-wrap gap-2 mb-10">
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all"
            :class="activeCategory === cat ? 'bg-horizon-500 text-white' : 'bg-white border border-light-gray text-neutral-500 hover:border-raspberry-300 hover:text-horizon-500'"
            @click="activeCategory = cat"
          >
            {{ cat }}
            <span class="ml-1 opacity-70">({{ categoryCount(cat) }})</span>
          </button>
        </div>

        <!-- Masonry grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link
            v-for="(article, idx) in otherArticles"
            :key="article.slug"
            :to="'/insights/' + article.slug"
            class="group bg-white rounded-3xl overflow-hidden hover:bg-light-pink-100 hover:shadow-lg transition-all flex flex-col"
            :class="isTallCard(idx) ? 'md:row-span-2' : ''"
          >
            <div
              class="overflow-hidden bg-horizon-100"
              :class="isTallCard(idx) ? 'aspect-[4/5] md:aspect-auto md:flex-1 min-h-[280px]' : 'aspect-[16/10]'"
            >
              <img
                v-if="article.image"
                :src="article.image"
                :alt="article.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
              <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-horizon-500 to-raspberry-500">
                <svg class="w-12 h-12 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v10a2 2 0 01-2 2z" />
                </svg>
              </div>
            </div>
            <div :class="isTallCard(idx) ? 'p-6' : 'p-5'" class="flex-1 flex flex-col">
              <p class="text-xs text-neutral-400 mb-1">{{ article.date }}</p>
              <h3
                class="font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-2 leading-tight"
                :class="isTallCard(idx) ? 'text-xl' : 'text-lg'"
                :style="isTallCard(idx) ? 'letter-spacing:-0.02em;' : ''"
              >
                {{ article.title }}
              </h3>
              <p class="text-sm text-neutral-500 mb-3 leading-relaxed flex-1">{{ article.summary }}</p>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="tag in article.tags"
                  :key="tag"
                  class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide self-start"
                  :class="tagClass(tag)"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Stay Updated -->
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
import { mapActions, mapGetters } from 'vuex';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatDateLong } from '@/utils/dateFormatter';

// Human-readable labels for each `category` enum value stored on the article.
// The category filter uses these labels; matching back to the raw value for
// filtering is done via CATEGORY_VALUE_BY_LABEL.
const CATEGORY_LABELS = {
  'tax': 'Tax',
  'pensions': 'Pensions',
  'savings-isa': 'Savings & ISA',
  'estate-planning': 'Estate planning',
  'financial-planning': 'Financial planning',
  'ai': 'Artificial intelligence',
  'fintech': 'Fintech',
  'developer': 'Developer',
  'international': 'International',
  'platform-updates': 'Platform updates',
};

export default {
  name: 'InsightsHubPage',
  components: { PublicLayout },

  data() {
    return {
      activeCategory: 'All',
      categories: ['All', 'Tax', 'Pensions', 'Savings & ISA', 'Estate planning', 'Financial planning', 'Artificial intelligence', 'Fintech', 'Developer', 'International', 'Platform updates'],
      loading: true,
      // Resilience fallback: rendered only when the API fetch returns an
      // empty list (e.g. seeder hasn't run on a fresh deploy). Slugs are
      // slug-only — the template prefixes `/insights/`. Images are null;
      // bespoke Vue pages still own their full layouts when visited.
      legacyArticles: [
        { slug: 'how-much-to-retire-uk', title: 'How Much Do I Need to Retire in the UK? A Realistic Guide', date: '14 April 2026', summary: 'Calculate your UK retirement number using 2026 PLSA living standards. Pension pot sizes needed and how to bridge the State Pension gap.', tags: ['Pensions'], image: null },
        { slug: 'stocks-shares-isa-uk', title: 'What Is a Stocks and Shares ISA? How It Works, Benefits & Risks', date: '13 April 2026', summary: 'A complete guide to Stocks and Shares ISAs — how they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.', tags: ['Savings & ISA'], image: null },
        { slug: 'isa-guide-uk', title: 'The Ultimate Guide to ISAs in the UK: Types, Rules & Best Options', date: '8 April 2026', summary: 'Everything you need to know about ISAs in 2026 — types, allowances, rules, and how to choose the right one for your goals.', tags: ['Savings & ISA'], image: null },
        { slug: 'retirement-planning-uk', title: 'The Complete Guide to Retirement Planning in the UK', date: '8 April 2026', summary: 'Plan a retirement that lasts — pensions, State Pension, ISAs, drawdown strategies, tax and how to estimate what you will need.', tags: ['Pensions'], image: null },
        { slug: 'inheritance-tax-uk', title: 'Inheritance Tax Explained: Thresholds, Rules & How to Calculate IHT', date: 'April 2026', summary: 'Understand UK inheritance tax with our 2026 guide. Learn IHT thresholds, nil rate bands, calculation methods and strategies to reduce your estate\'s tax bill.', tags: ['Estate planning'], image: null },
        { slug: 'pension-contribution-limits-uk', title: 'Pension Contribution Limits UK 2026/27: How Much Can You Pay In?', date: 'April 2026', summary: 'Find out the 2026/27 pension contribution limits, annual allowance, tax relief rates and carry forward rules. Updated guide for UK savers.', tags: ['Pensions'], image: null },
        { slug: 'pension-iht-changes-2027', title: 'Pension Inheritance Tax Changes: April 2027', date: 'March 2026', summary: 'From April 2027, unused pension pots will be included in your estate for Inheritance Tax. Here\'s what\'s changing and what you can do.', tags: ['Pensions', 'Estate planning'], image: null },
        { slug: 'isa-allowance-2025-26', title: 'ISA Allowance: Make the Most of Your Tax-Free Allowance', date: 'April 2025', summary: 'The ISA allowance remains at the annual limit. Types, deadlines, and strategies for maximising your tax-free savings.', tags: ['Savings & ISA'], image: null },
      ],
    };
  },

  computed: {
    ...mapGetters('insights', ['listItems']),

    // Normalise DB articles into the shape the template expects.
    // `slug` stays as a slug (template prefixes `/insights/`); `image` is the
    // full URL returned by InsightArticleListResource; `date` is the human
    // string derived from published_at.
    articles() {
      const source = Array.isArray(this.listItems) && this.listItems.length > 0
        ? this.listItems
        : this.legacyArticles;

      return source.map(a => ({
        id: a.id,
        slug: a.slug,
        title: a.title,
        summary: a.summary,
        tags: a.tags || [],
        image: a.image_card || a.image || null,
        date: a.published_at ? formatDateLong(a.published_at) : (a.date || ''),
        category: a.category || null,
      }));
    },

    latestArticles() {
      return this.articles.slice(0, 3);
    },

    remainingArticles() {
      return this.articles.slice(3);
    },

    otherArticles() {
      if (this.activeCategory === 'All') return this.remainingArticles;
      return this.remainingArticles.filter(a => this.matchesCategory(a, this.activeCategory));
    },
  },

  async mounted() {
    document.title = 'Insights \u2014 UK Financial Planning News & Commentary | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'UK financial planning insights covering tax changes, pension rules, budget updates, and platform news from Fynla.');

    // Feature-flag off (production default until backend is verified) → skip
    // the API call and render the legacyArticles fallback. Feature-flag on →
    // fetch from /api/insights and render whatever the DB returns.
    if (import.meta.env.VITE_INSIGHTS_CMS_ENABLED === 'true') {
      try {
        await this.fetchList();
      } catch (e) {
        // non-fatal — the legacy hardcoded list keeps the page alive
        // if the API is unavailable during a transition deploy.
      }
    }
    this.loading = false;
  },

  methods: {
    ...mapActions('insights', ['fetchList']),

    // An article matches a filter label when either one of its tags
    // equals that label, OR its category enum maps to that label.
    matchesCategory(article, label) {
      if (Array.isArray(article.tags) && article.tags.includes(label)) return true;
      if (article.category && CATEGORY_LABELS[article.category] === label) return true;
      return false;
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

    categoryCount(cat) {
      if (cat === 'All') return this.remainingArticles.length;
      return this.remainingArticles.filter(a => this.matchesCategory(a, cat)).length;
    },

    isTallCard(idx) {
      return idx === 0;
    },
  },
};
</script>
