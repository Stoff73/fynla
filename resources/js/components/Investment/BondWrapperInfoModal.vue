<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-horizon-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

      <!-- Center modal -->
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
        <!-- Header -->
        <div class="bg-raspberry-500 px-6 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-white mr-3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
              </svg>
              <h3 id="modal-title" class="text-lg font-semibold text-white">Investment Bond Wrapper</h3>
            </div>
            <button @click="$emit('close')" class="text-white hover:text-savannah-200">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="px-6 py-6 space-y-6">
          <!-- Summary Card -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-violet-600">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
              </div>
              <div class="ml-3">
                <h4 class="font-medium text-violet-800">Recommendation</h4>
                <p class="text-sm text-violet-700 mt-1">
                  Your General Investment Account balance of <strong>{{ formatCurrency(data.gia_balance) }}</strong> may benefit from
                  {{ wrapperTypeLabel }} wrapper, potentially saving
                  <strong class="text-spring-600">{{ formatCurrency(data.tax_deferral_benefit) }}/year</strong>
                  in tax deferral.
                </p>
              </div>
            </div>
          </div>

          <!-- What is a Bond Wrapper -->
          <div>
            <h4 class="font-semibold text-horizon-500 mb-3">What is an Investment Bond?</h4>
            <p class="text-sm text-neutral-500 mb-4">
              An investment bond is a tax-efficient wrapper for your investments. Unlike a General Investment Account where you pay tax annually
              on gains and dividends, a bond allows your investments to grow with tax deferred until you make a withdrawal.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Onshore Bond -->
              <div class="bg-eggshell-500 rounded-lg p-4 border" :class="data.recommended_wrapper === 'onshore_bond' ? 'border-violet-400' : 'border-light-gray'">
                <div class="flex items-center mb-2">
                  <h5 class="font-medium text-horizon-500">Onshore Bond</h5>
                  <span v-if="data.recommended_wrapper === 'onshore_bond'" class="ml-2 text-xs bg-violet-500 text-white px-2 py-0.5 rounded">Recommended</span>
                </div>
                <ul class="text-sm text-neutral-500 space-y-1">
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    20% tax credit on gains
                  </li>
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Top-slicing relief available
                  </li>
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    5% annual tax-free withdrawal
                  </li>
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    No personal tax on internal funds
                  </li>
                </ul>
              </div>

              <!-- Offshore Bond -->
              <div class="bg-eggshell-500 rounded-lg p-4 border" :class="data.recommended_wrapper === 'offshore_bond' ? 'border-violet-400' : 'border-light-gray'">
                <div class="flex items-center mb-2">
                  <h5 class="font-medium text-horizon-500">Offshore Bond</h5>
                  <span v-if="data.recommended_wrapper === 'offshore_bond'" class="ml-2 text-xs bg-violet-500 text-white px-2 py-0.5 rounded">Recommended</span>
                </div>
                <ul class="text-sm text-neutral-500 space-y-1">
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Gross roll-up (no internal tax)
                  </li>
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Top-slicing relief available
                  </li>
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    5% annual tax-free withdrawal
                  </li>
                  <li class="flex items-start">
                    <svg class="w-4 h-4 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Better for larger sums (100k+)
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Top-Slicing Explanation -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <div class="flex items-start">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-600 mt-0.5 mr-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
              </svg>
              <div>
                <h5 class="font-medium text-violet-800">What is Top-Slicing Relief?</h5>
                <p class="text-sm text-violet-700 mt-1">
                  Top-slicing spreads the gain over the years you held the bond, potentially reducing the tax rate.
                  This is particularly valuable for {{ data.tax_band }} rate taxpayers who might drop to a lower band when withdrawing.
                </p>
              </div>
            </div>
          </div>

          <!-- Who is this for? -->
          <div>
            <h4 class="font-semibold text-horizon-500 mb-3">Best Suited For</h4>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm text-neutral-500">Higher/additional rate taxpayers</span>
              </div>
              <div class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm text-neutral-500">Long-term investors (5+ years)</span>
              </div>
              <div class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm text-neutral-500">Used up ISA/pension allowances</span>
              </div>
              <div class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm text-neutral-500">Expect lower tax band at withdrawal</span>
              </div>
            </div>
          </div>

          <!-- Important Notes -->
          <div class="bg-savannah-100 rounded-lg p-4">
            <h5 class="font-medium text-horizon-500 mb-2">Important Considerations</h5>
            <ul class="text-sm text-neutral-500 space-y-1">
              <li>- Investment bonds are typically not suitable for basic rate taxpayers</li>
              <li>- Early surrender may trigger a chargeable event</li>
              <li>- Consider your overall investment strategy and objectives</li>
              <li>- Seek professional advice before proceeding</li>
            </ul>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-eggshell-500 px-6 py-4 flex justify-end space-x-3">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-eggshell-500"
          >
            Close
          </button>
          <a
            href="https://www.gov.uk/guidance/life-insurance-policies-gains-on-uk-bonds"
            target="_blank"
            rel="noopener noreferrer"
            class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 flex items-center"
          >
            HM Revenue & Customs Guidance
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ml-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'BondWrapperInfoModal',

  mixins: [currencyMixin],

  props: {
    data: {
      type: Object,
      default: () => ({}),
    },
  },

  emits: ['close'],

  computed: {
    wrapperTypeLabel() {
      return this.data.recommended_wrapper === 'offshore_bond' ? 'an Offshore Bond' : 'an Onshore Bond';
    },
  },
};
</script>
