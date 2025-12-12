<template>
  <AppLayout>
    <div class="net-worth-dashboard">

    <div class="tab-navigation">
      <router-link
        v-for="tab in tabs"
        :key="tab.path"
        :to="`/net-worth/${tab.path}`"
        class="tab-link"
        active-class="active"
      >
        {{ tab.label }}
      </router-link>
    </div>

    <div class="dashboard-content">
      <router-view></router-view>
    </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'NetWorthDashboard',

  components: {
    AppLayout,
  },

  data() {
    return {
      tabs: [
        { path: 'overview', label: 'Overview' },
        { path: 'retirement', label: 'Retirement' },
        { path: 'property', label: 'Property' },
        { path: 'investments', label: 'Investments' },
        { path: 'cash', label: 'Cash' },
        { path: 'business', label: 'Business Interests' },
        { path: 'chattels', label: 'Chattels' },
        { path: 'joint-history', label: 'Joint History' },
      ],
    };
  },

  computed: {
    ...mapState('netWorth', ['loading']),
  },

  methods: {
    ...mapActions('netWorth', ['refreshNetWorth']),
  },

  mounted() {
    // Redirect to overview if at root
    if (this.$route.path === '/net-worth' || this.$route.path === '/net-worth/') {
      this.$router.replace('/net-worth/overview');
    }
  },
};
</script>

<style scoped>
.net-worth-dashboard {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.tab-navigation {
  display: flex;
  gap: 4px;
  border-bottom: 2px solid #e5e7eb;
  margin-bottom: 24px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.tab-navigation::-webkit-scrollbar {
  display: none;
}

.tab-link {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  text-decoration: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
  white-space: nowrap;
}

.tab-link:hover {
  color: #3b82f6;
}

.tab-link.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
  font-weight: 600;
}

.dashboard-content {
  min-height: 500px;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .net-worth-dashboard {
    padding: 16px;
  }

  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .tab-navigation {
    gap: 2px;
  }

  .tab-link {
    padding: 10px 12px;
    font-size: 13px;
  }

  .refresh-button {
    padding: 8px 12px;
    font-size: 13px;
  }
}
</style>
