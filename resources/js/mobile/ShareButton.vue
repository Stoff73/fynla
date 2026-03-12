<template>
  <button
    class="share-button p-2 text-horizon-500"
    :disabled="loading"
    @click="handleShare"
  >
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
    </svg>
  </button>
</template>

<script>
import api from '@/services/api';
import { platform } from '@/utils/platform';

export default {
  name: 'ShareButton',
  props: {
    shareType: { type: String, required: true },
    entityId: { type: [String, Number], default: null },
  },

  data() {
    return { loading: false };
  },

  methods: {
    async handleShare() {
      this.loading = true;
      try {
        const url = this.entityId
          ? `/v1/mobile/share/${this.shareType}/${this.entityId}`
          : `/v1/mobile/share/${this.shareType}`;

        const response = await api.get(url);
        const payload = response.data.data;

        if (platform.isNative()) {
          const { Share } = await import('@capacitor/share');
          await Share.share({
            title: payload.title,
            text: payload.text,
            url: payload.url,
            dialogTitle: 'Share via',
          });
        } else if (navigator.share) {
          await navigator.share({
            title: payload.title,
            text: payload.text,
            url: payload.url,
          });
        } else {
          await navigator.clipboard.writeText(`${payload.text} ${payload.url}`);
        }
      } catch {
        // User cancelled share or error — silent fail
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
