<template>
  <span
    :class="[
      'inline-flex items-center gap-1 rounded-full font-medium',
      sizeClasses,
      colorClasses,
      hasCustomRisk ? 'ring-2 ring-blue-300 ring-offset-1' : ''
    ]"
    :title="tooltipText"
  >
    <!-- Risk indicator icon -->
    <svg
      v-if="showIcon"
      :class="iconSizeClasses"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M13 10V3L4 14h7v7l9-11h-7z"
      />
    </svg>
    <span>{{ displayLabel }}</span>
  </span>
</template>

<script>
export default {
  name: 'RiskBadge',

  props: {
    level: {
      type: String,
      required: true,
      validator: (value) => [
        'low',
        'lower_medium',
        'medium',
        'upper_medium',
        'high',
        // Legacy values
        'cautious',
        'balanced',
        'adventurous',
      ].includes(value),
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
    abbreviated: {
      type: Boolean,
      default: false,
    },
    showIcon: {
      type: Boolean,
      default: false,
    },
    hasCustomRisk: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    normalizedLevel() {
      // Map legacy values to new system
      const legacyMap = {
        cautious: 'lower_medium',
        balanced: 'medium',
        adventurous: 'upper_medium',
      };
      return legacyMap[this.level] || this.level;
    },

    displayLabel() {
      const labels = {
        low: this.abbreviated ? 'Low' : 'Low Risk',
        lower_medium: this.abbreviated ? 'L-Med' : 'Lower-Medium',
        medium: this.abbreviated ? 'Med' : 'Medium',
        upper_medium: this.abbreviated ? 'U-Med' : 'Upper-Medium',
        high: this.abbreviated ? 'High' : 'High Risk',
      };
      return labels[this.normalizedLevel] || this.level;
    },

    tooltipText() {
      const descriptions = {
        low: 'Low Risk - Capital preservation focus',
        lower_medium: 'Lower-Medium Risk - Stability with modest growth',
        medium: 'Medium Risk - Balanced approach',
        upper_medium: 'Upper-Medium Risk - Growth focus',
        high: 'High Risk - Maximum growth potential',
      };
      let text = descriptions[this.normalizedLevel] || '';
      if (this.hasCustomRisk) {
        text += ' (Custom setting)';
      }
      return text;
    },

    sizeClasses() {
      return {
        xs: 'px-1.5 py-0.5 text-xs',
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-1 text-sm',
        lg: 'px-3 py-1.5 text-sm',
      }[this.size];
    },

    iconSizeClasses() {
      return {
        xs: 'w-3 h-3',
        sm: 'w-3 h-3',
        md: 'w-4 h-4',
        lg: 'w-5 h-5',
      }[this.size];
    },

    colorClasses() {
      const colors = {
        low: 'bg-yellow-100 text-yellow-800',
        lower_medium: 'bg-pink-100 text-pink-800',
        medium: 'bg-green-100 text-green-800',
        upper_medium: 'bg-teal-100 text-teal-800',
        high: 'bg-blue-100 text-blue-800',
      };
      return colors[this.normalizedLevel] || 'bg-gray-100 text-gray-800';
    },
  },
};
</script>
