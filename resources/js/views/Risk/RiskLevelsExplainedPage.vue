<template>
  <AppLayout>
    <div class="module-gradient py-4 sm:py-8 px-4 sm:px-6">
      <ModuleStatusBar />
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-horizon-500">Risk Levels Explained</h1>
            <p class="mt-2 text-sm sm:text-base text-neutral-500">
              Understanding what each risk level means for your investments
            </p>
          </div>
          <button
            @click="goBack"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-savannah-100 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </button>
        </div>
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
      </div>

      <template v-else>
        <!-- Your Current Level -->
        <div v-if="currentRiskLevel" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 rounded-full flex items-center justify-center text-xl font-bold"
              :class="getRiskLevelCircleClass(currentRiskLevel)"
            >
              {{ getRiskLevelNumeric(currentRiskLevel) }}
            </div>
            <div>
              <p class="text-sm text-blue-700">Your current risk level</p>
              <p class="font-semibold text-blue-900">{{ getRiskLevelDisplayName(currentRiskLevel) }}</p>
            </div>
          </div>
        </div>

        <!-- Risk Levels List -->
        <div class="space-y-4">
          <div
            v-for="level in allRiskLevels"
            :key="level.key"
            class="bg-white rounded-lg shadow-sm border p-6 transition-all"
            :class="level.key === currentRiskLevel
              ? 'border-2 border-blue-400'
              : 'border-light-gray'"
          >
            <div class="flex items-start gap-4">
              <div
                class="flex-shrink-0 w-14 h-14 rounded-full flex items-center justify-center text-2xl font-bold"
                :class="getRiskLevelCircleClass(level.key)"
              >
                {{ level.level_numeric }}
              </div>
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <h2 class="text-xl font-semibold text-horizon-500">{{ level.display_name }}</h2>
                  <span
                    v-if="level.key === currentRiskLevel"
                    class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                  >
                    Your Level
                  </span>
                </div>
                <p class="text-neutral-500 mb-4">{{ level.full_description }}</p>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-eggshell-500 rounded-lg">
                  <div class="text-center">
                    <p class="text-lg font-bold text-blue-600">{{ level.expected_returns?.min }}% - {{ level.expected_returns?.max }}%</p>
                    <p class="text-xs text-neutral-500">Expected Returns</p>
                  </div>
                  <div class="text-center">
                    <p class="text-lg font-bold text-red-600">{{ level.volatility_percent }}%</p>
                    <p class="text-xs text-neutral-500">Volatility</p>
                  </div>
                  <div class="text-center">
                    <p class="text-lg font-bold text-green-600">{{ level.asset_allocation?.equities }}%</p>
                    <p class="text-xs text-neutral-500">Equities</p>
                  </div>
                  <div class="text-center">
                    <p class="text-lg font-bold text-teal-600">{{ level.asset_allocation?.bonds }}%</p>
                    <p class="text-xs text-neutral-500">Bonds</p>
                  </div>
                </div>

                <!-- Asset Allocation Bar -->
                <div class="mt-4">
                  <p class="text-xs text-neutral-500 mb-1">Asset Allocation</p>
                  <div class="flex h-3 rounded-full overflow-hidden">
                    <div
                      class="bg-blue-500"
                      :style="{ width: level.asset_allocation?.equities + '%' }"
                      title="Equities"
                    ></div>
                    <div
                      class="bg-green-500"
                      :style="{ width: level.asset_allocation?.bonds + '%' }"
                      title="Bonds"
                    ></div>
                    <div
                      class="bg-teal-500"
                      :style="{ width: level.asset_allocation?.cash + '%' }"
                      title="Cash"
                    ></div>
                    <div
                      class="bg-purple-500"
                      :style="{ width: level.asset_allocation?.alternatives + '%' }"
                      title="Alternatives"
                    ></div>
                  </div>
                  <div class="flex justify-between text-xs text-neutral-500 mt-1">
                    <span>Equities {{ level.asset_allocation?.equities }}%</span>
                    <span>Bonds {{ level.asset_allocation?.bonds }}%</span>
                    <span>Cash {{ level.asset_allocation?.cash }}%</span>
                    <span>Alt {{ level.asset_allocation?.alternatives }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Educational Note -->
        <div class="mt-6 bg-eggshell-500 border border-light-gray rounded-lg p-4">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-neutral-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="text-sm font-medium text-horizon-500">How is my risk level determined?</p>
              <p class="text-sm text-neutral-500 mt-1">
                Your risk level is automatically calculated by analyzing 7 financial factors including
                your capacity for loss, time horizon, dependants, employment status, and more.
                The most common risk level across all factors becomes your overall risk level.
              </p>
              <router-link
                to="/risk-profile"
                class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block"
              >
                View your factor breakdown →
              </router-link>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import riskService from '@/services/riskService';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'RiskLevelsExplainedPage',

  components: {
    AppLayout,
    ModuleStatusBar,
  },

  data() {
    return {
      loading: true,
      currentRiskLevel: null,
      allRiskLevels: [],
    };
  },

  async created() {
    await this.loadData();
  },

  methods: {
    goBack() {
      // Use browser history to return to previous page (Valuable Info or Risk Profile)
      this.$router.back();
    },

    async loadData() {
      this.loading = true;
      try {
        const [profileResponse, levelsResponse] = await Promise.all([
          riskService.getProfile(),
          riskService.getLevels(),
        ]);

        if (profileResponse.data) {
          this.currentRiskLevel = profileResponse.data.risk_level;
        }

        if (levelsResponse.data) {
          this.allRiskLevels = levelsResponse.data;
        }
      } catch (error) {
        logger.error('Error loading risk levels:', error);
      } finally {
        this.loading = false;
      }
    },

    getRiskLevelDisplayName(level) {
      const names = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return names[level] || level;
    },

    getRiskLevelNumeric(level) {
      const numerics = {
        low: 1,
        lower_medium: 2,
        medium: 3,
        upper_medium: 4,
        high: 5,
      };
      return numerics[level] || '-';
    },

    getRiskLevelCircleClass(level) {
      const classes = {
        low: 'bg-yellow-100 text-yellow-700',
        lower_medium: 'bg-pink-100 text-pink-700',
        medium: 'bg-green-100 text-green-700',
        upper_medium: 'bg-teal-100 text-teal-700',
        high: 'bg-blue-100 text-blue-700',
      };
      return classes[level] || 'bg-savannah-100 text-neutral-500';
    },
  },
};
</script>
