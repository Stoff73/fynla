<template>
  <PublicLayout>
    <div v-if="loading" class="max-w-4xl mx-auto px-4 py-20 text-center">
      <div
        class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"
      ></div>
    </div>

    <div v-else-if="article" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
      <header class="mb-10">
        <div class="flex items-center gap-2 mb-4 flex-wrap">
          <span
            class="text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-raspberry-100 text-raspberry-700"
          >
            {{ categoryLabel }}
          </span>
          <span
            v-for="tag in article.tags"
            :key="tag"
            class="text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-light-gray text-neutral-600"
          >
            {{ tag }}
          </span>
        </div>
        <h1
          class="text-4xl md:text-5xl font-black text-horizon-500 mb-4 leading-tight"
          style="letter-spacing:-0.02em;"
        >
          {{ article.title }}
        </h1>
        <p v-if="article.subtitle" class="text-lg text-neutral-600 mb-3 leading-relaxed">
          {{ article.subtitle }}
        </p>
        <p v-if="formattedDate" class="text-sm text-neutral-400">{{ formattedDate }}</p>
      </header>

      <figure v-if="article.hero_image && article.hero_image.full" class="mb-10 rounded-lg overflow-hidden">
        <img :src="article.hero_image.full" :alt="article.title" class="w-full h-auto" />
      </figure>

      <ArticleBlockRenderer :blocks="article.body_blocks || []" />

      <footer class="mt-16 pt-8 border-t border-light-gray text-sm text-neutral-500 italic">
        {{ article.summary }}
      </footer>
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
import { formatDateLong } from '@/utils/dateFormatter';

const CATEGORY_LABELS = {
  'tax-changes': 'Tax changes',
  'pensions': 'Pensions',
  'savings-isa': 'Savings & ISA',
  'estate-planning': 'Estate planning',
  'platform-updates': 'Platform updates',
};

export default {
  name: 'InsightArticlePage',
  components: { PublicLayout, ArticleBlockRenderer },
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
