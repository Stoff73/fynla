<template>
  <div v-if="impersonating" class="fixed top-0 left-0 right-0 z-50 bg-violet-500 text-white px-6 py-2.5 flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-3">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
      <span class="text-sm font-semibold">
        You are viewing <strong>{{ clientName }}</strong>'s profile as their advisor
      </span>
    </div>
    <button
      @click="exitImpersonation"
      class="px-4 py-1.5 bg-white text-violet-500 text-sm font-semibold rounded-lg hover:bg-violet-50 transition-colors"
    >
      Exit
    </button>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';

import logger from '@/utils/logger';
export default {
  name: 'AdvisorBanner',

  computed: {
    ...mapGetters('advisor', ['impersonating', 'impersonatedClient']),

    clientName() {
      if (!this.impersonatedClient) return '';
      return `${this.impersonatedClient.first_name} ${this.impersonatedClient.surname || this.impersonatedClient.last_name || ''}`.trim();
    },
  },

  methods: {
    ...mapActions('advisor', ['exitClient']),

    async exitImpersonation() {
      try {
        await this.exitClient();
        this.$router.push({ name: 'AdvisorDashboard' });
      } catch (error) {
        logger.error('Failed to exit impersonation:', error);
      }
    },
  },
};
</script>
