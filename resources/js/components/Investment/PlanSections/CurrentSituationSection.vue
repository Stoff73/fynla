<template>
  <div class="current-situation-section">
    <h4 class="text-md font-semibold text-gray-800 mb-4">Current Portfolio Situation</h4>

    <div v-if="!data" class="text-center py-8 text-gray-500">
      <p>No current situation data available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Asset Allocation -->
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h5 class="text-sm font-semibold text-gray-700 mb-3">Asset Allocation</h5>
        <div v-if="data.asset_allocation" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="(value, key) in data.asset_allocation" :key="key">
            <p class="text-xs text-gray-600 mb-1">{{ formatAssetClass(key) }}</p>
            <p class="text-lg font-semibold text-gray-800">{{ formatPercentage(value) }}%</p>
          </div>
        </div>
      </div>

      <!-- Account Breakdown -->
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h5 class="text-sm font-semibold text-gray-700 mb-3">Account Breakdown</h5>
        <div v-if="data.accounts && data.accounts.length > 0">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">% of Total</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(account, index) in data.accounts" :key="index">
                <td class="px-4 py-2 text-sm text-gray-800">{{ account.name }}</td>
                <td class="px-4 py-2 text-sm text-gray-600">{{ account.type }}</td>
                <td class="px-4 py-2 text-sm text-right font-medium text-gray-800">
                  £{{ formatNumber(account.value) }}
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-600">
                  {{ formatPercentage(account.percentage) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-4 text-gray-500 text-sm">
          No account data available
        </div>
      </div>

      <!-- Performance Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs text-gray-600 mb-1">1 Year Return</p>
          <p class="text-2xl font-bold" :class="data.performance?.one_year >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatPercentage(data.performance?.one_year || 0) }}%
          </p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs text-gray-600 mb-1">3 Year Return (Ann.)</p>
          <p class="text-2xl font-bold" :class="data.performance?.three_year >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatPercentage(data.performance?.three_year || 0) }}%
          </p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs text-gray-600 mb-1">5 Year Return (Ann.)</p>
          <p class="text-2xl font-bold" :class="data.performance?.five_year >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatPercentage(data.performance?.five_year || 0) }}%
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CurrentSituationSection',

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Math.round(value).toLocaleString('en-GB');
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return '0.0';
      return value.toFixed(1);
    },

    formatAssetClass(key) {
      const names = {
        equities: 'Equities',
        bonds: 'Bonds',
        cash: 'Cash',
        property: 'Property',
        alternatives: 'Alternatives',
      };
      return names[key] || key.charAt(0).toUpperCase() + key.slice(1);
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
