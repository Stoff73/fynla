<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-horizon-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <!-- Header -->
        <div class="bg-spring-600 px-6 py-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Transfer to ISA</h3>
            <button @click="$emit('close')" class="text-white hover:text-spring-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="px-6 py-4">
          <!-- ISA Allowance Info -->
          <div class="bg-eggshell-500 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
              <span class="text-neutral-500">Remaining ISA Allowance</span>
              <span class="text-2xl font-bold text-spring-600">{{ formatCurrency(isaRemaining) }}</span>
            </div>
          </div>

          <!-- Eligible Holdings -->
          <div v-if="opportunities.length > 0" class="mb-6">
            <h4 class="font-semibold text-horizon-500 mb-3">Eligible Holdings for Transfer</h4>
            <div class="space-y-2 max-h-48 overflow-y-auto">
              <div
                v-for="(holding, index) in opportunities"
                :key="index"
                class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg border border-light-gray"
              >
                <div>
                  <p class="font-medium text-horizon-500">{{ holding.security_name }}</p>
                  <p class="text-xs text-neutral-500">Gain: {{ formatCurrency(holding.gain) }} (within Capital Gains Tax allowance)</p>
                </div>
                <span class="font-semibold text-neutral-500">{{ formatCurrency(holding.current_value) }}</span>
              </div>
            </div>
          </div>

          <div v-else class="mb-6 text-center py-6 bg-eggshell-500 rounded-lg">
            <p class="text-neutral-500">No holdings currently eligible for Bed & ISA transfer.</p>
            <p class="text-sm text-horizon-400 mt-1">You can still contribute new funds to your ISA.</p>
          </div>

          <!-- Execution Steps -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <h4 class="font-semibold text-violet-800 mb-3">How to Execute</h4>
            <ol class="text-sm text-violet-700 space-y-2 list-decimal list-inside">
              <li>Log in to your investment platform</li>
              <li>Sell the selected holdings in your General Investment Account</li>
              <li>Wait for settlement (usually T+2)</li>
              <li>Transfer cash to your Stocks & Shares ISA</li>
              <li>Repurchase the same or similar holdings in your ISA</li>
            </ol>
          </div>

          <!-- Important Notes -->
          <div class="mt-4 p-4 bg-eggshell-500 rounded-lg">
            <h4 class="font-semibold text-violet-800 mb-2">Important Notes</h4>
            <ul class="text-sm text-violet-700 space-y-1 list-disc list-inside">
              <li>The 30-day rule does not apply to Bed & ISA (only to Bed & Breakfast)</li>
              <li>You can repurchase the same securities immediately in your ISA</li>
              <li>Any gains realised will use your Capital Gains Tax allowance</li>
              <li>Ensure you have sufficient ISA allowance before selling</li>
            </ul>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-eggshell-500 px-6 py-4 flex justify-end space-x-3">
          <button
            @click="$emit('close')"
            class="px-4 py-2 border border-horizon-300 rounded-lg text-neutral-500 hover:bg-savannah-100"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ISATransferModal',

  mixins: [currencyMixin],

  props: {
    isaRemaining: {
      type: Number,
      default: 0,
    },
    opportunities: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['close'],
};
</script>
