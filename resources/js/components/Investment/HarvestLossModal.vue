<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-horizon-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <!-- Header -->
        <div class="bg-violet-500 px-6 py-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Harvest Tax Loss</h3>
            <button @click="$emit('close')" class="text-white hover:text-violet-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="px-6 py-4">
          <!-- Holding Details -->
          <div class="bg-eggshell-500 rounded-lg p-4 mb-6 border border-light-gray">
            <h4 class="font-semibold text-horizon-500 mb-3">{{ holding?.security_name || 'Selected Holding' }}</h4>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-neutral-500">Current Value</p>
                <p class="font-semibold text-horizon-500">{{ formatCurrency(holding?.current_value) }}</p>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Unrealised Loss</p>
                <p class="font-semibold text-raspberry-600">-{{ formatCurrency(holding?.loss_amount) }}</p>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Estimated Tax Saving</p>
                <p class="font-semibold text-spring-600">{{ formatCurrency(holding?.tax_saving) }}</p>
              </div>
              <div>
                <p class="text-sm text-neutral-500">Repurchase Eligible</p>
                <p class="font-semibold text-horizon-500">{{ repurchaseDate }}</p>
              </div>
            </div>
          </div>

          <!-- 30-Day Warning -->
          <div class="bg-eggshell-500 rounded-lg p-4 mb-6">
            <div class="flex items-start">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-raspberry-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <h4 class="font-semibold text-raspberry-800">30-Day Rule Warning</h4>
                <p class="text-sm text-raspberry-700 mt-1">
                  If you sell this holding and repurchase substantially the same security within 30 days,
                  the loss will be disallowed for Capital Gains Tax purposes. This is known as the "bed-and-breakfasting" rule.
                </p>
              </div>
            </div>
          </div>

          <!-- Execution Steps -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <h4 class="font-semibold text-violet-800 mb-3">How to Harvest This Loss</h4>
            <ol class="text-sm text-violet-700 space-y-2 list-decimal list-inside">
              <li>Sell the holding in your General Investment Account</li>
              <li>Record the loss for your tax records</li>
              <li>Wait at least 30 days before repurchasing (or use in Bed & ISA)</li>
              <li>Consider purchasing a similar but not identical investment to maintain exposure</li>
            </ol>
          </div>

          <!-- Alternative Securities -->
          <div class="mt-4 p-4 bg-eggshell-500 rounded-lg border border-light-gray">
            <h4 class="font-semibold text-horizon-500 mb-2">Alternative Investments</h4>
            <p class="text-sm text-neutral-500 mb-3">
              To maintain market exposure while avoiding the 30-day rule, consider these similar investments:
            </p>
            <ul class="text-sm text-neutral-500 space-y-1 list-disc list-inside">
              <li>A different fund tracking a similar index</li>
              <li>An ETF from a different provider</li>
              <li>A fund with slightly different holdings</li>
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
  name: 'HarvestLossModal',

  mixins: [currencyMixin],

  props: {
    holding: {
      type: Object,
      default: null,
    },
  },

  emits: ['close'],

  computed: {
    repurchaseDate() {
      const date = new Date();
      date.setDate(date.getDate() + 31);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },
};
</script>
