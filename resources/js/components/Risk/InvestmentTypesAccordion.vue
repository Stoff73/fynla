<template>
  <div class="investment-types-accordion">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Investment Types by Risk Level</h3>
    <p class="text-sm text-neutral-500 mb-6">
      Different asset classes carry different levels of risk and potential return.
      Click on each type to learn more.
    </p>

    <div class="space-y-2">
      <!-- Cash -->
      <div class="border border-light-gray rounded-lg overflow-hidden">
        <button
          type="button"
          class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-savannah-100 transition-colors"
          @click="toggleSection('cash')"
        >
          <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-violet-500"></span>
            <span class="font-medium text-horizon-500">Cash & Cash Equivalents</span>
            <span class="px-2 py-0.5 text-xs font-medium bg-violet-100 text-violet-800 rounded-full">
              Low Risk
            </span>
          </div>
          <svg
            :class="['w-5 h-5 text-neutral-500 transition-transform', { 'rotate-180': openSections.cash }]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <div v-if="openSections.cash" class="px-4 pb-4">
            <div class="pt-2 border-t border-savannah-100">
              <p class="text-sm text-neutral-500 mb-3">
                Bank and building society deposits, money market funds, and NS&I products.
                These offer low risk of capital loss but returns are unlikely to keep pace with inflation over time.
              </p>
              <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="font-medium text-horizon-500">Typical Returns:</span>
                  <span class="text-neutral-500 ml-1">1-4% p.a.</span>
                </div>
                <div>
                  <span class="font-medium text-horizon-500">Liquidity:</span>
                  <span class="text-neutral-500 ml-1">Very High</span>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Savings accounts</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Money market funds</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Premium Bonds</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Cash ISAs</span>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Bonds -->
      <div class="border border-light-gray rounded-lg overflow-hidden">
        <button
          type="button"
          class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-savannah-100 transition-colors"
          @click="toggleSection('bonds')"
        >
          <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-pink-500"></span>
            <span class="font-medium text-horizon-500">Bonds (Fixed Income)</span>
            <span class="px-2 py-0.5 text-xs font-medium bg-pink-100 text-pink-800 rounded-full">
              Lower-Medium Risk
            </span>
          </div>
          <svg
            :class="['w-5 h-5 text-neutral-500 transition-transform', { 'rotate-180': openSections.bonds }]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <div v-if="openSections.bonds" class="px-4 pb-4">
            <div class="pt-2 border-t border-savannah-100">
              <p class="text-sm text-neutral-500 mb-3">
                Government bonds (gilts) and corporate bonds provide fixed returns and regular income.
                They are sensitive to interest rate changes and inflation. Generally less volatile than equities
                but can still fluctuate in value.
              </p>
              <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="font-medium text-horizon-500">Typical Returns:</span>
                  <span class="text-neutral-500 ml-1">3-6% p.a.</span>
                </div>
                <div>
                  <span class="font-medium text-horizon-500">Liquidity:</span>
                  <span class="text-neutral-500 ml-1">High</span>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">UK Gilts</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Corporate bonds</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Bond funds</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Index-linked gilts</span>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Commercial Property -->
      <div class="border border-light-gray rounded-lg overflow-hidden">
        <button
          type="button"
          class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-savannah-100 transition-colors"
          @click="toggleSection('property')"
        >
          <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            <span class="font-medium text-horizon-500">Commercial Property</span>
            <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
              Medium Risk
            </span>
          </div>
          <svg
            :class="['w-5 h-5 text-neutral-500 transition-transform', { 'rotate-180': openSections.property }]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <div v-if="openSections.property" class="px-4 pb-4">
            <div class="pt-2 border-t border-savannah-100">
              <p class="text-sm text-neutral-500 mb-3">
                Investment in offices, retail, and industrial properties through REITs or property funds.
                Generally fluctuates less than equities but can fall sharply in economic downturns.
                Provides higher income than cash but properties are not always readily sold.
              </p>
              <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="font-medium text-horizon-500">Typical Returns:</span>
                  <span class="text-neutral-500 ml-1">4-8% p.a.</span>
                </div>
                <div>
                  <span class="font-medium text-horizon-500">Liquidity:</span>
                  <span class="text-neutral-500 ml-1">Medium-Low</span>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">REITs</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Property funds</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Direct property</span>
              </div>
              <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800">
                Note: Property funds may suspend redemptions during market stress.
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Equities -->
      <div class="border border-light-gray rounded-lg overflow-hidden">
        <button
          type="button"
          class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-savannah-100 transition-colors"
          @click="toggleSection('equities')"
        >
          <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-teal-500"></span>
            <span class="font-medium text-horizon-500">Equities (Shares)</span>
            <span class="px-2 py-0.5 text-xs font-medium bg-teal-100 text-teal-800 rounded-full">
              Medium-High Risk
            </span>
          </div>
          <svg
            :class="['w-5 h-5 text-neutral-500 transition-transform', { 'rotate-180': openSections.equities }]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <div v-if="openSections.equities" class="px-4 pb-4">
            <div class="pt-2 border-t border-savannah-100">
              <p class="text-sm text-neutral-500 mb-3">
                Ownership shares in companies. Equities fluctuate more than bonds but have historically
                delivered higher returns over the medium to long term. Dividends provide income, and
                capital growth provides wealth accumulation.
              </p>
              <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="font-medium text-horizon-500">Typical Returns:</span>
                  <span class="text-neutral-500 ml-1">5-10% p.a.</span>
                </div>
                <div>
                  <span class="font-medium text-horizon-500">Liquidity:</span>
                  <span class="text-neutral-500 ml-1">High</span>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">UK shares</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Global equities</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Index funds</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Dividend stocks</span>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Alternative Investments -->
      <div class="border border-light-gray rounded-lg overflow-hidden">
        <button
          type="button"
          class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-savannah-100 transition-colors"
          @click="toggleSection('alternatives')"
        >
          <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
            <span class="font-medium text-horizon-500">Alternative Investments</span>
            <span class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
              High Risk
            </span>
          </div>
          <svg
            :class="['w-5 h-5 text-neutral-500 transition-transform', { 'rotate-180': openSections.alternatives }]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <div v-if="openSections.alternatives" class="px-4 pb-4">
            <div class="pt-2 border-t border-savannah-100">
              <p class="text-sm text-neutral-500 mb-3">
                Commodities, derivatives, hedge funds, private equity, and other specialist investments.
                Typically represent a small proportion of a diversified portfolio due to higher volatility
                and complexity. Can provide diversification benefits but require careful consideration.
              </p>
              <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="font-medium text-horizon-500">Typical Returns:</span>
                  <span class="text-neutral-500 ml-1">Variable</span>
                </div>
                <div>
                  <span class="font-medium text-horizon-500">Liquidity:</span>
                  <span class="text-neutral-500 ml-1">Low-Medium</span>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Gold</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Commodities</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Venture Capital Trust / Enterprise Investment Scheme</span>
                <span class="px-2 py-1 text-xs bg-savannah-100 text-neutral-500 rounded">Hedge funds</span>
              </div>
              <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800">
                Higher risk investments are generally only suitable for sophisticated investors
                with appropriate risk tolerance and investment knowledge.
              </div>
            </div>
          </div>
        </transition>
      </div>
    </div>

    <!-- Diversification note -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <p class="text-sm text-blue-800">
          <strong>The power of diversification:</strong> Spreading investments across different asset
          classes can help reduce overall portfolio risk while maintaining growth potential.
          A well-diversified portfolio typically performs more consistently than one concentrated
          in a single asset class.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InvestmentTypesAccordion',

  data() {
    return {
      openSections: {
        cash: false,
        bonds: false,
        property: false,
        equities: false,
        alternatives: false,
      },
    };
  },

  methods: {
    toggleSection(section) {
      this.openSections[section] = !this.openSections[section];
    },

    expandAll() {
      Object.keys(this.openSections).forEach((key) => {
        this.openSections[key] = true;
      });
    },

    collapseAll() {
      Object.keys(this.openSections).forEach((key) => {
        this.openSections[key] = false;
      });
    },
  },
};
</script>

