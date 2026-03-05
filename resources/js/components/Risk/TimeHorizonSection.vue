<template>
  <div class="time-horizon-section">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Time Horizon for Your Investments</h3>

    <div class="space-y-4 text-sm text-neutral-500 mb-6">
      <p>
        Your investment time horizon - how long until you need the money - is one of the most
        important factors in determining appropriate risk levels.
      </p>
      <p>
        Some goals have a <strong>defined end date</strong> (retirement, child's university, house purchase),
        while others are <strong>less specific</strong> (general wealth building, inheritance planning).
      </p>
    </div>

    <!-- Interactive Time Horizon Selector -->
    <div class="bg-eggshell-500 rounded-lg p-6 mb-6">
      <label class="block text-sm font-medium text-neutral-500 mb-3">
        What is your investment time horizon?
      </label>

      <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
        <button
          v-for="horizon in timeHorizons"
          :key="horizon.value"
          type="button"
          :class="[
            'py-3 px-2 rounded-lg text-sm font-medium transition-all border-2',
            selectedHorizon === horizon.value
              ? 'border-blue-600 bg-blue-50 text-blue-800'
              : 'border-light-gray bg-white text-neutral-500 hover:border-horizon-300 hover:bg-savannah-100'
          ]"
          @click="selectHorizon(horizon.value)"
        >
          {{ horizon.label }}
        </button>
      </div>

      <!-- Time Horizon Visualisation -->
      <div class="mt-6">
        <div class="relative h-3 bg-horizon-200 rounded-full overflow-hidden">
          <!-- Progress indicator -->
          <div
            class="absolute inset-y-0 left-0 transition-all duration-300 rounded-full"
            :class="progressColorClass"
            :style="{ width: progressWidth }"
          ></div>
        </div>

        <!-- Timeline markers -->
        <div class="flex justify-between mt-2 text-xs text-neutral-500">
          <span>Now</span>
          <span>5 yrs</span>
          <span>10 yrs</span>
          <span>15 yrs</span>
          <span>20+ yrs</span>
        </div>
      </div>

      <!-- Horizon interpretation -->
      <transition name="fade">
        <div v-if="selectedHorizon" class="mt-4 p-4 rounded-lg" :class="interpretationClasses">
          <div class="flex items-start gap-3">
            <svg :class="['w-5 h-5 flex-shrink-0 mt-0.5', interpretationIconClass]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="interpretationIcon" />
            </svg>
            <div>
              <h4 class="font-semibold text-sm mb-1">{{ interpretationTitle }}</h4>
              <p class="text-sm">{{ interpretationText }}</p>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- Risk/Horizon Matrix -->
    <div class="bg-white border border-light-gray rounded-lg overflow-hidden">
      <div class="p-4 border-b border-light-gray bg-eggshell-500">
        <h4 class="text-sm font-semibold text-horizon-500">How Time Horizon Affects Recommended Risk</h4>
      </div>
      <div class="p-4">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left border-b border-light-gray">
              <th class="pb-2 font-medium text-neutral-500">Time Horizon</th>
              <th class="pb-2 font-medium text-neutral-500">Suggested Risk Adjustment</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-savannah-100">
            <tr>
              <td class="py-2 text-neutral-500">0-3 years (Short)</td>
              <td class="py-2">
                <span class="inline-flex items-center gap-1 text-red-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                  </svg>
                  Reduce risk - protect capital
                </span>
              </td>
            </tr>
            <tr>
              <td class="py-2 text-neutral-500">3-7 years (Medium)</td>
              <td class="py-2">
                <span class="inline-flex items-center gap-1 text-blue-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                  </svg>
                  Moderate approach - balanced growth
                </span>
              </td>
            </tr>
            <tr>
              <td class="py-2 text-neutral-500">7-15 years (Long)</td>
              <td class="py-2">
                <span class="inline-flex items-center gap-1 text-green-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                  </svg>
                  Can accept more volatility
                </span>
              </td>
            </tr>
            <tr>
              <td class="py-2 text-neutral-500">15+ years (Very Long)</td>
              <td class="py-2">
                <span class="inline-flex items-center gap-1 text-teal-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                  Maximum growth potential
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Important note -->
    <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-blue-800">
          <strong>Remember:</strong> Time horizon is just one factor. Your capacity for loss,
          income stability, and personal comfort with volatility all play important roles in
          determining the right risk level for you.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TimeHorizonSection',

  props: {
    modelValue: {
      type: String,
      default: null,
    },
  },

  emits: ['update:modelValue', 'change'],

  data() {
    return {
      selectedHorizon: this.modelValue,
      timeHorizons: [
        { value: 'short', label: '0-3 years', years: 2 },
        { value: 'medium', label: '3-7 years', years: 5 },
        { value: 'long', label: '7-15 years', years: 11 },
        { value: 'very_long', label: '15-25 years', years: 20 },
        { value: 'indefinite', label: '25+ years', years: 30 },
      ],
    };
  },

  computed: {
    progressWidth() {
      const widths = {
        short: '15%',
        medium: '35%',
        long: '55%',
        very_long: '80%',
        indefinite: '100%',
      };
      return widths[this.selectedHorizon] || '0%';
    },

    progressColorClass() {
      const colors = {
        short: 'bg-red-400',
        medium: 'bg-blue-400',
        long: 'bg-green-400',
        very_long: 'bg-teal-400',
        indefinite: 'bg-purple-400',
      };
      return colors[this.selectedHorizon] || 'bg-horizon-400';
    },

    interpretationClasses() {
      const classes = {
        short: 'bg-red-50 border border-red-200',
        medium: 'bg-blue-50 border border-blue-200',
        long: 'bg-green-50 border border-green-200',
        very_long: 'bg-teal-50 border border-teal-200',
        indefinite: 'bg-purple-50 border border-purple-200',
      };
      return classes[this.selectedHorizon] || '';
    },

    interpretationIconClass() {
      const classes = {
        short: 'text-red-600',
        medium: 'text-blue-600',
        long: 'text-green-600',
        very_long: 'text-teal-600',
        indefinite: 'text-purple-600',
      };
      return classes[this.selectedHorizon] || 'text-neutral-500';
    },

    interpretationIcon() {
      const icons = {
        short: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        medium: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        long: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        very_long: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        indefinite: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
      };
      return icons[this.selectedHorizon] || '';
    },

    interpretationTitle() {
      const titles = {
        short: 'Short-Term Investment (0-3 years)',
        medium: 'Medium-Term Investment (3-7 years)',
        long: 'Long-Term Investment (7-15 years)',
        very_long: 'Very Long-Term Investment (15-25 years)',
        indefinite: 'Generational Investment (25+ years)',
      };
      return titles[this.selectedHorizon] || '';
    },

    interpretationText() {
      const texts = {
        short: 'With a short time horizon, capital preservation is crucial. Consider reducing risk to protect against short-term volatility. Focus on cash and high-quality bonds.',
        medium: 'A medium time horizon allows for some growth-focused investing while maintaining reasonable protection. A balanced portfolio can help achieve your goals.',
        long: 'With 7-15 years, you have time to recover from market downturns. This allows for a more growth-oriented approach with higher equity allocation.',
        very_long: 'A very long time horizon gives you significant capacity to ride out market cycles. You can afford to take on more risk for potentially higher returns.',
        indefinite: 'With a generational time horizon, you have maximum flexibility. Consider a growth-focused strategy that can compound over decades.',
      };
      return texts[this.selectedHorizon] || '';
    },
  },

  watch: {
    modelValue(newVal) {
      this.selectedHorizon = newVal;
    },
  },

  methods: {
    selectHorizon(value) {
      this.selectedHorizon = value;
      this.$emit('update:modelValue', value);
      this.$emit('change', value);
    },
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
