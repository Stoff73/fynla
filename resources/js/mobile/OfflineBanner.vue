<template>
  <transition name="offline-slide">
    <div
      v-if="isOffline"
      role="alert"
      aria-live="assertive"
      class="bg-savannah-200 border-b border-savannah-300 px-4 sm:px-6 lg:px-8 py-2.5"
    >
      <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
          <!-- Wifi-off icon -->
          <svg
            class="w-5 h-5 text-horizon-600 flex-shrink-0"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 12h.01M3 3l18 18"
            />
          </svg>
          <span class="text-sm font-medium text-horizon-600">
            Offline — showing last updated data
          </span>
        </div>
        <span
          v-if="lastUpdatedText"
          class="text-xs text-neutral-500 flex-shrink-0 hidden sm:inline"
        >
          {{ lastUpdatedText }}
        </span>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'OfflineBanner',

  data() {
    return {
      isOffline: !navigator.onLine,
      lastUpdatedAt: null,
    };
  },

  computed: {
    lastUpdatedText() {
      if (!this.lastUpdatedAt) return '';
      const date = new Date(this.lastUpdatedAt);
      const hours = date.getHours().toString().padStart(2, '0');
      const minutes = date.getMinutes().toString().padStart(2, '0');
      return `Last updated at ${hours}:${minutes}`;
    },
  },

  mounted() {
    // Set initial last-updated timestamp (the moment we know data was fresh)
    if (navigator.onLine) {
      this.lastUpdatedAt = Date.now();
    }

    window.addEventListener('online', this.handleOnline);
    window.addEventListener('offline', this.handleOffline);
  },

  beforeUnmount() {
    window.removeEventListener('online', this.handleOnline);
    window.removeEventListener('offline', this.handleOffline);
  },

  methods: {
    handleOnline() {
      this.isOffline = false;
      // Update the timestamp since we are back online and data is fresh
      this.lastUpdatedAt = Date.now();
    },

    handleOffline() {
      this.isOffline = true;
      // If we never recorded a timestamp, set it now as the last known-good time
      if (!this.lastUpdatedAt) {
        this.lastUpdatedAt = Date.now();
      }
    },
  },
};
</script>

<style scoped>
.offline-slide-enter-active,
.offline-slide-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.offline-slide-enter-from,
.offline-slide-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.offline-slide-enter-to,
.offline-slide-leave-from {
  max-height: 60px;
  opacity: 1;
}
</style>
