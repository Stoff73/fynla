<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex items-center justify-between mb-6">
        <div>
          <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">Admin › Pipeline › Articles</p>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Article manager</h1>
          <p class="text-sm text-neutral-500 mt-1">Word docs auto-import from Marketing Automation → Articles daily at 06:45.</p>
        </div>
      </header>

      <section v-if="syncStatus" class="mb-5">
        <div
          class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm border"
          :class="statusBandClass"
        >
          <span class="text-lg" aria-hidden="true">{{ statusBandIcon }}</span>
          <div class="flex-1">
            <p class="font-semibold" :class="statusBandTextClass">
              {{ statusBandHeading }}
            </p>
            <p class="text-xs mt-0.5" :class="statusBandTextClass">
              <span class="mr-3">Dev: {{ envStatusLabel(syncStatus.dev) }}</span>
              <span class="mr-3">Live: {{ envStatusLabel(syncStatus.prod) }}</span>
              <span>Inbound token on this env: {{ syncStatus.inbound.configured ? '✓' : '—' }}</span>
            </p>
          </div>
          <button
            @click="refreshSyncStatus"
            class="text-xs px-2 py-1 border rounded"
            :class="statusBandTextClass"
          >Re-check</button>
        </div>
      </section>

      <div class="flex flex-wrap gap-3 mb-6">
        <select v-model="filters.status" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
        <select v-model="filters.campaign_id" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All campaigns</option>
          <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search title or slug…"
          class="flex-1 min-w-[220px] px-3 py-2 text-sm border border-light-gray rounded-lg"
        />
        <label class="flex items-center gap-2 text-sm text-horizon-500">
          <input type="checkbox" v-model="filters.has_docx" />
          From Word only
        </label>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="articles.length === 0" class="text-center py-16 text-neutral-500">
        No articles match the current filters.
      </div>

      <div v-else class="card overflow-hidden p-0">
        <table class="w-full text-sm">
          <thead class="bg-eggshell-50 text-horizon-500 font-semibold">
            <tr>
              <th class="text-left px-4 py-3">Article</th>
              <th class="text-left px-4 py-3 w-32">Category</th>
              <th class="text-center px-2 py-3 w-20">Local</th>
              <th class="text-center px-2 py-3 w-20">Dev</th>
              <th class="text-center px-2 py-3 w-20">Live</th>
              <th class="text-left px-4 py-3 w-40">Campaign</th>
              <th class="text-right px-4 py-3 w-56">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="article in articles"
              :key="article.id"
              class="border-t border-savannah-100 hover:bg-eggshell-50"
            >
              <td class="px-4 py-3 align-top">
                <p class="font-semibold text-horizon-500">{{ article.title }}</p>
                <p class="text-xs text-neutral-500 font-mono mt-0.5">{{ article.slug }}</p>
                <p v-if="article.source_docx.drive_file_id" class="text-xs text-horizon-400 mt-1">
                  <i class="ti ti-file-word text-horizon-500"></i>
                  From Word · imported {{ humanTime(article.source_docx.imported_at) }}
                </p>
              </td>
              <td class="px-4 py-3 align-top text-horizon-400">{{ article.category }}</td>
              <td class="px-2 py-3 align-top text-center">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="envPillClass(article.env_state.local_status)"
                >{{ envPillLabel(article.env_state.local_status) }}</span>
              </td>
              <td class="px-2 py-3 align-top text-center">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="envPillClass(article.env_state.dev_synced_at ? 'live' : 'none')"
                >{{ article.env_state.dev_synced_at ? '✓ Live' : '—' }}</span>
              </td>
              <td class="px-2 py-3 align-top text-center">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="envPillClass(article.env_state.prod_synced_at ? 'live' : 'none')"
                >{{ article.env_state.prod_synced_at ? '✓ Live' : '—' }}</span>
              </td>
              <td class="px-4 py-3 align-top text-horizon-400">
                <span v-if="article.campaign">{{ article.campaign.name }}</span>
                <span v-else class="italic text-neutral-400">—</span>
              </td>
              <td class="px-4 py-3 align-top text-right space-x-1">
                <router-link
                  :to="`/admin/pipeline/articles/${article.id}`"
                  class="inline-block px-3 py-1.5 text-xs border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50"
                >
                  Open
                </router-link>
                <button
                  v-if="article.actions.can_push_to_live"
                  @click="pushToLive(article)"
                  class="px-3 py-1.5 text-xs bg-spring-500 text-white rounded font-semibold hover:bg-spring-600"
                >
                  Push to live
                </button>
                <button
                  v-else-if="article.actions.can_push_to_dev"
                  @click="pushToDev(article)"
                  class="px-3 py-1.5 text-xs bg-raspberry-500 text-white rounded font-semibold hover:bg-raspberry-600"
                >
                  Push to dev
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import pipelineArticlesService from '@/services/pipelineArticlesService';

