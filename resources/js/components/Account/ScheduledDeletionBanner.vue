<template>
  <div v-if="scheduled" class="bg-violet-100 border-b border-violet-300 px-4 py-3">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
      <p class="text-body-sm text-violet-700">
        Your account is scheduled for deletion on <strong>{{ formattedDate }}</strong>
        ({{ daysRemaining }} {{ daysRemaining === 1 ? 'day' : 'days' }}).
      </p>
      <button class="btn-secondary text-body-sm" :disabled="cancelling" @click="cancelDeletion">
        <span v-if="cancelling">Cancelling…</span>
        <span v-else>Cancel scheduled deletion</span>
      </button>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import privacyService from '@/services/privacyService';
import logger from '@/utils/logger';

export default {
  name: 'ScheduledDeletionBanner',
  data() {
    return { cancelling: false };
  },
  computed: {
    ...mapState({
      scheduledFor: state => state.auth.user?.deletion_scheduled_for,
    }),
    scheduled() {
      return !!this.scheduledFor;
    },
    formattedDate() {
      return this.scheduledFor
        ? new Date(this.scheduledFor).toLocaleDateString('en-GB',
          { day: 'numeric', month: 'long', year: 'numeric' })
        : '';
    },
    daysRemaining() {
      if (!this.scheduledFor) return 0;
      const diff = new Date(this.scheduledFor) - new Date();
      return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
    },
  },
  methods: {
    async cancelDeletion() {
      this.cancelling = true;
      try {
        await privacyService.cancelScheduledDeletion();
        await this.$store.dispatch('auth/fetchUser');
      } catch (e) {
        logger.error('Cancel scheduled deletion failed', e);
        alert('Could not cancel. Please try again.');
      } finally {
        this.cancelling = false;
      }
    },
  },
};
</script>
