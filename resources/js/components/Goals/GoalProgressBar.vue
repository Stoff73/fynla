<template>
  <div class="goal-progress-bar">
    <div class="flex justify-between items-center mb-1">
      <span v-if="showLabel" class="text-sm text-neutral-500">{{ label }}</span>
      <span class="text-sm font-semibold" :class="percentageClass">{{ displayPercentage }}%</span>
    </div>
    <div class="w-full bg-horizon-200 rounded-full" :class="heightClass">
      <div
        class="rounded-full transition-all duration-500"
        :class="[heightClass, barClass]"
        :style="{ width: Math.min(percentage, 100) + '%' }"
      >
        <!-- Milestone markers -->
        <div v-if="showMilestones" class="relative h-full">
          <div
            v-for="milestone in [25, 50, 75]"
            :key="milestone"
            class="absolute top-0 w-0.5 h-full bg-white/50"
            :style="{ left: milestone + '%' }"
          ></div>
        </div>
      </div>
    </div>
    <div v-if="showAmounts" class="flex justify-between items-center mt-1 text-xs text-neutral-500">
      <span>{{ formatCurrency(currentAmount) }}</span>
      <span>{{ formatCurrency(targetAmount) }}</span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalProgressBar',
  mixins: [currencyMixin],

  props: {
    percentage: {
      type: Number,
      default: 0,
    },
    currentAmount: {
      type: Number,
      default: 0,
    },
    targetAmount: {
      type: Number,
      default: 0,
    },
    isOnTrack: {
      type: Boolean,
      default: true,
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    showLabel: {
      type: Boolean,
      default: false,
    },
    label: {
      type: String,
      default: 'Progress',
    },
    showAmounts: {
      type: Boolean,
      default: false,
    },
    showMilestones: {
      type: Boolean,
      default: false,
    },
    variant: {
      type: String,
      default: 'auto',
      validator: (value) => ['auto', 'success', 'warning', 'danger', 'info'].includes(value),
    },
  },

  computed: {
    displayPercentage() {
      return Math.round(Math.min(this.percentage, 100));
    },

    heightClass() {
      const heights = {
        sm: 'h-1.5',
        md: 'h-2.5',
        lg: 'h-4',
      };
      return heights[this.size] || heights.md;
    },

    barClass() {
      if (this.variant !== 'auto') {
        const variants = {
          success: 'bg-spring-500',
          warning: 'bg-violet-500',
          danger: 'bg-raspberry-500',
          info: 'bg-violet-500',
        };
        return variants[this.variant] || 'bg-violet-500';
      }

      // Auto color based on progress and track status
      if (this.percentage >= 100) return 'bg-spring-500';
      if (this.isOnTrack) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    percentageClass() {
      if (this.variant !== 'auto') {
        const variants = {
          success: 'text-spring-600',
          warning: 'text-violet-600',
          danger: 'text-raspberry-600',
          info: 'text-violet-600',
        };
        return variants[this.variant] || 'text-violet-600';
      }

      if (this.percentage >= 100) return 'text-spring-600';
      if (this.isOnTrack) return 'text-violet-600';
      return 'text-violet-600';
    },
  },
};
</script>
