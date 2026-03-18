<template>
  <div class="mobile-layout flex flex-col h-screen bg-eggshell-500">
    <!-- Header -->
    <MobileHeader
      :show-back="canGoBack"
      @back="handleBack"
      @profile="navigateToSettings"
    />

    <!-- Content -->
    <main class="flex-1 overflow-y-auto" ref="contentArea">
      <keep-alive :include="keepAliveIncludes">
        <router-view />
      </keep-alive>
    </main>

    <!-- Tab Bar -->
    <MobileTabBar
      :active-tab="activeTab"
      :alert-count="alertCount"
      :unread-count="unreadCount"
      :milestone-count="milestoneCount"
      @tab="handleTabChange"
    />

    <!-- In-app notification toast -->
    <InAppNotificationToast
      v-if="inAppNotification"
      :notification="inAppNotification"
      @dismiss="dismissNotification"
    />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import MobileTabBar from '@/mobile/MobileTabBar.vue';
import MobileHeader from '@/mobile/MobileHeader.vue';

export default {
  name: 'MobileLayout',

  components: {
    MobileTabBar,
    MobileHeader,
    InAppNotificationToast: () => import('@/mobile/InAppNotificationToast.vue'),
  },

  data() {
    return {
      keepAliveIncludes: [
        'MobileDashboard',
        'MobileFynChat',
        'LearnHub',
        'MobileGoalsList',
        'MoreMenu',
      ],
    };
  },

  computed: {
    ...mapGetters('mobileDashboard', { dashboardAlerts: 'alerts' }),
    ...mapGetters('mobileNotifications', ['unreadCount', 'inAppNotification']),

    activeTab() {
      const path = this.$route.path;
      if (path.startsWith('/m/home')) return 'home';
      if (path.startsWith('/m/fyn')) return 'fyn';
      if (path.startsWith('/m/learn')) return 'learn';
      if (path.startsWith('/m/goals')) return 'goals';
      if (path.startsWith('/m/more')) return 'more';
      return 'home';
    },

    canGoBack() {
      // Show back button if deeper than tab root
      const tabRoots = ['/m/home', '/m/fyn', '/m/learn', '/m/goals', '/m/more'];
      return !tabRoots.includes(this.$route.path);
    },

    alertCount() {
      return this.dashboardAlerts?.length || 0;
    },

    milestoneCount() {
      // Uses activeGoals count from the goals store as milestone indicator.
      // A dedicated activeMilestoneCount getter does not exist yet.
      return this.$store.getters['goals/activeGoals']?.length || 0;
    },
  },

  methods: {
    handleBack() {
      this.$router.back();
    },

    navigateToSettings() {
      this.$router.push('/m/more');
    },

    handleTabChange(tab) {
      const routes = {
        home: '/m/home',
        fyn: '/m/fyn',
        learn: '/m/learn',
        goals: '/m/goals',
        more: '/m/more',
      };

      if (this.activeTab === tab) {
        // Already on this tab — scroll to top
        if (this.$refs.contentArea) {
          this.$refs.contentArea.scrollTo({ top: 0, behavior: 'smooth' });
        }
      } else {
        this.$router.push(routes[tab]);
      }
    },

    dismissNotification() {
      this.$store.dispatch('mobileNotifications/clearUnread');
    },
  },
};
</script>

<style scoped>
.mobile-layout {
  /* Ensure layout fills viewport including safe areas */
  padding-top: env(safe-area-inset-top);
}
</style>
