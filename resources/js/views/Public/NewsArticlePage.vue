<template>
  <PublicLayout>
    <div v-if="loading" class="bg-eggshell-500 py-20 text-center text-neutral-500">
      Loading article…
    </div>

    <div v-else-if="notFound" class="bg-eggshell-500 py-20 text-center">
      <h1 class="text-3xl font-bold text-horizon-500 mb-3">Article not found</h1>
      <p class="text-neutral-500 mb-6">The news article you're looking for doesn't exist or hasn't been published yet.</p>
      <router-link to="/news" class="inline-block px-6 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
        Back to News
      </router-link>
    </div>

    <template v-else-if="article">
      <!-- Hero -->
      <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-left w-full">
          <p class="text-sm text-white/70 mb-3">
            {{ formatDate(article.published_at) }}
            <span v-if="article.author_name" class="text-white/50">&middot; By {{ article.author_name }}</span>
          </p>
          <h1 class="text-3xl md:text-5xl font-black text-white mb-4 leading-tight">
            {{ article.title }}
          </h1>
          <p class="text-lg text-white/80 max-w-2xl">{{ article.summary }}</p>
        </div>
      </div>

      <!-- Body -->
      <article class="bg-eggshell-500 py-14">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="news-article prose prose-lg max-w-none" v-html="article.body"></div>

          <div class="mt-12 pt-8 border-t border-light-gray">
            <router-link to="/news" class="inline-flex items-center gap-1 text-sm font-semibold text-horizon-500 hover:text-raspberry-500 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" /></svg>
              Back to all news
            </router-link>
          </div>
        </div>
      </article>
    </template>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import newsService from '@/services/newsService';

export default {
  name: 'NewsArticlePage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      article: null,
      loading: true,
      notFound: false,
    };
  },

  watch: {
    '$route.params.slug'() {
      this.fetchArticle();
    },
  },

  mounted() {
    this.fetchArticle();
  },

  methods: {
    async fetchArticle() {
      this.loading = true;
      this.notFound = false;
      this.article = null;
      try {
        const response = await newsService.getBySlug(this.$route.params.slug);
        this.article = response.data;
        this.applyMeta();
      } catch (err) {
        this.notFound = true;
      } finally {
        this.loading = false;
      }
    },
    applyMeta() {
      const a = this.article;
      if (!a) return;
      document.title = (a.meta_title || `${a.title} | Fynla`);
      const meta = document.querySelector('meta[name="description"]');
      if (meta) {
        meta.setAttribute('content', a.meta_description || a.summary || '');
      }
    },
    formatDate(iso) {
      if (!iso) return '';
      const date = new Date(iso);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.news-article :deep(h2) {
  font-size: 1.625rem;
  font-weight: 700;
  color: #1F2A44;
  margin: 2rem 0 1rem;
  line-height: 1.3;
}
.news-article :deep(h3) {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1F2A44;
  margin: 1.5rem 0 0.75rem;
  line-height: 1.35;
}
.news-article :deep(p) {
  font-size: 1rem;
  color: #4b5563;
  line-height: 1.7;
  margin: 0 0 1rem;
}
.news-article :deep(p.lead) {
  font-size: 1.125rem;
  color: #1F2A44;
  font-weight: 500;
  margin-bottom: 1.5rem;
}
.news-article :deep(ul) {
  margin: 0 0 1.25rem 1.25rem;
  padding-left: 1rem;
  list-style: disc;
}
.news-article :deep(li) {
  font-size: 1rem;
  color: #4b5563;
  line-height: 1.7;
  margin-bottom: 0.5rem;
}
.news-article :deep(a) {
  color: #e74c6f;
  text-decoration: underline;
}
.news-article :deep(a:hover) {
  color: #d63d61;
}
.news-article :deep(strong) {
  color: #1F2A44;
  font-weight: 600;
}
</style>
