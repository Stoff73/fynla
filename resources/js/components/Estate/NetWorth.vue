<template>
  <div class="net-worth-tab">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-blue-50 rounded-lg p-6">
        <p class="text-sm text-blue-600 font-medium mb-2">Total Assets</p>
        <p class="text-3xl font-bold text-gray-900">{{ formattedTotalAssets }}</p>
      </div>
      <div class="bg-red-50 rounded-lg p-6">
        <p class="text-sm text-red-600 font-medium mb-2">Total Liabilities</p>
        <p class="text-3xl font-bold text-gray-900">{{ formattedTotalLiabilities }}</p>
      </div>
      <div class="bg-green-50 rounded-lg p-6">
        <p class="text-sm text-green-600 font-medium mb-2">Net Worth</p>
        <p class="text-3xl font-bold text-gray-900">{{ formattedNetWorth }}</p>
      </div>
    </div>

    <!-- Net Worth Waterfall Chart -->
    <div class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Net Worth Breakdown</h3>
      <!-- Chart temporarily disabled to fix navigation issues -->
      <div class="text-center py-8 text-gray-500">
        Chart visualization coming soon
      </div>
      <!-- <NetWorthWaterfallChart :assets="assets" :liabilities="liabilities" /> -->
    </div>

    <!-- Asset Composition -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Composition</h3>
        <div v-if="assets.length === 0" class="text-center py-8 text-gray-500">
          No assets recorded yet
        </div>
        <div v-else class="space-y-4">
          <div
            v-for="(group, type) in assetsByType"
            :key="type"
            class="flex justify-between items-center"
          >
            <span class="text-sm text-gray-600">{{ type }}</span>
            <span class="text-sm font-medium text-gray-900">
              {{ formatCurrency(sumAssets(group)) }}
            </span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Liability Composition</h3>
        <div v-if="liabilities.length === 0" class="text-center py-8 text-gray-500">
          No liabilities recorded yet
        </div>
        <div v-else class="space-y-4">
          <div
            v-for="(group, type) in liabilitiesByType"
            :key="type"
            class="flex justify-between items-center"
          >
            <span class="text-sm text-gray-600">{{ type }}</span>
            <span class="text-sm font-medium text-gray-900">
              {{ formatCurrency(sumLiabilities(group)) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import NetWorthWaterfallChart from './NetWorthWaterfallChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'NetWorth',
  mixins: [currencyMixin],

  components: {
    NetWorthWaterfallChart,
  },

  computed: {
    ...mapState('estate', ['assets', 'liabilities']),
    ...mapGetters('estate', [
      'totalAssets',
      'totalLiabilities',
      'netWorthValue',
      'assetsByType',
      'liabilitiesByType',
    ]),

    formattedTotalAssets() {
      return this.formatCurrency(this.totalAssets);
    },

    formattedTotalLiabilities() {
      return this.formatCurrency(this.totalLiabilities);
    },

    formattedNetWorth() {
      return this.formatCurrency(this.netWorthValue);
    },
  },

  methods: {
    sumAssets(assets) {
      return assets.reduce((sum, asset) => sum + parseFloat(asset.current_value || 0), 0);
    },

    sumLiabilities(liabilities) {
      return liabilities.reduce((sum, liability) => sum + parseFloat(liability.current_balance || 0), 0);
    },
  },
};
</script>
