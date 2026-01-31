<template>
  <div class="bed-and-isa-transfers">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Bed and ISA Transfer Opportunities</h3>

    <!-- Explanation Banner -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="flex items-start">
        <svg class="h-5 w-5 text-blue-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-gray-800 mb-1">What is Bed and ISA?</p>
          <p class="text-sm text-gray-700">
            Sell holdings from your General Investment Account (GIA) and immediately repurchase them in an ISA wrapper.
            This protects future growth from CGT while utilizing your CGT allowance and ISA allowance efficiently.
          </p>
        </div>
      </div>
    </div>

    <!-- No Data State -->
    <div v-if="!opportunities || opportunities.opportunities.length === 0" class="text-center py-12 text-gray-500">
      <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>No Bed and ISA opportunities found</p>
      <p class="text-sm mt-2">Either ISA allowance or CGT allowance fully utilized</p>
    </div>

    <!-- Bed and ISA Content -->
    <div v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-gray-600 mb-1">ISA Allowance Remaining</p>
          <p class="text-2xl font-bold text-blue-600">£{{ formatNumber(opportunities.isa_allowance_remaining) }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-gray-600 mb-1">CGT Allowance</p>
          <p class="text-2xl font-bold text-gray-800">£{{ formatNumber(opportunities.cgt_allowance) }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-gray-600 mb-1">Potential Annual Saving</p>
          <p class="text-2xl font-bold text-green-600">
            £{{ formatNumber(opportunities.transfer_strategy?.total_annual_saving || 0) }}
          </p>
        </div>
      </div>

      <!-- Transfer Strategy -->
      <div v-if="opportunities.transfer_strategy" class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-gray-800 mb-4">Recommended Transfers</h4>

        <!-- Strategy Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
          <div>
            <p class="text-xs text-gray-600 mb-1">Total Value to Transfer</p>
            <p class="text-lg font-bold text-gray-800">
              £{{ formatNumber(opportunities.transfer_strategy.total_transfer_value) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">CGT Liability</p>
            <p class="text-lg font-bold text-blue-600">
              £{{ formatNumber(opportunities.transfer_strategy.total_cgt_liability) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Annual Saving</p>
            <p class="text-lg font-bold text-green-600">
              £{{ formatNumber(opportunities.transfer_strategy.total_annual_saving) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Break-Even</p>
            <p class="text-lg font-bold text-gray-800">
              {{ opportunities.transfer_strategy.break_even_years?.toFixed(1) || 'N/A' }} years
            </p>
          </div>
        </div>

        <!-- Recommended Transfers List -->
        <div class="space-y-3">
          <div
            v-for="(transfer, index) in opportunities.transfer_strategy.recommended_transfers"
            :key="index"
            class="p-4 bg-gray-50 rounded-lg"
          >
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="font-semibold text-gray-800">{{ transfer.security_name }}</p>
                <p class="text-xs text-gray-600">{{ transfer.ticker }} • {{ transfer.asset_type }}</p>
              </div>
              <span
                class="px-2 py-1 text-xs font-semibold rounded-full"
                :class="getPriorityClass(transfer.priority)"
              >
                {{ transfer.priority }}
              </span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-2">
              <div>
                <p class="text-xs text-gray-600">Transfer Amount</p>
                <p class="text-sm font-semibold text-gray-800">£{{ formatNumber(transfer.transfer_amount) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-600">Gain on Sale</p>
                <p class="text-sm font-semibold" :class="transfer.gain_on_transfer >= 0 ? 'text-green-600' : 'text-red-600'">
                  £{{ formatNumber(Math.abs(transfer.gain_on_transfer)) }}
                </p>
              </div>
              <div>
                <p class="text-xs text-gray-600">CGT Payable</p>
                <p class="text-sm font-semibold text-blue-600">£{{ formatNumber(transfer.cgt_liability) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-600">Annual Saving</p>
                <p class="text-sm font-semibold text-green-600">£{{ formatNumber(transfer.annual_saving) }}</p>
              </div>
            </div>

            <p class="text-xs text-gray-600 mt-2">
              <strong>Rationale:</strong> {{ transfer.rationale }}
            </p>
          </div>
        </div>
      </div>

      <!-- Execution Plan -->
      <div v-if="opportunities.execution_plan" class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-gray-800 mb-4">Step-by-Step Execution Plan</h4>
        <div class="space-y-3">
          <div
            v-for="(step, index) in opportunities.execution_plan.steps"
            :key="index"
            class="flex items-start p-3 bg-gray-50 rounded-md border border-gray-200"
          >
            <div class="flex-shrink-0 mr-3">
              <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-600 text-white text-sm font-bold">
                {{ index + 1 }}
              </span>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800 mb-1">{{ step.action }}</p>
              <p class="text-xs text-gray-600">{{ step.details }}</p>
              <p v-if="step.notes" class="text-xs text-blue-600 mt-1">💡 {{ step.notes }}</p>
            </div>
          </div>
        </div>
        <div class="mt-4 p-3 bg-gray-50 rounded-md">
          <p class="text-sm text-gray-700">
            <strong>Timeline:</strong> {{ opportunities.execution_plan.timeline }}
          </p>
        </div>
      </div>

      <!-- All Opportunities Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <h4 class="text-md font-semibold text-gray-800 p-4 border-b border-gray-200">All Transfer Opportunities</h4>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Security</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gain</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CGT</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Annual Saving</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Suitability</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(opp, index) in opportunities.opportunities" :key="index" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">
                <p class="font-medium text-gray-800">{{ opp.security_name }}</p>
                <p class="text-xs text-gray-500">{{ opp.ticker }}</p>
              </td>
              <td class="px-4 py-3 text-sm text-gray-800">£{{ formatNumber(opp.current_value) }}</td>
              <td class="px-4 py-3 text-sm" :class="opp.unrealised_gain >= 0 ? 'text-green-600' : 'text-red-600'">
                £{{ formatNumber(Math.abs(opp.unrealised_gain)) }}
              </td>
              <td class="px-4 py-3 text-sm text-blue-600">£{{ formatNumber(opp.cgt_on_full_transfer) }}</td>
              <td class="px-4 py-3 text-sm font-semibold text-green-600">£{{ formatNumber(opp.annual_saving) }}</td>
              <td class="px-4 py-3 text-sm">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getSuitabilityClass(opp.transfer_potential.can_transfer)"
                >
                  {{ opp.transfer_potential.can_transfer ? 'Suitable' : 'Not Suitable' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Important Notes -->
      <div class="mt-6 p-4 bg-gray-50 rounded-lg">
        <h5 class="text-sm font-semibold text-gray-800 mb-2">Important Considerations:</h5>
        <ul class="text-sm text-gray-700 space-y-1">
          <li>• Execute sales and purchases on same day to minimize market risk</li>
          <li>• Check with your broker - some platforms offer "Bed and ISA" service</li>
          <li>• Any CGT payable is due by January 31 following the tax year</li>
          <li>• ISA allowance is "use it or lose it" - resets April 6 each year</li>
          <li>• Consider transaction costs when executing transfers</li>
        </ul>
      </div>

      <!-- Action Buttons -->
      <div class="mt-6 flex justify-end">
        <button
          @click="$emit('refresh')"
          class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors duration-200"
        >
          Refresh Analysis
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BedAndISATransfers',

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

    getSuitabilityClass(suitable) {
      return suitable ? 'bg-green-500 text-white' : 'bg-gray-500 text-white';
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
