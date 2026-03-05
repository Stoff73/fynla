<template>
  <div class="capacity-for-loss-section">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Readiness & Capacity to Withstand Losses</h3>

    <div class="space-y-4 text-sm text-neutral-500 mb-6">
      <p>
        The <strong>impact of investment losses is only really felt if you need to access your money
        whilst values are depressed</strong>. If you can ride out the storm, historically markets
        have recovered over time.
      </p>
      <p>
        Your capacity for loss is driven by <strong>how much of your net worth is exposed to
        market risk</strong> through investments and pensions.
      </p>
    </div>

    <!-- Capacity Spectrum Visualisation -->
    <div class="bg-eggshell-500 rounded-lg p-6">
      <h4 class="text-sm font-medium text-neutral-500 mb-4 text-center">Your Capacity for Loss Spectrum</h4>

      <!-- Spectrum bar (4 zones) -->
      <div class="relative h-8 rounded-full overflow-hidden mb-2">
        <div class="absolute inset-0 flex">
          <div class="flex-1 bg-gradient-to-r from-green-400 to-green-500"></div>
          <div class="flex-1 bg-gradient-to-r from-blue-400 to-blue-500"></div>
          <div class="flex-1 bg-gradient-to-r from-teal-400 to-teal-500"></div>
          <div class="flex-1 bg-gradient-to-r from-red-400 to-red-500"></div>
        </div>

        <!-- Marker for current selection -->
        <transition name="slide">
          <div
            v-if="selectedLevel"
            class="absolute top-0 bottom-0 w-1 bg-white shadow-lg transform -translate-x-1/2"
            :style="{ left: markerPosition }"
          >
            <div class="absolute -top-2 left-1/2 transform -translate-x-1/2">
              <div class="w-4 h-4 bg-white border-2 border-horizon-500 rounded-full shadow"></div>
            </div>
          </div>
        </transition>
      </div>

      <!-- Labels (4 zones) -->
      <div class="flex justify-between text-xs text-neutral-500 mb-4">
        <span class="text-green-700 font-medium">High Capacity</span>
        <span class="text-blue-700 font-medium">Medium</span>
        <span class="text-teal-700 font-medium">Medium-Low</span>
        <span class="text-red-700 font-medium">Low Capacity</span>
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
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-white border border-light-gray rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h5 class="font-medium text-horizon-500 text-sm">Emergency Fund</h5>
        </div>
        <p class="text-xs text-neutral-500">
          Keep 3-6 months of expenses in low-risk, easily accessible accounts before
          taking on investment risk.
        </p>
      </div>

      <div class="bg-white border border-light-gray rounded-lg p-4">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h5 class="font-medium text-horizon-500 text-sm">Time Matters</h5>
        </div>
        <p class="text-xs text-neutral-500">
          The longer you can leave money invested, the more capacity you have to
          recover from short-term losses.
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
        high: '12.5%',
        medium: '37.5%',
        lower_medium: '62.5%',
        low: '87.5%',
      };
      return positions[this.selectedLevel] || '50%';
    },

    interpretationClasses() {
      const classes = {
        high: 'bg-green-50 border border-green-200 text-green-800',
        medium: 'bg-blue-50 border border-blue-200 text-blue-800',
        lower_medium: 'bg-teal-50 border border-teal-200 text-teal-800',
        low: 'bg-red-50 border border-red-200 text-red-800',
      };
      return classes[this.selectedLevel] || 'bg-eggshell-500 border border-light-gray text-horizon-500';
    },

    interpretationTitle() {
      const titles = {
        high: 'High Capacity for Loss',
        medium: 'Medium Capacity',
        lower_medium: 'Medium-Low Capacity',
        low: 'Low Capacity for Loss',
      };
      return titles[this.selectedLevel] || '';
    },

    interpretationText() {
      const texts = {
        high: 'Less than 15% of your net worth is at market risk. You have strong capacity to withstand investment losses without affecting your lifestyle.',
        medium: '15-50% of your net worth is at market risk. A diversified portfolio can help achieve reasonable growth while managing your exposure.',
        lower_medium: '50-75% of your net worth is at market risk. Consider whether your overall exposure is appropriate for your circumstances.',
        low: 'More than 75% of your net worth is at market risk. A significant downturn could materially affect your financial position.',
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
