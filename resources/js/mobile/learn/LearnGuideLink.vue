<template>
  <button
    class="w-full bg-white rounded-xl border border-light-gray p-4 flex items-center gap-3
           text-left active:bg-savannah-100 transition-colors"
    @click="openGuide"
  >
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-medium text-horizon-500 truncate">{{ guide.title }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">{{ guide.source }}</p>
    </div>
    <svg class="w-4 h-4 text-neutral-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
  </button>
</template>

<script>
export default {
  name: 'LearnGuideLink',

  props: {
    guide: {
      type: Object,
      required: true,
    },
  },

  methods: {
    async openGuide() {
      const url = this.guide.url;
      if (!url) return;

      // Use Capacitor Browser plugin on native
      try {
        const { Browser } = await import('@capacitor/browser');
        await Browser.open({ url });
      } catch {
        // Fallback to standard browser open
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    },
  },
};
</script>
