<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Social Post Approvals</h1>
          <p class="text-sm text-neutral-500 mt-1">
            Review, edit, and approve Stage 4 posts before they're scheduled.
          </p>
        </div>
      </header>

      <div class="flex flex-wrap gap-3 mb-6">
        <select v-model="filters.status" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="awaiting_approval">Awaiting approval</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="scheduled">Scheduled</option>
          <option value="published">Published</option>
          <option value="failed">Failed</option>
          <option value="">All</option>
        </select>
        <select v-model="filters.platform" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All platforms</option>
          <option value="instagram">Instagram</option>
          <option value="facebook">Facebook</option>
          <option value="tiktok">TikTok</option>
        </select>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="posts.length === 0" class="text-center py-16 text-neutral-500">
        No posts match the current filters.
      </div>

      <div v-else class="space-y-4">
        <article
          v-for="post in posts"
          :key="post.id"
          class="card"
        >
          <header class="flex items-start justify-between mb-3">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span
                  class="px-2 py-0.5 text-xs font-semibold rounded"
                  :class="platformBadgeClass(post.platform)"
                >
                  {{ post.platform }}
                </span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded bg-savannah-100 text-horizon-500">
                  Variant {{ post.variant }}
                </span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded bg-neutral-100 text-neutral-500">
                  Clip {{ post.clip_index }}
                </span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded" :class="statusBadgeClass(post.status)">
                  {{ post.status.replace('_', ' ') }}
                </span>
              </div>
              <h3 class="text-base font-bold text-horizon-500">
                {{ post.pipeline_article?.insight_article?.title ?? '(unknown article)' }}
              </h3>
            </div>
          </header>

          <div class="mb-3">
            <label class="text-xs font-semibold uppercase text-neutral-500 block mb-1">Caption</label>
            <textarea
              v-model="post.caption"
              rows="5"
              class="w-full px-3 py-2 text-sm border border-light-gray rounded-lg font-mono"
              :disabled="post.status !== 'awaiting_approval'"
            ></textarea>
          </div>

          <div class="mb-3">
            <label class="text-xs font-semibold uppercase text-neutral-500 block mb-1">Hashtags (2–5)</label>
            <input
              :value="post.hashtags?.join(' ')"
              @input="post.hashtags = $event.target.value.trim().split(/\s+/).filter(Boolean)"
              class="w-full px-3 py-2 text-sm border border-light-gray rounded-lg font-mono"
              :disabled="post.status !== 'awaiting_approval'"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div>
              <label class="text-xs font-semibold uppercase text-neutral-500 block mb-1">Destination</label>
              <select
                v-model="post.destination_type"
                class="w-full px-3 py-2 text-sm border border-light-gray rounded-lg"
                :disabled="post.status !== 'awaiting_approval'"
              >
                <option value="article">Article (default)</option>
                <option value="custom">Custom URL</option>
                <option value="campaign">Campaign landing page</option>
              </select>
            </div>
            <div v-if="post.destination_type === 'custom'" class="sm:col-span-2">
              <label class="text-xs font-semibold uppercase text-neutral-500 block mb-1">Custom URL</label>
              <input
                v-model="post.destination_url_final"
                type="url"
                class="w-full px-3 py-2 text-sm border border-light-gray rounded-lg"
                :disabled="post.status !== 'awaiting_approval'"
              />
            </div>
            <div v-if="post.destination_type === 'campaign'" class="sm:col-span-2">
              <label class="text-xs font-semibold uppercase text-neutral-500 block mb-1">Campaign</label>
              <select
                v-model="post.pipeline_campaign_id"
                class="w-full px-3 py-2 text-sm border border-light-gray rounded-lg"
                :disabled="post.status !== 'awaiting_approval'"
              >
                <option :value="null">— Pick a campaign —</option>
                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <footer class="flex items-center justify-between pt-3 border-t border-savannah-100">
            <p class="text-xs text-neutral-500 truncate flex-1 mr-3">
              Default URL: <code class="bg-savannah-50 px-1 rounded">{{ post.destination_url_default }}</code>
            </p>
            <div class="flex items-center gap-2">
              <button
                v-if="post.status === 'awaiting_approval'"
                @click="save(post)"
                class="px-3 py-1.5 text-xs border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50"
              >
                Save changes
              </button>
              <button
                v-if="post.status === 'awaiting_approval'"
                @click="reject(post)"
                class="px-3 py-1.5 text-xs border border-raspberry-500 text-raspberry-500 rounded hover:bg-raspberry-50"
              >
                Reject
              </button>
              <button
                v-if="post.status === 'awaiting_approval'"
                @click="approve(post)"
                class="px-3 py-1.5 text-xs bg-raspberry-500 text-white rounded font-semibold hover:bg-raspberry-600"
              >
                Approve
              </button>
            </div>
          </footer>
        </article>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import pipelinePostsService from '@/services/pipelinePostsService';

export default {
  name: 'PostApprovalQueue',
  components: { AppLayout },
  data() {
    return {
      loading: true,
      posts: [],
      campaigns: [],
      filters: {
        status: 'awaiting_approval',
        platform: '',
      },
    };
  },
  watch: {
    filters: {
      handler: 'refresh',
      deep: true,
    },
  },
  async mounted() {
    await this.loadCampaigns();
    await this.refresh();
  },
  methods: {
    async refresh() {
      this.loading = true;
      try {
        const query = {};
        if (this.filters.status) query.status = this.filters.status;
        if (this.filters.platform) query.platform = this.filters.platform;
        if (this.$route.query.article_id) query.article_id = this.$route.query.article_id;
        const res = await pipelinePostsService.list(query);
        this.posts = res.data?.data ?? [];
      } finally {
        this.loading = false;
      }
    },
    async loadCampaigns() {
      const res = await pipelinePostsService.listCampaigns({ active_only: true });
      this.campaigns = res.data?.data ?? [];
    },
    async save(post) {
      await pipelinePostsService.update(post.id, {
        caption: post.caption,
        hashtags: post.hashtags,
        destination_type: post.destination_type,
        destination_url_final: post.destination_url_final,
        pipeline_campaign_id: post.pipeline_campaign_id,
      });
    },
    async approve(post) {
      await this.save(post);
      await pipelinePostsService.approve(post.id);
      await this.refresh();
    },
    async reject(post) {
      const reason = window.prompt('Why is this being rejected?');
      if (!reason) return;
      await pipelinePostsService.reject(post.id, reason);
      await this.refresh();
    },
    platformBadgeClass(platform) {
      return {
        instagram: 'bg-raspberry-100 text-raspberry-500',
        facebook: 'bg-horizon-100 text-horizon-500',
        tiktok: 'bg-neutral-800 text-white',
      }[platform] ?? 'bg-neutral-100 text-neutral-500';
    },
    statusBadgeClass(status) {
      return {
        awaiting_approval: 'bg-violet-100 text-violet-500',
        approved: 'bg-spring-100 text-spring-500',
        rejected: 'bg-raspberry-100 text-raspberry-500',
        scheduled: 'bg-horizon-100 text-horizon-500',
        published: 'bg-spring-100 text-spring-500',
        failed: 'bg-raspberry-100 text-raspberry-500',
      }[status] ?? 'bg-neutral-100 text-neutral-500';
    },
  },
};
</script>