export default {
  name: 'ArticleManager',
  components: { AppLayout },
  data() {
    return {
      loading: true,
      articles: [],
      campaigns: [],
      syncStatus: null,
      filters: { status: '', campaign_id: '', search: '', has_docx: false },
    };
  },
  computed: {
    worstEnvStatus() {
      if (!this.syncStatus) return 'unknown';
      const envs = [this.syncStatus.dev, this.syncStatus.prod];
      if (envs.some(e => e.configured && e.reachable && e.token_valid)) {
        if (envs.every(e => e.configured && e.reachable && e.token_valid)) return 'green';
        return 'amber';
      }
      if (envs.every(e => !e.configured)) return 'red';
      return 'amber';
    },
    statusBandClass() {
      return {
        green: 'bg-spring-50 border-spring-200',
        amber: 'bg-violet-50 border-violet-200',
        red: 'bg-raspberry-50 border-raspberry-200',
        unknown: 'bg-savannah-50 border-savannah-200',
      }[this.worstEnvStatus];
    },
    statusBandTextClass() {
      return {
        green: 'text-spring-500',
        amber: 'text-violet-500',
        red: 'text-raspberry-500',
        unknown: 'text-horizon-500',
      }[this.worstEnvStatus];
    },
    statusBandIcon() {
      return { green: '✓', amber: '⚠', red: '⚠', unknown: '…' }[this.worstEnvStatus];
    },
    statusBandHeading() {
      return {
        green: 'Cross-env sync is healthy',
        amber: 'Cross-env sync has warnings — dev or live may fail',
        red: 'Cross-env sync not configured — dev + live push are unavailable',
        unknown: 'Checking sync credentials…',
      }[this.worstEnvStatus];
    },
  },
  watch: {
    filters: { handler: 'refresh', deep: true },
  },
  async mounted() {
    await Promise.all([this.loadCampaigns(), this.refreshSyncStatus()]);
    await this.refresh();
  },
  methods: {
    async refresh() {
      this.loading = true;
      try {
        const params = {};
        if (this.filters.status) params.status = this.filters.status;
        if (this.filters.campaign_id) params.campaign_id = this.filters.campaign_id;
        if (this.filters.search) params.search = this.filters.search;
        if (this.filters.has_docx) params.has_docx = 1;
        const res = await pipelineArticlesService.list(params);
        this.articles = res.data?.data ?? [];
      } finally {
        this.loading = false;
      }
    },
    async loadCampaigns() {
      const res = await pipelineArticlesService.listCampaigns();
      this.campaigns = res.data?.data ?? [];
    },
    async refreshSyncStatus() {
      try {
        const res = await pipelineArticlesService.syncStatus();
        this.syncStatus = res.data ?? null;
      } catch {
        this.syncStatus = null;
      }
    },
    envStatusLabel(env) {
      if (!env) return '—';
      if (!env.configured) return 'not configured';
      if (env.reachable === false) return 'unreachable';
      if (env.token_valid === false) return 'token invalid';
      if (env.token_valid === true) return '✓ ready';
      return 'partial';
    },
    async pushToDev(article) {
      if (!confirm(`Push "${article.title}" to dev (csjones.co/fynla)?`)) return;
      try {
        await pipelineArticlesService.pushToDev(article.id);
        await this.refresh();
      } catch (e) {
        alert('Push to dev failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    async pushToLive(article) {
      if (!confirm(`Push "${article.title}" LIVE on fynla.org?\n\nThis is a live publish — the article will be visible to real visitors immediately.`)) return;
      try {
        await pipelineArticlesService.pushToLive(article.id);
        await this.refresh();
      } catch (e) {
        alert('Push to live failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    envPillClass(state) {
      return {
        live: 'bg-spring-100 text-spring-500',
        draft: 'bg-horizon-100 text-horizon-500',
        pending: 'bg-violet-100 text-violet-500',
        none: 'bg-neutral-100 text-neutral-400',
        archived: 'bg-neutral-100 text-neutral-500',
      }[state] ?? 'bg-neutral-100 text-neutral-400';
    },
    envPillLabel(state) {
      return { live: '✓ Live', draft: 'Draft', archived: 'Archived', none: '—' }[state] ?? state;
    },
    humanTime(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      const diffMin = Math.round((Date.now() - d.getTime()) / 60000);
      if (diffMin < 60) return `${diffMin}m ago`;
      if (diffMin < 60 * 24) return `${Math.round(diffMin / 60)}h ago`;
      return `${Math.round(diffMin / (60 * 24))}d ago`;
    },
  },
};
</script>
