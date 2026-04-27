<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla
          <span class="text-raspberry-300">news</span>
        </h1>
        <p class="text-lg text-white/70">
          Announcements, product updates and stories from the Fynla team.
        </p>
      </div>
    </div>

    <!-- Article list -->
    <section class="bg-eggshell-500 py-14 min-h-[60vh]">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- RSS subscription bar (whole row clickable, non-coloured icon) -->
        <a href="/feed/news.xml" target="_blank" rel="noopener noreferrer" class="group block bg-light-pink-100 rounded-xl px-5 py-4 mb-10 no-underline hover:bg-light-pink-100/80 transition-colors">
          <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a14 14 0 0114 14M5 11a8 8 0 018 8M6.5 18.5a1 1 0 11-2 0 1 1 0 012 0z" />
            </svg>
            <div class="flex-1">
              <p class="text-sm font-semibold text-horizon-500 leading-tight group-hover:text-raspberry-500 transition-colors">Subscribe to our news feed</p>
              <p class="text-xs text-neutral-500 mt-0.5">Get every Fynla announcement in your reader of choice.</p>
            </div>
            <svg class="w-4 h-4 text-horizon-500 flex-shrink-0 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
          </div>
        </a>

        <div v-if="loading" class="text-center text-neutral-500 py-12">
          Loading news…
        </div>

        <div v-else-if="error" class="text-center text-raspberry-500 py-12">
          {{ error }}
        </div>

        <div v-else-if="articles.length === 0" class="text-center text-neutral-500 py-12">
          No news articles published yet — check back soon.
        </div>

        <template v-else>
          <!-- Featured (first article) — full-width hero card -->
          <router-link
            v-if="featuredArticle"
            :to="`/news/${featuredArticle.slug}`"
            class="group relative block rounded-3xl overflow-hidden bg-gradient-to-br from-horizon-500 to-horizon-700 min-h-[360px] lg:min-h-[480px] mb-10 no-underline"
          >
            <div class="absolute -top-20 -right-20 w-[28rem] h-[28rem] bg-raspberry-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -left-20 w-[28rem] h-[28rem] bg-raspberry-500/10 rounded-full blur-3xl"></div>

            <div class="relative h-full flex flex-col justify-end p-8 md:p-14">
              <div class="flex items-center gap-2 mb-3">
                <span class="text-[0.65rem] font-bold px-2 py-1 rounded-md uppercase tracking-wider bg-raspberry-500 text-white">Latest</span>
              </div>
              <p class="text-xs text-white/70 mb-2">
                {{ formatDate(featuredArticle.published_at) }}
                <span v-if="featuredArticle.author_name">&middot; By {{ featuredArticle.author_name }}</span>
              </p>
              <h2 class="text-3xl md:text-5xl font-bold text-white mb-4 leading-tight group-hover:text-raspberry-300 transition-colors max-w-4xl" style="letter-spacing:-0.02em;">
                {{ featuredArticle.title }}
              </h2>
              <p class="text-base md:text-lg text-white/80 mb-6 leading-relaxed max-w-3xl">
                {{ featuredArticle.summary }}
              </p>
              <span class="inline-flex items-center gap-1 text-sm font-semibold text-white">
                Read the full announcement
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </span>
            </div>
          </router-link>

          <!-- Recent articles — 3-col grid -->
          <div v-if="recentArticles.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <router-link
              v-for="article in recentArticles"
              :key="article.id"
              :to="`/news/${article.slug}`"
              class="group relative block rounded-3xl overflow-hidden bg-white hover:shadow-xl transition-all border border-light-gray no-underline"
            >
              <div class="h-full p-6 flex flex-col">
                <p class="text-[0.65rem] text-neutral-400 mb-1 uppercase tracking-wide">{{ formatDate(article.published_at) }}</p>
                <h3 class="text-lg font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-2 leading-tight">
                  {{ article.title }}
                </h3>
                <p class="text-sm text-neutral-500 leading-relaxed flex-1 mb-3">{{ article.summary }}</p>
                <span class="inline-flex items-center gap-1 text-xs font-semibold text-raspberry-500">
                  Read more
                  <svg class="w-3 h-3 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                </span>
              </div>
            </router-link>
          </div>
        </template>

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

      </div>
    </section>

    <!-- Stay updated CTA -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Want to stay updated?</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          Register for a free Fynla account and we'll let you know when new product updates and announcements go live.
        </p>
        <router-link
          to="/register"
          class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Register for free
        </router-link>
        <p class="text-xs text-neutral-400 mt-6">
          Or subscribe via
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

  computed: {
    featuredArticle() {
      return this.articles[0] || null;
    },
    recentArticles() {
      return this.articles.slice(1);
    },
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
