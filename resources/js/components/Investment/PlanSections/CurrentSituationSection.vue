<template>
  <div class="current-situation-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Current Portfolio Situation</h4>

    <div v-if="!data" class="text-center py-8 text-neutral-500">
      <p>No current situation data available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Asset Allocation -->
      <div class="bg-white border border-light-gray rounded-lg p-4">
        <h5 class="text-sm font-semibold text-neutral-500 mb-3">Asset Allocation</h5>
        <div v-if="data.asset_allocation" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="(value, key) in data.asset_allocation" :key="key">
            <p class="text-xs text-neutral-500 mb-1">{{ formatAssetClass(key) }}</p>
            <p class="text-lg font-semibold text-horizon-500">{{ formatPercentage(value) }}%</p>
          </div>
        </div>
      </div>

      <!-- Account Breakdown -->
      <div class="bg-white border border-light-gray rounded-lg p-4">
        <h5 class="text-sm font-semibold text-neutral-500 mb-3">Account Breakdown</h5>
        <div v-if="data.accounts && data.accounts.length > 0">
          <table class="min-w-full divide-y divide-light-gray">
            <thead class="bg-eggshell-500">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500 uppercase">Account</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500 uppercase">Type</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500 uppercase">Value</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500 uppercase">% of Total</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-light-gray">
              <tr v-for="(account, index) in data.accounts" :key="index">
                <td class="px-4 py-2 text-sm text-horizon-500">{{ account.name }}</td>
                <td class="px-4 py-2 text-sm text-neutral-500">{{ account.type }}</td>
                <td class="px-4 py-2 text-sm text-right font-medium text-horizon-500">
                  £{{ formatNumber(account.value) }}
                </td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">
                  {{ formatPercentage(account.percentage) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-4 text-neutral-500 text-sm">
          No account data available
        </div>
      </div>

      <!-- Performance Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 mb-1">1 Year Return</p>
          <p class="text-2xl font-bold" :class="data.performance?.one_year >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatPercentage(data.performance?.one_year || 0) }}%
          </p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 mb-1">3 Year Return (Ann.)</p>
          <p class="text-2xl font-bold" :class="data.performance?.three_year >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatPercentage(data.performance?.three_year || 0) }}%
          </p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 mb-1">5 Year Return (Ann.)</p>
          <p class="text-2xl font-bold" :class="data.performance?.five_year >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatPercentage(data.performance?.five_year || 0) }}%
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'CurrentSituationSection',

  mixins: [currencyMixin],

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
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
