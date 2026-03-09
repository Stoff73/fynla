<template>
  <div class="card">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-horizon-500">Household Net Worth</h3>
      <button
        v-if="!loading && data"
        @click="fetchData"
        class="text-sm text-horizon-400 hover:text-raspberry-500 transition-colors"
      >
        Refresh
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="text-center py-6">
      <p class="text-neutral-500 mb-2">{{ error }}</p>
      <button @click="fetchData" class="text-sm text-raspberry-500 hover:text-raspberry-600">Retry</button>
    </div>

    <!-- Data -->
    <div v-else-if="data">
      <!-- Combined net worth -->
      <div class="text-center mb-6">
        <p class="text-sm text-neutral-500 mb-1">Combined Net Worth</p>
        <p class="text-3xl font-black text-horizon-500">{{ formatCurrency(data.net_worth) }}</p>
      </div>

      <!-- Stacked bar showing each person's share -->
      <div v-if="data.has_spouse" class="mb-6">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-horizon-500 font-semibold">{{ data.user_name }}</span>
          <span class="text-violet-500 font-semibold">{{ data.spouse_name }}</span>
        </div>
        <div class="h-4 rounded-full overflow-hidden bg-eggshell-500 flex">
          <div
            class="bg-horizon-500 transition-all duration-500"
            :style="{ width: userSharePercent + '%' }"
          ></div>
          <div
            class="bg-violet-500 transition-all duration-500"
            :style="{ width: spouseSharePercent + '%' }"
          ></div>
        </div>
        <div class="flex items-center justify-between text-sm mt-1">
          <span class="text-neutral-500">{{ formatCurrency(data.user_share) }}</span>
          <span class="text-neutral-500">{{ formatCurrency(data.spouse_share) }}</span>
        </div>
      </div>

      <!-- Assets & Liabilities summary -->
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="bg-spring-50 rounded-lg p-3 text-center">
          <p class="text-xs text-neutral-500 mb-1">Total Assets</p>
          <p class="text-lg font-bold text-spring-500">{{ formatCurrency(data.total_assets) }}</p>
        </div>
        <div class="bg-light-pink-50 rounded-lg p-3 text-center">
          <p class="text-xs text-neutral-500 mb-1">Total Liabilities</p>
          <p class="text-lg font-bold text-raspberry-500">{{ formatCurrency(data.total_liabilities) }}</p>
        </div>
      </div>

      <!-- Breakdown by type -->
      <div v-if="hasBreakdown" class="space-y-2">
        <p class="text-sm font-bold text-horizon-500">Breakdown by Asset Type</p>
        <div
          v-for="(item, key) in visibleBreakdown"
          :key="key"
          class="flex items-center justify-between py-1 border-b border-light-gray last:border-0"
        >
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: assetColor(key) }"></div>
            <span class="text-sm text-horizon-500 capitalize">{{ formatLabel(key) }}</span>
          </div>
          <div class="text-right">
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(item.total) }}</span>
            <div v-if="data.has_spouse" class="text-xs text-neutral-500">
              {{ data.user_name }}: {{ formatCurrency(item.user) }}
              <span class="mx-1">|</span>
              {{ data.spouse_name }}: {{ formatCurrency(item.spouse) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- No data -->
    <div v-else class="text-center py-6">
      <p class="text-neutral-500">No financial data available yet.</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS } from '@/constants/designSystem';
import householdService from '@/services/householdService';

export default {
  name: 'HouseholdNetWorth',
  mixins: [currencyMixin],
  data() {
    return {
      data: null,
      loading: false,
      error: null,
    };
  },
  computed: {
    userSharePercent() {
      if (!this.data || this.data.net_worth === 0) return 50;
      const total = Math.abs(this.data.user_share) + Math.abs(this.data.spouse_share);
      if (total === 0) return 50;
      return Math.round((Math.abs(this.data.user_share) / total) * 100);
    },
    spouseSharePercent() {
      return 100 - this.userSharePercent;
    },
    visibleBreakdown() {
      if (!this.data?.breakdown_by_type) return {};
      const result = {};
      for (const [key, value] of Object.entries(this.data.breakdown_by_type)) {
        if (value.total > 0) {
          result[key] = value;
        }
      }
      return result;
    },
    hasBreakdown() {
      return Object.keys(this.visibleBreakdown).length > 0;
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      this.error = null;
      try {
        const response = await householdService.getNetWorth();
        if (response.success) {
          this.data = response.data;
        } else {
          this.error = response.message || 'Failed to load household data';
        }
      } catch (err) {
        this.error = 'Unable to load household net worth';
      } finally {
        this.loading = false;
      }
    },
    assetColor(key) {
      const colorMap = {
        properties: ASSET_COLORS.property,
        savings: ASSET_COLORS.cash,
        investments: ASSET_COLORS.investments,
        pensions: ASSET_COLORS.pensions,
        business: ASSET_COLORS.business,
        cash: ASSET_COLORS.cash,
        chattels: ASSET_COLORS.chattels,
      };
      return colorMap[key] || '#717171';
    },
    formatLabel(key) {
      const labels = {
        properties: 'Property',
        savings: 'Savings',
        investments: 'Investments',
        pensions: 'Pensions',
        business: 'Business',
        cash: 'Cash',
        chattels: 'Personal Valuables',
      };
      return labels[key] || key;
    },
  },
};
</script>
