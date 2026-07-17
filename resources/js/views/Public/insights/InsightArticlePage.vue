<template>
  <PublicLayout>
    <div v-if="loading" class="max-w-4xl mx-auto px-4 py-20 text-center">
      <div
        class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"
      ></div>
    </div>

    <div v-else-if="article" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
      <div v-if="article.tags && article.tags.length" class="flex items-center gap-2 mb-4 flex-wrap">
        <span
          v-for="tag in article.tags"
          :key="tag"
          class="text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-light-gray text-neutral-600"
        >
          {{ tag }}
        </span>
      </div>

      <!-- Hero with overlaid title/subtitle and top-right category chip -->
      <section
        v-if="hasHero"
        class="relative rounded-lg overflow-hidden mb-8 bg-horizon-500"
      >
        <img
          :src="article.hero_image.full"
          :alt="article.title"
          class="block w-full h-auto"
        />
        <div
          class="pointer-events-none absolute inset-0 bg-gradient-to-t from-horizon-500/85 via-horizon-500/40 to-transparent"
        ></div>
        <span
          class="absolute top-4 right-4 text-xs font-semibold px-2.5 py-1 rounded-md uppercase tracking-wide bg-raspberry-500 text-white shadow-sm"
        >
          {{ categoryLabel }}
        </span>
        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-10 text-white">
          <h1
            class="text-2xl sm:text-3xl md:text-5xl font-black leading-tight drop-shadow-sm"
            style="letter-spacing:-0.02em;"
          >
            {{ article.title }}
          </h1>
          <p
            v-if="article.subtitle"
            class="text-sm sm:text-base md:text-lg mt-2 md:mt-3 leading-relaxed drop-shadow-sm opacity-95"
          >
            {{ article.subtitle }}
          </p>
        </div>
      </section>

      <!-- Fallback header (no hero image): title on left, category chip inline right -->
      <header v-else class="mb-8">
        <div class="flex items-start justify-between gap-4 mb-4">
          <h1
            class="flex-1 text-4xl md:text-5xl font-black text-horizon-500 leading-tight"
            style="letter-spacing:-0.02em;"
          >
            {{ article.title }}
          </h1>
          <span
            class="flex-shrink-0 mt-2 text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-raspberry-100 text-raspberry-700"
          >
            {{ categoryLabel }}
          </span>
        </div>
        <p v-if="article.subtitle" class="text-lg text-neutral-600 leading-relaxed">
          {{ article.subtitle }}
        </p>
      </header>

      <p v-if="byline" class="text-sm text-neutral-400 mb-10">{{ byline }}</p>

      <ArticleBlockRenderer :blocks="article.body_blocks || []" />

      <InsightCtaPanel :article="article" />
    </div>

    <div v-else class="max-w-4xl mx-auto px-4 py-20 text-center">
      <h1 class="text-3xl font-bold text-horizon-500 mb-4">Article not found</h1>
      <p class="text-neutral-500 mb-6">
        The article you&rsquo;re looking for doesn&rsquo;t exist or has been unpublished.
      </p>
      <router-link
        to="/insights"
        class="inline-block px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-semibold"
      >
        Back to insights
      </router-link>
    </div>
  </PublicLayout>
</template>

<script>
import { mapActions } from 'vuex';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ArticleBlockRenderer from '@/components/Insights/ArticleBlockRenderer.vue';
import InsightCtaPanel from '@/components/Insights/InsightCtaPanel.vue';
import { formatDateLong } from '@/utils/dateFormatter';

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
  name: 'InsightArticlePage',
  components: { PublicLayout, ArticleBlockRenderer, InsightCtaPanel },
  data() {
    return { loading: true, article: null };
  },
  computed: {
    categoryLabel() {
      return CATEGORY_LABELS[this.article?.category] || this.article?.category;
    },
    formattedDate() {
      return this.article?.published_at ? formatDateLong(this.article.published_at) : '';
    },
    authorByline() {
      const authors = (this.article?.authors || []).filter(Boolean);
      if (!authors.length) return '';
      if (authors.length === 1) return `By ${authors[0]}`;
      if (authors.length === 2) return `By ${authors[0]} and ${authors[1]}`;
      return `By ${authors.slice(0, -1).join(', ')} and ${authors[authors.length - 1]}`;
    },
    byline() {
      return [this.authorByline, this.formattedDate].filter(Boolean).join(' · ');
    },
    hasHero() {
      return !!this.article?.hero_image?.full;
    },
  },
  async mounted() {
    await this.load();
  },
  async beforeRouteUpdate(to) {
    this.article = null;
    this.loading = true;
    await this.load(to.params.slug);
  },
  methods: {
    ...mapActions('insights', ['fetchBySlug']),
    async load(slug = this.$route.params.slug) {
      try {
        const preview = this.$route.query.preview === 'true';
        const article = await this.fetchBySlug({ slug, preview });
        // Bespoke articles should be caught by named routes — if one leaks
        // through to the catch-all, show the not-found state rather than
        // rendering its empty body_blocks through the generic renderer.
        this.article = article && !article.is_bespoke ? article : null;
      } catch (e) {
        this.article = null;
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
