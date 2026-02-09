<template>
  <div class="cgt-harvesting-opportunities">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">CGT Tax-Loss Harvesting</h3>

    <!-- No Data State -->
    <div v-if="!opportunities || opportunities.opportunities.length === 0" class="text-center py-12 text-gray-500">
      <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>No tax-loss harvesting opportunities found</p>
      <p class="text-sm mt-2">All holdings are showing gains</p>
    </div>

    <!-- CGT Harvesting Content -->
    <div v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 border-l-4 border-blue-500">
          <p class="text-sm text-gray-600 mb-1">CGT Allowance</p>
          <p class="text-2xl font-bold text-gray-800">£{{ formatNumber(opportunities.cgt_allowance) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-red-500">
          <p class="text-sm text-gray-600 mb-1">Harvestable Losses</p>
          <p class="text-2xl font-bold text-red-600">£{{ formatNumber(opportunities.total_harvestable_losses) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-blue-500">
          <p class="text-sm text-gray-600 mb-1">Expected Gains</p>
          <p class="text-2xl font-bold text-gray-800">£{{ formatNumber(opportunities.expected_gains) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-green-500">
          <p class="text-sm text-gray-600 mb-1">Potential Saving</p>
          <p class="text-2xl font-bold text-green-600">£{{ formatNumber(opportunities.potential_tax_saving) }}</p>
        </div>
      </div>

      <!-- Harvesting Strategy -->
      <div v-if="opportunities.harvesting_strategy" class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-gray-800 mb-4">Recommended Strategy</h4>

        <!-- Harvest Now -->
        <div v-if="opportunities.harvesting_strategy.harvest_now.length > 0" class="mb-4">
          <h5 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
            <span class="inline-block w-3 h-3 bg-red-600 rounded-full mr-2"></span>
            Harvest Now ({{ opportunities.harvesting_strategy.harvest_now.length }})
          </h5>
          <div class="space-y-2">
            <div
              v-for="(item, index) in opportunities.harvesting_strategy.harvest_now"
              :key="index"
              class="flex items-center justify-between p-3 bg-white rounded-md border-l-4 border-red-500"
            >
              <div>
                <p class="text-sm font-medium text-gray-800">{{ item.security_name }}</p>
                <p class="text-xs text-gray-600">{{ item.rationale }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-red-600">-£{{ formatNumber(item.loss_amount) }}</p>
                <p class="text-xs text-green-600">Save £{{ formatNumber(item.potential_tax_saving) }}</p>
              </div>
            </div>
          </div>
          <div class="mt-3 p-3 bg-white rounded-md border-l-4 border-green-500">
            <p class="text-sm">
              <strong>Total tax saving:</strong>
              <span class="text-green-600 font-semibold ml-2">
                £{{ formatNumber(opportunities.harvesting_strategy.total_tax_saving) }}
              </span>
            </p>
          </div>
        </div>

        <!-- Harvest Later -->
        <div v-if="opportunities.harvesting_strategy.harvest_later.length > 0">
          <h5 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
            <span class="inline-block w-3 h-3 bg-gray-400 rounded-full mr-2"></span>
            Consider for Future ({{ opportunities.harvesting_strategy.harvest_later.length }})
          </h5>
          <div class="space-y-2">
            <div
              v-for="(item, index) in opportunities.harvesting_strategy.harvest_later.slice(0, 3)"
              :key="index"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-md border border-gray-200"
            >
              <div>
                <p class="text-sm font-medium text-gray-800">{{ item.security_name }}</p>
                <p class="text-xs text-gray-600">{{ item.recovery_potential.recommendation }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium text-gray-600">-£{{ formatNumber(item.loss_amount) }}</p>
                <p class="text-xs text-gray-500">{{ item.loss_percent.toFixed(1) }}% loss</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Explanation -->
        <div v-if="opportunities.harvesting_strategy.explanation" class="mt-4 p-4 bg-white rounded-md border-l-4 border-blue-500">
          <p class="text-sm font-medium text-gray-700 mb-2">Strategy Explanation:</p>
          <ul class="text-sm text-gray-600 space-y-1">
            <li v-for="(exp, index) in opportunities.harvesting_strategy.explanation" :key="index">
              • {{ exp }}
            </li>
          </ul>
        </div>
      </div>

      <!-- All Opportunities Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <h4 class="text-md font-semibold text-gray-800 p-4 border-b border-gray-200">All Holdings with Losses</h4>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Security</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Basis</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Value</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loss</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Saving</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(opp, index) in opportunities.opportunities" :key="index" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">
                <p class="font-medium text-gray-800">{{ opp.security_name }}</p>
                <p class="text-xs text-gray-500">{{ opp.ticker }}</p>
              </td>
              <td class="px-4 py-3 text-sm text-gray-800">£{{ formatNumber(opp.cost_basis) }}</td>
              <td class="px-4 py-3 text-sm text-gray-800">£{{ formatNumber(opp.current_value) }}</td>
              <td class="px-4 py-3 text-sm">
                <p class="font-medium text-red-600">-£{{ formatNumber(opp.loss_amount) }}</p>
                <p class="text-xs text-gray-500">{{ opp.loss_percent.toFixed(1) }}%</p>
              </td>
              <td class="px-4 py-3 text-sm font-semibold text-green-600">£{{ formatNumber(opp.potential_tax_saving) }}</td>
              <td class="px-4 py-3 text-sm">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getPriorityClass(opp.priority)"
                >
                  {{ opp.priority }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Important Notes -->
      <div class="mt-6 p-4 bg-white rounded-lg border-l-4 border-blue-500">
        <h5 class="text-sm font-semibold text-gray-800 mb-2">Important Considerations:</h5>
        <ul class="text-sm text-gray-700 space-y-1">
          <li>• <strong>30-Day Rule:</strong> You cannot repurchase the same security within 30 days</li>
          <li>• <strong>Bed and Breakfasting:</strong> Avoid triggering this rule by waiting 31 days</li>
          <li>• <strong>Loss Carryforward:</strong> Losses can be carried forward indefinitely</li>
          <li>• <strong>Tax Year End:</strong> Consider harvesting before April 5 to use current year allowance</li>
        </ul>
      </div>

      <!-- Action Buttons -->
      <div class="mt-6 flex justify-end">
        <button
          @click="$emit('refresh')"
          class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-button hover:bg-primary-700 transition-colors duration-200"
        >
          Refresh Analysis
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CGTHarvestingOpportunities',

  props: {
    opportunities: {
      type: Object,
      default: null,
    },
  },

  methods: {
    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Math.round(value).toLocaleString('en-GB');
    },

    getPriorityClass(priority) {
      const classes = {
        high: 'bg-red-500 text-white',
        medium: 'bg-blue-500 text-white',
        low: 'bg-blue-500 text-white',
      };
      return classes[priority] || 'bg-gray-500 text-white';
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
