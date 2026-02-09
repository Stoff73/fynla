<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
        <!-- Header -->
        <div class="bg-green-600 px-6 py-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Bed & ISA Execution Plan</h3>
            <button @click="$emit('close')" class="text-white hover:text-green-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Step Indicator -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div
              v-for="(step, index) in steps"
              :key="index"
              class="flex items-center"
              :class="{ 'flex-1': index < steps.length - 1 }"
            >
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center font-semibold text-sm"
                :class="currentStep >= index ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'"
              >
                {{ index + 1 }}
              </div>
              <span
                class="ml-2 text-sm font-medium hidden sm:inline"
                :class="currentStep >= index ? 'text-green-600' : 'text-gray-500'"
              >
                {{ step.title }}
              </span>
              <div
                v-if="index < steps.length - 1"
                class="flex-1 h-0.5 mx-4"
                :class="currentStep > index ? 'bg-green-600' : 'bg-gray-300'"
              ></div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="px-6 py-6">
          <!-- Step 1: Review Holdings -->
          <div v-if="currentStep === 0">
            <h4 class="font-semibold text-gray-800 mb-4">Review Holdings for Transfer</h4>
            <p class="text-sm text-gray-600 mb-4">
              These holdings in your GIA have gains within your CGT allowance and can be transferred tax-free:
            </p>

            <div class="space-y-3 max-h-64 overflow-y-auto">
              <div
                v-for="(holding, index) in opportunity?.suitable_holdings || []"
                :key="index"
                class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200"
              >
                <div>
                  <p class="font-medium text-gray-800">{{ holding.security_name }}</p>
                  <p class="text-sm text-gray-500">Gain: {{ formatCurrency(holding.gain) }}</p>
                </div>
                <div class="text-right">
                  <p class="font-semibold text-gray-800">{{ formatCurrency(holding.current_value) }}</p>
                </div>
              </div>
            </div>

            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
              <div class="flex justify-between items-center">
                <span class="font-medium text-green-800">Total Transferable</span>
                <span class="text-xl font-bold text-green-600">{{ formatCurrency(opportunity?.transferable_amount) }}</span>
              </div>
            </div>
          </div>

          <!-- Step 2: ISA Destination -->
          <div v-if="currentStep === 1">
            <h4 class="font-semibold text-gray-800 mb-4">Confirm ISA Destination</h4>

            <div class="bg-gray-50 rounded-lg p-4 mb-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-gray-700">Your ISA Allowance Remaining</span>
                <span class="text-xl font-bold text-blue-600">{{ formatCurrency(isaRemaining) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-700">Amount to Transfer</span>
                <span class="text-xl font-bold text-gray-800">{{ formatCurrency(opportunity?.transferable_amount) }}</span>
              </div>
            </div>

            <div v-if="opportunity?.transferable_amount <= isaRemaining" class="p-4 bg-gray-50 rounded-lg">
              <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="font-semibold text-green-800">Transfer Possible</p>
                  <p class="text-sm text-green-700">You have sufficient ISA allowance for this transfer.</p>
                </div>
              </div>
            </div>

            <div v-else class="p-4 bg-gray-50 rounded-lg">
              <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                  <p class="font-semibold text-blue-800">Partial Transfer Only</p>
                  <p class="text-sm text-blue-700">
                    You can only transfer up to {{ formatCurrency(isaRemaining) }} this tax year.
                    Consider transferring the rest in the next tax year.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: CGT Review -->
          <div v-if="currentStep === 2">
            <h4 class="font-semibold text-gray-800 mb-4">Capital Gains Tax Review</h4>

            <div class="grid grid-cols-2 gap-4 mb-4">
              <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <p class="text-sm text-gray-500">Total Gains on Sale</p>
                <p class="text-xl font-bold text-gray-800">{{ formatCurrency(totalGains) }}</p>
              </div>
              <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-500">CGT Liability</p>
                <p class="text-xl font-bold text-green-600">{{ formatCurrency(opportunity?.cgt_on_transfer || 0) }}</p>
              </div>
            </div>

            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="font-semibold text-green-800">Tax-Free Transfer</p>
                  <p class="text-sm text-green-700">
                    The gains on these holdings are within your annual CGT allowance, so no Capital Gains Tax is due.
                  </p>
                </div>
              </div>
            </div>

            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
              <h5 class="font-semibold text-blue-800 mb-2">Annual Tax Savings (Once in ISA)</h5>
              <p class="text-sm text-blue-700">
                By holding these investments in your ISA instead of your GIA, you will save approximately
                <strong>{{ formatCurrency(opportunity?.potential_annual_saving) }}</strong> per year in tax on dividends and future capital gains.
              </p>
            </div>
          </div>

          <!-- Step 4: Execution Checklist -->
          <div v-if="currentStep === 3">
            <h4 class="font-semibold text-gray-800 mb-4">Execution Checklist</h4>

            <div class="space-y-4">
              <div
                v-for="(item, index) in executionChecklist"
                :key="index"
                class="flex items-start p-4 bg-gray-50 rounded-lg border border-gray-200"
              >
                <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center font-semibold mr-4 flex-shrink-0">
                  {{ index + 1 }}
                </div>
                <div>
                  <p class="font-medium text-gray-800">{{ item.title }}</p>
                  <p class="text-sm text-gray-600">{{ item.description }}</p>
                </div>
              </div>
            </div>

            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
              <h5 class="font-semibold text-blue-800 mb-2">Important Reminders</h5>
              <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
                <li>Unlike Bed & Breakfast, the 30-day rule does NOT apply to Bed & ISA</li>
                <li>You can repurchase the same securities immediately in your ISA</li>
                <li>Keep records of the transactions for your tax return</li>
                <li>Settlement typically takes T+2 (2 business days)</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-between">
          <button
            v-if="currentStep > 0"
            @click="currentStep--"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100"
          >
            Back
          </button>
          <div v-else></div>

          <div class="flex space-x-3">
            <button
              @click="$emit('close')"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100"
            >
              Close
            </button>
            <button
              v-if="currentStep < steps.length - 1"
              @click="currentStep++"
              class="px-4 py-2 bg-primary-600 text-white rounded-button hover:bg-primary-700"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'BedAndISAWizardModal',

  mixins: [currencyMixin],

  props: {
    opportunity: {
      type: Object,
      default: null,
    },
    isaRemaining: {
      type: Number,
      default: 0,
    },
  },

  emits: ['close'],

  data() {
    return {
      currentStep: 0,
      steps: [
        { title: 'Review' },
        { title: 'ISA' },
        { title: 'CGT' },
        { title: 'Execute' },
      ],
      executionChecklist: [
        {
          title: 'Sell holdings in GIA',
          description: 'Log into your platform and place sell orders for the selected holdings.',
        },
        {
          title: 'Wait for settlement',
          description: 'Wait for the sale to settle (typically 2 business days).',
        },
        {
          title: 'Transfer cash to ISA',
          description: 'Move the proceeds from your GIA to your Stocks & Shares ISA.',
        },
        {
          title: 'Repurchase in ISA',
          description: 'Buy the same or similar holdings within your ISA wrapper.',
        },
        {
          title: 'Record for tax return',
          description: 'Keep records of the sale and purchase for your Self Assessment.',
        },
      ],
    };
  },

  computed: {
    totalGains() {
      if (!this.opportunity?.suitable_holdings) return 0;
      return this.opportunity.suitable_holdings.reduce((sum, h) => sum + (h.gain || 0), 0);
    },
  },
};
</script>
