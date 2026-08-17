<template>
  <div class="card">
    <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Version history</h3>

    <div v-if="loading" class="flex justify-center py-6">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <p v-else-if="error" class="text-sm text-raspberry-500">{{ error }}</p>

    <p v-else-if="!versions.length" class="text-sm text-neutral-500">
      No earlier versions yet. One is saved every time this article changes, including when it is
      re-imported from the Word document.
    </p>

    <template v-else>
      <ul class="space-y-2">
        <li
          v-for="(version, index) in versions"
          :key="version.id"
          class="border border-neutral-200 rounded-lg p-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-horizon-500 truncate">{{ version.title }}</p>
              <p class="text-xs text-neutral-500 mt-0.5">
                {{ formatSavedAt(version.saved_at) }} · {{ authorFor(version) }}
              </p>
            </div>
            <span
              v-if="index === 0"
              class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded bg-eggshell-500 text-horizon-500"
            >Current</span>
            <button
              v-else
              @click="restore(version)"
              :disabled="restoringId !== null"
              class="shrink-0 px-3 py-1 text-xs rounded font-semibold"
              :class="restoringId !== null
                ? 'bg-neutral-100 text-neutral-400 cursor-not-allowed'
                : 'bg-horizon-500 text-white hover:bg-horizon-600'"
            >
              {{ restoringId === version.id ? 'Restoring…' : 'Restore' }}
            </button>
          </div>
        </li>
      </ul>

      <p class="text-xs text-neutral-500 mt-3">
        The {{ versions.length }} most recent versions are shown. Restoring makes a new version, so
        nothing is lost and you can undo a restore.
      </p>
    </template>
  </div>
</template>

<script>
import { formatDateLong } from '@/utils/dateFormatter';
import insightsService from '@/services/insightsService';

const SOURCE_LABELS = {
  'drive-import': 'Imported from the Word document',
};

export default {
  name: 'ArticleVersionHistory',
  props: {
    articleId: { type: [Number, String], required: true },
  },
  emits: ['restored'],
  data() {
    return {
      versions: [],
      loading: false,
      error: null,
      restoringId: null,
    };
  },
  watch: {
    articleId: { immediate: true, handler: 'load' },
  },
  methods: {
    async load() {
      if (!this.articleId) return;
      this.loading = true;
      this.error = null;
      try {
        const response = await insightsService.revisions(this.articleId);
        this.versions = response.data ?? [];
      } catch {
        this.error = 'Could not load version history.';
      } finally {
        this.loading = false;
      }
    },
    async restore(version) {
      const when = this.formatSavedAt(version.saved_at);
      if (!window.confirm(
        `Restore the version saved ${when}? The current version is kept, so you can undo this.`
      )) return;

      this.restoringId = version.id;
      try {
        await insightsService.restoreRevision(this.articleId, version.id);
        await this.load();
        this.$emit('restored');
      } catch {
        this.error = 'Restore failed. The article has not been changed.';
      } finally {
        this.restoringId = null;
      }
    },
    // A person's save is attributed to them; an automated import has no user.
    // The eager-loaded savedBy relation serialises over the saved_by column, so
    // this arrives as an object when a person saved it and null when not.
    authorFor(version) {
      const user = version.saved_by;
      if (user && typeof user === 'object') {
        return [user.first_name, user.surname].filter(Boolean).join(' ') || 'A colleague';
      }
      return SOURCE_LABELS[version.source] ?? 'Automated update';
    },
    formatSavedAt(value) {
      if (!value) return 'date unknown';
      const date = new Date(value);
      return `${formatDateLong(date)} at ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
    },
  },
};
</script>
