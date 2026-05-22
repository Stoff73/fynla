<template>
  <PublicLayout>
    <div v-if="loading" class="max-w-4xl mx-auto px-4 py-20 text-center">
      <div
        class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"
      ></div>
    </div>

    <article
      v-else-if="article"
      class="insight-body max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14"
      itemscope
      itemtype="https://schema.org/Article"
    >
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
          itemprop="image"
        />
        <div
          class="pointer-events-none absolute inset-0 bg-gradient-to-t from-horizon-500/85 via-horizon-500/40 to-transparent"
        ></div>
        <span
          v-if="categoryLabel"
          class="absolute top-4 right-4 text-xs font-semibold px-2.5 py-1 rounded-md uppercase tracking-wide bg-raspberry-500 text-white shadow-sm"
        >
          {{ categoryLabel }}
        </span>
        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-10 text-white">
          <h1
            class="text-2xl sm:text-3xl md:text-5xl font-black leading-tight drop-shadow-sm"
            style="letter-spacing:-0.02em;"
            itemprop="name"
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
            itemprop="name"
          >
            {{ article.title }}
          </h1>
          <span
            v-if="categoryLabel"
            class="flex-shrink-0 mt-2 text-xs font-semibold px-2 py-1 rounded-md uppercase tracking-wide bg-raspberry-100 text-raspberry-700"
          >
            {{ categoryLabel }}
          </span>
        </div>
        <p v-if="article.subtitle" class="text-lg text-neutral-600 leading-relaxed">
          {{ article.subtitle }}
        </p>
      </header>

      <p v-if="byline" class="text-sm text-neutral-400 mb-10" itemprop="author">{{ byline }}</p>

      <!-- CMS-imported document articles carry sanitised raw HTML in body_html
           (already passed through HTMLPurifier on import). Native insights use
           structured body_blocks. Render whichever the article supplies. -->
      <div class="insight-content" itemprop="articleBody">
        <div
          v-if="article.body_html"
          class="article-html-body"
          v-html="article.body_html"
        ></div>
        <ArticleBlockRenderer v-else :blocks="article.body_blocks || []" />
      </div>
    </article>

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

<style scoped>
/* Styling for raw-HTML article bodies imported from .docx via the CMS.
   Mirrors the visual scale used by the structured ArticleBlockRenderer
   blocks but applies it directly to elements emitted by HTMLPurifier. */
.article-html-body {
  @apply text-base leading-relaxed text-horizon-700;
  display: flow-root;
}
.article-html-body :deep(h2) {
  @apply text-2xl md:text-3xl font-bold text-horizon-500 mt-10 mb-4 leading-tight;
  letter-spacing: -0.01em;
}
.article-html-body :deep(h3) {
  @apply text-xl md:text-2xl font-bold text-horizon-500 mt-8 mb-3 leading-tight;
}
.article-html-body :deep(h4) {
  @apply text-lg font-bold text-horizon-500 mt-6 mb-2;
}
.article-html-body :deep(p) {
  @apply mb-5;
}
.article-html-body :deep(a) {
  @apply text-raspberry-500 underline decoration-raspberry-300 underline-offset-2 hover:text-raspberry-700;
}
.article-html-body :deep(ul),
.article-html-body :deep(ol) {
  @apply mb-5 pl-6 space-y-2;
}
.article-html-body :deep(ul) { list-style: disc; }
.article-html-body :deep(ol) { list-style: decimal; }
.article-html-body :deep(li) { @apply leading-relaxed; }
.article-html-body :deep(blockquote) {
  @apply border-l-4 border-raspberry-500 pl-4 my-6 italic text-neutral-600;
}
.article-html-body :deep(img) {
  @apply rounded-md my-6 max-w-full h-auto;
}
.article-html-body :deep(table) {
  @apply w-full my-6 border-collapse;
}
.article-html-body :deep(th),
.article-html-body :deep(td) {
  @apply border border-eggshell-900 px-3 py-2 text-left;
}
.article-html-body :deep(th) {
  @apply bg-savannah-300 font-bold;
}
.article-html-body :deep(pre) {
  @apply bg-eggshell-100 rounded-md p-4 my-5 overflow-x-auto text-sm font-mono;
}
.article-html-body :deep(code) {
  @apply bg-eggshell-100 px-1 py-0.5 rounded text-sm font-mono;
}
.article-html-body :deep(pre code) {
  @apply bg-transparent p-0;
}
.article-html-body :deep(hr) {
  @apply my-8 border-t border-eggshell-900;
}
</style>
