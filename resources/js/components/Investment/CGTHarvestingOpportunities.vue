<template>
  <div class="cgt-harvesting-opportunities">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Capital Gains Tax-Loss Harvesting</h3>

    <!-- No Data State -->
    <div v-if="!opportunities || opportunities.opportunities.length === 0" class="text-center py-12 text-neutral-500">
      <svg class="mx-auto h-12 w-12 text-horizon-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>No tax-loss harvesting opportunities found</p>
      <p class="text-sm mt-2">All holdings are showing gains</p>
    </div>

    <!-- CGT Harvesting Content -->
    <div v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 border-l-4 border-violet-500">
          <p class="text-sm text-neutral-500 mb-1">Capital Gains Tax Allowance</p>
          <p class="text-2xl font-bold text-horizon-500">£{{ formatNumber(opportunities.cgt_allowance) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-raspberry-500">
          <p class="text-sm text-neutral-500 mb-1">Harvestable Losses</p>
          <p class="text-2xl font-bold text-raspberry-600">£{{ formatNumber(opportunities.total_harvestable_losses) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-violet-500">
          <p class="text-sm text-neutral-500 mb-1">Expected Gains</p>
          <p class="text-2xl font-bold text-horizon-500">£{{ formatNumber(opportunities.expected_gains) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border-l-4 border-spring-500">
          <p class="text-sm text-neutral-500 mb-1">Potential Saving</p>
          <p class="text-2xl font-bold text-spring-600">£{{ formatNumber(opportunities.potential_tax_saving) }}</p>
        </div>
      </div>

      <!-- Harvesting Strategy -->
      <div v-if="opportunities.harvesting_strategy" class="bg-white border border-light-gray rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-4">Recommended Strategy</h4>

        <!-- Harvest Now -->
        <div v-if="opportunities.harvesting_strategy.harvest_now.length > 0" class="mb-4">
          <h5 class="text-sm font-semibold text-neutral-500 mb-2 flex items-center">
            <span class="inline-block w-3 h-3 bg-raspberry-600 rounded-full mr-2"></span>
            Harvest Now ({{ opportunities.harvesting_strategy.harvest_now.length }})
          </h5>
          <div class="space-y-2">
            <div
              v-for="(item, index) in opportunities.harvesting_strategy.harvest_now"
              :key="index"
              class="flex items-center justify-between p-3 bg-white rounded-md border-l-4 border-raspberry-500"
            >
              <div>
                <p class="text-sm font-medium text-horizon-500">{{ item.security_name }}</p>
                <p class="text-xs text-neutral-500">{{ item.rationale }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-raspberry-600">-£{{ formatNumber(item.loss_amount) }}</p>
                <p class="text-xs text-spring-600">Save £{{ formatNumber(item.potential_tax_saving) }}</p>
              </div>
            </div>
          </div>
          <div class="mt-3 p-3 bg-white rounded-md border-l-4 border-spring-500">
            <p class="text-sm">
              <strong>Total tax saving:</strong>
              <span class="text-spring-600 font-semibold ml-2">
                £{{ formatNumber(opportunities.harvesting_strategy.total_tax_saving) }}
              </span>
            </p>
          </div>
        </div>

        <!-- Harvest Later -->
        <div v-if="opportunities.harvesting_strategy.harvest_later.length > 0">
          <h5 class="text-sm font-semibold text-neutral-500 mb-2 flex items-center">
            <span class="inline-block w-3 h-3 bg-savannah-300 rounded-full mr-2"></span>
            Consider for Future ({{ opportunities.harvesting_strategy.harvest_later.length }})
          </h5>
          <div class="space-y-2">
            <div
              v-for="(item, index) in opportunities.harvesting_strategy.harvest_later.slice(0, 3)"
              :key="index"
              class="flex items-center justify-between p-3 bg-eggshell-500 rounded-md border border-light-gray"
            >
              <div>
                <p class="text-sm font-medium text-horizon-500">{{ item.security_name }}</p>
                <p class="text-xs text-neutral-500">{{ item.recovery_potential.recommendation }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium text-neutral-500">-£{{ formatNumber(item.loss_amount) }}</p>
                <p class="text-xs text-neutral-500">{{ item.loss_percent.toFixed(1) }}% loss</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Explanation -->
        <div v-if="opportunities.harvesting_strategy.explanation" class="mt-4 p-4 bg-white rounded-md border-l-4 border-violet-500">
          <p class="text-sm font-medium text-neutral-500 mb-2">Strategy Explanation:</p>
          <ul class="text-sm text-neutral-500 space-y-1">
            <li v-for="(exp, index) in opportunities.harvesting_strategy.explanation" :key="index">
              • {{ exp }}
            </li>
          </ul>
        </div>
      </div>

      <!-- All Opportunities Table -->
      <div class="bg-white border border-light-gray rounded-lg overflow-hidden">
        <h4 class="text-md font-semibold text-horizon-500 p-4 border-b border-light-gray">All Holdings with Losses</h4>
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Security</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Cost Basis</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Current Value</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Loss</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tax Saving</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Priority</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="(opp, index) in opportunities.opportunities" :key="index" class="hover:bg-eggshell-500">
              <td class="px-4 py-3 text-sm">
                <p class="font-medium text-horizon-500">{{ opp.security_name }}</p>
                <p class="text-xs text-neutral-500">{{ opp.ticker }}</p>
              </td>
              <td class="px-4 py-3 text-sm text-horizon-500">£{{ formatNumber(opp.cost_basis) }}</td>
              <td class="px-4 py-3 text-sm text-horizon-500">£{{ formatNumber(opp.current_value) }}</td>
              <td class="px-4 py-3 text-sm">
                <p class="font-medium text-raspberry-600">-£{{ formatNumber(opp.loss_amount) }}</p>
                <p class="text-xs text-neutral-500">{{ opp.loss_percent.toFixed(1) }}%</p>
              </td>
              <td class="px-4 py-3 text-sm font-semibold text-spring-600">£{{ formatNumber(opp.potential_tax_saving) }}</td>
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
      <div class="mt-6 p-4 bg-white rounded-lg border-l-4 border-violet-500">
        <h5 class="text-sm font-semibold text-horizon-500 mb-2">Important Considerations:</h5>
        <ul class="text-sm text-neutral-500 space-y-1">
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
          class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors duration-200"
        >
          Refresh Analysis
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'CGTHarvestingOpportunities',

  mixins: [currencyMixin],

  emits: ['refresh'],

  props: {
    opportunities: {
      type: Object,
      default: null,
    },
  },

  methods: {
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
