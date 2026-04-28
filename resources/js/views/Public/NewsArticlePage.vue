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
      <div class="relative bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
          <h1 class="text-3xl md:text-5xl font-black text-white leading-tight text-left">
            {{ article.title }}
          </h1>
        </div>
      </div>

      <!-- Body -->
      <section class="py-12 bg-eggshell-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <!-- Back link -->
          <div class="flex items-center gap-3 mb-2">
            <router-link to="/news" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to News</router-link>
          </div>
          <p class="text-xs text-neutral-400 mb-8">
            <span v-if="article.author_name">By {{ article.author_name }} &middot; </span>Published on {{ formatDate(article.published_at) }}
          </p>

          <article class="space-y-10">
            <!-- Intro / summary -->
            <div v-if="article.summary">
              <p class="text-sm text-neutral-500 leading-relaxed italic">
                {{ article.summary }}
              </p>
            </div>

            <!-- Article body -->
            <div class="news-article" v-html="article.body"></div>
          </article>
        </div>
      </section>

      <!-- CTA -->
      <section class="py-12 bg-light-pink-100">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">See your complete financial picture</h2>
          <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
            Plan savings, investments, retirement and estate in one place. Start your free trial today.
          </p>
          <router-link
            to="/register"
            class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
          >
            Start your free trial
          </router-link>
        </div>
      </section>
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
/* Match the bespoke insights pages' typography (e.g. IsaGuideUkPage):
   h2: text-xl sm:text-2xl font-bold text-horizon-500 mb-3
   h3: text-lg font-semibold text-horizon-500 mb-2 mt-6
   p:  text-sm text-neutral-500 leading-relaxed mb-3 */
.news-article :deep(h2) {
  @apply text-xl sm:text-2xl font-bold text-horizon-500 mb-3 mt-8;
  line-height: 1.3;
}
.news-article :deep(h3) {
  @apply text-lg font-semibold text-horizon-500 mb-2 mt-6;
  line-height: 1.35;
}
.news-article :deep(p) {
  @apply text-sm text-neutral-500 leading-relaxed mb-3;
}
/* Lead paragraph — first paragraph of the article body, or any <p class="lead">.
   Matches the article subtitle formatting (h2: bold horizon-500, larger). */
.news-article :deep(p.lead),
.news-article :deep(p:first-child) {
  @apply text-xl sm:text-2xl font-bold text-horizon-500 mb-6 mt-0;
  line-height: 1.3;
}
.news-article :deep(p.lead strong),
.news-article :deep(p:first-child strong) {
  @apply text-horizon-500 font-bold;
}
.news-article :deep(ul) {
  @apply text-sm text-neutral-500 leading-relaxed mb-3 ml-5;
  list-style: disc;
}
.news-article :deep(li) {
  @apply mb-1;
}
.news-article :deep(a) {
  @apply text-raspberry-500 underline hover:text-raspberry-600;
}
.news-article :deep(strong) {
  @apply text-horizon-500 font-semibold;
}
</style>
