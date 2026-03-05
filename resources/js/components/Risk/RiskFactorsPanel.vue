<template>
  <div class="risk-factors-panel">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">What Risks Do You Face When Investing?</h3>
    <p class="text-sm text-neutral-500 mb-6">
      Understanding these key investment risks will help you make informed decisions about your portfolio.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Risk Factor 1: Investments could fall -->
      <div
        class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4 transition-transform hover:scale-[1.02]"
      >
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 bg-red-200 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-red-900 mb-1">Investments could fall in value</h4>
            <p class="text-sm text-red-800">
              Almost all investments carry the risk of falling, particularly in the short-term.
              Due to market fluctuations, interest rate changes, or other factors, the value
              of your investments may decrease.
            </p>
          </div>
        </div>
      </div>

      <!-- Risk Factor 2: Cannot afford to withstand fall -->
      <div
        class="bg-gradient-to-br from-teal-50 to-teal-100 border border-teal-200 rounded-lg p-4 transition-transform hover:scale-[1.02]"
      >
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 bg-teal-200 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-teal-900 mb-1">Cannot afford to withstand a fall</h4>
            <p class="text-sm text-teal-800">
              It's important to hold cash for short-term needs and only invest money you can
              leave for the longer term. Be comfortable that you could withstand losses if
              forced to sell after a fall.
            </p>
          </div>
        </div>
      </div>

      <!-- Risk Factor 3: Inflation risk -->
      <div
        class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 transition-transform hover:scale-[1.02]"
      >
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 bg-blue-200 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-blue-900 mb-1">Value may not keep pace with inflation</h4>
            <p class="text-sm text-blue-800">
              Low-risk investments typically offer lower returns. While the actual value might
              not fall, over the longer term it has a greater chance of being eroded by inflation,
              reducing your purchasing power.
            </p>
          </div>
        </div>
      </div>

      <!-- Risk Factor 4: Liquidity risk -->
      <div
        class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-4 transition-transform hover:scale-[1.02]"
      >
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 w-10 h-10 bg-purple-200 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-purple-900 mb-1">May not access money when needed</h4>
            <p class="text-sm text-purple-800">
              Some investments are difficult to sell quickly without the value dropping.
              When markets are under stress, buyers may be few and far between, making it
              harder to access your money.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary callout -->
    <div class="mt-6 bg-eggshell-500 border border-light-gray rounded-lg p-4">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-neutral-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-neutral-500">
          <strong>Key insight:</strong> Different risks require different approaches.
          A well-balanced portfolio considers all these factors based on your personal circumstances,
          time horizon, and goals.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RiskFactorsPanel',
};
</script>
