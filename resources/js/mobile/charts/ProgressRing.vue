<template>
  <svg
    :width="size"
    :height="size"
    :viewBox="`0 0 ${size} ${size}`"
    class="progress-ring"
  >
    <!-- Background circle -->
    <circle
      :cx="center"
      :cy="center"
      :r="radius"
      fill="none"
      :stroke-width="strokeWidth"
      class="text-neutral-200"
      stroke="currentColor"
    />
    <!-- Progress circle -->
    <circle
      :cx="center"
      :cy="center"
      :r="radius"
      fill="none"
      :stroke-width="strokeWidth"
      :stroke="strokeColor"
      stroke-linecap="round"
      :stroke-dasharray="circumference"
      :stroke-dashoffset="dashOffset"
      :style="{ transition: 'stroke-dashoffset 0.6s ease-in-out' }"
      transform-origin="center"
      :transform="`rotate(-90 ${center} ${center})`"
    />
    <!-- Percentage text -->
    <text
      :x="center"
      :y="center"
      text-anchor="middle"
      dominant-baseline="central"
      :font-size="fontSize"
      font-weight="700"
      class="fill-horizon-500"
    >
      {{ percentage }}%
    </text>
  </svg>
</template>

<script>
import { SUCCESS_COLORS, WARNING_COLORS, PRIMARY_COLORS } from '@/constants/designSystem';

export default {
  name: 'ProgressRing',

  props: {
    percentage: {
      type: Number,
      default: 0,
      validator: (val) => val >= 0 && val <= 100,
    },
    size: {
      type: Number,
      default: 56,
    },
    strokeWidth: {
      type: Number,
      default: 4,
    },
    status: {
      type: String,
      default: 'on-track',
      validator: (val) => ['on-track', 'behind', 'at-risk'].includes(val),
    },
  },

  computed: {
    center() {
      return this.size / 2;
    },

    radius() {
      return (this.size - this.strokeWidth) / 2;
    },

    circumference() {
      return 2 * Math.PI * this.radius;
    },

    dashOffset() {
      return this.circumference * (1 - this.percentage / 100);
    },

    fontSize() {
      return Math.round(this.size * 0.22);
    },

    strokeColor() {
      const colours = {
        'on-track': SUCCESS_COLORS[500],
        'behind': WARNING_COLORS[500],
        'at-risk': PRIMARY_COLORS[500],
      };
      return colours[this.status] || colours['on-track'];
    },
  },
};
</script>
