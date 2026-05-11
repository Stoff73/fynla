<template>
  <div class="bg-white rounded-lg shadow-sm mb-6">
    <div class="border-b border-light-gray">
      <nav class="-mb-px flex overflow-x-auto scrollbar-hide px-3" aria-label="Settings tabs">
        <router-link
          v-for="tab in tabs"
          :key="tab.to"
          :to="tab.to"
          :class="[
            isActive(tab)
              ? 'border-raspberry-500 text-raspberry-700'
              : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300',
            'whitespace-nowrap py-3 px-2 sm:px-3 border-b-2 font-medium text-xs sm:text-sm transition-colors flex-shrink-0',
          ]"
        >
          {{ tab.label }}
        </router-link>
      </nav>
    </div>
  </div>
</template>

<script>
import { getRequiredTier, hasFeatureAccess } from '@/constants/featureGating';

const ALL_TABS = [
  { label: 'General', to: '/settings' },
  { label: 'Personal Info', to: '/settings/personal' },
  { label: 'Health', to: '/settings/health' },
  { label: 'Family', to: '/settings/family' },
  { label: 'Subscription', to: '/settings/subscription' },
  { label: 'Notifications', to: '/settings/notifications' },
  { label: 'Security', to: '/settings/security' },
  { label: 'Privacy', to: '/settings/privacy' },
  { label: 'Assumptions', to: '/settings/assumptions' },
];

export default {
  name: 'SettingsTabBar',
  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    subscriptionData() {
      return this.$store.state.auth.subscriptionData;
    },
    effectivePlan() {
      if (this.isPreviewMode) return 'pro';
      if (!this.subscriptionData) return 'pro';
      if (this.subscriptionData.status === 'trialing') return 'pro';
      return this.subscriptionData.plan || 'student';
    },
    tabs() {
      return ALL_TABS.filter((tab) => {
        const requiredTier = getRequiredTier(tab.to);
        if (!requiredTier) return true;
        return hasFeatureAccess(this.effectivePlan, requiredTier);
      });
    },
  },
  methods: {
    isActive(tab) {
      if (tab.to === '/settings') {
        return this.$route.path === '/settings';
      }
      return this.$route.path.startsWith(tab.to);
    },
  },
};
</script>
