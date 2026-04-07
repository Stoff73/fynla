<template>
  <div class="isa-optimization-strategy">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">ISA Allowance Optimisation</h3>

    <!-- No Data State -->
    <div v-if="!strategy" class="text-center py-12 text-neutral-500">
      <p>No ISA strategy available</p>
    </div>

    <!-- ISA Allowance Summary -->
    <div v-else>
      <!-- Allowance Status Card -->
      <div class="bg-white rounded-lg p-6 mb-6 border-l-4 border-violet-500">
        <div class="flex justify-between items-center mb-4">
          <h4 class="text-md font-semibold text-horizon-500">ISA Allowance ({{ taxYear }})</h4>
          <span
            class="px-3 py-1 text-xs font-semibold rounded-full"
            :class="getAllowanceStatusClass(strategy.allowance.utilization_percent)"
          >
            {{ strategy.allowance.utilization_percent.toFixed(0) }}% Used
          </span>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Total Allowance</p>
            <p class="text-xl font-bold text-horizon-500">£{{ formatNumber(strategy.allowance.total) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Used</p>
            <p class="text-xl font-bold text-violet-600">£{{ formatNumber(strategy.allowance.used) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Remaining</p>
            <p class="text-xl font-bold text-spring-600">£{{ formatNumber(strategy.allowance.remaining) }}</p>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-savannah-200 rounded-full h-3 overflow-hidden">
          <div
            class="h-3 rounded-full transition-all duration-500"
            :class="getAllowanceBarClass(strategy.allowance.utilization_percent)"
            :style="{ width: Math.min(strategy.allowance.utilization_percent, 100) + '%' }"
          ></div>
        </div>

        <!-- Warning if over allowance -->
        <div
          v-if="strategy.allowance.utilization_percent > 100"
          class="mt-4 flex items-center p-3 bg-white rounded-md border-l-4 border-raspberry-500"
        >
          <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          <span class="text-sm font-medium text-raspberry-800">
            ISA allowance exceeded by £{{ formatNumber(strategy.allowance.used - strategy.allowance.total) }}
          </span>
        </div>
      </div>

      <!-- Transfer Recommendations -->
      <div v-if="strategy.transfer_recommendations && strategy.transfer_recommendations.length > 0" class="mb-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-3">Transfer Recommendations (General Investment Account → ISA)</h4>
        <div class="bg-white border border-light-gray rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-light-gray">
            <thead class="bg-eggshell-500">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Security</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Value</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Gain/Loss</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Annual Saving</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Priority</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-light-gray">
              <tr v-for="(rec, index) in strategy.transfer_recommendations" :key="index" class="hover:bg-eggshell-500">
                <td class="px-4 py-3 text-sm text-horizon-500">{{ rec.security_name || rec.ticker }}</td>
                <td class="px-4 py-3 text-sm font-medium text-horizon-500">£{{ formatNumber(rec.transfer_amount) }}</td>
                <td class="px-4 py-3 text-sm" :class="rec.gain_loss >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                  £{{ formatNumber(Math.abs(rec.gain_loss)) }}
                  <span class="text-xs">{{ rec.gain_loss >= 0 ? '↑' : '↓' }}</span>
                </td>
                <td class="px-4 py-3 text-sm font-semibold text-spring-600">£{{ formatNumber(rec.annual_saving) }}</td>
                <td class="px-4 py-3 text-sm">
                  <span
                    class="px-2 py-1 text-xs font-semibold rounded-full"
                    :class="getPriorityClass(rec.priority)"
                  >
                    {{ rec.priority }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-3 p-3 bg-white rounded-md border-l-4 border-violet-500">
          <p class="text-sm text-neutral-500">
            <strong>Total annual tax saving from transfers:</strong>
            <span class="text-spring-600 font-semibold ml-2">
              £{{ formatNumber(totalTransferSaving) }}/year
            </span>
          </p>
        </div>
      </div>

      <!-- Contribution Recommendations -->
      <div class="mb-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-3">Contribution Strategy</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Lump Sum Strategy -->
          <div class="bg-white border border-light-gray rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <h5 class="text-sm font-semibold text-neutral-500">Lump Sum</h5>
              <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="mb-2">
              <p class="text-xs text-neutral-500">Recommended Amount</p>
              <p class="text-2xl font-bold text-horizon-500">
                £{{ formatNumber(strategy.contribution_recommendations?.lump_sum?.optimal_amount || 0) }}
              </p>
            </div>
            <div class="mb-2">
              <p class="text-xs text-neutral-500">Annual Saving</p>
              <p class="text-lg font-semibold text-spring-600">
                £{{ formatNumber(strategy.contribution_recommendations?.lump_sum?.annual_saving || 0) }}
              </p>
            </div>
            <p class="text-xs text-neutral-500 mt-3">
              {{ strategy.contribution_recommendations?.lump_sum?.explanation }}
            </p>
          </div>

          <!-- Monthly Contribution Strategy -->
          <div class="bg-white border border-light-gray rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <h5 class="text-sm font-semibold text-neutral-500">Monthly Contributions</h5>
              <svg class="w-5 h-5 text-spring-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="mb-2">
              <p class="text-xs text-neutral-500">Recommended Monthly</p>
              <p class="text-2xl font-bold text-horizon-500">
                £{{ formatNumber(strategy.contribution_recommendations?.monthly?.optimal_amount || 0) }}
              </p>
            </div>
            <div class="mb-2">
              <p class="text-xs text-neutral-500">Annual Total</p>
              <p class="text-lg font-semibold text-horizon-500">
                £{{ formatNumber((strategy.contribution_recommendations?.monthly?.optimal_amount || 0) * 12) }}
              </p>
            </div>
            <p class="text-xs text-neutral-500 mt-3">
              {{ strategy.contribution_recommendations?.monthly?.explanation }}
            </p>
          </div>
        </div>
      </div>

      <!-- Projected Savings -->
      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-4">Projected Tax Savings</h4>
        <div class="grid grid-cols-3 gap-6">
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">1 Year</p>
            <p class="text-2xl font-bold text-spring-600">
              £{{ formatNumber(strategy.potential_savings?.one_year || 0) }}
            </p>
          </div>
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">5 Years</p>
            <p class="text-2xl font-bold text-spring-600">
              £{{ formatNumber(strategy.potential_savings?.five_year || 0) }}
            </p>
          </div>
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">10 Years</p>
            <p class="text-2xl font-bold text-spring-600">
              £{{ formatNumber(strategy.potential_savings?.ten_year || 0) }}
            </p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-light-gray">
          <p class="text-xs text-neutral-500 text-center">
            Projections based on {{ (strategy.assumptions?.expected_return * 100).toFixed(1) }}% annual return
            and {{ (strategy.assumptions?.tax_rate * 100).toFixed(0) }}% tax rate
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-6 flex justify-end space-x-3">
        <button
          @click="$emit('refresh')"
          class="px-4 py-2 bg-savannah-200 text-neutral-500 text-sm font-medium rounded-button hover:bg-savannah-300 transition-colors duration-200"
        >
          Refresh
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ISAOptimizationStrategy',

  mixins: [currencyMixin],

  emits: ['refresh'],

  props: {
    strategy: {
      type: Object,
      default: null,
    },
  },

  computed: {
    taxYear() {
      return this.strategy?.allowance?.tax_year || getCurrentTaxYear();
    },

    totalTransferSaving() {
      if (!this.strategy?.transfer_recommendations) return 0;
      return this.strategy.transfer_recommendations.reduce((sum, rec) => sum + (rec.annual_saving || 0), 0);
    },
  },

  methods: {
    getAllowanceStatusClass(utilization) {
      if (utilization > 100) return 'bg-raspberry-500 text-white';
      if (utilization > 80) return 'bg-violet-500 text-white';
      if (utilization > 50) return 'bg-violet-500 text-white';
      return 'bg-eggshell-500 text-white';
    },

    getAllowanceBarClass(utilization) {
      if (utilization > 100) return 'bg-raspberry-600';
      if (utilization > 80) return 'bg-raspberry-500';
      return 'bg-raspberry-500';
    },

    getPriorityClass(priority) {
      const classes = {
        high: 'bg-raspberry-500 text-white',
        medium: 'bg-violet-500 text-white',
        low: 'bg-violet-500 text-white',
      };
      return classes[priority] || 'bg-eggshell-500 text-white';
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
