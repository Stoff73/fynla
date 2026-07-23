<template>
  <PublicLayout>
    <div v-if="loading" class="max-w-4xl mx-auto px-4 py-20 text-center">
      <div
        class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"
      ></div>
    </div>

    <div
      v-else-if="article"
      class="insight-body"
      itemscope
      itemtype="https://schema.org/Article"
    >
      <!-- Full-bleed hero: photo (with overlay) if present, otherwise the
           branded gradient. Inner content is constrained to max-w-7xl to match
           the rest of the article. -->
      <div
        v-if="hasHero"
        class="relative flex items-center overflow-hidden bg-horizon-500 min-h-[280px] md:min-h-[360px]"
      >
        <img
          :src="article.hero_image.full"
          :alt="article.title"
          class="absolute inset-0 w-full h-full object-cover"
          itemprop="image"
        />
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-horizon-500/90 via-horizon-500/70 to-horizon-500/30"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16 w-full text-white">
          <h1 class="text-4xl md:text-6xl font-black leading-tight max-w-4xl" style="letter-spacing:-0.02em;" itemprop="name">{{ article.title }}</h1>
          <p v-if="article.subtitle" class="text-lg md:text-xl mt-4 leading-relaxed text-white/90 max-w-3xl">{{ article.subtitle }}</p>
        </div>
      </div>

      <div v-else class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-20 w-full text-left">
          <h1 class="text-4xl md:text-6xl font-black text-white leading-tight max-w-4xl" style="letter-spacing:-0.02em;" itemprop="name">{{ article.title }}</h1>
          <p v-if="article.subtitle" class="text-lg md:text-xl mt-4 leading-relaxed text-white/90 max-w-3xl">{{ article.subtitle }}</p>
        </div>
      </div>

      <!-- Article body -->
      <section class="py-12 bg-eggshell-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center gap-3 mb-2 flex-wrap">
            <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
            <span
              v-if="categoryLabel"
              class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700"
            >{{ categoryLabel }}</span>
            <span
              v-for="tag in article.tags || []"
              :key="tag"
              class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700"
            >{{ tag }}</span>
          </div>
          <p v-if="byline" class="text-xs text-neutral-400 mb-8" itemprop="author">{{ byline }}</p>

          <!-- CMS-imported document articles carry sanitised raw HTML in
               body_html (already passed through HTMLPurifier on import). Native
               insights use structured body_blocks. Render whichever supplies. -->
          <div class="insight-content" itemprop="articleBody">
            <div
              v-if="article.body_html"
              class="article-html-body"
              v-html="article.body_html"
            ></div>
            <ArticleBlockRenderer v-else :blocks="article.body_blocks || []" />
          </div>

          <!-- Bottom call-to-action. A linked campaign overrides the default
               register prompt with the campaign's heading/button + landing page. -->
          <aside
            v-if="article.cta"
            class="mt-16 rounded-2xl overflow-hidden bg-gradient-to-r from-horizon-500 to-raspberry-500 text-white text-center px-6 py-10 sm:px-12 sm:py-14 shadow-lg"
          >
            <h2 class="text-2xl sm:text-4xl font-black mb-3 leading-tight" style="letter-spacing:-0.02em;">
              {{ article.cta.heading }}
            </h2>
            <p class="text-white/90 mb-8 max-w-2xl mx-auto text-base sm:text-lg leading-relaxed">
              {{ article.cta.subheading }}
            </p>
            <a
              :href="article.cta.url"
              class="inline-block px-8 py-4 bg-white text-horizon-500 rounded-xl font-bold text-base sm:text-lg hover:bg-eggshell-500 transition-colors shadow"
            >
              {{ article.cta.button_text }}
            </a>
          </aside>
        </div>
      </section>
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
