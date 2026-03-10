<template>
  <PullToRefresh @refresh="refreshDashboard">
    <div class="px-4 pt-4 pb-6 space-y-4">
      <!-- Greeting -->
      <div>
        <h2 class="text-xl font-bold text-horizon-500">{{ greeting }}, {{ firstName }}</h2>
        <p class="text-sm text-neutral-500 mt-0.5">Here's your financial snapshot</p>
      </div>

      <!-- Loading skeleton -->
      <template v-if="loading && !dashboard">
        <div class="bg-savannah-100 animate-pulse rounded-xl h-32"></div>
        <div class="bg-savannah-100 animate-pulse rounded-xl h-20"></div>
        <div class="grid grid-cols-2 gap-3">
          <div v-for="i in 4" :key="i" class="bg-savannah-100 animate-pulse rounded-xl h-24"></div>
        </div>
      </template>

      <!-- Content -->
      <template v-else-if="dashboard">
        <MobileNetWorthCard
          :net-worth="dashboard.net_worth"
          :change="dashboard.net_worth_change"
          :history="dashboard.net_worth_history"
        />

        <FynInsightCard
          v-if="dashboard.fyn_insight"
          :insight="dashboard.fyn_insight"
        />

        <MobileAlertsList
          v-if="dashboard.alerts && dashboard.alerts.length"
          :alerts="dashboard.alerts"
        />

        <div class="grid grid-cols-2 gap-3">
          <ModuleSummaryCard
            v-for="mod in dashboard.modules"
            :key="mod.name"
            :module-data="mod"
            @click="navigateToModule(mod.name)"
          />
        </div>
      </template>

      <!-- Empty state -->
      <div v-else class="text-center py-12">
        <img src="/images/logos/favicon.png" alt="Fynla" class="w-16 h-16 mx-auto mb-4 opacity-50" />
        <p class="text-neutral-500">Welcome to Fynla! Start by adding your financial details on the web app.</p>
      </div>
    </div>
  </PullToRefresh>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import MobileNetWorthCard from '@/mobile/MobileNetWorthCard.vue';
import FynInsightCard from '@/mobile/FynInsightCard.vue';
import MobileAlertsList from '@/mobile/MobileAlertsList.vue';
import ModuleSummaryCard from '@/mobile/ModuleSummaryCard.vue';
import PullToRefresh from '@/mobile/PullToRefresh.vue';

export default {
  name: 'MobileDashboard',

  components: {
    MobileNetWorthCard,
    FynInsightCard,
    MobileAlertsList,
    ModuleSummaryCard,
    PullToRefresh,
  },

  computed: {
    ...mapState('mobileDashboard', ['dashboard', 'loading']),
    ...mapState('auth', ['user']),

    firstName() {
      return this.user?.first_name || 'there';
    },

    greeting() {
      const hour = new Date().getHours();
      if (hour < 12) return 'Good morning';
      if (hour < 18) return 'Good afternoon';
      return 'Good evening';
    },
  },

  mounted() {
    if (!this.dashboard) {
      this.fetchDashboard();
    }
  },

  methods: {
    ...mapActions('mobileDashboard', ['fetchDashboard', 'refreshDashboard']),

    navigateToModule(moduleName) {
      this.$router.push(`/m/more/summary/${moduleName}`);
    },
  },
};
</script>
