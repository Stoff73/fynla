<template>
  <div class="decumulation-planning">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Decumulation Planning</h2>
      <p class="text-gray-600 mt-1">Plan how to withdraw your pension savings in retirement</p>
    </div>

    <!-- Drawdown Simulator -->
    <div class="mb-8">
      <DrawdownSimulator />
    </div>

    <!-- PCLS Strategy -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Pension Commencement Lump Sum (PCLS) Strategy</h3>

      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
          <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <div>
            <p class="text-sm font-medium text-blue-900 mb-1">25% Tax-Free Cash</p>
            <p class="text-sm text-blue-800">
              You can typically take up to 25% of your DC pension pot as a tax-free lump sum (PCLS).
              This is sometimes called "tax-free cash".
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
          <div>
            <p class="text-sm text-gray-600">Total DC Pension Pot</p>
            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalDCPot) }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-600">Maximum PCLS (25%)</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(maxPCLS) }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="p-4 bg-white border border-gray-200 rounded-lg">
            <h4 class="font-medium text-gray-900 mb-2">Option A: Take Full PCLS</h4>
            <p class="text-sm text-gray-600 mb-3">Take all tax-free cash upfront</p>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Tax-Free Cash:</span>
                <span class="font-semibold">{{ formatCurrency(maxPCLS) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Remaining for Drawdown:</span>
                <span class="font-semibold">{{ formatCurrency(totalDCPot - maxPCLS) }}</span>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white border border-gray-200 rounded-lg">
            <h4 class="font-medium text-gray-900 mb-2">Option B: Phased Approach</h4>
            <p class="text-sm text-gray-600 mb-3">Take PCLS gradually over time</p>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Tax-Free per Year:</span>
                <span class="font-semibold">{{ formatCurrency(Math.round(maxPCLS / 10)) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Duration:</span>
                <span class="font-semibold">10 years</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Annuity vs Drawdown Comparison -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-6">Annuity vs Drawdown Comparison</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Drawdown -->
        <div class="border-2 border-indigo-200 rounded-lg p-5">
          <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Income Drawdown</h4>
          </div>

          <div class="space-y-3 mb-4">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span class="text-sm text-gray-700">Flexibility to adjust income</span>
            </div>
            <div class="flex items-start">
              <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span class="text-sm text-gray-700">Potential for investment growth</span>
            </div>
            <div class="flex items-start">
              <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span class="text-sm text-gray-700">Remaining pot can be inherited</span>
            </div>
          </div>

          <div class="border-t border-gray-200 pt-3">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              <span class="text-sm text-gray-700">Investment risk - pot can go down</span>
            </div>
            <div class="flex items-start mt-2">
              <svg class="w-5 h-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              <span class="text-sm text-gray-700">Income not guaranteed for life</span>
            </div>
          </div>
        </div>

        <!-- Annuity -->
        <div class="border-2 border-purple-200 rounded-lg p-5">
          <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-3">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Annuity</h4>
          </div>

          <div class="space-y-3 mb-4">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span class="text-sm text-gray-700">Guaranteed income for life</span>
            </div>
            <div class="flex items-start">
              <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span class="text-sm text-gray-700">No investment risk</span>
            </div>
            <div class="flex items-start">
              <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              <span class="text-sm text-gray-700">Can include spouse protection</span>
            </div>
          </div>

          <div class="border-t border-gray-200 pt-3">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              <span class="text-sm text-gray-700">No flexibility - income is fixed</span>
            </div>
            <div class="flex items-start mt-2">
              <svg class="w-5 h-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              <span class="text-sm text-gray-700">Usually cannot be inherited</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <p class="text-sm text-amber-900">
          <strong>Hybrid Approach:</strong> Many retirees use a combination of both strategies.
          For example, use an annuity to cover essential expenses and drawdown for discretionary spending.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import DrawdownSimulator from '../../components/Retirement/DrawdownSimulator.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'DecumulationPlanning',
  mixins: [currencyMixin],

  components: {
    DrawdownSimulator,
  },

  computed: {
    ...mapState('retirement', ['dcPensions']),
    ...mapGetters('retirement', ['totalPensionWealth']),

    totalDCPot() {
      return this.totalPensionWealth;
    },

    maxPCLS() {
      return Math.round(this.totalDCPot * 0.25);
    },
  },

  // formatCurrency provided by currencyMixin
};
</script>

<style scoped>
/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.decumulation-planning > div {
  animation: fadeIn 0.5s ease-out;
}
</style>
