<template>
  <div class="capacity-for-loss-section">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Readiness & Capacity to Withstand Losses</h3>

    <div class="space-y-4 text-sm text-gray-700 mb-6">
      <p>
        The <strong>impact of investment losses is only really felt if you need to access your money
        whilst values are depressed</strong>. If you can ride out the storm, historically markets
        have recovered over time.
      </p>
      <p>
        You may <strong>need to consider investments which may fall in value</strong> in order to
        achieve better long-term growth and keep pace with inflation.
      </p>
      <p>
        Your <strong>investment goals may limit the extent to which you can withstand losses</strong>.
        For example, if you're saving for a house deposit needed in 2 years, you have less
        capacity to take risk than someone investing for retirement in 20 years.
      </p>
      <p>
        Consider taking <strong>different approaches for different portions of your money</strong>.
        Emergency funds should be in easily accessible, low-risk accounts, while long-term
        investments can afford more volatility.
      </p>
    </div>

    <!-- Capacity Spectrum Visualisation -->
    <div class="bg-gray-50 rounded-lg p-6">
      <h4 class="text-sm font-medium text-gray-700 mb-4 text-center">Your Capacity for Loss Spectrum</h4>

      <!-- Spectrum bar -->
      <div class="relative h-8 rounded-full overflow-hidden mb-2">
        <div class="absolute inset-0 flex">
          <div class="flex-1 bg-gradient-to-r from-green-400 to-green-500"></div>
          <div class="flex-1 bg-gradient-to-r from-green-500 to-teal-500"></div>
          <div class="flex-1 bg-gradient-to-r from-teal-500 to-blue-500"></div>
          <div class="flex-1 bg-gradient-to-r from-blue-500 to-amber-500"></div>
          <div class="flex-1 bg-gradient-to-r from-amber-500 to-red-500"></div>
        </div>

        <!-- Marker for current selection -->
        <transition name="slide">
          <div
            v-if="selectedLevel"
            class="absolute top-0 bottom-0 w-1 bg-white shadow-lg transform -translate-x-1/2"
            :style="{ left: markerPosition }"
          >
            <div class="absolute -top-2 left-1/2 transform -translate-x-1/2">
              <div class="w-4 h-4 bg-white border-2 border-gray-800 rounded-full shadow"></div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Labels -->
      <div class="flex justify-between text-xs text-gray-600 mb-4">
        <span class="text-green-700 font-medium">Low</span>
        <span class="text-teal-700 font-medium">Lower-Med</span>
        <span class="text-blue-700 font-medium">Medium</span>
        <span class="text-amber-700 font-medium">Upper-Med</span>
        <span class="text-red-700 font-medium">High</span>
      </div>

      <!-- Interpretation based on selection -->
      <div v-if="selectedLevel" class="mt-4 p-3 rounded-md" :class="interpretationClasses">
        <p class="text-sm">
          <strong>{{ interpretationTitle }}</strong><br>
          {{ interpretationText }}
        </p>
      </div>
    </div>

    <!-- Key considerations -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h5 class="font-medium text-gray-900 text-sm">Emergency Fund</h5>
        </div>
        <p class="text-xs text-gray-600">
          Keep 3-6 months of expenses in low-risk, easily accessible accounts before
          taking on investment risk.
        </p>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h5 class="font-medium text-gray-900 text-sm">Time Matters</h5>
        </div>
        <p class="text-xs text-gray-600">
          The longer you can leave money invested, the more capacity you have to
          recover from short-term losses.
        </p>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <h5 class="font-medium text-gray-900 text-sm">Income Stability</h5>
        </div>
        <p class="text-xs text-gray-600">
          Secure, stable income allows more capacity for investment risk than
          variable or uncertain income.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CapacityForLossSection',

  props: {
    selectedLevel: {
      type: String,
      default: null,
    },
  },

  computed: {
    markerPosition() {
      const positions = {
        low: '10%',
        lower_medium: '30%',
        medium: '50%',
        upper_medium: '70%',
        high: '90%',
      };
      return positions[this.selectedLevel] || '50%';
    },

    interpretationClasses() {
      const classes = {
        low: 'bg-green-50 border border-green-200 text-green-800',
        lower_medium: 'bg-teal-50 border border-teal-200 text-teal-800',
        medium: 'bg-blue-50 border border-blue-200 text-blue-800',
        upper_medium: 'bg-amber-50 border border-amber-200 text-amber-800',
        high: 'bg-red-50 border border-red-200 text-red-800',
      };
      return classes[this.selectedLevel] || 'bg-gray-50 border border-gray-200 text-gray-800';
    },

    interpretationTitle() {
      const titles = {
        low: 'Low Capacity for Loss',
        lower_medium: 'Lower-Medium Capacity',
        medium: 'Medium Capacity',
        upper_medium: 'Upper-Medium Capacity',
        high: 'High Capacity for Loss',
      };
      return titles[this.selectedLevel] || '';
    },

    interpretationText() {
      const texts = {
        low: 'You prefer to prioritise capital preservation. Consider keeping most investments in lower-risk assets like bonds and cash.',
        lower_medium: 'You can accept small fluctuations for modest growth. A conservative mix with some equity exposure may be suitable.',
        medium: 'You have balanced capacity. A diversified portfolio across asset classes can help achieve reasonable growth while managing risk.',
        upper_medium: 'You can accept significant fluctuations for growth. A portfolio with substantial equity allocation may be appropriate.',
        high: 'You have strong capacity to withstand losses. An aggressive growth strategy with high equity exposure could be suitable for long-term goals.',
      };
      return texts[this.selectedLevel] || '';
    },
  },
};
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  opacity: 0;
}
</style>
