<template>
  <div
    class="savings-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer border border-light-gray hover:shadow-lg hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200"
    @click="navigateToSavings"
  >
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-horizon-500">Savings</h3>
      <div class="text-sm text-neutral-500">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
          />
        </svg>
      </div>
    </div>

    <!-- Emergency Fund Runway -->
    <div class="mb-6">
      <div class="flex items-baseline mb-2">
        <span
          class="text-4xl font-bold"
          :class="runwayColour"
        >
          {{ emergencyFundRunway.toFixed(1) }}
        </span>
        <span class="ml-2 text-sm text-neutral-500">months runway</span>
      </div>
      <div class="w-full bg-savannah-200 rounded-full h-2">
        <div
          class="h-2 rounded-full transition-all duration-300"
          :class="runwayBarColour"
          :style="{ width: Math.min(runwayPercentage, 100) + '%' }"
        ></div>
      </div>
      <p class="text-xs text-neutral-500 mt-1">Target: 6 months</p>
    </div>

    <!-- Total Savings and ISA -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <p class="text-sm text-neutral-500 mb-1">Total Savings</p>
        <p class="text-lg font-semibold text-horizon-500">
          {{ formatCurrency(totalSavings) }}
        </p>
      </div>
      <div>
        <p class="text-sm text-neutral-500 mb-1">ISA Used</p>
        <p class="text-lg font-semibold text-horizon-500">
          {{ isaUsagePercent }}%
        </p>
      </div>
    </div>

    <!-- Goals Status -->
    <div
      class="flex items-center p-3 rounded-md"
      :class="goalsStatusClass"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5 mr-2"
        :class="goalsIconClass"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="text-sm font-medium" :class="goalsTextClass">
        {{ goalsStatusText }}
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'SavingsOverviewCard',
  mixins: [currencyMixin],

  props: {
    emergencyFundRunway: {
      type: Number,
      required: true,
      default: 0,
    },
    totalSavings: {
      type: Number,
      required: true,
      default: 0,
    },
    isaUsagePercent: {
      type: Number,
      required: true,
      default: 0,
      validator: (value) => value >= 0 && value <= 100,
    },
    goalsStatus: {
      type: Object,
      required: true,
      default: () => ({ onTrack: 0, total: 0 }),
    },
  },

  computed: {
    runwayPercentage() {
      return (this.emergencyFundRunway / 6) * 100; // 6 months is target
    },

    runwayColour() {
      if (this.emergencyFundRunway >= 6) {
        return 'text-spring-600';
      } else if (this.emergencyFundRunway >= 3) {
        return 'text-violet-600';
      } else {
        return 'text-raspberry-600';
      }
    },

    runwayBarColour() {
      if (this.emergencyFundRunway >= 6) {
        return 'bg-spring-600';
      } else if (this.emergencyFundRunway >= 3) {
        return 'bg-raspberry-500';
      } else {
        return 'bg-raspberry-600';
      }
    },

    goalsStatusClass() {
      if (this.goalsStatus.total === 0) {
        return 'bg-eggshell-500';
      } else if (this.goalsStatus.onTrack === this.goalsStatus.total) {
        return 'bg-spring-50';
      } else if (this.goalsStatus.onTrack > 0) {
        return 'bg-violet-50';
      } else {
        return 'bg-raspberry-50';
      }
    },

    goalsIconClass() {
      if (this.goalsStatus.total === 0) {
        return 'text-neutral-500';
      } else if (this.goalsStatus.onTrack === this.goalsStatus.total) {
        return 'text-spring-600';
      } else if (this.goalsStatus.onTrack > 0) {
        return 'text-violet-600';
      } else {
        return 'text-raspberry-600';
      }
    },

    goalsTextClass() {
      if (this.goalsStatus.total === 0) {
        return 'text-horizon-500';
      } else if (this.goalsStatus.onTrack === this.goalsStatus.total) {
        return 'text-spring-800';
      } else if (this.goalsStatus.onTrack > 0) {
        return 'text-violet-800';
      } else {
        return 'text-raspberry-800';
      }
    },

    goalsStatusText() {
      if (this.goalsStatus.total === 0) {
        return 'No savings goals set';
      }
      return `${this.goalsStatus.onTrack} of ${this.goalsStatus.total} goals on track`;
    },
  },

  methods: {
    navigateToSavings() {
      this.$router.push('/savings');
    },
    // formatCurrency provided by currencyMixin
  },
};
</script>

<style scoped>
.savings-overview-card {
  min-width: 280px;
  max-width: 100%;
}

@media (min-width: 640px) {
  .savings-overview-card {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .savings-overview-card {
    min-width: 360px;
  }
}
</style>
