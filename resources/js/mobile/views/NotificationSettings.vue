<template>
  <div class="px-4 pt-4 pb-6">
    <!-- Push permission banner -->
    <div
      v-if="!hasPermission"
      class="bg-violet-50 rounded-xl border border-violet-200 p-4 mb-4"
    >
      <p class="text-sm text-horizon-500 mb-2">Enable notifications to receive these alerts</p>
      <button
        class="px-4 py-2 bg-raspberry-500 text-white text-sm font-semibold rounded-lg"
        @click="requestPermission"
      >
        Enable
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 8" :key="i" class="bg-savannah-100 animate-pulse rounded-xl h-14"></div>
    </div>

    <!-- Toggles -->
    <div v-else class="space-y-1">
      <div
        v-for="item in toggleItems"
        :key="item.key"
        class="flex items-center justify-between py-3 px-1 border-b border-light-gray last:border-0"
      >
        <div class="flex-1 min-w-0 mr-3">
          <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">{{ item.description }}</p>
        </div>
        <button
          class="relative w-11 h-6 rounded-full transition-colors flex-shrink-0"
          :class="preferences[item.key] ? 'bg-spring-500' : 'bg-neutral-300'"
          @click="togglePreference(item.key)"
        >
          <span
            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
            :class="preferences[item.key] ? 'translate-x-5' : 'translate-x-0'"
          ></span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import api from '@/services/api';

export default {
  name: 'NotificationSettings',

  data() {
    return {
      loading: true,
      preferences: {},
      toggleItems: [
        { key: 'policy_renewals', label: 'Policy Renewals', description: 'Reminders when policies are due for renewal' },
        { key: 'goal_milestones', label: 'Goal Milestones', description: 'Celebrations when you hit savings milestones' },
        { key: 'contribution_reminders', label: 'Contribution Reminders', description: 'Reminders to make regular contributions' },
        { key: 'market_updates', label: 'Market Updates', description: 'Notable changes in your investments' },
        { key: 'fyn_daily_insight', label: 'Fyn Daily Insight', description: 'A daily financial tip from Fyn' },
        { key: 'security_alerts', label: 'Security Alerts', description: 'Login attempts and security changes' },
        { key: 'payment_alerts', label: 'Payment Alerts', description: 'Subscription payment confirmations' },
        { key: 'mortgage_rate_alerts', label: 'Mortgage Rate Alerts', description: 'Warnings when fixed rates are expiring' },
      ],
    };
  },

  computed: {
    ...mapGetters('mobileNotifications', ['hasPermission']),
  },

  async mounted() {
    await this.fetchPreferences();
  },

  methods: {
    async fetchPreferences() {
      this.loading = true;
      try {
        const response = await api.get('/v1/mobile/notifications/preferences');
        this.preferences = response.data.data || response.data;
      } catch {
        this.preferences = {};
      } finally {
        this.loading = false;
      }
    },

    async togglePreference(key) {
      const newValue = !this.preferences[key];
      this.preferences = { ...this.preferences, [key]: newValue };

      try {
        await api.put('/v1/mobile/notifications/preferences', { [key]: newValue });
      } catch {
        // Revert on failure
        this.preferences = { ...this.preferences, [key]: !newValue };
      }
    },

    async requestPermission() {
      await this.$store.dispatch('mobileNotifications/requestPermission');
    },
  },
};
</script>
