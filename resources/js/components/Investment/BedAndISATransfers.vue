<template>
  <div class="bed-and-isa-transfers">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Bed and ISA Transfer Opportunities</h3>

    <!-- Explanation Banner -->
    <div class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-start">
        <svg class="h-5 w-5 text-violet-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-horizon-500 mb-1">What is Bed and ISA?</p>
          <p class="text-sm text-neutral-500">
            Sell holdings from your General Investment Account and immediately repurchase them in an ISA wrapper.
            This protects future growth from Capital Gains Tax while utilising your Capital Gains Tax allowance and ISA allowance efficiently.
          </p>
        </div>
      </div>
    </div>

    <!-- No Data State -->
    <div v-if="!opportunities || opportunities.opportunities.length === 0" class="text-center py-12 text-neutral-500">
      <svg class="mx-auto h-12 w-12 text-horizon-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>No Bed and ISA opportunities found</p>
      <p class="text-sm mt-2">Either ISA allowance or Capital Gains Tax allowance fully utilized</p>
    </div>

    <!-- Bed and ISA Content -->
    <div v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">ISA Allowance Remaining</p>
          <p class="text-2xl font-bold text-violet-600">£{{ formatNumber(opportunities.isa_allowance_remaining) }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Capital Gains Tax Allowance</p>
          <p class="text-2xl font-bold text-horizon-500">£{{ formatNumber(opportunities.cgt_allowance) }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Potential Annual Saving</p>
          <p class="text-2xl font-bold text-spring-600">
            £{{ formatNumber(opportunities.transfer_strategy?.total_annual_saving || 0) }}
          </p>
        </div>
      </div>

      <!-- Transfer Strategy -->
      <div v-if="opportunities.transfer_strategy" class="bg-white border border-light-gray rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-4">Recommended Transfers</h4>

        <!-- Strategy Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Total Value to Transfer</p>
            <p class="text-lg font-bold text-horizon-500">
              £{{ formatNumber(opportunities.transfer_strategy.total_transfer_value) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Capital Gains Tax Liability</p>
            <p class="text-lg font-bold text-violet-600">
              £{{ formatNumber(opportunities.transfer_strategy.total_cgt_liability) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Annual Saving</p>
            <p class="text-lg font-bold text-spring-600">
              £{{ formatNumber(opportunities.transfer_strategy.total_annual_saving) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Break-Even</p>
            <p class="text-lg font-bold text-horizon-500">
              {{ opportunities.transfer_strategy.break_even_years?.toFixed(1) || 'N/A' }} years
            </p>
          </div>
        </div>

        <!-- Recommended Transfers List -->
        <div class="space-y-3">
          <div
            v-for="(transfer, index) in opportunities.transfer_strategy.recommended_transfers"
            :key="index"
            class="p-4 bg-eggshell-500 rounded-lg"
          >
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="font-semibold text-horizon-500">{{ transfer.security_name }}</p>
                <p class="text-xs text-neutral-500">{{ transfer.ticker }} • {{ transfer.asset_type }}</p>
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
                <p class="text-xs text-neutral-500">Transfer Amount</p>
                <p class="text-sm font-semibold text-horizon-500">£{{ formatNumber(transfer.transfer_amount) }}</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Gain on Sale</p>
                <p class="text-sm font-semibold" :class="transfer.gain_on_transfer >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                  £{{ formatNumber(Math.abs(transfer.gain_on_transfer)) }}
                </p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Capital Gains Tax Payable</p>
                <p class="text-sm font-semibold text-violet-600">£{{ formatNumber(transfer.cgt_liability) }}</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Annual Saving</p>
                <p class="text-sm font-semibold text-spring-600">£{{ formatNumber(transfer.annual_saving) }}</p>
              </div>
            </div>

            <p class="text-xs text-neutral-500 mt-2">
              <strong>Rationale:</strong> {{ transfer.rationale }}
            </p>
          </div>
        </div>
      </div>

      <!-- Execution Plan -->
      <div v-if="opportunities.execution_plan" class="bg-white border border-light-gray rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-4">Step-by-Step Execution Plan</h4>
        <div class="space-y-3">
          <div
            v-for="(step, index) in opportunities.execution_plan.steps"
            :key="index"
            class="flex items-start p-3 bg-eggshell-500 rounded-md border border-light-gray"
          >
            <div class="flex-shrink-0 mr-3">
              <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-raspberry-500 text-white text-sm font-bold">
                {{ index + 1 }}
              </span>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-horizon-500 mb-1">{{ step.action }}</p>
              <p class="text-xs text-neutral-500">{{ step.details }}</p>
              <p v-if="step.notes" class="text-xs text-violet-600 mt-1">💡 {{ step.notes }}</p>
            </div>
          </div>
        </div>
        <div class="mt-4 p-3 bg-eggshell-500 rounded-md">
          <p class="text-sm text-neutral-500">
            <strong>Timeline:</strong> {{ opportunities.execution_plan.timeline }}
          </p>
        </div>
      </div>

      <!-- All Opportunities Table -->
      <div class="bg-white border border-light-gray rounded-lg overflow-hidden">
        <h4 class="text-md font-semibold text-horizon-500 p-4 border-b border-light-gray">All Transfer Opportunities</h4>
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Security</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Value</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Gain</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Capital Gains Tax</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Annual Saving</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Suitability</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="(opp, index) in opportunities.opportunities" :key="index" class="hover:bg-eggshell-500">
              <td class="px-4 py-3 text-sm">
                <p class="font-medium text-horizon-500">{{ opp.security_name }}</p>
                <p class="text-xs text-neutral-500">{{ opp.ticker }}</p>
              </td>
              <td class="px-4 py-3 text-sm text-horizon-500">£{{ formatNumber(opp.current_value) }}</td>
              <td class="px-4 py-3 text-sm" :class="opp.unrealised_gain >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                £{{ formatNumber(Math.abs(opp.unrealised_gain)) }}
              </td>
              <td class="px-4 py-3 text-sm text-violet-600">£{{ formatNumber(opp.cgt_on_full_transfer) }}</td>
              <td class="px-4 py-3 text-sm font-semibold text-spring-600">£{{ formatNumber(opp.annual_saving) }}</td>
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
      <div class="mt-6 p-4 bg-eggshell-500 rounded-lg">
        <h5 class="text-sm font-semibold text-horizon-500 mb-2">Important Considerations:</h5>
        <ul class="text-sm text-neutral-500 space-y-1">
          <li>• Execute sales and purchases on same day to minimize market risk</li>
          <li>• Check with your broker - some platforms offer "Bed and ISA" service</li>
          <li>• Any Capital Gains Tax payable is due by January 31 following the tax year</li>
          <li>• ISA allowance is "use it or lose it" - resets April 6 each year</li>
          <li>• Consider transaction costs when executing transfers</li>
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
  name: 'BedAndISATransfers',

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

    getSuitabilityClass(suitable) {
      return suitable ? 'bg-spring-500 text-white' : 'bg-eggshell-500 text-white';
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
