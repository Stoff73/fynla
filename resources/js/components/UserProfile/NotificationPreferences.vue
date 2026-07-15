<template>
  <div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
      <router-link
        to="/profile"
        class="inline-flex items-center text-sm text-neutral-500 hover:text-horizon-500 mb-4"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Profile
      </router-link>
      <h2 class="text-2xl font-black text-horizon-500 mb-2">Notification Preferences</h2>
      <p class="text-sm text-neutral-500">
        Choose which Fynla emails you'd like to receive. You can change these at any time.
      </p>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 14" :key="i" class="bg-savannah-100 animate-pulse rounded-xl h-14"></div>
    </div>

    <div v-else>
      <div v-for="section in sections" :key="section.title" class="mb-8">
        <h3 class="text-lg font-bold text-horizon-500 mb-3">{{ section.title }}</h3>
        <div class="border border-light-gray rounded-xl divide-y divide-light-gray bg-white">
          <div
            v-for="item in section.items"
            :key="item.key"
            class="flex items-center justify-between p-4"
          >
            <div class="flex-1 min-w-0 mr-4">
              <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
              <p class="text-xs text-neutral-500 mt-0.5">{{ item.description }}</p>
            </div>
            <button
              type="button"
              class="relative w-11 h-6 rounded-full transition-colors flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
              :class="preferences[item.key] ? 'bg-spring-500' : 'bg-neutral-300'"
              :aria-pressed="!!preferences[item.key]"
              :aria-label="item.label"
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
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'NotificationPreferences',

  data() {
    return {
      loading: true,
      preferences: {},
      sections: [
        {
          title: 'Account',
          items: [
            { key: 'security_alerts', label: 'Security Alerts', description: 'Login attempts and security changes' },
            { key: 'payment_alerts', label: 'Payment Alerts', description: 'Subscription payment confirmations' },
          ],
        },
        {
          title: 'Feature Alerts',
          items: [
            { key: 'policy_renewals', label: 'Policy Renewals', description: 'Reminders when policies are due for renewal' },
            { key: 'goal_milestones', label: 'Goal Milestones', description: 'Celebrations when you hit savings milestones' },
            { key: 'contribution_reminders', label: 'Contribution Reminders', description: 'Reminders to make regular contributions' },
            { key: 'market_updates', label: 'Market Updates', description: 'Notable changes in your investments' },
            { key: 'fyn_daily_insight', label: 'Fyn Daily Insight', description: 'A daily financial tip from Fyn' },
            { key: 'mortgage_rate_alerts', label: 'Mortgage Rate Alerts', description: 'Warnings when fixed rates are expiring' },
            { key: 'estate_alerts', label: 'Estate Alerts', description: 'Gift exemption and trust anniversary reminders' },
          ],
        },
        {
          title: 'Lifecycle Emails',
          items: [
            { key: 'lifecycle_churned_subscriber', label: 'Subscription Cancellation Feedback', description: 'Brief feedback request after cancelling a subscription' },
            { key: 'lifecycle_lapsed_subscriber', label: 'Payment Recovery', description: 'Help with renewing your subscription if a payment fails' },
          ],
        },
      ],
    };
  },

  async mounted() {
    await this.fetchPreferences();
  },

  methods: {
    async fetchPreferences() {
      this.loading = true;
      try {
        const response = await api.get('/notifications/preferences');
        this.preferences = response.data.data || response.data;
      } catch (error) {
        console.error('Failed to load notification preferences', error);
        this.preferences = {};
      } finally {
        this.loading = false;
      }
    },

    async togglePreference(key) {
      const previousValue = this.preferences[key];
      const newValue = !previousValue;
      this.preferences = { ...this.preferences, [key]: newValue };

      try {
        await api.put('/notifications/preferences', { [key]: newValue });
      } catch (error) {
        console.error('Failed to update preference', error);
        // Revert on failure
        this.preferences = { ...this.preferences, [key]: previousValue };
      }
    },
  },
};
</script>
