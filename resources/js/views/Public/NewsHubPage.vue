<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla
          <span class="text-raspberry-300">News</span>
        </h1>
        <p class="text-lg text-white/70">
          Announcements, product updates and stories from the Fynla team.
        </p>
      </div>
    </div>

    <!-- Article list -->
    <section class="bg-eggshell-500 py-14 min-h-[60vh]">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div v-if="loading" class="text-center text-neutral-500 py-12">
          Loading news…
        </div>

        <div v-else-if="error" class="text-center text-raspberry-500 py-12">
          {{ error }}
        </div>

        <div v-else-if="articles.length === 0" class="text-center text-neutral-500 py-12">
          No news articles published yet — check back soon.
        </div>

        <div v-else class="space-y-6">
          <article
            v-for="article in articles"
            :key="article.id"
            class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-light-gray hover:shadow-md transition-shadow"
          >
            <p class="text-sm text-neutral-500 mb-2">
              {{ formatDate(article.published_at) }}
              <span v-if="article.author_name" class="text-neutral-400">&middot; By {{ article.author_name }}</span>
            </p>
            <h2 class="text-2xl sm:text-3xl font-bold text-horizon-500 mb-3">
              <router-link :to="`/news/${article.slug}`" class="hover:text-raspberry-500 transition-colors">
                {{ article.title }}
              </router-link>
            </h2>
            <p class="text-base text-neutral-500 leading-relaxed mb-4">
              {{ article.summary }}
            </p>
            <router-link
              :to="`/news/${article.slug}`"
              class="inline-flex items-center gap-1 text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 transition-colors"
            >
              Read full article
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
            </router-link>
          </article>
        </div>

        <!-- Pagination — only renders when more than one page exists -->
        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-10">
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold text-horizon-500 disabled:opacity-40 disabled:cursor-not-allowed hover:text-raspberry-500 transition-colors"
            :disabled="page <= 1"
            @click="changePage(page - 1)"
          >
            Previous
          </button>
          <span class="text-sm text-neutral-500">Page {{ page }} of {{ meta.last_page }}</span>
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold text-horizon-500 disabled:opacity-40 disabled:cursor-not-allowed hover:text-raspberry-500 transition-colors"
            :disabled="page >= meta.last_page"
            @click="changePage(page + 1)"
          >
            Next
          </button>
        </div>

        <!-- RSS link -->
        <p class="text-xs text-neutral-400 text-center mt-10">
          Subscribe via
          <a href="/feed/news.xml" class="underline hover:text-raspberry-500 transition-colors">RSS</a>
        </p>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import newsService from '@/services/newsService';

export default {
  name: 'NewsHubPage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      articles: [],
      meta: null,
      page: 1,
      loading: true,
      error: null,
    };
  },

  mounted() {
    document.title = 'News | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Announcements, product updates and stories from the Fynla team.');
    }
    this.fetchArticles();
  },

  methods: {
    async fetchArticles() {
      this.loading = true;
      this.error = null;
      try {
        const response = await newsService.list({ page: this.page });
        this.articles = response.data || [];
        this.meta = response.meta || null;
      } catch (err) {
        this.error = 'Could not load news articles. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    changePage(newPage) {
      if (newPage < 1 || (this.meta && newPage > this.meta.last_page)) return;
      this.page = newPage;
      this.fetchArticles();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    formatDate(iso) {
      if (!iso) return '';
      const date = new Date(iso);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },
  },
};
</script>
