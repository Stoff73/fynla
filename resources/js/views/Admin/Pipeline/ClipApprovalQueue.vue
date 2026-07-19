<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">Admin › Pipeline › Clip approvals</p>
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Clips awaiting approval</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Approve or reject each clip before it's composed into posts. Anything still pending
          <strong>{{ autoApproveMinutes }} min</strong> before its scheduled post time is auto-approved.
        </p>
      </header>

      <div class="flex flex-wrap gap-3 mb-6">
        <select v-model="filters.status" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="auto_approved">Auto-approved</option>
          <option value="rejected">Rejected</option>
          <option value="">All statuses</option>
        </select>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="groups.length === 0" class="text-center py-16 text-neutral-500">
        No clips waiting.
      </div>

      <div v-else class="space-y-6">
        <article
          v-for="group in groups"
          :key="group.article_id"
          class="card"
        >
          <header class="flex items-start justify-between mb-4">
            <div>
              <p class="text-xs font-mono text-neutral-500 mb-1">{{ group.article_slug }}</p>
              <h3 class="text-lg font-bold text-horizon-500">{{ group.article_title }}</h3>
              <p class="text-xs text-neutral-500 mt-1">
                {{ group.pending_count }} of {{ group.total_clips }} pending · earliest post
                {{ formatHumanTime(group.earliest_scheduled_at) }}
              </p>
            </div>
            <button
              v-if="group.pending_count > 0"
              @click="approveAll(group)"
              class="px-4 py-2 text-sm bg-spring-500 text-white rounded font-semibold hover:bg-spring-600"
            >
              Approve all ({{ group.pending_count }})
            </button>
          </header>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="clip in group.clips"
              :key="clip.id"
              class="border border-savannah-100 rounded-lg p-3"
              :class="statusBg(clip.status)"
            >
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-horizon-500">Clip {{ clip.clip_index }}</span>
                <span
                  class="text-xs font-semibold px-2 py-0.5 rounded"
                  :class="statusPill(clip.status)"
                >{{ clip.status.replace('_', ' ') }}</span>
              </div>

              <div v-if="clip.preview_url" class="mb-2 rounded overflow-hidden bg-black" style="aspect-ratio: 9/16; max-height: 320px;">
                <video :src="clip.preview_url" controls preload="metadata" style="width: 100%; height: 100%; object-fit: contain;"></video>
              </div>

              <p class="text-xs text-horizon-500 mb-3">
                <strong>{{ clip.scheduled_day }}</strong>, {{ clip.scheduled_date_human }} at
                <strong>{{ clip.scheduled_time }}</strong>
              </p>

              <div v-if="clip.status === 'pending'" class="flex gap-2">
                <button
                  @click="approve(clip)"
                  class="flex-1 px-3 py-1.5 text-xs bg-spring-500 text-white rounded font-semibold hover:bg-spring-600"
                >Approve</button>
                <button
                  @click="reject(clip)"
                  class="flex-1 px-3 py-1.5 text-xs border border-raspberry-500 text-raspberry-500 rounded font-semibold hover:bg-raspberry-50"
                >Reject</button>
              </div>
              <p v-else-if="clip.status === 'rejected'" class="text-xs text-raspberry-500 italic">
                {{ clip.rejection_reason || 'Rejected.' }}
              </p>
              <p v-else class="text-xs text-neutral-500">
                {{ clip.approved_via_email ? 'via email' : 'via admin' }} · {{ formatHumanTime(clip.approved_at) }}
              </p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import pipelineClipApprovalsService from '@/services/pipelineClipApprovalsService';

export default {
  name: 'ClipApprovalQueue',
  components: { AppLayout },
  data() {
    return {
      loading: true,
      groups: [],
      filters: { status: 'pending' },
      autoApproveMinutes: 10,
    };
  },
  watch: {
    filters: { handler: 'refresh', deep: true },
  },
  async mounted() {
    await this.refresh();
  },
  methods: {
    async refresh() {
      this.loading = true;
      try {
        const params = {};
        if (this.filters.status) params.status = this.filters.status;
        if (this.$route.query.article_id) params.article_id = this.$route.query.article_id;
        const res = await pipelineClipApprovalsService.list(params);
        this.groups = res.data ?? [];
      } finally {
        this.loading = false;
      }
    },
    async approve(clip) {
      try {
        await pipelineClipApprovalsService.approve(clip.id);
        await this.refresh();
      } catch (e) {
        alert('Approve failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    async reject(clip) {
      const reason = window.prompt('Reason for rejecting clip ' + clip.clip_index + '?');
      if (!reason) return;
      try {
        await pipelineClipApprovalsService.reject(clip.id, reason);
        await this.refresh();
      } catch (e) {
        alert('Reject failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    async approveAll(group) {
      if (!confirm(`Approve all ${group.pending_count} pending clip(s) for "${group.article_title}"?`)) return;
      try {
        await pipelineClipApprovalsService.approveAll(group.article_id);
        await this.refresh();
      } catch (e) {
        alert('Approve all failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    statusPill(status) {
      return {
        pending: 'bg-violet-100 text-violet-500',
        approved: 'bg-spring-100 text-spring-500',
        auto_approved: 'bg-spring-100 text-spring-500',
        rejected: 'bg-raspberry-100 text-raspberry-500',
      }[status] ?? 'bg-neutral-100 text-neutral-500';
    },
    statusBg(status) {
      return {
        pending: 'bg-white',
        approved: 'bg-spring-50',
        auto_approved: 'bg-spring-50',
        rejected: 'bg-raspberry-50',
      }[status] ?? 'bg-white';
    },
    formatHumanTime(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      const diffMin = Math.round((d.getTime() - Date.now()) / 60000);
      if (Math.abs(diffMin) < 60) return `${Math.abs(diffMin)}m ${diffMin < 0 ? 'ago' : 'from now'}`;
      const diffH = Math.round(diffMin / 60);
      if (Math.abs(diffH) < 48) return `${Math.abs(diffH)}h ${diffH < 0 ? 'ago' : 'from now'}`;
      return d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    },
  },
};
</script>
